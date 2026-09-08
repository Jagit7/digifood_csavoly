<?php

namespace Tests\Feature\MealCancellations;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionMealType;
use App\Models\MealCancellation;
use App\Models\MealType;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\MealCancellationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminMealCancellationOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public static function closedDates(): array
    {
        return [
            'past' => ['2026-09-08 09:00', '2026-09-07'],
            'cutoff' => ['2026-09-08 09:00', '2026-09-09'],
            'friday after cutoff' => ['2026-09-04 09:00', '2026-09-07'],
            'weekend' => ['2026-09-05 07:00', '2026-09-07'],
            'public holiday' => ['2026-04-03 07:00', '2026-04-07'],
            'school break' => ['2026-10-23 07:00', '2026-11-02', true],
        ];
    }

    #[DataProvider('closedDates')]
    public function test_admin_must_confirm_closed_dates_before_saving(string $now, string $date, bool $schoolBreak = false): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($now, 'Europe/Budapest'));
        [$institution, $admin, $child] = $this->context();
        if ($schoolBreak) {
            SchoolBreak::create(['institution_id' => $institution->id, 'title' => 'Autumn break', 'type' => 'school_break', 'start_date' => '2026-10-26', 'end_date' => '2026-10-30']);
        }
        $create = route('dashboard.institution.meal-cancellations.create', ['child_id' => $child->id]);
        $payload = ['child_id' => $child->id, 'mode' => 'single', 'service_date' => $date, 'reason' => 'Telefonon jelezve'];

        $this->actingAs($admin)->from($create)->post(route('dashboard.institution.meal-cancellations.store'), $payload)
            ->assertRedirect($create)->assertSessionHasErrors('admin_override', null, 'adminOverride');
        $this->assertDatabaseCount('meal_cancellations', 0);
        $this->get($create)->assertOk()->assertSee('Adminisztrátori felülbírálás')->assertSee('btn btn-warning', false)
            ->assertSee('Igen, rögzítem a lemondást');

        $this->post(route('dashboard.institution.meal-cancellations.store'), $payload + ['admin_override' => true])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard.institution.meal-cancellations.index'));
        $this->assertDatabaseHas('meal_cancellations', [
            'child_id' => $child->id, 'service_date' => $date, 'source' => 'admin', 'created_by' => $admin->id,
            'reason' => '[admin_override=true] Telefonon jelezve',
        ]);
    }

    #[DataProvider('closedDates')]
    public function test_parent_cannot_bypass_closed_dates_with_override_flag(string $now, string $date, bool $schoolBreak = false): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($now, 'Europe/Budapest'));
        [$institution, , $child] = $this->context();
        if ($schoolBreak) {
            SchoolBreak::create(['institution_id' => $institution->id, 'title' => 'Autumn break', 'type' => 'school_break', 'start_date' => '2026-10-26', 'end_date' => '2026-10-30']);
        }
        $parent = User::factory()->create(['role' => User::ROLE_PARENT, 'institution_id' => $institution->id, 'is_active' => true]);
        $guardian = Guardian::create([
            'institution_id' => $institution->id, 'user_id' => $parent->id,
            'last_name' => 'Test', 'first_name' => 'Parent', 'email' => $parent->email, 'source_type' => 'manual', 'active' => true,
        ]);
        $child->guardians()->attach($guardian);
        $mealType = MealType::create(['code' => 'LUNCH', 'name' => 'Lunch', 'default_order' => 1]);
        $institutionMealType = InstitutionMealType::create([
            'institution_id' => $institution->id, 'meal_type_id' => $mealType->id,
            'is_active' => true, 'is_parent_selectable' => true, 'is_required' => true, 'display_order' => 1,
        ]);
        $package = InstitutionMealPackage::create([
            'institution_id' => $institution->id, 'name' => 'Normal', 'is_active' => true,
            'is_default' => true, 'display_order' => 1, 'pricing_mode' => 'component_sum', 'created_by' => $parent->id,
        ]);
        $package->mealTypes()->attach($institutionMealType->id, ['display_order' => 1]);
        StudentMealSetting::create([
            'student_id' => $child->id, 'institution_id' => $institution->id, 'institution_meal_package_id' => $package->id,
            'mode' => StudentMealSetting::MODE_PACKAGE, 'valid_from' => '2026-01-01', 'created_by' => $parent->id,
        ]);
        $this->actingAs($parent)->post('/szulo/etkezesek-es-lemondasok/lemondas', [
            'child_ids' => [$child->id], 'service_date' => $date, 'admin_override' => true,
        ])->assertSessionHasErrors('service_date');
        $this->assertStringContainsString('határidő', session('errors')->first('service_date'));
        $this->assertDatabaseCount('meal_cancellations', 0);
    }

    public function test_open_future_date_saves_without_confirmation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 07:00', 'Europe/Budapest'));
        [, $admin, $child] = $this->context();
        $this->actingAs($admin)->post(route('dashboard.institution.meal-cancellations.store'), [
            'child_id' => $child->id, 'mode' => 'single', 'service_date' => '2026-09-07',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard.institution.meal-cancellations.index'));
        $this->assertDatabaseHas('meal_cancellations', ['child_id' => $child->id, 'reason' => null]);
    }

    public function test_foreign_child_is_rejected_even_with_confirmation(): void
    {
        [, $admin] = $this->context();
        [, , $foreignChild] = $this->context();
        $this->actingAs($admin)->post(route('dashboard.institution.meal-cancellations.store'), [
            'child_id' => $foreignChild->id, 'mode' => 'single', 'service_date' => '2026-09-01', 'admin_override' => true,
        ])->assertSessionHasErrors('child_id');
        $this->assertDatabaseCount('meal_cancellations', 0);
    }

    public function test_inactive_child_can_be_cancelled_retroactively(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-08 09:00', 'Europe/Budapest'));
        [, $admin, $child] = $this->context();
        $child->update(['active' => false]);
        $this->actingAs($admin)->get(route('dashboard.institution.meal-cancellations.create', ['child_id' => $child->id]))
            ->assertOk()->assertSee($child->name);
        $this->post(route('dashboard.institution.meal-cancellations.store'), [
            'child_id' => $child->id, 'mode' => 'single', 'service_date' => '2026-09-07', 'admin_override' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('meal_cancellations', 1);
    }

    public function test_missing_deadline_requires_confirmation_but_does_not_block_admin(): void
    {
        [$institution, $admin, $child] = $this->context();
        InstitutionMealSetting::where('institution_id', $institution->id)->delete();
        $payload = ['child_id' => $child->id, 'mode' => 'single', 'service_date' => '2026-09-07'];
        $this->actingAs($admin)->post(route('dashboard.institution.meal-cancellations.store'), $payload)
            ->assertSessionHasErrors('admin_override', null, 'adminOverride');
        $this->assertDatabaseCount('meal_cancellations', 0);
        $this->post(route('dashboard.institution.meal-cancellations.store'), $payload + ['admin_override' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('meal_cancellations', 1);
    }

    public function test_service_rejects_non_admin_override(): void
    {
        [$institution, , $child] = $this->context();
        $parent = User::factory()->create(['role' => User::ROLE_PARENT, 'institution_id' => $institution->id]);
        $this->expectException(HttpException::class);
        app(MealCancellationService::class)->recordSingle($institution, $child, '2026-09-01', $parent, null, true);
    }

    public function test_range_is_atomic_and_requires_confirmation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-08 09:00', 'Europe/Budapest'));
        [, $admin, $child] = $this->context();
        $payload = ['child_id' => $child->id, 'mode' => 'range', 'date_from' => '2026-09-07', 'date_to' => '2026-09-10'];
        $this->actingAs($admin)->post(route('dashboard.institution.meal-cancellations.store'), $payload)
            ->assertSessionHasErrors('admin_override', null, 'adminOverride');
        $this->assertDatabaseCount('meal_cancellations', 0);
        $this->post(route('dashboard.institution.meal-cancellations.store'), $payload + ['admin_override' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('meal_cancellations', 4);
    }

    public function test_recurring_rule_accepts_confirmed_past_start(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-08 09:00', 'Europe/Budapest'));
        [, $admin, $child] = $this->context();
        $payload = ['child_id' => $child->id, 'mode' => 'recurring', 'weekday' => 1, 'starts_on' => '2026-09-07'];
        $this->actingAs($admin)->post(route('dashboard.institution.meal-cancellations.store'), $payload)
            ->assertSessionHasErrors('admin_override', null, 'adminOverride');
        $this->assertDatabaseCount('recurring_cancellation_rules', 0);
        $this->post(route('dashboard.institution.meal-cancellations.store'), $payload + ['admin_override' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recurring_cancellation_rules', ['child_id' => $child->id, 'reason' => '[admin_override=true]']);
    }

    public function test_repeated_submission_and_revoked_record_reuse_do_not_create_duplicate_rows(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-08 09:00', 'Europe/Budapest'));
        [, $admin, $child] = $this->context();
        $payload = ['child_id' => $child->id, 'mode' => 'single', 'service_date' => '2026-09-07', 'admin_override' => true];
        $this->actingAs($admin)->post(route('dashboard.institution.meal-cancellations.store'), $payload)->assertSessionHasNoErrors();
        $cancellation = MealCancellation::firstOrFail();
        $this->post(route('dashboard.institution.meal-cancellations.store'), $payload)->assertSessionHasErrors('service_date');
        $cancellation->update(['status' => MealCancellation::STATUS_REVOKED, 'revoked_by' => $admin->id, 'revoked_at' => now()]);
        $this->post(route('dashboard.institution.meal-cancellations.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('meal_cancellations', 1);
        $this->assertSame($cancellation->id, MealCancellation::firstOrFail()->id);
        $this->assertSame(MealCancellation::STATUS_ACTIVE, $cancellation->fresh()->status);
    }

    private function context(): array
    {
        $institution = Institution::create(['name' => 'Override school', 'institution_code' => 'OV'.random_int(100000, 999999), 'type' => 'iskola', 'active' => true]);
        $admin = User::factory()->create(['role' => User::ROLE_INSTITUTION_ADMIN, 'institution_id' => $institution->id, 'is_active' => true]);
        $admin->institutions()->attach($institution->id, ['scope_role' => User::ROLE_INSTITUTION_ADMIN]);
        InstitutionMealSetting::create(['institution_id' => $institution->id, 'cancellation_hour' => 8, 'cancellation_minute' => 30]);
        $discount = DiscountType::create(['institution_id' => $institution->id, 'name' => 'Full price', 'percentage' => 0, 'active' => true]);
        $child = Child::create([
            'institution_id' => $institution->id, 'discount_type_id' => $discount->id, 'name' => 'Test Child',
            'educational_identifier' => 'ID'.random_int(100000, 999999), 'group_name' => '1.A', 'school_year' => '2026/2027', 'source_type' => 'manual', 'active' => true,
        ]);

        return [$institution, $admin, $child];
    }
}
