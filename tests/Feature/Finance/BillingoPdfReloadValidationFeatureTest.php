<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceController;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class BillingoPdfReloadValidationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_pdf_header_response_is_saved_for_invoice_reload(): void
    {
        [$institution, $user, $invoice] = $this->seedBillingoInvoiceContext('BPDF01', [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-0001',
            'invoice_pdf_path' => null,
        ]);

        $this->resetInvoiceStorage($institution);
        Http::fake(fn () => Http::response("%PDF-1.7\nvalid invoice pdf", 200, ['Content-Type' => 'application/pdf']));

        $response = $this->reloadInvoicePdf($user, $invoice);

        $this->assertSame(route('dashboard.institution.finance.invoices.show', $invoice), $response->getTargetUrl());

        $invoice->refresh();

        $this->assertSame('invoices/billingo/'.$institution->id.'/BILL-2026-0001.pdf', $invoice->invoice_pdf_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($invoice->invoice_pdf_path));
        Http::assertSent(fn (HttpClientRequest $request) => $request->method() === 'GET'
            && $request->url() === 'https://api.billingo.hu/v3/documents/12345/download');
        Http::assertNotSent(fn (HttpClientRequest $request) => $request->method() === 'POST');
    }

    public function test_valid_pdf_header_response_is_saved_for_cancellation_reload(): void
    {
        [$institution, $user, $invoice] = $this->seedBillingoInvoiceContext('BPDF02', [
            'status' => InstitutionInvoice::STATUS_VOIDED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-0002',
            'cancellation_provider_document_id' => '67890',
            'cancellation_invoice_number' => 'STORNO-2026-0001',
            'cancellation_pdf_path' => null,
        ]);

        $this->resetInvoiceStorage($institution);
        Http::fake(fn () => Http::response("%PDF-1.4\nvalid storno pdf", 200, ['Content-Type' => 'application/pdf']));

        $response = $this->reloadCancellationPdf($user, $invoice);

        $this->assertSame(route('dashboard.institution.finance.invoices.show', $invoice), $response->getTargetUrl());

        $invoice->refresh();
        $cancellationInvoice = $invoice->cancellationInvoice()->firstOrFail();

        $this->assertNull($invoice->cancellation_pdf_path);
        $this->assertSame('invoices/billingo_storno/'.$institution->id.'/STORNO-2026-0001.pdf', $cancellationInvoice->invoice_pdf_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($cancellationInvoice->invoice_pdf_path));
        Http::assertSent(fn (HttpClientRequest $request) => $request->method() === 'GET'
            && $request->url() === 'https://api.billingo.hu/v3/documents/67890/download');
        Http::assertNotSent(fn (HttpClientRequest $request) => $request->method() === 'POST');
    }

    public function test_json_response_is_rejected_and_logged(): void
    {
        [$institution, $user, $invoice] = $this->seedBillingoInvoiceContext('BPDF03', [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-JSON',
            'invoice_pdf_path' => null,
            'invoice_url' => null,
        ]);

        $this->resetInvoiceStorage($institution);
        Log::spy();
        Http::fake(fn () => Http::response('{"message":"Not ready yet","token":"secret"}', 200, ['Content-Type' => 'application/json']));

        $response = $this->reloadInvoicePdf($user, $invoice, true);

        $this->assertStringContainsString('/dashboard/institution-admin/finance/invoices/'.$invoice->id, $response->getTargetUrl());
        $this->assertSame(
            'A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.',
            $response->getSession()->get('errors')->getBag('default')->first('invoice')
        );

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

    public function test_html_response_is_rejected(): void
    {
        [$institution, $user, $invoice] = $this->seedBillingoInvoiceContext('BPDF04', [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-HTML',
            'invoice_pdf_path' => null,
        ]);

        $this->resetInvoiceStorage($institution);
        Log::spy();
        Http::fake(fn () => Http::response('<html><body>Waiting room</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']));

        $response = $this->reloadInvoicePdf($user, $invoice, true);

        $this->assertStringContainsString('/dashboard/institution-admin/finance/invoices/'.$invoice->id, $response->getTargetUrl());
        $this->assertSame(
            'A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.',
            $response->getSession()->get('errors')->getBag('default')->first('invoice')
        );

        $invoice->refresh();
        $this->assertNull($invoice->invoice_pdf_path);
    }

    public function test_empty_pdf_response_is_rejected(): void
    {
        [$institution, $user, $invoice] = $this->seedBillingoInvoiceContext('BPDF05', [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-EMPTY',
            'invoice_pdf_path' => null,
        ]);

        $this->resetInvoiceStorage($institution);
        Log::spy();
        Http::fake(fn () => Http::response('', 200, ['Content-Type' => 'application/pdf']));

        $response = $this->reloadInvoicePdf($user, $invoice, true);

        $this->assertStringContainsString('/dashboard/institution-admin/finance/invoices/'.$invoice->id, $response->getTargetUrl());
        $this->assertSame(
            'A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.',
            $response->getSession()->get('errors')->getBag('default')->first('invoice')
        );

        $invoice->refresh();
        $this->assertNull($invoice->invoice_pdf_path);
    }

    public function test_failed_http_response_is_rejected(): void
    {
        [$institution, $user, $invoice] = $this->seedBillingoInvoiceContext('BPDF06', [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-HTTP',
            'invoice_pdf_path' => null,
        ]);

        $this->resetInvoiceStorage($institution);
        Log::spy();
        Http::fake(fn () => Http::response(['message' => 'Not ready yet'], 424, ['Content-Type' => 'application/json']));

        $response = $this->reloadInvoicePdf($user, $invoice, true);

        $this->assertStringContainsString('/dashboard/institution-admin/finance/invoices/'.$invoice->id, $response->getTargetUrl());
        $this->assertSame(
            'A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.',
            $response->getSession()->get('errors')->getBag('default')->first('invoice')
        );

        $invoice->refresh();
        $this->assertNull($invoice->invoice_pdf_path);
        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
    }

    public function test_invalid_local_pdf_is_not_treated_as_downloadable_and_valid_reload_overwrites_it(): void
    {
        [$institution, $user, $invoice] = $this->seedBillingoInvoiceContext('BPDF07', [
            'status' => InstitutionInvoice::STATUS_VOIDED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'ETK-2026-1',
            'invoice_url' => null,
            'invoice_pdf_path' => 'invoices/billingo/__INSTITUTION__/ETK-2026-1.pdf',
        ]);

        $this->resetInvoiceStorage($institution);
        $invoice->invoice_pdf_path = str_replace('__INSTITUTION__', (string) $institution->id, $invoice->invoice_pdf_path);
        $invoice->save();
        Storage::disk('local')->put($invoice->invoice_pdf_path, '{"message":"not a pdf"}');

        $html = $this->renderInvoiceShow($user, $invoice);

        $this->assertStringContainsString('A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.', $html);
        $this->assertStringContainsString(route('dashboard.institution.finance.invoices.reload-pdf', $invoice), $html);
        $this->assertStringNotContainsString(route('dashboard.institution.finance.invoices.download', $invoice), $html);

        $this->expectException(NotFoundHttpException::class);
        $this->actingAs($user);
        app(InstitutionInvoiceController::class)->download($invoice);
    }

    public function test_invalid_local_pdf_can_be_overwritten_by_valid_reload(): void
    {
        [$institution, $user, $invoice] = $this->seedBillingoInvoiceContext('BPDF08', [
            'status' => InstitutionInvoice::STATUS_VOIDED,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'ETK-2026-2',
            'invoice_url' => null,
            'invoice_pdf_path' => 'invoices/billingo/__INSTITUTION__/ETK-2026-2.pdf',
        ]);

        $this->resetInvoiceStorage($institution);
        $invoice->invoice_pdf_path = str_replace('__INSTITUTION__', (string) $institution->id, $invoice->invoice_pdf_path);
        $invoice->save();
        Storage::disk('local')->put($invoice->invoice_pdf_path, '{"message":"not a pdf"}');

        Http::fake(fn () => Http::response("%PDF-1.7\nfixed pdf", 200, ['Content-Type' => 'application/pdf']));

        $reloadResponse = $this->reloadInvoicePdf($user, $invoice);

        $this->assertSame(route('dashboard.institution.finance.invoices.show', $invoice), $reloadResponse->getTargetUrl());

        $invoice->refresh();
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($invoice->invoice_pdf_path));
        Http::assertNotSent(fn (HttpClientRequest $request) => $request->method() === 'POST');
    }

    private function seedBillingoInvoiceContext(string $code, array $invoiceOverrides = []): array
    {
        $institution = Institution::create([
            'name' => 'Billingo Intezmeny '.$code,
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
            'name' => 'Billingo Gyermek '.$code,
            'educational_identifier' => 'EDU-'.$code,
            'group_name' => '3.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Szulo',
            'first_name' => 'Payer',
            'email' => 'szulo-'.$code.'@example.test',
            'postal_code' => '1111',
            'city' => 'Budapest',
            'street_name' => 'Fo',
            'street_type' => 'utca',
            'house_number' => '1',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 12500,
            'previous_cancellation_credit' => 0,
            'billing_adjustment_amount' => 0,
            'invoiceable_amount' => 12500,
            'previous_balance' => 0,
            'total_payable' => 12500,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
        ]);

        $invoice = new InstitutionInvoice();
        $invoice->fill(array_merge([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-DEFAULT',
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'net_amount' => 12500,
            'vat_amount' => 0,
            'gross_amount' => 12500,
            'currency' => 'HUF',
            'payment_method' => 'bank_transfer',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo-'.$code.'@example.test',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'invoice_url' => 'https://billingo.test/invoice/'.$code,
            'invoice_pdf_path' => null,
            'error_message' => null,
            'cancellation_provider_document_id' => null,
            'cancellation_invoice_number' => null,
            'cancellation_pdf_path' => null,
        ], $invoiceOverrides));
        $invoice->institution_id = $institution->id;
        $invoice->created_by = $user->id;
        $invoice->save();

        return [$institution, $user, $invoice];
    }

    private function renderInvoiceShow(User $user, InstitutionInvoice $invoice): string
    {
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        return app(InstitutionInvoiceController::class)->show($invoice)->render();
    }

    private function resetInvoiceStorage(Institution $institution): void
    {
        Storage::disk('local')->deleteDirectory('invoices/billingo/'.$institution->id);
        Storage::disk('local')->deleteDirectory('invoices/billingo_storno/'.$institution->id);
    }

    private function reloadInvoicePdf(User $user, InstitutionInvoice $invoice, bool $expectValidationError = false): RedirectResponse
    {
        $this->actingAs($user);

        $request = HttpRequest::create(
            route('dashboard.institution.finance.invoices.reload-pdf', $invoice),
            'POST'
        );
        $request->headers->set('referer', route('dashboard.institution.finance.invoices.show', $invoice));
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);

        try {
            /** @var RedirectResponse $response */
            $response = app(InstitutionInvoiceController::class)->reloadPdf($invoice);

            return $response;
        } catch (HttpResponseException $exception) {
            if (! $expectValidationError) {
                throw $exception;
            }

            /** @var RedirectResponse $response */
            $response = $exception->getResponse();

            return $response;
        }
    }

    private function reloadCancellationPdf(User $user, InstitutionInvoice $invoice): RedirectResponse
    {
        $this->actingAs($user);

        $request = HttpRequest::create(
            route('dashboard.institution.finance.invoices.reload-cancellation-pdf', $invoice),
            'POST'
        );
        $request->headers->set('referer', route('dashboard.institution.finance.invoices.show', $invoice));
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);

        /** @var RedirectResponse $response */
        $response = app(InstitutionInvoiceController::class)->reloadCancellationPdf($invoice);

        return $response;
    }
}
