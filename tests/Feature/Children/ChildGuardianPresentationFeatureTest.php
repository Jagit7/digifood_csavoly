<?php

namespace Tests\Feature\Children;

use App\Http\Controllers\Dashboard\InstitutionAdmin\BillingAddressController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildController;
use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ChildGuardianPresentationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_list_and_billing_basics_use_same_guardian_order_and_billing_guardian_slot(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CHGUARD01');
        $child = $this->createChild($institution, 'Rendezett Gyermek');
        $guardian1 = $this->attachGuardian($child, $institution, 'Zulu', 'Anna');
        $guardian2 = $this->attachGuardian($child, $institution, 'Alpha', 'Bela');
        $this->attachBillingProfile($institution, $child, $guardian2);

        $childPageChild = $this->childIndexViewData($user)['children']->getCollection()->firstWhere('id', $child->id);
        $billingPageChild = $this->billingIndexViewData($user)['children']->getCollection()->firstWhere('id', $child->id);

        $this->assertSame($guardian1->id, $childPageChild->display_guardian_1->id);
        $this->assertSame($guardian2->id, $childPageChild->display_guardian_2->id);
        $this->assertSame(2, $childPageChild->display_billing_guardian_slot);

        $this->assertSame($guardian1->id, $billingPageChild->display_guardian_1->id);
        $this->assertSame($guardian2->id, $billingPageChild->display_guardian_2->id);
        $this->assertSame(2, $billingPageChild->display_billing_guardian_slot);

        $billingHtml = $this->renderBillingAddressesPage($user);

        $this->assertStringContainsString('Zulu Anna', $billingHtml);
        $this->assertStringContainsString('Alpha Bela', $billingHtml);
        $this->assertStringContainsString('Számlázó', $billingHtml);
    }

    public function test_warning_is_shown_when_billing_profile_guardian_is_not_linked_to_child(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CHGUARD02');
        $child = $this->createChild($institution, 'Kapcsolati Hibas');
        $this->attachGuardian($child, $institution, 'Első', 'Gondviselő');
        $this->attachGuardian($child, $institution, 'Második', 'Gondviselő');
        $foreignBillingGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Kulso',
            'first_name' => 'Szamlazo',
            'email' => 'kulso@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);
        $this->attachBillingProfile($institution, $child, $foreignBillingGuardian);

        $childPageChild = $this->childIndexViewData($user)['children']->getCollection()->firstWhere('id', $child->id);
        $billingPageChild = $this->billingIndexViewData($user)['children']->getCollection()->firstWhere('id', $child->id);

        $this->assertNull($childPageChild->display_billing_guardian_slot);
        $this->assertSame(
            'A számlázási profilhoz tartozó gondviselő nincs a gyermekhez kapcsolva.',
            $childPageChild->display_billing_guardian_note
        );
        $this->assertNull($billingPageChild->display_billing_guardian_slot);
        $this->assertSame(
            'A számlázási profilhoz tartozó gondviselő nincs a gyermekhez kapcsolva.',
            $billingPageChild->display_billing_guardian_note
        );

        $childHtml = $this->renderChildIndexPage($user);
        $billingHtml = $this->renderBillingAddressesPage($user);

        $this->assertStringContainsString('A számlázási profilhoz tartozó gondviselő nincs a gyermekhez kapcsolva.', $childHtml);
        $this->assertStringContainsString('A számlázási profilhoz tartozó gondviselő nincs a gyermekhez kapcsolva.', $billingHtml);
    }

    public function test_only_current_active_primary_billing_profile_marks_the_billing_guardian(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CHGUARD03');
        $child = $this->createChild($institution, 'Ervenyes Fizeto');
        $guardian1 = $this->attachGuardian($child, $institution, 'Lejart', 'Profil');
        $guardian2 = $this->attachGuardian($child, $institution, 'Aktualis', 'Profil');

        $this->attachBillingProfile($institution, $child, $guardian1, [
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-08-10',
        ]);
        $this->attachBillingProfile($institution, $child, $guardian2, [
            'valid_from' => '2026-08-11',
            'valid_to' => null,
        ]);

        $childPageChild = $this->childIndexViewData($user)['children']->getCollection()->firstWhere('id', $child->id);
        $billingPageChild = $this->billingIndexViewData($user)['children']->getCollection()->firstWhere('id', $child->id);

        $this->assertSame($guardian2->id, $childPageChild->display_billing_guardian->id);
        $this->assertSame(2, $childPageChild->display_billing_guardian_slot);
        $this->assertSame($guardian2->id, $billingPageChild->display_billing_guardian->id);
        $this->assertSame(2, $billingPageChild->display_billing_guardian_slot);
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

    private function createChild(Institution $institution, string $name): Child
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

        return Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'educational_identifier' => 'EDU-'.md5($name),
            'group_name' => '3.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function attachGuardian(
        Child $child,
        Institution $institution,
        string $lastName,
        string $firstName,
        bool $isLegalRepresentative = false
    ): Guardian {
        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'email' => strtolower($lastName.'.'.$firstName).'@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        DB::table('child_guardian')->insert([
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'Szülő',
            'is_legal_representative' => $isLegalRepresentative,
            'has_no_custody' => false,
            'is_emergency_contact' => false,
            'receives_family_allowance' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $guardian;
    }

    private function attachBillingProfile(
        Institution $institution,
        Child $child,
        Guardian $guardian,
        array $overrides = []
    ): BillingProfile {
        $profile = BillingProfile::create([
            'institution_id' => $institution->id,
            'guardian_id' => $guardian->id,
            'payer_type' => 'guardian',
            'billing_name' => $guardian->full_name,
            'email' => $guardian->email,
            'active' => true,
        ]);

        DB::table('billing_profile_child')->insert([
            'billing_profile_id' => $profile->id,
            'child_id' => $child->id,
            'is_primary' => true,
            'valid_from' => $overrides['valid_from'] ?? now()->toDateString(),
            'valid_to' => $overrides['valid_to'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (array_key_exists('active', $overrides)) {
            $profile->update(['active' => (bool) $overrides['active']]);
        }

        return $profile->fresh();
    }

    private function childIndexViewData(User $user): array
    {
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag);

        $request = Request::create(route('dashboard.institution.children.index'), 'GET');
        $request->setUserResolver(fn () => $user);

        return app(ChildController::class)->index($request)->getData();
    }

    private function billingIndexViewData(User $user): array
    {
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag);

        $request = Request::create(route('dashboard.institution.billing-addresses.index'), 'GET');
        $request->setUserResolver(fn () => $user);

        return app(BillingAddressController::class)->index($request)->getData();
    }

    private function renderChildIndexPage(User $user): string
    {
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag);

        $request = Request::create(route('dashboard.institution.children.index'), 'GET');
        $request->setUserResolver(fn () => $user);

        return app(ChildController::class)->index($request)->render();
    }

    private function renderBillingAddressesPage(User $user): string
    {
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag);

        $request = Request::create(route('dashboard.institution.billing-addresses.index'), 'GET');
        $request->setUserResolver(fn () => $user);

        return app(BillingAddressController::class)->index($request)->render();
    }
}
