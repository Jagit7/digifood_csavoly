<?php

namespace Tests\Feature\InstitutionAdmin;

use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionSettingController;
use App\Models\Institution;
use App\Models\InstitutionSetting;
use App\Models\User;
use App\Services\Meals\AbMenuNotificationTemplateService;
use App\Services\PaymentNotifications\PaymentNotificationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regressziós tesztek annak biztosítására, hogy az intézményi admin
 * "Általános beállítások" űrlap mentése (InstitutionSettingController::update)
 * kizárólag a saját mezőit (konyhai értesítés, fizetési/A-B menü emlékeztető
 * beállítások, payment_due_day, ab_menu_choice_deadline_day) módosítja, és
 * SOHA nem írja alapértékre/NULL-ra a másik admin űrlap (Billingo/CIB) által
 * kezelt InstitutionSetting mezőket.
 */
class InstitutionSettingControllerPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_settings_update_preserves_billingo_and_cib_fields(): void
    {
        [$institution, $user] = $this->seedUserWithFullSettings('GEN001');
        $this->actingAs($user);

        $request = $this->makeGeneralSettingsRequest([
            'payment_notification_day' => '5',
            'payment_due_day' => '10',
            'ab_menu_choice_deadline_day' => '15',
        ]);

        app(InstitutionSettingController::class)->update(
            $request,
            app(PaymentNotificationTemplateService::class),
            app(AbMenuNotificationTemplateService::class)
        );

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertSame('existing-billingo-key', $setting->billingo_api_key);
        $this->assertSame('329136', $setting->billingo_document_block_id);
        $this->assertSame('SNL0001', $setting->cib_terminal_id);
        $this->assertSame('existing-cib-secret', $setting->cib_secret_key);
    }

    /**
     * A ténylegesen ezen az űrlapon beküldött payment_due_day /
     * ab_menu_choice_deadline_day értékeknek el kell mentődniük - nem
     * maradhatnak a korábbi értéken és nem eshetnek vissza alapértékre.
     */
    public function test_general_settings_update_saves_submitted_payment_and_ab_menu_days(): void
    {
        [$institution, $user] = $this->seedUserWithFullSettings('GEN002');
        $this->actingAs($user);

        $request = $this->makeGeneralSettingsRequest([
            'payment_notification_day' => '5',
            'payment_due_day' => '12',
            'ab_menu_choice_deadline_day' => '18',
        ]);

        app(InstitutionSettingController::class)->update(
            $request,
            app(PaymentNotificationTemplateService::class),
            app(AbMenuNotificationTemplateService::class)
        );

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertSame(12, $setting->payment_due_day);
        $this->assertSame(18, $setting->ab_menu_choice_deadline_day);
    }

    public function test_general_settings_update_redirects_to_settings_edit(): void
    {
        [, $user] = $this->seedUserWithFullSettings('GEN003');
        $this->actingAs($user);

        $request = $this->makeGeneralSettingsRequest([
            'payment_notification_day' => '5',
            'payment_due_day' => '10',
            'ab_menu_choice_deadline_day' => '15',
        ]);

        $response = app(InstitutionSettingController::class)->update(
            $request,
            app(PaymentNotificationTemplateService::class),
            app(AbMenuNotificationTemplateService::class)
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.settings.edit'), $response->getTargetUrl());
    }

    private function seedUserWithFullSettings(string $code): array
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
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
                'billingo_api_key' => 'existing-billingo-key',
                'billingo_document_block_id' => '329136',
                'card_payment_enabled' => true,
                'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
                'cib_terminal_id' => 'SNL0001',
                'cib_secret_key' => 'existing-cib-secret',
                'payment_due_day' => 5,
                'ab_menu_choice_deadline_day' => 20,
            ])
        );

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

    private function makeGeneralSettingsRequest(array $payload): Request
    {
        return Request::create(
            route('dashboard.institution.settings.update'),
            'PUT',
            $payload
        );
    }
}
