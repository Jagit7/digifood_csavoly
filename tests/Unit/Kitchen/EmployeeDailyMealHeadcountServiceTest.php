<?php

namespace Tests\Unit\Kitchen;

use App\Models\DietaryRestriction;
use App\Models\EmployeeMealCancellation;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\StudentMealSetting;
use App\Services\Kitchen\EmployeeDailyMealHeadcountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDailyMealHeadcountServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_only_active_employees_with_an_active_meal_setting(): void
    {
        $institution = $this->createInstitution('EMPHEAD01');
        $employee = $this->createEmployee($institution->id, 'Aktív Dolgozó');
        $inactive = $this->createEmployee($institution->id, 'Inaktív Dolgozó', active: false);
        $missing = $this->createEmployee($institution->id, 'Beállítás Nélküli');

        $this->assignEmployeeMealSetting($institution->id, $employee->id);
        $this->assignEmployeeMealSetting($institution->id, $inactive->id);

        $result = app(EmployeeDailyMealHeadcountService::class)->forDate($institution->id, '2026-09-10');
        $rows = $result['rows']->mapWithKeys(fn (array $row) => [$row['employee']->name => $row]);

        $this->assertSame(1, $result['stats']['daily_eaters']);
        $this->assertSame(1, $result['stats']['active_eaters']);
        $this->assertSame(1, $result['stats']['missing_employees']);
        $this->assertArrayHasKey('Aktív Dolgozó', $rows->all());
        $this->assertArrayHasKey('Beállítás Nélküli', $rows->all());
        $this->assertArrayNotHasKey('Inaktív Dolgozó', $rows->all());
        $this->assertSame(EmployeeDailyMealHeadcountService::STATUS_EATING, $rows['Aktív Dolgozó']['status']);
        $this->assertSame(EmployeeDailyMealHeadcountService::STATUS_NO_ACTIVE_MEAL, $rows['Beállítás Nélküli']['status']);
    }

    public function test_active_employee_cancellation_excludes_employee_and_revoked_cancellation_restores_counting(): void
    {
        $institution = $this->createInstitution('EMPHEAD02');
        $cancelled = $this->createEmployee($institution->id, 'Lemondott Dolgozó');
        $restored = $this->createEmployee($institution->id, 'Visszaállított Dolgozó');

        $this->assignEmployeeMealSetting($institution->id, $cancelled->id);
        $this->assignEmployeeMealSetting($institution->id, $restored->id);

        EmployeeMealCancellation::create([
            'institution_id' => $institution->id,
            'institution_employee_id' => $cancelled->id,
            'service_date' => '2026-09-10',
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'status' => EmployeeMealCancellation::STATUS_ACTIVE,
        ]);
        EmployeeMealCancellation::create([
            'institution_id' => $institution->id,
            'institution_employee_id' => $restored->id,
            'service_date' => '2026-09-10',
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'status' => EmployeeMealCancellation::STATUS_REVOKED,
        ]);

        $result = app(EmployeeDailyMealHeadcountService::class)->forDate($institution->id, '2026-09-10');
        $rows = $result['rows']->mapWithKeys(fn (array $row) => [$row['employee']->name => $row]);

        $this->assertSame(1, $result['stats']['daily_eaters']);
        $this->assertSame(1, $result['stats']['cancelled_meals']);
        $this->assertSame(EmployeeDailyMealHeadcountService::STATUS_CANCELLED, $rows['Lemondott Dolgozó']['status']);
        $this->assertSame(EmployeeDailyMealHeadcountService::STATUS_EATING, $rows['Visszaállított Dolgozó']['status']);
    }

    public function test_it_counts_dietary_employee_and_ignores_other_institution_employee(): void
    {
        $institution = $this->createInstitution('EMPHEAD03');
        $otherInstitution = $this->createInstitution('EMPHEAD04');
        $restriction = DietaryRestriction::create([
            'institution_id' => $institution->id,
            'name' => 'Laktóz',
            'type' => DietaryRestriction::TYPE_INTOLERANCE,
            'active' => true,
            'sort_order' => 1,
        ]);

        $employee = $this->createEmployee($institution->id, 'Diétás Dolgozó');
        $employee->dietaryRestrictions()->attach($restriction->id);
        $this->assignEmployeeMealSetting($institution->id, $employee->id);

        $foreign = $this->createEmployee($otherInstitution->id, 'Másik Intézményes');
        $this->assignEmployeeMealSetting($otherInstitution->id, $foreign->id);

        $result = app(EmployeeDailyMealHeadcountService::class)->forDate($institution->id, '2026-09-10');

        $this->assertSame(1, $result['stats']['daily_eaters']);
        $this->assertSame(1, $result['stats']['dietary_eaters']);
        $this->assertSame(['Diétás Dolgozó'], $result['rows']->pluck('employee.name')->all());
    }

    private function createInstitution(string $code): Institution
    {
        return Institution::create([
            'name' => 'Dolgozói Fejteszt '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);
    }

    private function createEmployee(int $institutionId, string $name, bool $active = true): InstitutionEmployee
    {
        return InstitutionEmployee::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'source_type' => 'manual',
            'active' => $active,
        ]);
    }

    private function assignEmployeeMealSetting(int $institutionId, int $employeeId): void
    {
        StudentMealSetting::create([
            'institution_id' => $institutionId,
            'student_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employeeId,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-09-01',
            'valid_to' => null,
        ]);
    }
}
