<?php

namespace Tests\Unit;

use App\Models\Child;
use App\Models\AbMenuItem;
use App\Models\AbMenuPlan;
use App\Models\ClassCancellation;
use App\Models\ClassGroup;
use App\Models\DietaryRestriction;
use App\Models\Institution;
use App\Models\MealCancellation;
use App\Models\MenuChoice;
use App\Models\RecurringCancellationRule;
use App\Models\SchoolYear;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\DailyMealHeadcountService;
use App\Services\Meals\AbMenuSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyMealHeadcountServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_daily_meal_headcount_from_existing_rules(): void
    {
        $institution = Institution::create([
            'name' => 'Teszt Iskola',
            'institution_code' => 'TEST001',
            'type' => 'iskola',
            'active' => true,
        ]);
        $otherInstitution = Institution::create([
            'name' => 'Másik Intézmény',
            'institution_code' => 'TEST002',
            'type' => 'iskola',
            'active' => true,
        ]);
        $schoolYear = SchoolYear::create([
            'institution_id' => $institution->id,
            'name' => '2025/2026',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-08-31',
            'status' => 'active',
            'is_current' => true,
        ]);
        $classGroup = ClassGroup::create([
            'institution_id' => $institution->id,
            'school_year_id' => $schoolYear->id,
            'name' => '3.A',
            'group_type' => 'school_class',
            'active' => true,
        ]);

        $eatingChild = $this->createChild($institution->id, 'Étkező Elek', '3.A');
        $individualCancelledChild = $this->createChild($institution->id, 'Lemondott Lili', '2.B');
        $recurringCancelledChild = $this->createChild($institution->id, 'Rendszeres Robi', '2.B');
        $classCancelledChild = $this->createChild($institution->id, 'Csoportos Cili', '3.A');
        $missingChild = $this->createChild($institution->id, 'Kimaradó Kata');
        $outsideChild = $this->createChild($otherInstitution->id, 'Másik Misi');

        foreach ([$eatingChild, $individualCancelledChild, $recurringCancelledChild, $classCancelledChild] as $child) {
            StudentMealSetting::create([
                'student_id' => $child->id,
                'institution_id' => $institution->id,
                'institution_meal_package_id' => null,
                'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
                'valid_from' => '2026-07-01',
                'valid_to' => null,
            ]);
        }

        $classGroup->children()->attach($classCancelledChild->id, [
            'status' => 'active',
            'joined_on' => '2025-09-01',
            'left_on' => null,
        ]);

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $individualCancelledChild->id,
            'service_date' => '2026-07-16',
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);
        RecurringCancellationRule::create([
            'institution_id' => $institution->id,
            'child_id' => $recurringCancelledChild->id,
            'weekday' => 4,
            'starts_on' => '2026-07-01',
            'ends_on' => null,
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => RecurringCancellationRule::STATUS_ACTIVE,
        ]);
        ClassCancellation::create([
            'institution_id' => $institution->id,
            'class_group_id' => $classGroup->id,
            'date_from' => '2026-07-16',
            'date_to' => '2026-07-16',
            'affected_children_count' => 1,
        ]);

        $result = app(DailyMealHeadcountService::class)->forDate($institution->id, '2026-07-16');
        $statuses = $result['rows']
            ->mapWithKeys(fn (array $row) => [$row['child']->name => $row['status']]);

        $this->assertTrue($result['meta']['is_service_day']);
        $this->assertSame(1, $result['stats']['daily_eaters']);
        $this->assertSame(3, $result['stats']['cancelled_meals']);
        $this->assertSame(4, $result['stats']['active_eaters']);
        $this->assertSame(1, $result['stats']['missing_children']);
        $this->assertSame(0, $result['stats']['dietary_eaters']);
        $this->assertSame(DailyMealHeadcountService::STATUS_EATING, $statuses['Étkező Elek']);
        $this->assertSame(DailyMealHeadcountService::STATUS_CANCELLED, $statuses['Lemondott Lili']);
        $this->assertSame(DailyMealHeadcountService::STATUS_CANCELLED, $statuses['Rendszeres Robi']);
        $this->assertSame(DailyMealHeadcountService::STATUS_CANCELLED, $statuses['Csoportos Cili']);
        $this->assertSame(DailyMealHeadcountService::STATUS_NO_ACTIVE_MEAL, $statuses['Kimaradó Kata']);
        $this->assertArrayNotHasKey($outsideChild->name, $statuses->all());
        $this->assertSame(
            ['Lemondott Lili', 'Rendszeres Robi', 'Csoportos Cili', 'Kimaradó Kata', 'Étkező Elek'],
            $result['rows']->pluck('child.name')->all()
        );
    }

    public function test_it_counts_only_non_cancelled_dietary_children_on_the_selected_day(): void
    {
        $institution = Institution::create([
            'name' => 'Diétás Iskola',
            'institution_code' => 'DIET001',
            'type' => 'iskola',
            'active' => true,
        ]);
        $otherInstitution = Institution::create([
            'name' => 'Másik Diétás Intézmény',
            'institution_code' => 'DIET002',
            'type' => 'iskola',
            'active' => true,
        ]);

        $lactoseFree = DietaryRestriction::create([
            'institution_id' => $institution->id,
            'name' => 'Laktózmentes',
            'type' => DietaryRestriction::TYPE_INTOLERANCE,
            'active' => true,
            'sort_order' => 1,
        ]);
        $glutenFree = DietaryRestriction::create([
            'institution_id' => $institution->id,
            'name' => 'Gluténmentes',
            'type' => DietaryRestriction::TYPE_ALLERGEN,
            'active' => true,
            'sort_order' => 2,
        ]);
        $otherDiet = DietaryRestriction::create([
            'institution_id' => $otherInstitution->id,
            'name' => 'Tejmentes',
            'type' => DietaryRestriction::TYPE_INTOLERANCE,
            'active' => true,
            'sort_order' => 1,
        ]);

        $dietaryEatingChild = $this->createChild($institution->id, 'Alma Anna', '1.A');
        $dietaryCancelledChild = $this->createChild($institution->id, 'Béla Bence', '1.A');
        $nonDietaryEatingChild = $this->createChild($institution->id, 'Cili Csenge', '2.A');
        $otherInstitutionChild = $this->createChild($otherInstitution->id, 'Dodi Dániel', '1.A');

        $dietaryEatingChild->dietaryRestrictions()->attach([$lactoseFree->id, $glutenFree->id]);
        $dietaryCancelledChild->dietaryRestrictions()->attach($lactoseFree->id);
        $otherInstitutionChild->dietaryRestrictions()->attach($otherDiet->id);

        foreach ([$dietaryEatingChild, $dietaryCancelledChild, $nonDietaryEatingChild] as $child) {
            StudentMealSetting::create([
                'student_id' => $child->id,
                'institution_id' => $institution->id,
                'institution_meal_package_id' => null,
                'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
                'valid_from' => '2026-07-01',
                'valid_to' => null,
            ]);
        }

        StudentMealSetting::create([
            'student_id' => $otherInstitutionChild->id,
            'institution_id' => $otherInstitution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-07-01',
            'valid_to' => null,
        ]);

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $dietaryCancelledChild->id,
            'service_date' => '2026-07-16',
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);

        $result = app(DailyMealHeadcountService::class)->forDate($institution->id, '2026-07-16');

        $this->assertSame(2, $result['stats']['daily_eaters']);
        $this->assertSame(1, $result['stats']['cancelled_meals']);
        $this->assertSame(1, $result['stats']['dietary_eaters']);
        $this->assertSame(
            ['Alma Anna'],
            app(DailyMealHeadcountService::class)->dietaryRows($result['rows'])->pluck('child.name')->all()
        );
    }

    public function test_it_resolves_ab_menu_counts_from_the_existing_daily_headcount_population(): void
    {
        $institution = Institution::create([
            'name' => 'A/B Iskola',
            'institution_code' => 'AB001',
            'type' => 'iskola',
            'active' => true,
        ]);
        $otherInstitution = Institution::create([
            'name' => 'Masik A/B Iskola',
            'institution_code' => 'AB002',
            'type' => 'iskola',
            'active' => true,
        ]);
        $user = User::create([
            'name' => 'Teszt Admin',
            'email' => 'ab-headcount@example.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $childA = $this->createChild($institution->id, 'Alap Anna', '4.A');
        $childB = $this->createChild($institution->id, 'Bela Bence', '4.A');
        $childCancelledB = $this->createChild($institution->id, 'Cancel Cili', '4.A');
        $dietaryChild = $this->createChild($institution->id, 'Diet Dora', '4.A');
        $outsideChild = $this->createChild($otherInstitution->id, 'Kulso Karesz', '4.A');

        $restriction = DietaryRestriction::create([
            'institution_id' => $institution->id,
            'name' => 'Glutenmentes',
            'type' => DietaryRestriction::TYPE_ALLERGEN,
            'active' => true,
            'sort_order' => 1,
        ]);
        $dietaryChild->dietaryRestrictions()->attach($restriction->id);

        foreach ([$childA, $childB, $childCancelledB, $dietaryChild] as $child) {
            StudentMealSetting::create([
                'student_id' => $child->id,
                'institution_id' => $institution->id,
                'institution_meal_package_id' => null,
                'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
                'valid_from' => '2026-09-01',
                'valid_to' => null,
            ]);
        }

        StudentMealSetting::create([
            'student_id' => $outsideChild->id,
            'institution_id' => $otherInstitution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-09-01',
            'valid_to' => null,
        ]);

        $plan = AbMenuPlan::create([
            'institution_id' => $institution->id,
            'title' => 'Szeptemberi A/B',
            'valid_from' => '2026-09-10',
            'valid_to' => '2026-09-10',
            'active' => true,
            'published_at' => '2026-08-05 10:00:00',
            'created_by' => $user->id,
        ]);

        $item = AbMenuItem::create([
            'ab_menu_plan_id' => $plan->id,
            'menu_date' => '2026-09-10',
            'menu_a' => 'A menu',
            'menu_b' => 'B menu',
            'menu_dietary' => 'Diet menu',
            'allergens' => null,
            'note' => null,
        ]);

        MenuChoice::create([
            'institution_id' => $institution->id,
            'child_id' => $childB->id,
            'ab_menu_item_id' => $item->id,
            'menu_date' => '2026-09-10',
            'choice' => MenuChoice::CHOICE_B,
            'selected_by' => $user->id,
        ]);

        MenuChoice::create([
            'institution_id' => $institution->id,
            'child_id' => $childCancelledB->id,
            'ab_menu_item_id' => $item->id,
            'menu_date' => '2026-09-10',
            'choice' => MenuChoice::CHOICE_B,
            'selected_by' => $user->id,
        ]);

        MenuChoice::create([
            'institution_id' => $otherInstitution->id,
            'child_id' => $outsideChild->id,
            'ab_menu_item_id' => $item->id,
            'menu_date' => '2026-09-10',
            'choice' => MenuChoice::CHOICE_B,
            'selected_by' => $user->id,
        ]);

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $childCancelledB->id,
            'service_date' => '2026-09-10',
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);

        $result = app(DailyMealHeadcountService::class)->forDate($institution->id, '2026-09-10');
        $rows = $result['rows']->mapWithKeys(fn (array $row) => [$row['child']->name => $row]);

        $this->assertSame(3, $result['stats']['daily_eaters']);
        $this->assertSame(1, $result['stats']['menu_a_count']);
        $this->assertSame(1, $result['stats']['menu_b_count']);
        $this->assertSame(1, $result['stats']['dietary_count']);
        $this->assertSame(1, $result['stats']['cancelled_meals']);

        $this->assertSame(AbMenuSelectionService::CATEGORY_A, $rows['Alap Anna']['effective_menu_category']);
        $this->assertSame(AbMenuSelectionService::CATEGORY_B, $rows['Bela Bence']['effective_menu_category']);
        $this->assertSame(AbMenuSelectionService::CATEGORY_DIETARY, $rows['Diet Dora']['effective_menu_category']);
        $this->assertNull($rows['Cancel Cili']['effective_menu_category']);
        $this->assertSame(DailyMealHeadcountService::STATUS_CANCELLED, $rows['Cancel Cili']['status']);
        $this->assertArrayNotHasKey('Kulso Karesz', $rows->all());
    }

    public function test_it_handles_missing_ab_menu_item_without_crashing(): void
    {
        $institution = Institution::create([
            'name' => 'Menu Nelkuli Iskola',
            'institution_code' => 'AB003',
            'type' => 'iskola',
            'active' => true,
        ]);

        $child = $this->createChild($institution->id, 'Napi Nora', '2.B');

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-09-01',
            'valid_to' => null,
        ]);

        $result = app(DailyMealHeadcountService::class)->forDate($institution->id, '2026-09-11');
        $row = $result['rows']->first();

        $this->assertSame(1, $result['stats']['daily_eaters']);
        $this->assertSame(0, $result['stats']['menu_a_count']);
        $this->assertSame(0, $result['stats']['menu_b_count']);
        $this->assertSame(0, $result['stats']['dietary_count']);
        $this->assertSame(DailyMealHeadcountService::STATUS_EATING, $row['status']);
        $this->assertNull($row['effective_menu_category']);
    }

    public function test_child_is_not_counted_after_meal_relationship_valid_to_date(): void
    {
        $institution = Institution::create([
            'name' => 'Lezart Iskola',
            'institution_code' => 'HEAD009',
            'type' => 'iskola',
            'active' => true,
        ]);

        $child = $this->createChild($institution->id, 'Lezart Luca', '2.A');

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-09-01',
            'valid_to' => '2026-09-18',
        ]);

        $result = app(DailyMealHeadcountService::class)->forDate($institution->id, '2026-09-21');
        $row = $result['rows']->first();

        $this->assertSame(0, $result['stats']['daily_eaters']);
        $this->assertSame(0, $result['stats']['active_eaters']);
        $this->assertSame(1, $result['stats']['missing_children']);
        $this->assertSame(DailyMealHeadcountService::STATUS_NO_ACTIVE_MEAL, $row['status']);
    }

    private function createChild(int $institutionId, string $name, string $groupName = '3.A'): Child
    {
        return Child::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId), 0, 10),
            'group_name' => $groupName,
            'school_year' => '2025/2026',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }
}
