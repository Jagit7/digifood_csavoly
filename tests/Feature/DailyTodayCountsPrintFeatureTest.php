<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\InstitutionAdmin\DailyOperationController;
use App\Models\Child;
use App\Models\DietaryRestriction;
use App\Models\EmployeeMealCancellation;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionMealPackage;
use App\Models\MealCancellation;
use App\Models\StudentMealSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyTodayCountsPrintFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_print_groups_all_children_by_class_without_filter(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('TODAYP1', 'iskola');

        $this->createEatingChild($institution->id, 'Anna', '1.a');
        $this->createEatingChild($institution->id, 'Béla', '1.b');

        $this->actingAs($user);
        $html = $this->controller()->printTodayCounts($this->request([
            'date' => '2026-08-11',
        ]))->render();

        $this->assertStringContainsString('NAPI GYERMEKLISTA', $html);
        $this->assertStringContainsString('1.a', $html);
        $this->assertStringContainsString('1.b', $html);
        $this->assertStringContainsString('Anna', $html);
        $this->assertStringContainsString('Béla', $html);
    }

    public function test_school_print_with_group_filter_only_contains_selected_class(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('TODAYP2', 'iskola');

        $this->createEatingChild($institution->id, 'Csilla', '3.a');
        $this->createEatingChild($institution->id, 'Dénes', '3.b');

        $this->actingAs($user);
        $html = $this->controller()->printTodayCounts($this->request([
            'date' => '2026-08-11',
            'group_name' => '3.a',
        ]))->render();

        $this->assertStringContainsString('3.a osztály', $html);
        $this->assertStringContainsString('Csilla', $html);
        $this->assertStringNotContainsString('Dénes', $html);
    }

    public function test_kindergarten_print_with_group_filter_only_contains_selected_group_and_attendance_sheet_still_works(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('TODAYP3', 'ovoda');

        $this->createEatingChild($institution->id, 'Emese', 'Katica');
        $this->createEatingChild($institution->id, 'Feri', 'Maci');

        $this->actingAs($user);
        $printHtml = $this->controller()->printTodayCounts($this->request([
            'date' => '2026-08-11',
            'group_name' => 'Katica',
        ]))->render();
        $attendanceHtml = $this->controller()->printAttendanceSheet($this->request([
            'date' => '2026-08-11',
            'group_name' => 'Katica',
        ]))->render();

        $this->assertStringContainsString('Katica csoport', $printHtml);
        $this->assertStringContainsString('Emese', $printHtml);
        $this->assertStringNotContainsString('Feri', $printHtml);
        $this->assertStringContainsString('Óvodai jelenléti ív', $attendanceHtml);
        $this->assertStringContainsString('Katica', $attendanceHtml);
    }

    public function test_print_respects_status_and_dietary_filters_and_carries_them_in_print_link(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('TODAYP4', 'iskola');
        $restriction = $this->createDietaryRestriction($institution->id, 'Tej');

        $eatingChild = $this->createEatingChild($institution->id, 'Gábor', '4.a');
        $cancelledChild = $this->createEatingChild($institution->id, 'Hanna', '4.a');
        $cancelledChild->dietaryRestrictions()->attach($restriction->id);
        $this->cancelForDate($institution->id, $cancelledChild->id, '2026-08-11');

        $this->actingAs($user);

        $todayCountsView = $this->controller()->todayCounts($this->request([
            'date' => '2026-08-11',
            'group_name' => '4.a',
            'status' => 'cancelled',
            'dietary_filter' => 'dietary',
        ]));

        $printHtml = $this->controller()->printTodayCounts($this->request([
            'date' => '2026-08-11',
            'group_name' => '4.a',
            'status' => 'cancelled',
            'dietary_filter' => 'dietary',
        ]))->render();

        $this->assertSame([
            'date' => '2026-08-11',
            'group_name' => '4.a',
            'status' => 'cancelled',
            'dietary_filter' => 'dietary',
        ], $todayCountsView->getData()['printRouteParameters']);
        $this->assertStringContainsString('Hanna', $printHtml);
        $this->assertStringContainsString('Lemondva', $printHtml);
        $this->assertStringContainsString('Szabályosan lemondva', $printHtml);
        $this->assertStringContainsString('Tej', $printHtml);
        $this->assertStringNotContainsString('Gábor', $printHtml);
    }

    public function test_print_does_not_paginate_and_contains_all_matching_children(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('TODAYP5', 'iskola');

        for ($index = 1; $index <= 18; $index++) {
            $this->createEatingChild($institution->id, sprintf('Tanuló %02d', $index), '5.a');
        }

        $this->actingAs($user);
        $view = $this->controller()->printTodayCounts($this->request([
            'date' => '2026-08-11',
            'group_name' => '5.a',
        ]));
        $html = $view->render();

        $this->assertCount(18, $view->getData()['rows']);
        $this->assertStringContainsString('Tanuló 01', $html);
        $this->assertStringContainsString('Tanuló 18', $html);
    }

    public function test_today_counts_combines_child_and_employee_daily_eaters_but_keeps_child_list(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('TODAYP6', 'iskola');

        $this->createEatingChild($institution->id, 'Anna', '1.a');

        $employee = $this->createEatingEmployee($institution->id, 'Dolgozó Dénes');
        $cancelledEmployee = $this->createEatingEmployee($institution->id, 'Lemondott Elemér');

        EmployeeMealCancellation::create([
            'institution_id' => $institution->id,
            'institution_employee_id' => $cancelledEmployee->id,
            'service_date' => '2026-08-11',
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'status' => EmployeeMealCancellation::STATUS_ACTIVE,
        ]);

        $this->actingAs($user);
        $view = $this->controller()->todayCounts($this->request([
            'date' => '2026-08-11',
        ]));

        $stats = $view->getData()['stats'];
        $rows = $view->getData()['rows'];

        $this->assertSame(1, $stats['child_daily_eaters']);
        $this->assertSame(1, $stats['employee_daily_eaters']);
        $this->assertSame(2, $stats['daily_eaters']);
        $this->assertSame(1, $stats['employee_cancelled_meals']);
        $this->assertSame(1, $rows->total());
        $this->assertSame('dashboard.institution_admin.daily.today-counts', $view->name());
        $this->assertSame('Anna', $rows->items()[0]['child']->name);
    }

    private function controller(): DailyOperationController
    {
        return app(DailyOperationController::class);
    }

    private function request(array $query): Request
    {
        return Request::create('/dashboard/institution-admin/daily/today-counts', 'GET', $query);
    }

    private function seedUserWithInstitution(string $code, string $type): array
    {
        $institution = Institution::create([
            'name' => 'Teszt Intézmény '.$code,
            'institution_code' => $code,
            'type' => $type,
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        InstitutionMealPackage::create([
            'institution_id' => $institution->id,
            'name' => 'Alap csomag',
            'description' => null,
            'is_default' => true,
            'is_active' => true,
            'display_order' => 1,
            'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
        ]);

        return [$institution, $user];
    }

    private function createEatingChild(int $institutionId, string $name, string $groupName): Child
    {
        $child = Child::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId), 0, 10),
            'group_name' => $groupName,
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institutionId,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-08-01',
            'valid_to' => null,
        ]);

        return $child;
    }

    private function createEatingEmployee(int $institutionId, string $name): InstitutionEmployee
    {
        $employee = InstitutionEmployee::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        StudentMealSetting::create([
            'student_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employee->id,
            'institution_id' => $institutionId,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-08-01',
            'valid_to' => null,
        ]);

        return $employee;
    }

    private function createDietaryRestriction(int $institutionId, string $name): DietaryRestriction
    {
        return DietaryRestriction::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'type' => DietaryRestriction::TYPE_ALLERGEN,
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    private function cancelForDate(int $institutionId, int $childId, string $date): void
    {
        MealCancellation::create([
            'institution_id' => $institutionId,
            'child_id' => $childId,
            'service_date' => $date,
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);
    }
}
