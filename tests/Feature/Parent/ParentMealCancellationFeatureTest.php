<?php

namespace Tests\Feature\Parent;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionMealType;
use App\Models\MealCancellation;
use App\Models\MealType;
use App\Models\StudentMealSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentMealCancellationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_parent_can_open_meal_cancellations_page(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));
        [$user, $institution, $guardian] = $this->createParentContext();
        $child = $this->createChild($institution->id, 'Nagy Anna');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createDefaultMealContext($institution, $user, $child);

        $response = $this->actingAs($user)->get($this->parentUrl('/etkezesek-es-lemondasok'));

        $response->assertOk();
        $response->assertSee('Étkezések és lemondások');
        $response->assertSee('Heti nézet');
        $response->assertSee('name="view" value="week"', false);
        $response->assertSee('Hogyan működik?');
        $response->assertSee('Nagy Anna');
        $response->assertSee('Tanítási nap');
        $response->assertDontSee('Tanítási vagy nevelési nap');
    }

    public function test_parent_day_endpoint_uses_short_deadline_label(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));
        [$user, $institution, $guardian] = $this->createParentContext();
        $child = $this->createChild($institution->id, 'Nagy Anna');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createDefaultMealContext($institution, $user, $child);

        $response = $this->actingAs($user)->getJson($this->parentUrl('/etkezesek-es-lemondasok/nap/2026-09-07'));

        $response->assertOk();
        $response->assertJsonPath('day_type', 'Tanítási nap');
        $response->assertJsonPath('deadline_label', 'Határidő: holnap 08:30');
    }

    public function test_parent_day_endpoint_handles_children_from_multiple_institutions_without_reusing_first_institution_context(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));
        [$user, $firstInstitution, $firstGuardian] = $this->createParentContext();
        $secondInstitution = Institution::create([
            'name' => 'Masodik intezmeny',
            'institution_code' => 'PM'.substr(md5('second-institution'), 0, 4),
            'type' => 'ovoda',
            'active' => true,
        ]);
        $secondGuardian = Guardian::create([
            'institution_id' => $secondInstitution->id,
            'user_id' => $user->id,
            'last_name' => 'Teszt',
            'first_name' => 'Masodik',
            'email' => $user->email,
            'source_type' => 'manual',
            'active' => true,
        ]);

        $schoolChild = $this->createChild($firstInstitution->id, 'Elso Gyermek');
        $kindergartenChild = $this->createChild($secondInstitution->id, 'Masodik Gyermek');

        $schoolChild->guardians()->attach($firstGuardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $kindergartenChild->guardians()->attach($secondGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $this->createDefaultMealContext($firstInstitution, $user, $schoolChild, 8, 30);
        $this->createDefaultMealContext($secondInstitution, $user, $kindergartenChild, 9, 45);

        $response = $this->actingAs($user)->getJson(
            $this->parentUrl('/etkezesek-es-lemondasok/nap/2026-09-04?child_id=all')
        );

        $response->assertOk();
        $response->assertJsonPath('day_type', 'Eltérő intézményi nap');
        $response->assertJsonPath('deadline_label', 'Határidő: intézményenként eltérő');
        $response->assertJsonCount(2, 'rows');
        $response->assertJsonPath('rows.0.can_cancel', true);
        $response->assertJsonPath('rows.1.can_cancel', true);
    }

    public function test_parent_can_cancel_meal_before_deadline(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));
        [$user, $institution, $guardian] = $this->createParentContext();
        $child = $this->createChild($institution->id, 'SajĂˇt Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createDefaultMealContext($institution, $user, $child);

        $response = $this->actingAs($user)->from($this->parentUrl('/etkezesek-es-lemondasok'))->post(
            $this->parentUrl('/etkezesek-es-lemondasok/lemondas'),
            [
                'service_date' => '2026-09-07',
                'child_ids' => [$child->id],
            ]
        );

        $response->assertRedirect($this->parentUrl('/etkezesek-es-lemondasok'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('meal_cancellations', [
            'child_id' => $child->id,
            'service_date' => '2026-09-07 00:00:00',
            'source' => MealCancellation::SOURCE_PARENT,
            'status' => MealCancellation::STATUS_ACTIVE,
            'created_by' => $user->id,
        ]);
    }

    public function test_parent_page_opened_before_cutoff_can_still_cancel_if_post_happens_before_deadline(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:29:00', 'Europe/Budapest'));
        [$user, $institution, $guardian] = $this->createParentContext();
        $child = $this->createChild($institution->id, 'Idoben Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createDefaultMealContext($institution, $user, $child, 9, 30);

        $this->actingAs($user)->get($this->parentUrl('/etkezesek-es-lemondasok'));

        $response = $this->actingAs($user)->from($this->parentUrl('/etkezesek-es-lemondasok'))->post(
            $this->parentUrl('/etkezesek-es-lemondasok/lemondas'),
            [
                'service_date' => '2026-09-08',
                'child_ids' => [$child->id],
            ]
        );

        $response->assertRedirect($this->parentUrl('/etkezesek-es-lemondasok'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('meal_cancellations', [
            'child_id' => $child->id,
            'service_date' => '2026-09-08 00:00:00',
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);
    }

    public function test_parent_cannot_cancel_meal_after_deadline(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 09:00:00', 'Europe/Budapest'));
        [$user, $institution, $guardian] = $this->createParentContext();
        $child = $this->createChild($institution->id, 'SajĂˇt Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createDefaultMealContext($institution, $user, $child);

        $response = $this->actingAs($user)->from($this->parentUrl('/etkezesek-es-lemondasok'))->post(
            $this->parentUrl('/etkezesek-es-lemondasok/lemondas'),
            [
                'service_date' => '2026-09-07',
                'child_ids' => [$child->id],
            ]
        );

        $response->assertRedirect($this->parentUrl('/etkezesek-es-lemondasok'));
        $response->assertSessionHasErrors('service_date');
        $this->assertDatabaseCount('meal_cancellations', 0);
    }

    public function test_parent_post_rechecks_deadline_when_page_was_opened_before_cutoff(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:29:00', 'Europe/Budapest'));
        [$user, $institution, $guardian] = $this->createParentContext();
        $child = $this->createChild($institution->id, 'Keso Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createDefaultMealContext($institution, $user, $child, 9, 30);

        $this->actingAs($user)->get($this->parentUrl('/etkezesek-es-lemondasok'));

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:31:00', 'Europe/Budapest'));

        $response = $this->actingAs($user)->from($this->parentUrl('/etkezesek-es-lemondasok'))->post(
            $this->parentUrl('/etkezesek-es-lemondasok/lemondas'),
            [
                'service_date' => '2026-09-08',
                'child_ids' => [$child->id],
            ]
        );

        $response->assertRedirect($this->parentUrl('/etkezesek-es-lemondasok'));
        $response->assertSessionHasErrors('service_date');
        $response->assertSessionHasErrors([
            'service_date' => 'A lemondási határidő időközben lejárt. A lemondás késői lemondásként nem rögzíthető. Határidő: ma 09:30',
        ]);
        $this->assertDatabaseCount('meal_cancellations', 0);
    }

    public function test_frontend_flag_manipulation_cannot_bypass_backend_cutoff(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:31:00', 'Europe/Budapest'));
        [$user, $institution, $guardian] = $this->createParentContext();
        $child = $this->createChild($institution->id, 'Manipulalt Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createDefaultMealContext($institution, $user, $child, 9, 30);

        $response = $this->actingAs($user)->from($this->parentUrl('/etkezesek-es-lemondasok'))->post(
            $this->parentUrl('/etkezesek-es-lemondasok/lemondas'),
            [
                'service_date' => '2026-09-08',
                'child_ids' => [$child->id],
                'is_available' => '1',
                'can_cancel' => '1',
            ]
        );

        $response->assertRedirect($this->parentUrl('/etkezesek-es-lemondasok'));
        $response->assertSessionHasErrors('service_date');
        $this->assertDatabaseCount('meal_cancellations', 0);
    }

    public function test_parent_cannot_modify_other_guardians_child(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));
        [$user, $institution] = $this->createParentContext();

        $otherUser = User::factory()->create([
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);
        $otherGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'user_id' => $otherUser->id,
            'last_name' => 'MĂˇsik',
            'first_name' => 'SzĂĽlĹ‘',
            'email' => 'masik-szulo@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $foreignChild = $this->createChild($institution->id, 'Tiltott Gyermek');
        $foreignChild->guardians()->attach($otherGuardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createDefaultMealContext($institution, $otherUser, $foreignChild);

        $response = $this->actingAs($user)->from($this->parentUrl('/etkezesek-es-lemondasok'))->post(
            $this->parentUrl('/etkezesek-es-lemondasok/lemondas'),
            [
                'service_date' => '2026-09-07',
                'child_ids' => [$foreignChild->id],
            ]
        );

        $response->assertRedirect($this->parentUrl('/etkezesek-es-lemondasok'));
        $response->assertSessionHasErrors('child_ids');
    }

    public function test_parent_can_cancel_multiple_own_children_in_one_request(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));
        [$user, $institution, $guardian] = $this->createParentContext();
        $firstChild = $this->createChild($institution->id, 'ElsĹ‘ Gyermek');
        $secondChild = $this->createChild($institution->id, 'MĂˇsodik Gyermek');

        foreach ([$firstChild, $secondChild] as $child) {
            $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
            $this->createDefaultMealContext($institution, $user, $child);
        }

        $response = $this->actingAs($user)->from($this->parentUrl('/etkezesek-es-lemondasok'))->post(
            $this->parentUrl('/etkezesek-es-lemondasok/lemondas'),
            [
                'service_date' => '2026-09-07',
                'child_ids' => [$firstChild->id, $secondChild->id],
            ]
        );

        $response->assertRedirect($this->parentUrl('/etkezesek-es-lemondasok'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('meal_cancellations', 2);
    }

    public function test_parent_cannot_create_duplicate_cancellation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));
        [$user, $institution, $guardian] = $this->createParentContext();
        $child = $this->createChild($institution->id, 'SajĂˇt Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createDefaultMealContext($institution, $user, $child);

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-09-07',
            'source' => MealCancellation::SOURCE_PARENT,
            'status' => MealCancellation::STATUS_ACTIVE,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->from($this->parentUrl('/etkezesek-es-lemondasok'))->post(
            $this->parentUrl('/etkezesek-es-lemondasok/lemondas'),
            [
                'service_date' => '2026-09-07',
                'child_ids' => [$child->id],
            ]
        );

        $response->assertRedirect($this->parentUrl('/etkezesek-es-lemondasok'));
        $response->assertSessionHasErrors('service_date');
        $this->assertDatabaseCount('meal_cancellations', 1);
    }

    public function test_parent_cannot_cancel_day_without_active_meal(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));
        [$user, $institution, $guardian] = $this->createParentContext();
        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 8,
            'cancellation_minute' => 30,
        ]);

        $child = $this->createChild($institution->id, 'Ă‰tkezĂ©s NĂ©lkĂĽl');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->from($this->parentUrl('/etkezesek-es-lemondasok'))->post(
            $this->parentUrl('/etkezesek-es-lemondasok/lemondas'),
            [
                'service_date' => '2026-09-07',
                'child_ids' => [$child->id],
            ]
        );

        $response->assertRedirect($this->parentUrl('/etkezesek-es-lemondasok'));
        $response->assertSessionHasErrors('service_date');
    }

    private function createParentContext(): array
    {
        $institution = Institution::create([
            'name' => 'SzĂĽlĹ‘i intĂ©zmĂ©ny',
            'institution_code' => 'PM'.substr(md5((string) microtime(true)), 0, 4),
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Teszt SzĂĽlĹ‘',
            'email' => 'szulo-'.substr(md5((string) microtime(true)), 0, 6).'@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'last_name' => 'Teszt',
            'first_name' => 'SzĂĽlĹ‘',
            'email' => $user->email,
            'source_type' => 'manual',
            'active' => true,
        ]);

        return [$user, $institution, $guardian];
    }

    private function createChild(int $institutionId, string $name): Child
    {
        $discount = DiscountType::firstOrCreate(
            [
                'institution_id' => $institutionId,
                'name' => 'KedvezmĂ©ny nĂ©lkĂĽl',
                'percentage' => 0,
            ],
            [
                'active' => true,
                'sort_order' => 1,
            ]
        );

        return Child::create([
            'institution_id' => $institutionId,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId.microtime(true)), 0, 10),
            'group_name' => '1.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function createDefaultMealContext(
        Institution $institution,
        User $user,
        Child $child,
        int $cancellationHour = 8,
        int $cancellationMinute = 30
    ): void {
        InstitutionMealSetting::firstOrCreate([
            'institution_id' => $institution->id,
        ], [
            'cancellation_hour' => $cancellationHour,
            'cancellation_minute' => $cancellationMinute,
        ]);

        $mealType = MealType::firstOrCreate(
            ['code' => 'LUNCH-'.$institution->id],
            ['name' => 'EbĂ©d', 'default_order' => 1]
        );

        $institutionMealType = InstitutionMealType::firstOrCreate(
            ['institution_id' => $institution->id, 'meal_type_id' => $mealType->id],
            ['is_active' => true, 'is_parent_selectable' => true, 'is_required' => true, 'display_order' => 1]
        );

        $package = InstitutionMealPackage::firstOrCreate(
            ['institution_id' => $institution->id, 'name' => 'NormĂˇl csomag'],
            ['is_active' => true, 'is_default' => true, 'display_order' => 1, 'pricing_mode' => 'component_sum', 'created_by' => $user->id]
        );

        $package->mealTypes()->syncWithoutDetaching([$institutionMealType->id => ['display_order' => 1]]);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => $package->id,
            'mode' => StudentMealSetting::MODE_PACKAGE,
            'valid_from' => '2026-09-01',
            'valid_to' => null,
            'created_by' => $user->id,
        ]);
    }

    private function parentUrl(string $path): string
    {
        $appUrl = config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'http';
        $host = parse_url($appUrl, PHP_URL_HOST) ?? 'localhost';
        $port = parse_url($appUrl, PHP_URL_PORT);
        $authority = $port ? $host.':'.$port : $host;

        return $scheme.'://'.$authority.'/szulo'.$path;
    }
}
