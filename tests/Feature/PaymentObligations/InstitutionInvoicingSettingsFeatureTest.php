<?php

namespace Tests\Feature\PaymentObligations;

use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionInvoicingSettingController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation\PaymentObligationController;
use App\Http\Requests\Dashboard\InstitutionAdmin\InstitutionInvoicingSettingUpdateRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\ManualInvoiceUpdateRequest;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionMealType;
use App\Models\InstitutionSetting;
use App\Models\MealType;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Tests\TestCase;

class InstitutionInvoicingSettingsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoicing_settings_page_can_be_opened_for_own_institution(): void
    {
        [, $user] = $this->seedUserWithInstitution('INV001');
        $this->actingAs($user);

        $response = app(InstitutionInvoicingSettingController::class)->edit();

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('dashboard.institution_admin.institution.invoicing-settings', $response->name());
        $this->assertSame(config('integrations.invoice_providers'), $response->getData()['invoiceProviders']);
        $this->assertSame(config('integrations.payment_providers'), $response->getData()['paymentProviders']);
    }

    public function test_invoicing_settings_can_be_saved_for_own_institution(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('INV002');
        $this->actingAs($user);

        $request = $this->makeInvoicingSettingRequest($user, [
            'invoicing_enabled' => '1',
            'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
            'card_payment_enabled' => '1',
            'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_SIMPLEPAY,
            'card_payment_test_mode' => '1',
            'billingo_document_block_id' => 'block-123',
            'billingo_default_payment_method' => 'bankcard',
            'billingo_due_days' => '8',
            'billingo_invoice_language' => 'hu',
            'billingo_e_invoice_enabled' => '1',
            'billingo_test_mode' => '1',
            'billingo_api_key' => 'secret-billingo-key',
            'institution_id' => 999999,
        ]);

        $response = app(InstitutionInvoicingSettingController::class)->update($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.settings.invoicing.edit'), $response->getTargetUrl());

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertTrue($setting->invoicing_enabled);
        $this->assertSame(InstitutionSetting::INVOICING_PROVIDER_BILLINGO, $setting->invoicing_provider);
        $this->assertTrue($setting->card_payment_enabled);
        $this->assertSame(InstitutionSetting::CARD_PAYMENT_PROVIDER_SIMPLEPAY, $setting->card_payment_provider);
        $this->assertSame('block-123', $setting->billingo_document_block_id);
        $this->assertSame('secret-billingo-key', $setting->billingo_api_key);
        $this->assertSame($institution->id, $setting->institution_id);
    }

    public function test_existing_billingo_api_key_is_removed_only_when_explicitly_requested(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('INV004');
        $this->actingAs($user);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
                'billingo_api_key' => 'existing-key',
            ])
        );

        $request = $this->makeInvoicingSettingRequest($user, [
            'invoicing_enabled' => '1',
            'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
            'remove_billingo_api_key' => '1',
        ]);

        app(InstitutionInvoicingSettingController::class)->update($request);

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertNull($setting->billingo_api_key);
    }

    public function test_new_billingo_api_key_has_priority_over_remove_flag(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('INV005');
        $this->actingAs($user);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
                'billingo_api_key' => 'existing-key',
            ])
        );

        $request = $this->makeInvoicingSettingRequest($user, [
            'invoicing_enabled' => '1',
            'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
            'billingo_api_key' => 'replacement-key',
            'remove_billingo_api_key' => '1',
        ]);

        app(InstitutionInvoicingSettingController::class)->update($request);

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertSame('replacement-key', $setting->billingo_api_key);
    }

    /**
     * Regressziós teszt: a Billingo szekció mentése (ez az egyetlen közös
     * "Beállítások mentése" gomb) nem törölheti a már beállított CIB
     * kártyás fizetési adatokat (terminál-azonosító, titkos kulcs), csak
     * azért, mert a mentés pillanatában a számlázási szolgáltató Billingo.
     */
    public function test_saving_billingo_settings_does_not_delete_existing_cib_settings(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('INV007');
        $this->actingAs($user);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'card_payment_enabled' => true,
                'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
                'cib_terminal_id' => 'SNL0001',
                'cib_secret_key' => 'existing-cib-secret',
            ])
        );

        $request = $this->makeInvoicingSettingRequest($user, [
            'invoicing_enabled' => '1',
            'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
            'billingo_api_key' => 'new-billingo-key',
            'billingo_document_block_id' => 'block-777',
            // A CIB szekció ebben a mentésben nincs aktívan szerkesztve -
            // a card_payment_enabled/card_payment_provider mezőket nem
            // küldi a form, de a meglévő CIB adatoknak meg kell maradniuk.
        ]);

        app(InstitutionInvoicingSettingController::class)->update($request);

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertSame('new-billingo-key', $setting->billingo_api_key);
        $this->assertSame('block-777', $setting->billingo_document_block_id);
        $this->assertSame('SNL0001', $setting->cib_terminal_id);
        $this->assertSame('existing-cib-secret', $setting->cib_secret_key);
    }

    /**
     * Regressziós teszt: a CIB szekció mentése nem törölheti a már
     * beállított Billingo adatokat (API-kulcs, bizonylattömb-azonosító),
     * csak azért, mert a mentés pillanatában a kártyás fizetési
     * szolgáltató CIB.
     */
    public function test_saving_cib_settings_does_not_delete_existing_billingo_settings(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('INV008');
        $this->actingAs($user);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
                'billingo_api_key' => 'existing-billingo-key',
                'billingo_document_block_id' => '329136',
            ])
        );

        $request = $this->makeInvoicingSettingRequest($user, [
            'card_payment_enabled' => '1',
            'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
            'cib_terminal_id' => 'SNL0002',
            'cib_secret_key' => 'new-cib-secret',
            // A Billingo szekció ebben a mentésben nincs aktívan
            // szerkesztve - a meglévő Billingo adatoknak meg kell
            // maradniuk.
        ]);

        app(InstitutionInvoicingSettingController::class)->update($request);

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertSame('SNL0002', $setting->cib_terminal_id);
        $this->assertSame('new-cib-secret', $setting->cib_secret_key);
        $this->assertSame('existing-billingo-key', $setting->billingo_api_key);
        $this->assertSame('329136', $setting->billingo_document_block_id);
    }

    /**
     * A Billingo API-kulcshoz hasonlóan az üresen hagyott CIB titkos kulcs
     * mező sem törölheti a már elmentett kulcsot - törlés kizárólag az
     * explicit "remove_cib_secret_key" jelölőnégyzettel történhet.
     */
    public function test_empty_cib_secret_key_input_does_not_delete_previous_cib_key(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('INV009');
        $this->actingAs($user);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'card_payment_enabled' => true,
                'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
                'cib_terminal_id' => 'SNL0001',
                'cib_secret_key' => 'existing-cib-secret',
            ])
        );

        $request = $this->makeInvoicingSettingRequest($user, [
            'card_payment_enabled' => '1',
            'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
            'cib_terminal_id' => 'SNL0001',
            'cib_secret_key' => '',
        ]);

        app(InstitutionInvoicingSettingController::class)->update($request);

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertSame('existing-cib-secret', $setting->cib_secret_key);
    }

    /**
     * Az explicit "remove_cib_secret_key" jelölőnégyzet ténylegesen törli
     * a CIB titkos kulcsot (ilyenkor a CardPaymentService a .env-ben
     * beállított globális fallback kulcsra vált vissza).
     */
    public function test_existing_cib_secret_key_is_removed_only_when_explicitly_requested(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('INV010');
        $this->actingAs($user);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'card_payment_enabled' => true,
                'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
                'cib_terminal_id' => 'SNL0001',
                'cib_secret_key' => 'existing-cib-secret',
            ])
        );

        $request = $this->makeInvoicingSettingRequest($user, [
            'card_payment_enabled' => '1',
            'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
            'cib_terminal_id' => 'SNL0001',
            'remove_cib_secret_key' => '1',
        ]);

        app(InstitutionInvoicingSettingController::class)->update($request);

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertNull($setting->cib_secret_key);
        $this->assertSame('SNL0001', $setting->cib_terminal_id);
    }

    public function test_invoicing_provider_must_be_supported_value(): void
    {
        [, $user] = $this->seedUserWithInstitution('INV003');

        $request = InstitutionInvoicingSettingUpdateRequest::create(
            route('dashboard.institution.settings.invoicing.update'),
            'PUT',
            [
                'invoicing_enabled' => '1',
                'invoicing_provider' => 'unknown-provider',
            ]
        );
        $request->setUserResolver(fn () => $user);

        $validator = $this->app['validator']->make($request->all(), (new InstitutionInvoicingSettingUpdateRequest())->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('invoicing_provider', $validator->errors()->toArray());
    }

    public function test_card_payment_provider_must_be_supported_value(): void
    {
        [, $user] = $this->seedUserWithInstitution('INV006');

        $request = InstitutionInvoicingSettingUpdateRequest::create(
            route('dashboard.institution.settings.invoicing.update'),
            'PUT',
            [
                'card_payment_enabled' => '1',
                'card_payment_provider' => 'unknown-card-provider',
            ]
        );
        $request->setUserResolver(fn () => $user);

        $validator = $this->app['validator']->make($request->all(), (new InstitutionInvoicingSettingUpdateRequest())->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('card_payment_provider', $validator->errors()->toArray());
    }

    public function test_manual_invoice_number_can_be_saved_only_for_manual_provider(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_MANUAL,
            ])
        );

        $this->actingAs($user);
        $request = $this->makeManualInvoiceRequest($user, ['invoice_number' => 'MAN-2026-001']);
        $response = app(PaymentObligationController::class)->updateInvoice($request, $statement);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $statement->refresh();

        $this->assertSame('MAN-2026-001', $statement->invoice_number);
        $this->assertSame(MonthlyPaymentStatement::INVOICE_PROVIDER_MANUAL, $statement->invoice_provider);
        $this->assertSame(MonthlyPaymentStatement::INVOICE_STATUS_ISSUED, $statement->invoice_status);
        $this->assertNotNull($statement->invoiced_at);
    }

    public function test_manual_invoice_number_cannot_be_saved_for_billingo_provider(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
            ])
        );

        $this->actingAs($user);
        $request = $this->makeManualInvoiceRequest($user, ['invoice_number' => 'BLG-2026-001']);
        $response = app(PaymentObligationController::class)->updateInvoice($request, $statement);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $statement->refresh();
        $this->assertNull($statement->invoice_number);
        $this->assertNotNull($response->getSession()->get('error'));
    }

    public function test_manual_invoice_number_cannot_be_saved_for_szamlazz_hu_provider(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_SZAMLAZZ_HU,
            ])
        );

        $this->actingAs($user);
        $request = $this->makeManualInvoiceRequest($user, ['invoice_number' => 'SZH-2026-001']);
        $response = app(PaymentObligationController::class)->updateInvoice($request, $statement);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $statement->refresh();
        $this->assertNull($statement->invoice_number);
        $this->assertNotNull($response->getSession()->get('error'));
    }

    public function test_payment_obligations_view_contains_all_invoicing_provider_branches(): void
    {
        $view = file_get_contents(resource_path('views/dashboard/institution_admin/payment_obligations/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('INVOICING_PROVIDER_SZAMLAZZ_HU', $view);
        $this->assertStringContainsString('Billingo', $view);
        $this->assertStringContainsString('invoice_number', $view);
        $this->assertStringContainsString('INVOICING_PROVIDER_MANUAL', $view);
    }

    private function seedStatement(): array
    {
        [$institution, $user] = $this->seedUserWithInstitution('TINV01');

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
            'name' => 'Invoice Gyermek',
            'educational_identifier' => 'INV001',
            'group_name' => '2.B',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $mealType = MealType::create([
            'code' => 'lunch-inv',
            'name' => 'Ebéd',
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
            'price' => 1200,
            'valid_from' => '2026-01-01',
            'created_by' => $user->id,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institution->id,
            'name' => 'Számlázási csomag',
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

        return [$institution, $user, MonthlyPaymentStatement::query()->firstOrFail()];
    }

    private function seedUserWithInstitution(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Invoicing Intézmény ' . $code,
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

    private function makeInvoicingSettingRequest(User $user, array $payload): InstitutionInvoicingSettingUpdateRequest
    {
        $request = InstitutionInvoicingSettingUpdateRequest::create(
            route('dashboard.institution.settings.invoicing.update'),
            'PUT',
            $payload
        );
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $validator = $this->app['validator']->make($request->all(), $request->rules());
        $request->setValidator($validator);

        return $request;
    }

    private function makeManualInvoiceRequest(User $user, array $payload): ManualInvoiceUpdateRequest
    {
        $request = ManualInvoiceUpdateRequest::create(
            route('dashboard.institution.payment-obligations.invoice.update', ['statement' => 1]),
            'PUT',
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
