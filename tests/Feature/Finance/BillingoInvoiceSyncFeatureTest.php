<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceController;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceIndexRequest;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use App\Models\InstitutionInvoiceSyncRun;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\Finance\BillingoInvoiceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingoInvoiceSyncFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_downloads_missing_pdf_for_known_original_invoice(): void
    {
        [$institution, $user, $invoice] = $this->seedInvoiceContext('SYNC01');
        Storage::fake('local');

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/documents?page=1')) {
                return Http::response([
                    'data' => [[
                        'id' => '12345',
                        'block_id' => '77',
                        'invoice_number' => 'BILL-2026-0001',
                        'issue_date' => '2026-08-01',
                        'fulfillment_date' => '2026-07-31',
                        'due_date' => '2026-08-08',
                        'updated_at' => '2026-08-24T02:30:00+02:00',
                        'public_url' => 'https://billingo.test/invoice/12345',
                    ]],
                    'current_page' => 1,
                    'last_page' => 1,
                ]);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/12345/download') {
                return Http::response("%PDF-1.7\ninvoice pdf", 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('unexpected request', 500);
        });

        $summary = app(BillingoInvoiceSyncService::class)->syncInstitution(
            $institution,
            $user,
            InstitutionInvoiceSyncRun::MODE_MANUAL
        );

        $invoice->refresh();

        $this->assertSame(InstitutionInvoiceSyncRun::STATUS_SUCCESS, $summary['status']);
        $this->assertSame(1, $summary['downloaded_pdfs']);
        $this->assertSame('invoices/billingo/'.$institution->id.'/BILL-2026-0001.pdf', $invoice->invoice_pdf_path);
        Storage::disk('local')->assertExists($invoice->invoice_pdf_path);
    }

    public function test_sync_does_not_redownload_existing_valid_pdf(): void
    {
        [$institution, $user, $invoice] = $this->seedInvoiceContext('SYNC02');
        Storage::fake('local');

        $invoice->invoice_pdf_path = 'invoices/billingo/'.$institution->id.'/BILL-2026-0001.pdf';
        $invoice->pdf_disk = 'local';
        $invoice->save();
        Storage::disk('local')->put($invoice->invoice_pdf_path, "%PDF-1.7\nexisting pdf");

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/documents?page=1')) {
                return Http::response([
                    'data' => [[
                        'id' => '12345',
                        'block_id' => '77',
                        'invoice_number' => 'BILL-2026-0001',
                        'updated_at' => '2026-08-24T02:30:00+02:00',
                    ]],
                    'current_page' => 1,
                    'last_page' => 1,
                ]);
            }

            return Http::response('unexpected request', 500);
        });

        $summary = app(BillingoInvoiceSyncService::class)->syncInstitution(
            $institution,
            $user,
            InstitutionInvoiceSyncRun::MODE_MANUAL
        );

        $this->assertSame(1, $summary['existing_pdfs']);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/download'));
    }

    public function test_sync_creates_separate_cancellation_record_for_known_original_invoice(): void
    {
        [$institution, $user, $invoice] = $this->seedInvoiceContext('SYNC03');

        Http::fake(fn (Request $request) => Http::response([
            'data' => [[
                'id' => '67890',
                'block_id' => '77',
                'invoice_number' => 'STORNO-2026-0001',
                'type' => 'cancellation',
                'original_document_id' => '12345',
                'issue_date' => '2026-08-24',
                'updated_at' => '2026-08-24T02:35:00+02:00',
            ]],
            'current_page' => 1,
            'last_page' => 1,
        ]));

        $summary = app(BillingoInvoiceSyncService::class)->syncInstitution(
            $institution,
            $user,
            InstitutionInvoiceSyncRun::MODE_MANUAL
        );

        $invoice->refresh();
        $cancellationInvoice = InstitutionInvoice::query()
            ->where('original_invoice_id', $invoice->id)
            ->first();

        $this->assertSame(1, $summary['created_invoices']);
        $this->assertSame(InstitutionInvoice::STATUS_VOIDED, $invoice->status);
        $this->assertNotNull($cancellationInvoice);
        $this->assertSame(InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION, $cancellationInvoice->document_type);
        $this->assertNotNull($invoice->institution_payment_id);
        $this->assertNull($cancellationInvoice->institution_payment_id);
        $this->assertSame('67890', $cancellationInvoice->provider_invoice_id);
        $this->assertSame('12345', $cancellationInvoice->provider_original_invoice_id);

        // A Billingo-oldali (távoli) sztornó-szinkron is negatív összeggel
        // rögzítse a jóváíró számlát, ugyanúgy mint a helyi cancel()
        // művelet - az eredeti számla összege eközben nem változik.
        $this->assertSame(-1000, $cancellationInvoice->gross_amount);
        $this->assertSame(-1000, $cancellationInvoice->net_amount);
        $this->assertSame(0, $cancellationInvoice->vat_amount);
        $this->assertSame(1000, $invoice->gross_amount);
    }

    public function test_invoice_list_shows_original_and_cancellation_as_separate_rows(): void
    {
        [$institution, $user, $invoice] = $this->seedInvoiceContext('SYNC04');

        $cancellationInvoice = new InstitutionInvoice;
        $cancellationInvoice->fill([
            'institution_id' => $institution->id,
            'child_id' => $invoice->child_id,
            'guardian_id' => $invoice->guardian_id,
            'monthly_payment_statement_id' => $invoice->monthly_payment_statement_id,
            'original_invoice_id' => $invoice->id,
            'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
            'document_type' => InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION,
            'provider_invoice_id' => '67890',
            'provider_original_invoice_id' => '12345',
            'invoice_number' => 'STORNO-2026-0001',
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'issue_date' => '2026-08-24',
            'due_date' => '2026-08-24',
            'fulfillment_date' => '2026-08-24',
            'net_amount' => 1000,
            'vat_amount' => 0,
            'gross_amount' => 1000,
            'currency' => 'HUF',
            'payment_method' => 'bank_transfer',
            'customer_name' => 'Szulo Payer',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
        ]);
        $cancellationInvoice->created_by = $user->id;
        $cancellationInvoice->save();

        $request = InstitutionInvoiceIndexRequest::create(route('dashboard.institution.finance.invoices'), 'GET');
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);

        $this->actingAs($user);
        $html = app(InstitutionInvoiceController::class)->index($request)->render();

        $this->assertStringContainsString('BILL-2026-0001', $html);
        $this->assertStringContainsString('STORNO-2026-0001', $html);
        $this->assertStringContainsString('Eredeti számla', $html);
        $this->assertStringContainsString('Sztornószámla', $html);
    }

    private function seedInvoiceContext(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Billingo Sync '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => 'institution_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
                'billingo_api_key' => 'billingo-test-key',
                'billingo_document_block_id' => '77',
            ])
        );

        $discount = DiscountType::create([
            'institution_id' => $institution->id,
            'name' => 'Alap',
            'percentage' => 0,
            'active' => true,
            'sort_order' => 1,
        ]);

        $child = Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => 'Szinkron Gyermek',
            'educational_identifier' => 'SYNC001',
            'group_name' => '3.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Szulo',
            'first_name' => 'Payer',
            'email' => 'szulo@example.test',
            'postal_code' => '1111',
            'city' => 'Budapest',
            'street_name' => 'Fo',
            'street_type' => 'utca',
            'house_number' => '1',
            'source_type' => 'manual',
            'active' => true,
        ]);

        DB::table('child_guardian')->insert([
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'anya',
            'is_legal_representative' => true,
            'has_no_custody' => false,
            'is_emergency_contact' => true,
            'receives_family_allowance' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'discount_id' => $discount->id,
            'meal_amount' => 1000,
            'previous_cancellation_credit' => 0,
            'billing_adjustment_amount' => 0,
            'invoiceable_amount' => 1000,
            'previous_balance' => 0,
            'total_payable' => 1000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
        ]);

        $payment = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => 1000,
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'paid_at' => now(),
            'reference' => 'SYNC-PAY-'.$code,
            'recorded_by' => $user->id,
        ]);

        $invoice = new InstitutionInvoice;
        $invoice->fill([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'institution_payment_id' => $payment->id,
            'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
            'document_type' => InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-0001',
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'net_amount' => 1000,
            'vat_amount' => 0,
            'gross_amount' => 1000,
            'currency' => 'HUF',
            'payment_method' => 'bank_transfer',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'invoice_url' => 'https://billingo.test/invoice/12345',
        ]);
        $invoice->created_by = $user->id;
        $invoice->save();

        return [$institution, $user, $invoice];
    }
}
