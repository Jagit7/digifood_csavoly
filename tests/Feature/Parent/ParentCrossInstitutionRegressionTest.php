<?php

namespace Tests\Feature\Parent;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionMealType;
use App\Models\MealType;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Regression suite for the exact scenario audited on 2026-09-07:
 *
 *   Guardian A -> Child1 (Institution 1), Child2 (Institution 2)
 *   Guardian B -> Child3 (Institution 2)
 *
 * Expected (PASS):
 *   - Guardian A sees Child1 AND Child2 from a single parent account.
 *   - Guardian A can manage meal cancellations for both Child1 and Child2,
 *     each using its OWN institution's cutoff/meal configuration.
 *   - Guardian A sees monthly settlements for both children.
 *
 * Expected (FAIL / 403-like):
 *   - Guardian A cannot access Child3 (Guardian B's child), not even via
 *     direct URL/ID manipulation on the "show child", "cancel meal" or
 *     "monthly settlement" endpoints.
 */
class ParentCrossInstitutionRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_guardian_a_sees_both_own_children_across_institutions_on_dashboard_and_children_page(): void
    {
        [$userA, , $institution1, $institution2, $child1, $child2] = $this->buildScenario();

        $dashboard = $this->actingAs($userA)->get('/szulo/vezerlopult');
        $dashboard->assertOk();
        $dashboard->assertViewHas('childrenCount', 2);

        $childrenIndex = $this->actingAs($userA)->get('/szulo/gyermekeim');
        $childrenIndex->assertOk();
        $childrenIndex->assertViewHas('children', fn ($children) => $children->total() === 2);
        $childrenIndex->assertViewHas('childCards', function (Collection $childCards) use ($child1, $child2) {
            $ids = $childCards->pluck('child.id')->all();
            sort($ids);

            return $ids === collect([$child1->id, $child2->id])->sort()->values()->all();
        });
        $childrenIndex->assertSee($child1->name);
        $childrenIndex->assertSee($child2->name);
    }

    public function test_guardian_a_can_manage_meal_cancellation_for_both_own_children_with_each_institutions_own_rules(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));

        [$userA, , $institution1, $institution2, $child1, $child2] = $this->buildScenario();

        // Different cancellation cutoffs per institution, to prove each
        // child's OWN institution config is used, not a "first" institution.
        $this->createDefaultMealContext($institution1, $userA, $child1, 8, 30);
        $this->createDefaultMealContext($institution2, $userA, $child2, 9, 45);

        $responseChild1 = $this->actingAs($userA)->from('/szulo/etkezesek-es-lemondasok')->post(
            '/szulo/etkezesek-es-lemondasok/lemondas',
            [
                'service_date' => '2026-09-07',
                'child_ids' => [$child1->id],
            ]
        );
        $responseChild1->assertRedirect('/szulo/etkezesek-es-lemondasok');
        $responseChild1->assertSessionHas('success');

        $responseChild2 = $this->actingAs($userA)->from('/szulo/etkezesek-es-lemondasok')->post(
            '/szulo/etkezesek-es-lemondasok/lemondas',
            [
                'service_date' => '2026-09-07',
                'child_ids' => [$child2->id],
            ]
        );
        $responseChild2->assertRedirect('/szulo/etkezesek-es-lemondasok');
        $responseChild2->assertSessionHas('success');

        $this->assertDatabaseHas('meal_cancellations', [
            'child_id' => $child1->id,
            'institution_id' => $institution1->id,
            'service_date' => '2026-09-07 00:00:00',
        ]);
        $this->assertDatabaseHas('meal_cancellations', [
            'child_id' => $child2->id,
            'institution_id' => $institution2->id,
            'service_date' => '2026-09-07 00:00:00',
        ]);
        $this->assertDatabaseCount('meal_cancellations', 2);
    }

    public function test_guardian_a_sees_monthly_settlements_for_both_own_children(): void
    {
        [$userA, , $institution1, $institution2, $child1, $child2] = $this->buildScenario();

        MonthlyPaymentStatement::query()->create([
            'institution_id' => $institution1->id,
            'child_id' => $child1->id,
            'year' => 2026,
            'month' => 8,
            'meal_amount' => 4000,
            'invoiceable_amount' => 4000,
            'previous_balance' => 0,
            'total_payable' => 4000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
        MonthlyPaymentStatement::query()->create([
            'institution_id' => $institution2->id,
            'child_id' => $child2->id,
            'year' => 2026,
            'month' => 8,
            'meal_amount' => 5500,
            'invoiceable_amount' => 5500,
            'previous_balance' => 0,
            'total_payable' => 5500,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($userA)->get('/szulo/havi-elszamolasok?month=2026-08');

        $response->assertOk();
        $response->assertViewHas('children_count', 2);
        $response->assertSee($child1->name);
        $response->assertSee($child2->name);
    }

    public function test_guardian_a_cannot_access_guardian_b_child_via_url_manipulation(): void
    {
        [$userA, , , , , , $child3] = $this->buildScenario();

        $response = $this->actingAs($userA)->get('/szulo/gyermekeim/'.$child3->id);

        $response->assertNotFound();
    }

    public function test_guardian_a_cannot_cancel_meal_for_guardian_b_child_via_id_manipulation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));

        [$userA, $userB, $institution1, $institution2, $child1, , $child3] = $this->buildScenario();

        $this->createDefaultMealContext($institution1, $userA, $child1, 8, 30);
        $this->createDefaultMealContext($institution2, $userB, $child3, 8, 30);

        // Guardian A tries to cancel Child3's (Guardian B's) meal by simply
        // adding its id to the request payload.
        $response = $this->actingAs($userA)->from('/szulo/etkezesek-es-lemondasok')->post(
            '/szulo/etkezesek-es-lemondasok/lemondas',
            [
                'service_date' => '2026-09-07',
                'child_ids' => [$child3->id],
            ]
        );

        $response->assertRedirect('/szulo/etkezesek-es-lemondasok');
        $response->assertSessionHasErrors('child_ids');
        $this->assertDatabaseCount('meal_cancellations', 0);
    }

    public function test_guardian_a_cannot_mix_own_child_with_guardian_b_child_in_one_cancellation_request(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:30:00', 'Europe/Budapest'));

        [$userA, $userB, $institution1, $institution2, $child1, , $child3] = $this->buildScenario();

        $this->createDefaultMealContext($institution1, $userA, $child1, 8, 30);
        $this->createDefaultMealContext($institution2, $userB, $child3, 8, 30);

        $response = $this->actingAs($userA)->from('/szulo/etkezesek-es-lemondasok')->post(
            '/szulo/etkezesek-es-lemondasok/lemondas',
            [
                'service_date' => '2026-09-07',
                'child_ids' => [$child1->id, $child3->id],
            ]
        );

        $response->assertRedirect('/szulo/etkezesek-es-lemondasok');
        $response->assertSessionHasErrors('child_ids');
        // Nothing should be created at all -- not even for the legitimately
        // owned child -- because the whole request must be rejected.
        $this->assertDatabaseCount('meal_cancellations', 0);
    }

    /**
     * @return array{0: User, 1: User, 2: Institution, 3: Institution, 4: Child, 5: Child, 6: Child}
     */
    private function buildScenario(): array
    {
        $institution1 = $this->createInstitution('RGA1', 'Regresszio Intezmeny Egy');
        $institution2 = $this->createInstitution('RGA2', 'Regresszio Intezmeny Ketto');

        $userA = User::factory()->create([
            'name' => 'Regresszio Szulo A',
            'email' => 'regresszio-szulo-a@example.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution1->id,
            'is_active' => true,
        ]);

        $guardianA1 = Guardian::query()->create([
            'institution_id' => $institution1->id,
            'user_id' => $userA->id,
            'last_name' => 'Regresszio',
            'first_name' => 'Szulo A',
            'email' => $userA->email,
            'source_type' => 'manual',
            'active' => true,
        ]);
        $guardianA2 = Guardian::query()->create([
            'institution_id' => $institution2->id,
            'user_id' => $userA->id,
            'last_name' => 'Regresszio',
            'first_name' => 'Szulo A',
            'email' => 'regresszio-szulo-a-inst2@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $userB = User::factory()->create([
            'name' => 'Regresszio Szulo B',
            'email' => 'regresszio-szulo-b@example.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution2->id,
            'is_active' => true,
        ]);
        $guardianB = Guardian::query()->create([
            'institution_id' => $institution2->id,
            'user_id' => $userB->id,
            'last_name' => 'Regresszio',
            'first_name' => 'Szulo B',
            'email' => $userB->email,
            'source_type' => 'manual',
            'active' => true,
        ]);

        $child1 = $this->createChild($institution1->id, 'Regresszio Gyermek Egy');
        $child1->guardians()->attach($guardianA1->id, ['created_at' => now(), 'updated_at' => now()]);

        $child2 = $this->createChild($institution2->id, 'Regresszio Gyermek Ketto');
        $child2->guardians()->attach($guardianA2->id, ['created_at' => now(), 'updated_at' => now()]);

        $child3 = $this->createChild($institution2->id, 'Regresszio Gyermek Harom');
        $child3->guardians()->attach($guardianB->id, ['created_at' => now(), 'updated_at' => now()]);

        return [$userA, $userB, $institution1, $institution2, $child1, $child2, $child3];
    }

    private function createInstitution(string $code, string $name): Institution
    {
        $institution = Institution::query()->create([
            'name' => $name,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        InstitutionMealSetting::query()->firstOrCreate([
            'institution_id' => $institution->id,
        ], [
            'cancellation_hour' => 9,
            'cancellation_minute' => 0,
        ]);

        return $institution;
    }

    private function createChild(int $institutionId, string $name): Child
    {
        $discount = DiscountType::query()->firstOrCreate(
            [
                'institution_id' => $institutionId,
                'name' => 'Kedvezmeny nelkul',
                'percentage' => 0,
            ],
            [
                'active' => true,
                'sort_order' => 1,
            ]
        );

        return Child::query()->create([
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
        InstitutionMealSetting::query()->updateOrCreate([
            'institution_id' => $institution->id,
        ], [
            'cancellation_hour' => $cancellationHour,
            'cancellation_minute' => $cancellationMinute,
        ]);

        $mealType = MealType::query()->firstOrCreate(
            ['code' => 'LUNCH-'.$institution->id],
            ['name' => 'Ebed', 'default_order' => 1]
        );

        $institutionMealType = InstitutionMealType::query()->firstOrCreate(
            ['institution_id' => $institution->id, 'meal_type_id' => $mealType->id],
            ['is_active' => true, 'is_parent_selectable' => true, 'is_required' => true, 'display_order' => 1]
        );

        $package = InstitutionMealPackage::query()->firstOrCreate(
            ['institution_id' => $institution->id, 'name' => 'Normal csomag'],
            ['is_active' => true, 'is_default' => true, 'display_order' => 1, 'pricing_mode' => 'component_sum', 'created_by' => $user->id]
        );

        $package->mealTypes()->syncWithoutDetaching([$institutionMealType->id => ['display_order' => 1]]);

        StudentMealSetting::query()->create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => $package->id,
            'mode' => StudentMealSetting::MODE_PACKAGE,
            'valid_from' => '2026-09-01',
            'valid_to' => null,
            'created_by' => $user->id,
        ]);
    }
}
