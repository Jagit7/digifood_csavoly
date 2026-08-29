<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\InstitutionController;
use App\Models\Institution;
use App\Models\InstitutionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Regressziós tesztek annak biztosítására, hogy a szuperadmin "Intézmény
 * szerkesztése" űrlap mentése (InstitutionController::update) kizárólag a
 * saját mezőit (intézmény alapadatok + barcode_entry_enabled) módosítja,
 * és SOHA nem írja felül alapértékre/NULL-ra a már elmentett Billingo/CIB
 * beállításokat - lásd az éles hibát, ami emiatt törölte a Billingo
 * API-kulcsot, a bizonylattömb-azonosítót és a CIB terminálazonosítót.
 */
class InstitutionControllerSettingsPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_update_preserves_existing_billingo_api_key(): void
    {
        [$institution, $superAdmin] = $this->seedInstitutionWithFullSettings('SETT001');

        $this->actingAs($superAdmin);
        $request = $this->makeInstitutionUpdateRequest($institution, [
            'name' => $institution->name,
            'active' => '1',
        ]);
        app(InstitutionController::class)->update($request, $institution);

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertSame('existing-billingo-key', $setting->billingo_api_key);
    }

    public function test_institution_update_preserves_billingo_document_block_id(): void
    {
        [$institution, $superAdmin] = $this->seedInstitutionWithFullSettings('SETT002');

        $this->actingAs($superAdmin);
        $request = $this->makeInstitutionUpdateRequest($institution, [
            'name' => $institution->name,
            'active' => '1',
        ]);
        app(InstitutionController::class)->update($request, $institution);

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertSame('329136', $setting->billingo_document_block_id);
    }

    public function test_institution_update_preserves_cib_terminal_id_and_secret_key(): void
    {
        [$institution, $superAdmin] = $this->seedInstitutionWithFullSettings('SETT003');

        $this->actingAs($superAdmin);
        $request = $this->makeInstitutionUpdateRequest($institution, [
            'name' => $institution->name,
            'active' => '1',
        ]);
        app(InstitutionController::class)->update($request, $institution);

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertSame('SNL0001', $setting->cib_terminal_id);
        $this->assertSame('existing-cib-secret', $setting->cib_secret_key);
    }

    /**
     * A barcode_entry_enabled mező módosítása (ezt kezeli ez az űrlap) nem
     * módosíthat semmilyen más InstitutionSetting mezőt - sem a
     * Billingo/CIB adatokat, sem a másik admin űrlap (InstitutionSetting-
     * Controller) által kezelt ab_menu_choice_deadline_day / payment_due_day
     * mezőket.
     */
    public function test_barcode_entry_enabled_change_does_not_modify_other_institution_setting_fields(): void
    {
        [$institution, $superAdmin] = $this->seedInstitutionWithFullSettings('SETT004');

        $this->actingAs($superAdmin);
        $request = $this->makeInstitutionUpdateRequest($institution, [
            'name' => $institution->name,
            'active' => '1',
            'barcode_entry_enabled' => '1',
        ]);
        app(InstitutionController::class)->update($request, $institution);

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertTrue($setting->barcode_entry_enabled);
        $this->assertSame('existing-billingo-key', $setting->billingo_api_key);
        $this->assertSame('329136', $setting->billingo_document_block_id);
        $this->assertSame('SNL0001', $setting->cib_terminal_id);
        $this->assertSame('existing-cib-secret', $setting->cib_secret_key);
        $this->assertSame(20, $setting->ab_menu_choice_deadline_day);
        $this->assertSame(5, $setting->payment_due_day);
    }

    private function seedInstitutionWithFullSettings(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Intezmeny ' . $code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'barcode_entry_enabled' => false,
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
                'billingo_api_key' => 'existing-billingo-key',
                'billingo_document_block_id' => '329136',
                'card_payment_enabled' => true,
                'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
                'cib_terminal_id' => 'SNL0001',
                'cib_secret_key' => 'existing-cib-secret',
                'ab_menu_choice_deadline_day' => 20,
                'payment_due_day' => 5,
            ])
        );

        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        return [$institution, $superAdmin];
    }

    private function makeInstitutionUpdateRequest(Institution $institution, array $payload): Request
    {
        return Request::create(
            '/dashboard/institutions/' . $institution->id,
            'PUT',
            $payload
        );
    }
}
