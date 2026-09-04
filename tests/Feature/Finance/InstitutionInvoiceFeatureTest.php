<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceController;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceStoreRequest;
use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionMealType;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Models\MealType;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\Finance\InstitutionInvoiceService;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class InstitutionInvoiceFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_can_be_created_for_statement_with_billing_profile(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF01');

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_MANUAL,
                'payment_due_day' => 8,
            ])
        );

        $request = $this->makeStoreRequest($user, [
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'payment_method' => 'bank_transfer',
            'due_date' => '2026-07-20',
            'fulfillment_date' => '2026-07-31',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => 'Teszt szamla',
        ]);
        $this->actingAs($user);
        $response = app(InstitutionInvoiceController::class)->store($request);

        $invoice = InstitutionInvoice::query()->firstOrFail();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.finance.invoices.show', $invoice), $response->getTargetUrl());
        $this->assertSame($institution->id, $invoice->institution_id);
        $this->assertSame($statement->id, $invoice->monthly_payment_statement_id);
        $this->assertSame($statement->child_id, $invoice->child_id);
        $this->assertSame($guardian->id, $invoice->guardian_id);
        $this->assertSame((int) $statement->total_payable, $invoice->gross_amount);
        $this->assertSame(InstitutionInvoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame('Szulo Payer', $invoice->customer_name);
    }

    public function test_duplicate_invoice_for_same_statement_is_blocked(): void
    {
        [$institution, $user, $statement] = $this->seedStatementWithBillingProfile('INVF02');

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_MANUAL,
            ])
        );

        $existingInvoice = new InstitutionInvoice();
        $existingInvoice->fill([
            'institution_id' => $institution->id,
            'child_id' => $statement->child_id,
            'guardian_id' => null,
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'status' => InstitutionInvoice::STATUS_DRAFT,
            'due_date' => '2026-07-20',
            'fulfillment_date' => '2026-07-31',
            'net_amount' => (int) $statement->total_payable,
            'vat_amount' => 0,
            'gross_amount' => (int) $statement->total_payable,
            'currency' => 'HUF',
            'payment_method' => 'cash',
            'customer_name' => 'Meglevo vevo',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
        ]);
        $existingInvoice->institution_id = $institution->id;
        $existingInvoice->created_by = $user->id;
        $existingInvoice->save();

        $this->expectException(HttpResponseException::class);

        app(InstitutionInvoiceService::class)->store($institution, $user, [
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'payment_method' => 'cash',
            'due_date' => '2026-07-20',
            'customer_name' => 'Masik vevo',
            'customer_email' => null,
            'customer_tax_number' => null,
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => null,
        ]);

        $this->assertSame(1, InstitutionInvoice::query()->count());
    }

    public function test_preview_reports_missing_billingo_settings(): void
    {
        [$institution, $user, $statement] = $this->seedStatementWithBillingProfile('INVF03');

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
            ])
        );

        $preview = app(InstitutionInvoiceService::class)->preview(
            $institution,
            $statement,
            InstitutionInvoice::PROVIDER_BILLINGO
        );

        $this->assertFalse($preview['available']);
        $this->assertSame(route('dashboard.institution.settings.invoicing.edit'), $preview['settings_url']);
    }

    public function test_billingo_invoice_pdf_can_be_reloaded_without_creating_new_invoice(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF04');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-0001',
            'invoice_pdf_path' => null,
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/12345/download') {
                return Http::response("%PDF-1.7\nvalid invoice pdf", 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('unexpected request', 500);
        });

        $response = $this->actingAs($user)
            ->post(route('dashboard.institution.finance.invoices.reload-pdf', $invoice));

        $response->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));
        $response->assertSessionHas('success', 'A számla PDF-je elérhető a helyi tárolóban.');

        $invoice->refresh();

        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame('invoices/billingo/'.$institution->id.'/BILL-2026-0001.pdf', $invoice->invoice_pdf_path);
        Storage::disk('local')->assertExists($invoice->invoice_pdf_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($invoice->invoice_pdf_path));

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://api.billingo.hu/v3/documents/12345/download');
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.billingo.hu/v3/documents');
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.billingo.hu/v3/documents/12345/cancel');
    }

    public function test_billingo_cancellation_pdf_can_be_reloaded_without_creating_new_cancellation_document(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF05');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_VOIDED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-0002',
            'cancellation_provider_document_id' => '67890',
            'cancellation_invoice_number' => 'STORNO-2026-0001',
            'cancellation_pdf_path' => null,
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/67890/download') {
                return Http::response("%PDF-1.4\nvalid storno pdf", 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('unexpected request', 500);
        });

        $response = $this->actingAs($user)
            ->post(route('dashboard.institution.finance.invoices.reload-cancellation-pdf', $invoice));

        $response->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));
        $response->assertSessionHas('success', 'A sztornó bizonylat PDF-je elérhető a helyi tárolóban.');

        $invoice->refresh();
        $cancellationInvoice = $invoice->cancellationInvoice()->firstOrFail();

        $this->assertSame(InstitutionInvoice::STATUS_VOIDED, $invoice->status);
        $this->assertNull($invoice->cancellation_pdf_path);
        $this->assertSame('invoices/billingo_storno/'.$institution->id.'/STORNO-2026-0001.pdf', $cancellationInvoice->invoice_pdf_path);
        Storage::disk('local')->assertExists($cancellationInvoice->invoice_pdf_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($cancellationInvoice->invoice_pdf_path));

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://api.billingo.hu/v3/documents/67890/download');
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.billingo.hu/v3/documents');
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.billingo.hu/v3/documents/12345/cancel');
    }

    public function test_failed_billingo_pdf_reload_keeps_successfully_issued_invoice_state_unchanged(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF06');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-0003',
            'invoice_pdf_path' => null,
            'error_message' => null,
        ]);

        Http::fake(fn () => Http::response(['message' => 'Not ready yet'], 424, ['Content-Type' => 'application/json']));

        $response = $this->actingAs($user)
            ->from(route('dashboard.institution.finance.invoices.show', $invoice))
            ->post(route('dashboard.institution.finance.invoices.reload-pdf', $invoice));

        $response->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));
        $response->assertSessionHasErrors([
            'invoice' => 'A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.',
        ]);

        $invoice->refresh();

        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
        $this->assertNull($invoice->invoice_pdf_path);
        $this->assertNull($invoice->error_message);

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://api.billingo.hu/v3/documents/12345/download');
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_billingo_pdf_reload_rejects_json_response_and_logs_warning(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF07');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');
        Log::spy();

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-JSON',
            'invoice_pdf_path' => null,
            'invoice_url' => null,
        ]);

        Http::fake(fn () => Http::response('{"message":"Not ready yet","token":"secret"}', 200, ['Content-Type' => 'application/json']));

        $response = $this->actingAs($user)
            ->from(route('dashboard.institution.finance.invoices.show', $invoice))
            ->post(route('dashboard.institution.finance.invoices.reload-pdf', $invoice));

        $response->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));
        $response->assertSessionHasErrors([
            'invoice' => 'A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.',
        ]);

        $invoice->refresh();

        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
        $this->assertNull($invoice->invoice_pdf_path);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($invoice, $institution) {
                return $message === 'Billingo PDF letöltése sikertelen: érvénytelen PDF válasz'
                    && $context['invoice_id'] === $invoice->id
                    && $context['institution_id'] === $institution->id
                    && $context['status'] === 200
                    && $context['content_type'] === 'application/json'
                    && str_contains($context['body_excerpt'], 'Not ready yet')
                    && ! str_contains($context['body_excerpt'], 'secret');
            });
    }

    public function test_billingo_pdf_reload_rejects_html_response(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF08');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-HTML',
            'invoice_pdf_path' => null,
        ]);

        Http::fake(fn () => Http::response('<html><body>Waiting room</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']));

        $response = $this->actingAs($user)
            ->from(route('dashboard.institution.finance.invoices.show', $invoice))
            ->post(route('dashboard.institution.finance.invoices.reload-pdf', $invoice));

        $response->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));
        $response->assertSessionHasErrors([
            'invoice' => 'A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.',
        ]);

        $invoice->refresh();
        $this->assertNull($invoice->invoice_pdf_path);
        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
    }

    public function test_billingo_pdf_reload_rejects_empty_response(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF09');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-EMPTY',
            'invoice_pdf_path' => null,
        ]);

        Http::fake(fn () => Http::response('', 200, ['Content-Type' => 'application/pdf']));

        $response = $this->actingAs($user)
            ->from(route('dashboard.institution.finance.invoices.show', $invoice))
            ->post(route('dashboard.institution.finance.invoices.reload-pdf', $invoice));

        $response->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));
        $response->assertSessionHasErrors([
            'invoice' => 'A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.',
        ]);

        $invoice->refresh();
        $this->assertNull($invoice->invoice_pdf_path);
        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
    }

    public function test_billingo_pdf_reload_rejects_invalid_pdf_signature(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF09B');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-BADSIG',
            'invoice_pdf_path' => null,
        ]);

        Http::fake(fn () => Http::response('not-a-pdf', 200, ['Content-Type' => 'application/pdf']));

        $response = $this->actingAs($user)
            ->from(route('dashboard.institution.finance.invoices.show', $invoice))
            ->post(route('dashboard.institution.finance.invoices.reload-pdf', $invoice));

        $response->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));
        $response->assertSessionHasErrors([
            'invoice' => 'A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.',
        ]);

        $invoice->refresh();
        $this->assertNull($invoice->invoice_pdf_path);
        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
    }

    public function test_invalid_local_billingo_pdf_is_not_downloadable_and_can_be_overwritten_by_valid_reload(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF10');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $invalidPath = 'invoices/billingo/'.$institution->id.'/ETK-2026-1.pdf';
        Storage::disk('local')->put($invalidPath, '{"message":"not a pdf"}');

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_VOIDED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'ETK-2026-1',
            'invoice_url' => null,
            'invoice_pdf_path' => $invalidPath,
        ]);

        $html = $this->renderInvoiceShow($user, $invoice);

        $this->assertStringContainsString('A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.', $html);
        $this->assertStringContainsString(route('dashboard.institution.finance.invoices.reload-pdf', $invoice), $html);
        $this->assertStringNotContainsString(route('dashboard.institution.finance.invoices.download', $invoice), $html);

        $downloadResponse = $this->actingAs($user)
            ->get(route('dashboard.institution.finance.invoices.download', $invoice));
        $downloadResponse->assertNotFound();

        Http::fake(fn () => Http::response("%PDF-1.7\nfixed pdf", 200, ['Content-Type' => 'application/pdf']));

        $reloadResponse = $this->actingAs($user)
            ->post(route('dashboard.institution.finance.invoices.reload-pdf', $invoice));

        $reloadResponse->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame($invalidPath, $invoice->invoice_pdf_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($invalidPath));
    }

    public function test_billingo_invoice_creation_stays_issued_when_initial_pdf_download_is_invalid(): void
    {
        [$institution, $user, $statement] = $this->seedStatementWithBillingProfile('INVF11');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/documents') {
                return Http::response([
                    'id' => 55555,
                    'invoice_number' => 'ETK-2026-11',
                    'public_url' => 'https://billingo.test/invoice/55555',
                ], 201);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/55555/download') {
                return Http::response('{"message":"PDF generation pending"}', 200, ['Content-Type' => 'application/json']);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/partners') {
                return Http::response(['id' => 987], 201);
            }

            return Http::response('unexpected request', 500);
        });

        $request = $this->makeStoreRequest($user, [
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
            'payment_method' => 'bank_transfer',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => 'Billingo teszt',
        ]);

        $this->actingAs($user);
        $response = app(InstitutionInvoiceController::class)->store($request);

        $invoice = InstitutionInvoice::query()->firstOrFail();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame('55555', $invoice->provider_invoice_id);
        $this->assertSame('ETK-2026-11', $invoice->invoice_number);
        $this->assertSame('https://billingo.test/invoice/55555', $invoice->invoice_url);
        $this->assertNull($invoice->invoice_pdf_path);
        $this->assertNull($invoice->error_message);

        $html = $this->renderInvoiceShow($user, $invoice);

        $this->assertStringContainsString('A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.', $html);
        $this->assertStringContainsString(route('dashboard.institution.finance.invoices.reload-pdf', $invoice), $html);
    }

    public function test_billingo_cancellation_stays_voided_when_initial_cancellation_pdf_download_is_invalid(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF12');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'ETK-2026-12',
            'invoice_pdf_path' => null,
            'institution_payment_id' => InstitutionPayment::create([
                'institution_id' => $institution->id,
                'guardian_id' => $guardian->id,
                'child_id' => $statement->child_id,
                'monthly_payment_statement_id' => $statement->id,
                'amount' => (int) $statement->total_payable,
                'payment_method' => InstitutionPayment::METHOD_CASH,
                'status' => InstitutionPayment::STATUS_COMPLETED,
                'paid_at' => now(),
                'reference' => 'INVF12-PAY',
                'recorded_by' => $user->id,
            ])->id,
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/documents/12345/cancel') {
                return Http::response([
                    'id' => 67890,
                    'invoice_number' => 'STORNO-2026-12',
                    'public_url' => 'https://billingo.test/invoice/67890',
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/67890/download') {
                return Http::response('<html>still generating</html>', 200, ['Content-Type' => 'text/html']);
            }

            return Http::response('unexpected request', 500);
        });

        $cancelled = app(InstitutionInvoiceService::class)->cancel($institution, $user, $invoice, 'Teszt sztornó');

        $this->assertSame(InstitutionInvoice::STATUS_VOIDED, $cancelled->status);
        $this->assertSame('67890', $cancelled->cancellation_provider_document_id);
        $this->assertSame('STORNO-2026-12', $cancelled->cancellation_invoice_number);
        $this->assertNull($cancelled->cancellation_pdf_path);

        $cancellationInvoice = InstitutionInvoice::query()
            ->where('original_invoice_id', $invoice->id)
            ->where('provider_invoice_id', '67890')
            ->first();

        $this->assertNotNull($cancellationInvoice);
        $this->assertSame($invoice->institution_payment_id, $cancelled->institution_payment_id);
        $this->assertNull($cancellationInvoice->institution_payment_id);

        $html = $this->renderInvoiceShow($user, $cancelled);

        $this->assertStringContainsString('A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.', $html);
        $this->assertStringContainsString(route('dashboard.institution.finance.invoices.reload-pdf', $cancellationInvoice), $html);
    }

    /**
     * Regresszió teszt a "sztornó számla nem számít bele az
     * összesítésekbe/egyenlegbe" hibára: a cancel() által létrehozott
     * jóváíró számla összegét NEGATÍVAN kell rögzíteni, hogy az admin
     * számla-összesítő (InstitutionInvoiceService::summary()) automatikusan
     * nullázza az eredeti+sztornó párost - ne duplán, pozitívként adja
     * hozzá az eredeti számla összegéhez. Emellett a sztornó-sor saját
     * (kiállítva) állapotát kell mutatnia, nem a befizetés-alapú
     * "kifizetve"/"lejárt" számított státuszt.
     */
    public function test_cancelled_invoice_amount_is_netted_out_in_admin_summary(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF20');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $payment = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'guardian_id' => $guardian->id,
            'child_id' => $statement->child_id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => (int) $statement->total_payable,
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'paid_at' => now(),
            'reference' => 'INVF20-PAY',
            'recorded_by' => $user->id,
        ]);

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'ETK-2026-20',
            'invoice_pdf_path' => 'invoices/billingo/'.$institution->id.'/ETK-2026-20.pdf',
            'institution_payment_id' => $payment->id,
        ]);
        Storage::disk('local')->put($invoice->invoice_pdf_path, "%PDF-1.7\noriginal pdf");

        $originalGrossAmount = (int) $invoice->gross_amount;
        $originalNetAmount = (int) $invoice->net_amount;

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/documents/12345/cancel') {
                return Http::response([
                    'id' => 67890,
                    'invoice_number' => 'STORNO-2026-20',
                    'public_url' => 'https://billingo.test/invoice/67890',
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/67890/download') {
                return Http::response("%PDF-1.7\nstorno pdf", 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('unexpected request', 500);
        });

        $cancelled = app(InstitutionInvoiceService::class)->cancel($institution, $user, $invoice, 'Teszt sztornó összesítés');

        $cancellationInvoice = InstitutionInvoice::query()
            ->where('original_invoice_id', $invoice->id)
            ->firstOrFail();

        // A sztornó számla összege az eredeti negatívja, az eredeti számla
        // saját összege pedig nem változik.
        $this->assertSame(-$originalGrossAmount, $cancellationInvoice->gross_amount);
        $this->assertSame(-$originalNetAmount, $cancellationInvoice->net_amount);
        $this->assertSame($originalGrossAmount, $cancelled->gross_amount);
        $this->assertSame(InstitutionInvoice::STATUS_VOIDED, $cancelled->status);

        $invoiceService = app(InstitutionInvoiceService::class);
        $query = $invoiceService->query($institution);
        $summary = $invoiceService->summary($query);

        // A teljes befizetés ellenére a sztornóval lezárt tétel ne
        // szerepeljen "kiegyenlítetlen" (unpaid) összegként, és a bruttó
        // összesítő (gross_total) nullázódjon, ne duplázódjon.
        $this->assertSame(0, (int) $summary['gross_total']);
        $this->assertSame(0, (int) $summary['unpaid_total']);
        $this->assertSame(0, $summary['overdue_count']);

        // A sztornó dokumentum a listában a SAJÁT (kiállítva) állapotát
        // mutassa, ne a befizetés-alapú "kifizetve" számított státuszt -
        // hiába fedezi a korábbi teljes befizetés a (negatív) összegét.
        $paginated = $invoiceService->paginate($invoiceService->query($institution));
        $cancellationRow = $paginated->getCollection()->firstWhere('id', $cancellationInvoice->id);
        $originalRow = $paginated->getCollection()->firstWhere('id', $invoice->id);

        $this->assertNotNull($cancellationRow);
        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $cancellationRow->effective_status);
        $this->assertSame(InstitutionInvoice::STATUS_VOIDED, $originalRow->effective_status);
    }

    /**
     * Regresszió teszt arra a hibára, hogy sztornózás után a szülői
     * elszámolás (ParentMonthlySettlementService) továbbra is
     * "kifizetettként" mutatta a hónapot, mert a korábbi teljesült
     * befizetés (InstitutionPayment) nem lett visszavonva a számla
     * sztornózásakor. A cancel() mostantól - ha a befizetés az
     * institution_payment_id mezőn keresztül egyértelműen azonosítható -
     * "cancelled" állapotba állítja a befizetést, hogy a fennmaradó
     * összeg a sztornó után újra pozitív legyen és a szülő fizethessen.
     */
    public function test_cancelling_invoice_reverses_linked_payment_identified_by_institution_payment_id(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF21');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $payment = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'guardian_id' => $guardian->id,
            'child_id' => $statement->child_id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => (int) $statement->total_payable,
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'paid_at' => now(),
            'reference' => 'INVF21-PAY',
            'recorded_by' => $user->id,
        ]);

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'ETK-2026-21',
            'invoice_pdf_path' => null,
            'institution_payment_id' => $payment->id,
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/documents/12345/cancel') {
                return Http::response([
                    'id' => 67890,
                    'invoice_number' => 'STORNO-2026-21',
                    'public_url' => 'https://billingo.test/invoice/67890',
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/67890/download') {
                return Http::response('<html>still generating</html>', 200, ['Content-Type' => 'text/html']);
            }

            return Http::response('unexpected request', 500);
        });

        app(InstitutionInvoiceService::class)->cancel($institution, $user, $invoice, 'Teszt fizetés-visszavonás');

        $payment->refresh();
        $this->assertSame(InstitutionPayment::STATUS_CANCELLED, $payment->status);
        $this->assertStringContainsString('ETK-2026-21', (string) $payment->note);

        // A statement szintjén a szülői oldal logikájával megegyező módon
        // (SUM completed InstitutionPayment) most már 0-nak kell lennie a
        // teljesült befizetések összegének, tehát a teljes total_payable
        // ismét fizetendőnek számít.
        $completedTotal = (int) InstitutionPayment::query()
            ->where('monthly_payment_statement_id', $statement->id)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->sum('amount');
        $this->assertSame(0, $completedTotal);
    }

    /**
     * Ugyanaz mint fentebb, de az institution_payment_id mező NINCS
     * kitöltve a számlán (ez a gyakorlatban előfordul) - ilyenkor a
     * kimutatáshoz (monthly_payment_statement_id) tartozó, PONTOSAN EGY
     * teljesült befizetést kell megtalálnia és visszavonnia.
     */
    public function test_cancelling_invoice_reverses_the_single_completed_payment_when_institution_payment_id_is_missing(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF22');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $payment = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'guardian_id' => $guardian->id,
            'child_id' => $statement->child_id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => (int) $statement->total_payable,
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'paid_at' => now(),
            'reference' => 'INVF22-PAY',
            'recorded_by' => $user->id,
        ]);

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'ETK-2026-22',
            'invoice_pdf_path' => null,
            'institution_payment_id' => null,
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/documents/12345/cancel') {
                return Http::response([
                    'id' => 67891,
                    'invoice_number' => 'STORNO-2026-22',
                    'public_url' => 'https://billingo.test/invoice/67891',
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/67891/download') {
                return Http::response('<html>still generating</html>', 200, ['Content-Type' => 'text/html']);
            }

            return Http::response('unexpected request', 500);
        });

        app(InstitutionInvoiceService::class)->cancel($institution, $user, $invoice, 'Teszt fizetés-visszavonás fallback');

        $payment->refresh();
        $this->assertSame(InstitutionPayment::STATUS_CANCELLED, $payment->status);
    }

    /**
     * Ha egy kimutatáshoz TÖBB teljesült befizetés is tartozik, és a
     * számlán nincs kitöltve az institution_payment_id, a rendszer
     * szándékosan NEM nyúl automatikusan egyik befizetéshez sem, mert nem
     * egyértelmű, melyik tartozik az adott számlához - ezt az
     * intézménynek kézzel kell rendeznie a Befizetések felületen.
     */
    public function test_cancelling_invoice_does_not_touch_payments_when_more_than_one_completed_payment_exists_and_link_is_ambiguous(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF23');
        $this->storeBillingoSettings($institution);
        Storage::fake('local');

        $paymentA = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'guardian_id' => $guardian->id,
            'child_id' => $statement->child_id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => (int) $statement->total_payable / 2,
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'paid_at' => now(),
            'reference' => 'INVF23-PAY-A',
            'recorded_by' => $user->id,
        ]);

        $paymentB = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'guardian_id' => $guardian->id,
            'child_id' => $statement->child_id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => (int) $statement->total_payable / 2,
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'paid_at' => now(),
            'reference' => 'INVF23-PAY-B',
            'recorded_by' => $user->id,
        ]);

        $invoice = $this->createBillingoInvoice($institution, $statement, $guardian, $user, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'ETK-2026-23',
            'invoice_pdf_path' => null,
            'institution_payment_id' => null,
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/documents/12345/cancel') {
                return Http::response([
                    'id' => 67892,
                    'invoice_number' => 'STORNO-2026-23',
                    'public_url' => 'https://billingo.test/invoice/67892',
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/67892/download') {
                return Http::response('<html>still generating</html>', 200, ['Content-Type' => 'text/html']);
            }

            return Http::response('unexpected request', 500);
        });

        app(InstitutionInvoiceService::class)->cancel($institution, $user, $invoice, 'Teszt - kétértelmű befizetés');

        $paymentA->refresh();
        $paymentB->refresh();
        $this->assertSame(InstitutionPayment::STATUS_COMPLETED, $paymentA->status);
        $this->assertSame(InstitutionPayment::STATUS_COMPLETED, $paymentB->status);
    }

    /**
     * FONTOS PÉNZÜGYI SZABÁLY (2026-09-es javítás) regressziós tesztje: a
     * kiállított számla bruttó összege KIZÁRÓLAG az aktuális havi
     * (invoiceable_amount) rész lehet, a korábbi tartozás (previous_balance)
     * SOSEM kerülhet bele - még akkor sem, ha a total_payable (ami a kettő
     * összege) nagyobb. Ld. PaymentObligationCalculatorService::
     * refreshStatementTotals() és InstitutionInvoiceService::store().
     */
    public function test_invoice_gross_amount_excludes_prior_debt_from_total_payable(): void
    {
        [$institution, $user, $statement] = $this->seedStatementWithBillingProfile('INVF30');

        // Az elszámolás aktuális havi (számlázandó) része 10 000 Ft, de a
        // total_payable (10 000 + 5 000 korábbi tartozás) 15 000 Ft.
        $statement->forceFill([
            'invoiceable_amount' => 10000,
            'previous_balance' => 5000,
            'total_payable' => 15000,
        ])->save();

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_MANUAL,
            ])
        );

        $invoice = app(InstitutionInvoiceService::class)->store($institution, $user, [
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'payment_method' => 'cash',
            'due_date' => '2026-07-20',
            'fulfillment_date' => '2026-07-31',
            'customer_name' => 'Szulo Payer',
            'customer_email' => null,
            'customer_tax_number' => null,
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => null,
        ]);

        $this->assertSame(10000, $invoice->gross_amount);
        $this->assertSame(10000, $invoice->net_amount);
        $this->assertNotSame(15000, $invoice->gross_amount);
    }

    /**
     * Ugyanaz mint fent, de korábbi TÚLFIZETÉSSEL (negatív previous_balance):
     * a számlázott összegnek ekkor is pontosan az aktuális havi résznek kell
     * lennie, nem a (kisebb) total_payable-nek.
     */
    public function test_invoice_gross_amount_excludes_prior_overpayment_from_total_payable(): void
    {
        [$institution, $user, $statement] = $this->seedStatementWithBillingProfile('INVF31');

        // Aktuális havi rész 10 000 Ft, de 2 000 Ft korábbi túlfizetés miatt
        // a total_payable csak 8 000 Ft.
        $statement->forceFill([
            'invoiceable_amount' => 10000,
            'previous_balance' => -2000,
            'total_payable' => 8000,
        ])->save();

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_MANUAL,
            ])
        );

        $invoice = app(InstitutionInvoiceService::class)->store($institution, $user, [
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'payment_method' => 'cash',
            'due_date' => '2026-07-20',
            'fulfillment_date' => '2026-07-31',
            'customer_name' => 'Szulo Payer',
            'customer_email' => null,
            'customer_tax_number' => null,
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => null,
        ]);

        $this->assertSame(10000, $invoice->gross_amount);
        $this->assertNotSame(8000, $invoice->gross_amount);
    }

    /**
     * 0 Ft (vagy negatív) AKTUÁLIS HAVI (invoiceable_amount) összegre nem
     * lehet számlát kiállítani - még akkor sem, ha a total_payable pozitív
     * (mert pl. korábbi tartozás miatt), mivel a korábbi tartozást nem
     * szabad az újonnan kiállított számlába belefoglalni.
     */
    public function test_invoice_cannot_be_created_when_invoiceable_amount_is_zero_even_if_total_payable_is_positive(): void
    {
        [$institution, $user, $statement] = $this->seedStatementWithBillingProfile('INVF32');

        $statement->forceFill([
            'invoiceable_amount' => 0,
            'previous_balance' => 5000,
            'total_payable' => 5000,
        ])->save();

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_MANUAL,
            ])
        );

        $this->expectException(HttpResponseException::class);

        try {
            app(InstitutionInvoiceService::class)->store($institution, $user, [
                'monthly_payment_statement_id' => $statement->id,
                'provider' => InstitutionInvoice::PROVIDER_MANUAL,
                'payment_method' => 'cash',
                'due_date' => '2026-07-20',
                'fulfillment_date' => '2026-07-31',
                'customer_name' => 'Szulo Payer',
                'customer_email' => null,
                'customer_tax_number' => null,
                'billing_postcode' => '1111',
                'billing_city' => 'Budapest',
                'billing_address' => 'Fo utca 1.',
                'note' => null,
            ]);
        } finally {
            $this->assertSame(0, InstitutionInvoice::query()->count());
        }
    }

    /**
     * A preview()/buildPreview() adja a "Számla elkészítése" felület (és a
     * Phase 2-es szülői felület) alapját - az onnan visszakapott
     * gross_amount-nak szintén az invoiceable_amount-ot kell tükröznie, a
     * korábbi egyenleget pedig KÜLÖN, informatív mezőkben kell visszaadnia.
     */
    public function test_preview_separates_previous_balance_from_invoiceable_gross_amount(): void
    {
        [$institution, , $statement] = $this->seedStatementWithBillingProfile('INVF33');

        $statement->forceFill([
            'invoiceable_amount' => 10000,
            'previous_balance' => 5000,
            'total_payable' => 15000,
        ])->save();

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_MANUAL,
            ])
        );

        $preview = app(InstitutionInvoiceService::class)->preview(
            $institution,
            $statement,
            InstitutionInvoice::PROVIDER_MANUAL
        );

        $this->assertTrue($preview['available']);
        $this->assertSame(10000, $preview['statement']['gross_amount']);
        $this->assertSame(5000, $preview['statement']['previous_balance']);
        $this->assertSame(15000, $preview['statement']['total_payable_with_previous_balance']);
    }

    /**
     * Duplikáció/idempotencia (11. szakasz): egy MÁSODIK store() hívás
     * ugyanarra a kimutatásra - miután az első ténylegesen sikeresen
     * létrehozott egy számlát - blokkolva legyen, és NE hozzon létre második
     * számlát. (A test_duplicate_invoice_for_same_statement_is_blocked teszt
     * egy kézzel odarakott meglévő rekorddal szimulálja ugyanezt - ez a
     * teszt a valós store()->store() útvonalat futtatja végig kétszer.)
     */
    public function test_second_store_call_for_same_statement_does_not_create_a_second_invoice(): void
    {
        [$institution, $user, $statement] = $this->seedStatementWithBillingProfile('INVF34');

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_MANUAL,
            ])
        );

        $payload = [
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'payment_method' => 'cash',
            'due_date' => '2026-07-20',
            'fulfillment_date' => '2026-07-31',
            'customer_name' => 'Szulo Payer',
            'customer_email' => null,
            'customer_tax_number' => null,
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => null,
        ];

        app(InstitutionInvoiceService::class)->store($institution, $user, $payload);
        $this->assertSame(1, InstitutionInvoice::query()->count());

        $this->expectException(HttpResponseException::class);

        try {
            app(InstitutionInvoiceService::class)->store($institution, $user, $payload);
        } finally {
            $this->assertSame(1, InstitutionInvoice::query()->count());
        }
    }

    private function storeSzamlazzHuSettings(Institution $institution): void
    {
        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_SZAMLAZZ_HU,
                'szamlazz_hu_agent_key' => 'szamlazz-test-agent-key',
                'szamlazz_hu_invoice_prefix' => 'ETK',
            ])
        );

        $this->forgetCachedInstitutionSettingRelation($institution);
    }

    private function fakeSzamlazzHuSuccessXml(string $invoiceNumber, string $pdfContent = "%PDF-1.4\nszamlazz pdf"): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><xmlszamlavalasz>'
            .'<sikeres>true</sikeres>'
            .'<szamlaszam>'.$invoiceNumber.'</szamlaszam>'
            .'<pdf>'.base64_encode($pdfContent).'</pdf>'
            .'</xmlszamlavalasz>';
    }

    private function fakeSzamlazzHuErrorXml(string $message = 'Teszt hiba a Számlázz.hu válaszában', string $code = '57'): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><xmlszamlavalasz>'
            .'<sikeres>false</sikeres>'
            .'<hibakod>'.$code.'</hibakod>'
            .'<hibauzenet>'.$message.'</hibauzenet>'
            .'</xmlszamlavalasz>';
    }

    public function test_szamlazz_hu_invoice_can_be_created_with_current_month_amount_only_and_pdf_is_stored_locally(): void
    {
        [$institution, $user, $statement] = $this->seedStatementWithBillingProfile('INVF40');
        $this->storeSzamlazzHuSettings($institution);
        Storage::fake('local');

        $statement->forceFill([
            'invoiceable_amount' => 10000,
            'previous_balance' => 5000,
            'total_payable' => 15000,
        ])->save();

        Http::fake(function (Request $request) {
            if ($request->url() === 'https://www.szamlazz.hu/szamla/') {
                return Http::response(
                    $this->fakeSzamlazzHuSuccessXml('ETK-2026-0001'),
                    200,
                    ['Content-Type' => 'application/xml']
                );
            }

            return Http::response('unexpected request', 500);
        });

        $request = $this->makeStoreRequest($user, [
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_SZAMLAZZ_HU,
            'payment_method' => 'bank_transfer',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => 'Szamlazz.hu teszt',
        ]);

        $this->actingAs($user);
        app(InstitutionInvoiceController::class)->store($request);

        $invoice = InstitutionInvoice::query()->firstOrFail();

        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame(10000, $invoice->gross_amount);
        $this->assertNotSame(15000, $invoice->gross_amount);
        $this->assertSame('ETK-2026-0001', $invoice->invoice_number);
        $this->assertSame('ETK-2026-0001', $invoice->provider_invoice_id);
        $this->assertSame('invoices/szamlazz_hu/'.$institution->id.'/ETK-2026-0001.pdf', $invoice->invoice_pdf_path);
        Storage::disk('local')->assertExists($invoice->invoice_pdf_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($invoice->invoice_pdf_path));
    }

    /**
     * Ha a Számlázz.hu API hibaválaszt ad, a helyi rekordnak "failed"
     * állapotban kell maradnia - NEM szabad, hogy félkész "kiállított"
     * (issued) állapotú, de valójában sosem létrejött bizonylat maradjon a
     * rendszerben. A "failed" rekord emellett törölhető (isDeletable()),
     * hogy az admin újrapróbálkozhasson.
     */
    public function test_szamlazz_hu_api_error_leaves_no_half_created_issued_invoice(): void
    {
        [$institution, $user, $statement] = $this->seedStatementWithBillingProfile('INVF41');
        $this->storeSzamlazzHuSettings($institution);
        Storage::fake('local');

        Http::fake(fn () => Http::response($this->fakeSzamlazzHuErrorXml(), 200, ['Content-Type' => 'application/xml']));

        $request = $this->makeStoreRequest($user, [
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_SZAMLAZZ_HU,
            'payment_method' => 'bank_transfer',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => 'Szamlazz.hu hiba teszt',
        ]);

        $this->actingAs($user);
        app(InstitutionInvoiceController::class)->store($request);

        $invoice = InstitutionInvoice::query()->firstOrFail();

        $this->assertSame(InstitutionInvoice::STATUS_FAILED, $invoice->status);
        $this->assertNull($invoice->invoice_pdf_path);
        $this->assertNull($invoice->provider_invoice_id);
        $this->assertTrue($invoice->isDeletable());
        $this->assertStringContainsString('Teszt hiba', (string) $invoice->error_message);

        // Sikertelen kiállítás esetén NEM jöhet létre semmilyen PDF-fájl a
        // helyi tárolón ehhez az intézményhez.
        $this->assertSame([], Storage::disk('local')->allFiles('invoices/szamlazz_hu/'.$institution->id));
    }

    /**
     * Kapcsolati hiba (időtúllépés) esetén a bizonylat a szolgáltatónál
     * ELKÉSZÜLHETETT, csak a válasz nem érkezett meg - ilyenkor a helyi
     * rekord "failed" marad (törölhető/újrapróbálható), de a hibaüzenetnek
     * kifejezetten figyelmeztetnie kell az adminisztrátort, hogy a
     * Számlázz.hu felületén ellenőrizze a duplikáció elkerülése érdekében,
     * mielőtt törli/újrapróbálja - ld. handleConnectionException().
     */
    public function test_szamlazz_hu_connection_timeout_marks_invoice_failed_with_duplicate_risk_warning(): void
    {
        [$institution, $user, $statement] = $this->seedStatementWithBillingProfile('INVF42');
        $this->storeSzamlazzHuSettings($institution);
        Storage::fake('local');

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $request = $this->makeStoreRequest($user, [
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_SZAMLAZZ_HU,
            'payment_method' => 'bank_transfer',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => 'Szamlazz.hu idotullepes teszt',
        ]);

        $this->actingAs($user);
        app(InstitutionInvoiceController::class)->store($request);

        $invoice = InstitutionInvoice::query()->firstOrFail();

        $this->assertSame(InstitutionInvoice::STATUS_FAILED, $invoice->status);
        $this->assertSame(1, InstitutionInvoice::query()->count());
        $this->assertStringContainsString('ELŐFORDULHAT', (string) $invoice->error_message);
        $this->assertStringContainsString('duplikált', (string) $invoice->error_message);
    }

    /**
     * A korábban implementált Számlázz.hu PDF-újralekérdezés (9. szakasz):
     * a meglévő bizonylat PDF-je újralekérhető anélkül, hogy új
     * kiállítási/sztornó kérés menne ki.
     */
    public function test_szamlazz_hu_invoice_pdf_can_be_reloaded_without_reissuing(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF43');
        $this->storeSzamlazzHuSettings($institution);
        Storage::fake('local');

        $invoice = new InstitutionInvoice();
        $invoice->fill([
            'institution_id' => $institution->id,
            'child_id' => $statement->child_id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_SZAMLAZZ_HU,
            'provider_invoice_id' => 'ETK-2026-0099',
            'invoice_number' => 'ETK-2026-0099',
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'net_amount' => (int) $statement->invoiceable_amount,
            'vat_amount' => 0,
            'gross_amount' => (int) $statement->invoiceable_amount,
            'currency' => 'HUF',
            'payment_method' => 'bank_transfer',
            'customer_name' => 'Szulo Payer',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'invoice_pdf_path' => null,
        ]);
        $invoice->institution_id = $institution->id;
        $invoice->created_by = $user->id;
        $invoice->save();

        Http::fake(function (Request $request) {
            if ($request->url() === 'https://www.szamlazz.hu/szamla/') {
                return Http::response(
                    $this->fakeSzamlazzHuSuccessXml('ETK-2026-0099', "%PDF-1.7\nujralekerdezett pdf"),
                    200,
                    ['Content-Type' => 'application/xml']
                );
            }

            return Http::response('unexpected request', 500);
        });

        $response = $this->actingAs($user)
            ->post(route('dashboard.institution.finance.invoices.reload-pdf', $invoice));

        $response->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));

        $invoice->refresh();

        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame('invoices/szamlazz_hu/'.$institution->id.'/ETK-2026-0099.pdf', $invoice->invoice_pdf_path);
        Storage::disk('local')->assertExists($invoice->invoice_pdf_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($invoice->invoice_pdf_path));
    }

    /**
     * Regressziós teszt a buildCancelRequestXml() dupla escape-elési
     * hibájára: a sztornó indoklás (reason) mezőt a SimpleXMLElement::
     * addChild() automatikusan escape-eli - ha ezt előtte még
     * htmlspecialchars()-szel is escape-eljük, a különleges karakterek
     * (pl. "&") duplán escape-elődnek (pl. "&amp;amp;"). A javított kód a
     * nyers értéket adja át, ezért a kimenő XML-ben pontosan egyszeres
     * escape-elésnek kell megjelennie.
     */
    public function test_szamlazz_hu_cancel_reason_is_not_double_escaped_in_outgoing_xml(): void
    {
        [$institution, $user, $statement, $guardian] = $this->seedStatementWithBillingProfile('INVF44');
        $this->storeSzamlazzHuSettings($institution);
        Storage::fake('local');

        $invoice = new InstitutionInvoice();
        $invoice->fill([
            'institution_id' => $institution->id,
            'child_id' => $statement->child_id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_SZAMLAZZ_HU,
            'provider_invoice_id' => 'ETK-2026-0100',
            'invoice_number' => 'ETK-2026-0100',
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'net_amount' => (int) $statement->invoiceable_amount,
            'vat_amount' => 0,
            'gross_amount' => (int) $statement->invoiceable_amount,
            'currency' => 'HUF',
            'payment_method' => 'bank_transfer',
            'customer_name' => 'Szulo Payer',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'invoice_pdf_path' => null,
        ]);
        $invoice->institution_id = $institution->id;
        $invoice->created_by = $user->id;
        $invoice->save();

        $reasonWithSpecialChars = 'Rossz cim & hibas adat "teszt"';
        $capturedBody = null;

        Http::fake(function (Request $request) use (&$capturedBody) {
            $capturedBody = $request->body();

            return Http::response(
                $this->fakeSzamlazzHuSuccessXml('STORNO-2026-0100'),
                200,
                ['Content-Type' => 'application/xml']
            );
        });

        app(InstitutionInvoiceService::class)->cancel($institution, $user, $invoice, $reasonWithSpecialChars);

        $this->assertNotNull($capturedBody);
        // Egyszeres escape-elés esetén az "&" karakter "&amp;"-ként jelenik
        // meg az XML-ben - dupla escape-elés esetén "&amp;amp;" lenne.
        $this->assertStringContainsString('Rossz cim &amp; hibas adat', $capturedBody);
        $this->assertStringNotContainsString('&amp;amp;', $capturedBody);
    }

    private function seedStatementWithBillingProfile(string $code): array
    {
        [$institution, $user] = $this->seedUserWithInstitution($code);

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
            'name' => 'Szamla Gyermek',
            'educational_identifier' => 'SZG001',
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

        $billingProfile = BillingProfile::create([
            'institution_id' => $institution->id,
            'guardian_id' => $guardian->id,
            'payer_type' => 'guardian',
            'billing_name' => 'Szulo Payer',
            'tax_number' => '12345678-1-42',
            'postal_code' => '1111',
            'city' => 'Budapest',
            'address' => 'Fo utca 1.',
            'email' => 'szulo@example.test',
            'payment_method' => 'bank_transfer',
            'active' => true,
        ]);

        DB::table('billing_profile_child')->insert([
            'billing_profile_id' => $billingProfile->id,
            'child_id' => $child->id,
            'is_primary' => true,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mealType = MealType::create([
            'code' => 'invoice-lunch',
            'name' => 'Ebed',
            'default_order' => 1,
        ]);

        $institutionMealType = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $mealType->id,
            'is_active' => true,
            'is_required' => true,
            'display_order' => 1,
        ]);

        InstitutionMealPrice::create([
            'institution_meal_type_id' => $institutionMealType->id,
            'price' => 1400,
            'valid_from' => '2026-01-01',
            'created_by' => $user->id,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institution->id,
            'name' => 'Szamla csomag',
            'is_active' => true,
            'is_default' => true,
            'display_order' => 1,
            'pricing_mode' => 'component_sum',
            'created_by' => $user->id,
        ]);

        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionMealType->id,
            'display_order' => 1,
        ]);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-01-01',
            'created_by' => $user->id,
        ]);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        return [$institution, $user, MonthlyPaymentStatement::query()->firstOrFail(), $guardian];
    }

    private function seedUserWithInstitution(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Szamla Intezmeny ' . $code,
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

        return [$institution, $user];
    }

    private function storeBillingoSettings(Institution $institution): void
    {
        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
                'billingo_api_key' => 'billingo-test-key',
                'billingo_document_block_id' => '77',
            ])
        );

        $this->forgetCachedInstitutionSettingRelation($institution);
    }

    /**
     * REGRESSZIÓ-VIZSGÁLAT EREDMÉNYE (2026-09): a seedStatementWithBillingProfile()
     * végén meghívott PaymentObligationCalculatorService::recalculateMonth()
     * a fizetési modell eldöntéséhez (InstitutionPaymentComponentService::
     * usesSplitManualTransfer()) MÁR a $institution->setting Eloquent
     * relációt olvassa - ezen a ponton MÉG NEM létezik institution_settings
     * sor, ezért Eloquent a (null) eredményt gyorsítótárazza magán a $institution
     * PHP-objektumon. A storeBillingoSettings()/storeSzamlazzHuSettings()
     * ezután egy KÖZVETLEN query builder updateOrCreate()-tal hozza létre a
     * beállítás-sort - ez NEM frissíti a $institution objektumon már
     * gyorsítótárazott (elavult, null) relációt.
     *
     * Ameddig a teszt a controlleren/HTTP route-on keresztül megy (pl.
     * store()/reload-pdf), ez nem probléma, mert ott mindig egy FRISS,
     * AdminInstitutionContext által lekérdezett Institution-példány kerül
     * felhasználásra. DE ha a teszt közvetlenül, a service-en keresztül hívja
     * a cancel()-t UGYANAZZAL a $institution objektummal (ahogy ez a fájl
     * minden cancel()-tesztje teszi), a BillingoInvoiceProvider::cancelInvoice()
     * / SzamlazzHuInvoiceProvider::cancelInvoice() a régi, gyorsítótárazott
     * null relációt kapja vissza - a hasBillingoApiKey()/hasSzamlazzHuAgentKey()
     * emiatt hamisan hiányzónak látja a beállításokat, és a cancel()
     * (InstitutionInvoiceService.php:452-453) throwValidation()-t dob.
     *
     * FONTOS: ÉLES környezetben ez SOSEM fordulhat elő, mert minden HTTP-kérés
     * friss PHP-folyamatban, friss Institution-példánnyal fut - ez KIZÁRÓLAG a
     * teszt-fixture azon mintájának a következménye, hogy egyetlen, hosszú
     * élettartamú Institution-objektumot használ újra a settings létrehozása
     * ELŐTTI és UTÁNI lépésekhez is. A production validáció (hasBillingoApiKey()/
     * hasSzamlazzHuAgentKey()) HELYES és VÁLTOZATLAN maradt - a javítás
     * kizárólag a teszt-fixture-ben történt: a beállítások mentése UTÁN
     * explicit töröljük a gyorsítótárazott relációt, hogy a következő
     * $institution->setting hozzáférés friss adatot töltsön be.
     */
    private function forgetCachedInstitutionSettingRelation(Institution $institution): void
    {
        $institution->unsetRelation('setting');
    }

    private function createBillingoInvoice(
        Institution $institution,
        MonthlyPaymentStatement $statement,
        Guardian $guardian,
        User $user,
        array $overrides = []
    ): InstitutionInvoice {
        $invoice = new InstitutionInvoice();
        $invoice->fill(array_merge([
            'institution_id' => $institution->id,
            'child_id' => $statement->child_id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-DEFAULT',
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'net_amount' => (int) $statement->total_payable,
            'vat_amount' => 0,
            'gross_amount' => (int) $statement->total_payable,
            'currency' => 'HUF',
            'payment_method' => 'bank_transfer',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'invoice_url' => 'https://billingo.test/invoice.pdf',
            'invoice_pdf_path' => null,
            'error_message' => null,
            'cancellation_provider_document_id' => null,
            'cancellation_invoice_number' => null,
            'cancellation_pdf_path' => null,
        ], $overrides));
        $invoice->created_by = $user->id;
        $invoice->save();

        return $invoice;
    }

    private function makeStoreRequest(User $user, array $payload): InstitutionInvoiceStoreRequest
    {
        $request = InstitutionInvoiceStoreRequest::create(
            route('dashboard.institution.finance.invoices.store'),
            'POST',
            $payload
        );
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $validator = $this->app['validator']->make($request->all(), $request->rules());
        $request->setValidator($validator);

        return $request;
    }

    private function renderInvoiceShow(User $user, InstitutionInvoice $invoice): string
    {
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        return app(InstitutionInvoiceController::class)->show($invoice)->render();
    }
}
