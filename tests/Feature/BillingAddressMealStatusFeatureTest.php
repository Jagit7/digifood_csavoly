<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\InstitutionAdmin\BillingAddressController;
use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\StudentMealSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class BillingAddressMealStatusFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_meal_setting_is_shown_as_eater(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('BILLADDR01');
        $child = $this->createChildWithCompleteBilling($institution, 'Aktiv Etkezo');
        $this->createMealSetting($child, $institution, '2026-08-01');

        $html = $this->renderBillingAddressesPage($user);

        $this->assertStringContainsString('Aktiv Etkezo', $html);
        $this->assertStringContainsString('Étkező', $html);
        $this->assertStringNotContainsString('Étkező (ütemezve)', $html);
    }

    public function test_future_meal_setting_is_shown_as_scheduled_eater_with_start_date(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('BILLADDR02');
        $child = $this->createChildWithCompleteBilling($institution, 'Utemezett Etkezo');
        $this->createMealSetting($child, $institution, '2026-09-01');

        $html = $this->renderBillingAddressesPage($user);

        $this->assertStringContainsString('Utemezett Etkezo', $html);
        $this->assertStringContainsString('Étkező (ütemezve)', $html);
        $this->assertStringContainsString('2026.09.01-től', $html);
    }

    public function test_scheduled_eater_is_not_marked_incomplete_and_not_counted_as_missing_meal_data(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('BILLADDR03');
        $child = $this->createChildWithCompleteBilling($institution, 'Kesz Utemezett');
        $this->createMealSetting($child, $institution, '2026-09-01');

        $html = $this->renderBillingAddressesPage($user);

        $this->assertStringContainsString('Kész', $html);
        $this->assertStringNotContainsString('Hiányos (1)', $html);
        $this->assertStringContainsString('Nincs étkezési beállítás (0)', $html);
    }

    public function test_child_without_meal_setting_is_shown_as_missing_and_incomplete(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('BILLADDR04');
        $this->createChildWithCompleteBilling($institution, 'Beallitas Nelkul');

        $html = $this->renderBillingAddressesPage($user);

        $this->assertStringContainsString('Nincs beállítva', $html);
        $this->assertStringContainsString('Hiányos (1)', $html);
        $this->assertStringContainsString('Nincs étkezési beállítás (1)', $html);
    }

    public function test_only_expired_or_closed_meal_settings_are_treated_as_missing(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('BILLADDR05');
        $expiredChild = $this->createChildWithCompleteBilling($institution, 'Lejart Etkezo');
        $closedChild = $this->createChildWithCompleteBilling($institution, 'Lezart Etkezo');

        $this->createMealSetting($expiredChild, $institution, '2026-06-01', '2026-08-10');
        $this->createMealSetting($closedChild, $institution, '2026-09-01', '2026-09-01', [
            'closed_at' => now(),
            'closed_by' => $user->id,
            'closure_reason' => StudentMealSetting::CLOSURE_REASON_OTHER,
        ]);

        $html = $this->renderBillingAddressesPage($user);

        $this->assertStringContainsString('Lejart Etkezo', $html);
        $this->assertStringContainsString('Lezart Etkezo', $html);
        $this->assertStringContainsString('Nincs beállítva', $html);
    }

    public function test_current_meal_setting_takes_priority_over_future_one(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('BILLADDR06');
        $child = $this->createChildWithCompleteBilling($institution, 'Jelenlegi Elso');
        $this->createMealSetting($child, $institution, '2026-08-01', '2026-08-31');
        $this->createMealSetting($child, $institution, '2026-09-01');

        $html = $this->renderBillingAddressesPage($user);

        $this->assertStringContainsString('Jelenlegi Elso', $html);
        $this->assertStringContainsString('Étkező', $html);
        $this->assertStringNotContainsString('Étkező (ütemezve)', $html);
    }

    public function test_meal_statuses_do_not_mix_between_institutions(): void
    {
        [$institutionA, $userA] = $this->seedInstitutionAdmin('BILLADDR07A');
        [$institutionB] = $this->seedInstitutionAdmin('BILLADDR07B');

        $childA = $this->createChildWithCompleteBilling($institutionA, 'Sajat Intezmeny');
        $childB = $this->createChildWithCompleteBilling($institutionB, 'Masik Intezmeny');

        $this->createMealSetting($childB, $institutionB, '2026-08-01');

        $html = $this->renderBillingAddressesPage($userA);

        $this->assertStringContainsString('Sajat Intezmeny', $html);
        $this->assertStringNotContainsString('Masik Intezmeny', $html);
        $this->assertStringContainsString('Nincs beállítva', $html);
        $this->assertStringContainsString('Nincs étkezési beállítás (1)', $html);
    }

    private function seedInstitutionAdmin(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Intezet '.$code,
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

    private function createChildWithCompleteBilling(Institution $institution, string $name): Child
    {
        $discount = DiscountType::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'name' => 'Alap',
            ],
            [
                'percentage' => 0,
                'active' => true,
                'sort_order' => 1,
            ]
        );

        $child = Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'educational_identifier' => 'EDU-'.md5($name),
            'group_name' => '3.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $guardianUser = User::factory()->create();

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'user_id' => $guardianUser->id,
            'last_name' => 'Szulo',
            'first_name' => $name,
            'email' => 'guardian-'.md5($name).'@example.test',
            'postal_code' => '1111',
            'city' => 'Budapest',
            'street_name' => 'Fo',
            'street_type' => 'utca',
            'house_number' => '1',
            'bank_account_holder' => 'Szulo '.$name,
            'bank_account_number' => '11700000-00000000-00000000',
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
            'billing_name' => $guardian->full_name,
            'postal_code' => '1111',
            'city' => 'Budapest',
            'address' => 'Fo utca 1.',
            'email' => $guardian->email,
            'payment_method' => 'transfer',
            'active' => true,
        ]);

        DB::table('billing_profile_child')->insert([
            'billing_profile_id' => $billingProfile->id,
            'child_id' => $child->id,
            'is_primary' => true,
            'valid_from' => now()->toDateString(),
            'valid_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $child;
    }

    private function createMealSetting(
        Child $child,
        Institution $institution,
        string $validFrom,
        ?string $validTo = null,
        array $overrides = []
    ): StudentMealSetting {
        return StudentMealSetting::create(array_merge([
            'student_id' => $child->id,
            'eater_type' => $child->getMorphClass(),
            'eater_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'created_by' => 1,
            'note' => null,
            'closed_by' => null,
            'closed_at' => null,
            'closure_reason' => null,
            'closure_note' => null,
        ], $overrides));
    }

    private function renderBillingAddressesPage(User $user, array $query = []): string
    {
        $this->actingAs($user);

        $request = Request::create(
            route('dashboard.institution.billing-addresses.index', $query),
            'GET',
            $query
        );
        $request->setUserResolver(fn () => $user);
        view()->share('errors', new ViewErrorBag());

        return app(BillingAddressController::class)->index($request)->render();
    }
}
