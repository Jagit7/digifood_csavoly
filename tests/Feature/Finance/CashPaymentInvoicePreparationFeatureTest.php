<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionPaymentController;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceStoreRequest;
use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
use Tests\TestCase;

class CashPaymentInvoicePreparationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_payment_show_page_displays_invoice_creation_button_when_no_invoice_exists(): void
    {
        [$institution, $user, $payment] = $this->seedCashPaymentContext('CPIF01');

        $html = $this->renderPaymentShow($user, $payment);

        $this->assertStringContainsString('Számla készítése', $html);
        $this->assertStringContainsString(route('dashboard.institution.finance.invoices.create', ['payment_id' => $payment->id]), $html);
    }

    public function test_invoice_create_page_is_prefilled_from_completed_cash_payment(): void
    {
        [$institution, $user, $payment, $statement] = $this->seedCashPaymentContext('CPIF02');

        $response = $this->openInvoiceCreatePage($user, $payment);

        $this->assertInstanceOf(View::class, $response);

        $html = $response->render();

        $this->assertStringContainsString('Készpénzes befizetésből előkészített számla létrehozása', $html);
        $this->assertStringContainsString('name="source_payment_id" value="'.$payment->id.'"', $html);
        $this->assertStringContainsString('name="monthly_payment_statement_id" value="'.$statement->id.'"', $html);
        $this->assertStringContainsString('Szulo Payer', $html);
        $this->assertStringContainsString('Budapest', $html);
        $this->assertStringContainsString('Fo utca 1.', $html);
        $this->assertStringContainsString('12345678-1-42', $html);
        $this->assertStringContainsString('szulo@example.test', $html);
        $this->assertStringContainsString('name="payment_method" value="cash"', $html);
        $this->assertStringContainsString('name="due_date" value="2026-08-12"', $html);
        $this->assertStringContainsString('name="fulfillment_date" value="2026-08-12"', $html);
        $this->assertStringContainsString(now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString(), $html);
        $this->assertStringContainsString('HUF', $html);
        $this->assertStringContainsString('Étkezési térítési díj', $html);
        $this->assertStringContainsString('12 500 Ft', $html);
    }

    public function test_invoice_store_links_cash_payment_and_statement_and_forces_cash_payment_method(): void
    {
        [$institution, $user, $payment, $statement] = $this->seedCashPaymentContext('CPIF03');

        $request = $this->makeStoreRequest($user, [
                'monthly_payment_statement_id' => $statement->id,
                'source_payment_id' => $payment->id,
                'provider' => InstitutionInvoice::PROVIDER_MANUAL,
                'payment_method' => InstitutionPayment::METHOD_BANK_TRANSFER,
                'due_date' => '2026-08-12',
                'fulfillment_date' => '2026-08-12',
                'customer_name' => 'Szulo Payer',
                'customer_email' => 'szulo@example.test',
                'customer_tax_number' => '12345678-1-42',
                'billing_postcode' => '1111',
                'billing_city' => 'Budapest',
                'billing_address' => 'Fo utca 1.',
                'note' => 'Keszpenzes befizetes alapjan',
            ]);

        $this->actingAs($user);
        $response = app(InstitutionInvoiceController::class)->store($request);

        $invoice = InstitutionInvoice::query()->firstOrFail();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.finance.invoices.show', $invoice), $response->getTargetUrl());
        $this->assertSame($payment->id, $invoice->institution_payment_id);
        $this->assertSame($statement->id, $invoice->monthly_payment_statement_id);
        $this->assertSame(InstitutionPayment::METHOD_CASH, $invoice->payment_method);
        $this->assertSame('2026-08-12', $invoice->due_date?->toDateString());
        $this->assertSame('2026-08-12', $invoice->fulfillment_date?->toDateString());
        $this->assertSame(12500, $invoice->gross_amount);
        $this->assertSame(InstitutionInvoice::STATUS_DRAFT, $invoice->status);
    }

    public function test_foreign_institution_admin_cannot_open_cash_payment_invoice_preparation_page(): void
    {
        [$institution, $user, $payment] = $this->seedCashPaymentContext('CPIF04');
        [$foreignInstitution, $foreignUser] = $this->seedInstitutionUser('CPIF04X');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->openInvoiceCreatePage($foreignUser, $payment);
    }

    public function test_existing_active_invoice_offers_open_instead_of_new_creation(): void
    {
        [$institution, $user, $payment, $statement, $guardian] = $this->seedCashPaymentContext('CPIF05');
        $invoice = $this->createInvoice($institution, $statement, $guardian, $user, $payment, [
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'invoice_number' => 'INV-2026-0001',
        ]);

        $html = $this->renderPaymentShow($user, $payment);
        $this->assertStringContainsString('Számla megnyitása', $html);
        $this->assertStringContainsString(route('dashboard.institution.finance.invoices.show', $invoice), $html);
        $this->assertStringNotContainsString('Számla készítése', $html);

        $response = $this->openInvoiceCreatePage($user, $payment);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.finance.invoices.show', $invoice), $response->getTargetUrl());
    }

    public function test_second_invoice_request_for_same_cash_payment_is_blocked(): void
    {
        [$institution, $user, $payment, $statement] = $this->seedCashPaymentContext('CPIF06');

        $payload = [
            'monthly_payment_statement_id' => $statement->id,
            'source_payment_id' => $payment->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'due_date' => '2026-08-12',
            'fulfillment_date' => '2026-08-12',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => null,
        ];

        $firstRequest = $this->makeStoreRequest($user, $payload);
        $this->actingAs($user);
        $firstResponse = app(InstitutionInvoiceController::class)->store($firstRequest);

        $invoice = InstitutionInvoice::query()->firstOrFail();
        $this->assertInstanceOf(RedirectResponse::class, $firstResponse);
        $this->assertSame(route('dashboard.institution.finance.invoices.show', $invoice), $firstResponse->getTargetUrl());

        $secondRequest = $this->makeStoreRequest($user, $payload);

        try {
            app(InstitutionInvoiceController::class)->store($secondRequest);
            $this->fail('A masodik kerest a rendszernek blokkolnia kellett volna.');
        } catch (HttpResponseException $exception) {
            $redirect = $exception->getResponse();
            $this->assertInstanceOf(RedirectResponse::class, $redirect);
            $sessionErrors = $redirect->getSession()->get('errors');
            $this->assertNotNull($sessionErrors);
            $this->assertSame(
                'Ehhez a fizetési kötelezettséghez már tartozik számla.',
                $sessionErrors->getBag('default')->first('monthly_payment_statement_id')
            );
        }

        $this->assertSame(1, InstitutionInvoice::query()->count());
    }

    public function test_bank_transfer_and_card_payment_flows_do_not_show_cash_invoice_cta(): void
    {
        [$institution, $user, $bankPayment] = $this->seedCashPaymentContext('CPIF07', InstitutionPayment::METHOD_BANK_TRANSFER);
        [, $cardUser, $cardPayment] = $this->seedCashPaymentContext('CPIF08', InstitutionPayment::METHOD_CARD);

        $bankHtml = $this->renderPaymentShow($user, $bankPayment);
        $cardHtml = $this->renderPaymentShow($cardUser, $cardPayment);

        $this->assertStringNotContainsString('Számla készítése', $bankHtml);
        $this->assertStringNotContainsString('Számla megnyitása', $bankHtml);
        $this->assertStringNotContainsString('Számla készítése', $cardHtml);
        $this->assertStringNotContainsString('Számla megnyitása', $cardHtml);
    }

    private function seedCashPaymentContext(string $code, string $paymentMethod = InstitutionPayment::METHOD_CASH): array
    {
        [$institution, $user] = $this->seedInstitutionUser($code);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_MANUAL,
                'payment_due_day' => 8,
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
            'name' => 'Szamla Gyermek '.$code,
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
            'payment_method' => 'cash',
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

        $payment = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => 12500,
            'paid_at' => '2026-08-12 09:15:00',
            'payment_method' => $paymentMethod,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => 'CASH-'.$code,
            'note' => 'Teszt befizetes',
            'recorded_by' => $user->id,
        ]);

        return [$institution, $user, $payment, $statement, $guardian];
    }

    private function seedInstitutionUser(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Intezmeny '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create();
        $user->forceFill([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
        ])->save();

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => 'institution_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institution, $user];
    }

    private function createInvoice(
        Institution $institution,
        MonthlyPaymentStatement $statement,
        Guardian $guardian,
        User $user,
        InstitutionPayment $payment,
        array $overrides = []
    ): InstitutionInvoice {
        $invoice = new InstitutionInvoice();
        $invoice->fill(array_merge([
            'institution_id' => $institution->id,
            'child_id' => $statement->child_id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'institution_payment_id' => $payment->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'provider_invoice_id' => null,
            'invoice_number' => null,
            'status' => InstitutionInvoice::STATUS_DRAFT,
            'issue_date' => null,
            'due_date' => '2026-08-12',
            'fulfillment_date' => '2026-08-12',
            'net_amount' => 12500,
            'vat_amount' => 0,
            'gross_amount' => 12500,
            'currency' => 'HUF',
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'invoice_url' => null,
            'invoice_pdf_path' => null,
            'error_message' => null,
        ], $overrides));
        $invoice->institution_id = $institution->id;
        $invoice->created_by = $user->id;
        $invoice->save();

        return $invoice;
    }

    private function renderPaymentShow(User $user, InstitutionPayment $payment): string
    {
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        return app(InstitutionPaymentController::class)->show($payment)->render();
    }

    private function openInvoiceCreatePage(User $user, InstitutionPayment $payment): View|RedirectResponse
    {
        $this->actingAs($user);

        $request = Request::create(
            route('dashboard.institution.finance.invoices.create', ['payment_id' => $payment->id]),
            'GET',
            ['payment_id' => $payment->id]
        );
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);
        view()->share('errors', new ViewErrorBag());

        return app(InstitutionInvoiceController::class)->create();
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
}
