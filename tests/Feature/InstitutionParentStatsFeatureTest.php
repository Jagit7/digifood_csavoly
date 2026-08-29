<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\InstitutionAdmin\ParentController;
use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Tests\TestCase;

class InstitutionParentStatsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_child_with_two_guardians_counts_once(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('PST001');
        $child = $this->createChild($institution->id, 'Kettős Gergő');

        $firstGuardian = $this->createGuardian($institution->id, 'Első', 'Gondviselő');
        $secondGuardian = $this->createGuardian($institution->id, 'Második', 'Gondviselő');

        $this->attachChildToGuardian($child->id, $firstGuardian->id);
        $this->attachChildToGuardian($child->id, $secondGuardian->id);

        $this->attachBillingProfileToChild($institution->id, $firstGuardian->id, $child->id, true, '2026-08-01', null, true);
        $this->attachBillingProfileToChild($institution->id, $secondGuardian->id, $child->id, true, '2026-08-01', null, true);

        $stats = $this->statsFor($user);

        $this->assertSame(1, $stats['invoice_recipients']);
    }

    public function test_secondary_billing_link_does_not_count(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('PST002');
        $child = $this->createChild($institution->id, 'Másodlagos Mira');
        $guardian = $this->createGuardian($institution->id, 'Másodlagos', 'Gondviselő');

        $this->attachChildToGuardian($child->id, $guardian->id);
        $this->attachBillingProfileToChild($institution->id, $guardian->id, $child->id, false, '2026-08-01', null, true);

        $stats = $this->statsFor($user);

        $this->assertSame(0, $stats['invoice_recipients']);
    }

    public function test_expired_billing_link_does_not_count(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('PST003');
        $child = $this->createChild($institution->id, 'Lejárt Lili');
        $guardian = $this->createGuardian($institution->id, 'Lejárt', 'Gondviselő');

        $this->attachChildToGuardian($child->id, $guardian->id);
        $this->attachBillingProfileToChild($institution->id, $guardian->id, $child->id, true, '2026-08-01', '2026-08-23', true);

        $stats = $this->statsFor($user);

        $this->assertSame(0, $stats['invoice_recipients']);
    }

    public function test_inactive_billing_profile_does_not_count(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('PST004');
        $child = $this->createChild($institution->id, 'Inaktív Imola');
        $guardian = $this->createGuardian($institution->id, 'Inaktív', 'Gondviselő');

        $this->attachChildToGuardian($child->id, $guardian->id);
        $this->attachBillingProfileToChild($institution->id, $guardian->id, $child->id, true, '2026-08-01', null, false);

        $stats = $this->statsFor($user);

        $this->assertSame(0, $stats['invoice_recipients']);
    }

    public function test_other_institution_child_does_not_count(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('PST005');
        [$otherInstitution] = $this->seedInstitutionAdmin('PST005X');
        $otherChild = $this->createChild($otherInstitution->id, 'Másik Intézmény');
        $guardian = $this->createGuardian($institution->id, 'Saját', 'Gondviselő');

        $this->attachBillingProfileToChild($institution->id, $guardian->id, $otherChild->id, true, '2026-08-01', null, true);

        $stats = $this->statsFor($user);

        $this->assertSame(0, $stats['invoice_recipients']);
    }

    public function test_duplicate_primary_links_for_same_child_still_count_once(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('PST006');
        $child = $this->createChild($institution->id, 'Duplikált Dénes');
        $guardian = $this->createGuardian($institution->id, 'Duplikált', 'Gondviselő');

        $this->attachChildToGuardian($child->id, $guardian->id);
        $this->attachBillingProfileToChild($institution->id, $guardian->id, $child->id, true, '2026-08-01', null, true);
        $this->attachBillingProfileToChild($institution->id, $guardian->id, $child->id, true, '2026-08-10', null, true);

        $stats = $this->statsFor($user);

        $this->assertSame(1, $stats['invoice_recipients']);
    }

    private function statsFor(User $user): array
    {
        $this->actingAs($user);

        $view = $this->controller()->index($this->makeRequest($user));

        $this->assertInstanceOf(View::class, $view);

        return $view->getData()['stats'];
    }

    private function controller(): ParentController
    {
        return app(ParentController::class);
    }

    private function makeRequest(User $user): Request
    {
        $request = Request::create(route('dashboard.institution.parents.index'), 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function seedInstitutionAdmin(string $code): array
    {
        $institution = Institution::query()->create([
            'name' => 'Szülő Statisztika '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institution, $user];
    }

    private function createChild(int $institutionId, string $name): Child
    {
        return Child::query()->create([
            'institution_id' => $institutionId,
            'name' => $name,
            'active' => true,
        ]);
    }

    private function createGuardian(int $institutionId, string $lastName, string $firstName): Guardian
    {
        return Guardian::query()->create([
            'institution_id' => $institutionId,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'active' => true,
        ]);
    }

    private function attachChildToGuardian(int $childId, int $guardianId): void
    {
        DB::table('child_guardian')->insert([
            'child_id' => $childId,
            'guardian_id' => $guardianId,
            'relationship_type' => 'Édesanya',
            'is_legal_representative' => true,
            'has_no_custody' => false,
            'is_emergency_contact' => true,
            'receives_family_allowance' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachBillingProfileToChild(
        int $institutionId,
        int $guardianId,
        int $childId,
        bool $isPrimary,
        ?string $validFrom,
        ?string $validTo,
        bool $isActive
    ): void {
        $profile = BillingProfile::query()->create([
            'institution_id' => $institutionId,
            'guardian_id' => $guardianId,
            'payer_type' => 'guardian',
            'billing_name' => 'Teszt profil '.$guardianId.'-'.$childId.'-'.BillingProfile::query()->count(),
            'active' => $isActive,
        ]);

        DB::table('billing_profile_child')->insert([
            'billing_profile_id' => $profile->id,
            'child_id' => $childId,
            'is_primary' => $isPrimary,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
