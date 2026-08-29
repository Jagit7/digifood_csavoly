<?php

namespace Tests\Feature;

use App\Models\DiscountType;
use App\Models\EmployeeMealCancellation;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionMealType;
use App\Models\InstitutionSetting;
use App\Models\MealCancellation;
use App\Models\MealType;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\EmployeeMealCancellationService;
use App\Services\PaymentObligation\EmployeePaymentObligationCalculatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeMealCancellationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_employee_cancellation_can_be_created_before_cutoff(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->seedInstitutionAdmin('EMPCAN01');
        $employee = $this->createEmployee($institution->id, 'Teszt Dolgozó');
        $context = $this->createMealContext($institution->id, $user->id, 1500);
        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-08', '2026-09-08');

        $cancellation = app(EmployeeMealCancellationService::class)->recordSingle(
            $institution,
            $employee,
            '2026-09-08',
            $user,
            'Betegség'
        );

        $this->assertSame(EmployeeMealCancellation::STATUS_ACTIVE, $cancellation->status);
        $this->assertDatabaseHas('employee_meal_cancellations', [
            'institution_id' => $institution->id,
            'institution_employee_id' => $employee->id,
            'service_date' => '2026-09-08 00:00:00',
            'status' => EmployeeMealCancellation::STATUS_ACTIVE,
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseCount('meal_cancellations', 0);
    }

    public function test_same_employee_same_day_cannot_be_cancelled_twice_but_another_employee_can_cancel_the_same_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->seedInstitutionAdmin('EMPCAN02');
        $context = $this->createMealContext($institution->id, $user->id, 1200);
        $firstEmployee = $this->createEmployee($institution->id, 'Első Dolgozó');
        $secondEmployee = $this->createEmployee($institution->id, 'Második Dolgozó');

        foreach ([$firstEmployee, $secondEmployee] as $employee) {
            $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-08', '2026-09-08');
        }

        $service = app(EmployeeMealCancellationService::class);
        $service->recordSingle($institution, $firstEmployee, '2026-09-08', $user, null);
        $service->recordSingle($institution, $secondEmployee, '2026-09-08', $user, null);

        try {
            $service->recordSingle($institution, $firstEmployee, '2026-09-08', $user, null);
            $this->fail('ValidationException kivételre számítottunk duplikált lemondásnál.');
        } catch (ValidationException $exception) {
            $this->assertSame('Erre a napra már létezik aktív dolgozói lemondás.', $exception->errors()['service_date'][0] ?? null);
        }

        $this->assertDatabaseCount('employee_meal_cancellations', 2);
    }

    public function test_foreign_institution_employee_cannot_be_modified(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->seedInstitutionAdmin('EMPCAN03');
        [$otherInstitution, $otherUser] = $this->seedInstitutionAdmin('EMPCAN03X');
        $context = $this->createMealContext($otherInstitution->id, $otherUser->id, 1300);
        $foreignEmployee = $this->createEmployee($otherInstitution->id, 'Idegen Dolgozó');
        $this->assignEmployeeMealSetting($otherInstitution->id, $foreignEmployee->id, $otherUser->id, $context['package']->id, '2026-09-08', '2026-09-08');

        try {
            app(EmployeeMealCancellationService::class)->recordSingle(
                $institution,
                $foreignEmployee,
                '2026-09-08',
                $user,
                null
            );
            $this->fail('ValidationException kivételre számítottunk idegen intézmény dolgozójánál.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'A kiválasztott dolgozó nem az aktuális intézményhez tartozik.',
                $exception->errors()['institution_employee_id'][0] ?? null
            );
        }

        $this->assertDatabaseCount('employee_meal_cancellations', 0);
    }

    public function test_employee_cancellation_can_be_restored(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->seedInstitutionAdmin('EMPCAN04');
        $employee = $this->createEmployee($institution->id, 'Visszaállítható Dolgozó');
        $context = $this->createMealContext($institution->id, $user->id, 1100);
        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-08', '2026-09-08');

        $cancellation = EmployeeMealCancellation::create([
            'institution_id' => $institution->id,
            'institution_employee_id' => $employee->id,
            'service_date' => '2026-09-08',
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'status' => EmployeeMealCancellation::STATUS_ACTIVE,
            'created_by' => $user->id,
        ]);

        app(EmployeeMealCancellationService::class)->revoke($cancellation->fresh(['employee', 'institution']), $user);

        $this->assertDatabaseHas('employee_meal_cancellations', [
            'id' => $cancellation->id,
            'status' => EmployeeMealCancellation::STATUS_REVOKED,
            'revoked_by' => $user->id,
        ]);
    }

    public function test_employee_cancellation_is_blocked_after_cutoff(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:31:00', 'Europe/Budapest'));
        [$institution, $user] = $this->seedInstitutionAdmin('EMPCAN05');
        $employee = $this->createEmployee($institution->id, 'Késő Dolgozó');
        $context = $this->createMealContext($institution->id, $user->id, 1000);
        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-08', '2026-09-08');

        try {
            app(EmployeeMealCancellationService::class)->recordSingle(
                $institution,
                $employee,
                '2026-09-08',
                $user,
                null
            );
            $this->fail('ValidationException kivételre számítottunk cutoff után.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'A lemondási határidő lejárt.',
                $exception->errors()['service_date'][0] ?? ''
            );
        }

        $this->assertDatabaseCount('employee_meal_cancellations', 0);
    }

    public function test_cancelled_employee_day_becomes_zero_forint_in_statement_calculation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->seedInstitutionAdmin('EMPCAN06');
        $employee = $this->createEmployee($institution->id, 'Nullás Dolgozó');
        $context = $this->createMealContext($institution->id, $user->id, 1750);
        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-08', '2026-09-08');

        EmployeeMealCancellation::create([
            'institution_id' => $institution->id,
            'institution_employee_id' => $employee->id,
            'service_date' => '2026-09-08',
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'status' => EmployeeMealCancellation::STATUS_ACTIVE,
            'created_by' => $user->id,
        ]);

        app(EmployeePaymentObligationCalculatorService::class)
            ->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $statement = EmployeeMonthlyPaymentStatement::query()
            ->with('days')
            ->firstOrFail();
        $day = $statement->days->first(fn ($item) => $item->date?->toDateString() === '2026-09-08');

        $this->assertSame(0, $statement->meal_amount);
        $this->assertSame(0, $statement->invoiceable_amount);
        $this->assertSame('cancelled', $day?->status);
        $this->assertSame(0, $day?->payable_amount);
        $this->assertNotNull($day?->employee_meal_cancellation_id);
    }

    public function test_non_cancelled_employee_day_remains_payable(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->seedInstitutionAdmin('EMPCAN07');
        $employee = $this->createEmployee($institution->id, 'Fizetős Dolgozó');
        $context = $this->createMealContext($institution->id, $user->id, 1650);
        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-08', '2026-09-08');

        app(EmployeePaymentObligationCalculatorService::class)
            ->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $statement = EmployeeMonthlyPaymentStatement::query()
            ->with('days')
            ->firstOrFail();
        $day = $statement->days->first(fn ($item) => $item->date?->toDateString() === '2026-09-08');

        $this->assertSame(1650, $statement->meal_amount);
        $this->assertSame(1650, $statement->invoiceable_amount);
        $this->assertSame('payable', $day?->status);
        $this->assertSame(1650, $day?->payable_amount);
    }

    public function test_closed_statement_period_cannot_be_modified_by_new_employee_cancellation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 09:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->seedInstitutionAdmin('EMPCAN08');
        $employee = $this->createEmployee($institution->id, 'Lezárt Dolgozó');
        $context = $this->createMealContext($institution->id, $user->id, 1550);
        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-08', '2026-09-08');

        $calculator = app(EmployeePaymentObligationCalculatorService::class);
        $calculator->recalculateMonth($institution, Carbon::create(2026, 8, 1));
        $calculator->closeMonth($institution, Carbon::create(2026, 8, 1), $user);

        $statement = EmployeeMonthlyPaymentStatement::query()->firstOrFail();

        try {
            app(EmployeeMealCancellationService::class)->recordSingle(
                $institution,
                $employee,
                '2026-09-08',
                $user,
                null
            );
            $this->fail('ValidationException kivételre számítottunk lezárt statement esetén.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Ehhez a naphoz tartozó dolgozói pénzügyi időszak már le van zárva, ezért a lemondás nem módosítható.',
                $exception->errors()['service_date'][0] ?? null
            );
        }

        $this->assertDatabaseCount('employee_meal_cancellations', 0);
        $this->assertSame(EmployeeMonthlyPaymentStatement::STATUS_CLOSED, $statement->fresh()->status);
        $this->assertSame(1550, $statement->fresh()->meal_amount);
    }

    private function seedInstitutionAdmin(string $code, int $paymentDueDay = 5): array
    {
        $institution = Institution::create([
            'name' => 'Dolgozói Intézmény '.$code,
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

        InstitutionSetting::create(array_merge(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults(),
            ['payment_due_day' => $paymentDueDay]
        ));

        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 9,
            'cancellation_minute' => 30,
        ]);

        return [$institution, $user];
    }

    private function createEmployee(int $institutionId, string $name, ?string $email = null): InstitutionEmployee
    {
        $discount = DiscountType::firstOrCreate(
            [
                'institution_id' => $institutionId,
                'name' => 'Kedvezmény nélkül',
                'percentage' => 0,
            ],
            [
                'active' => true,
                'sort_order' => 1,
            ]
        );

        return InstitutionEmployee::create([
            'institution_id' => $institutionId,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'email' => $email,
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function createMealContext(int $institutionId, int $userId, int $price): array
    {
        $mealType = MealType::create([
            'code' => 'employee-cancel-'.$institutionId.'-'.$price,
            'name' => 'Ebéd',
            'default_order' => 1,
        ]);

        $institutionMealType = InstitutionMealType::create([
            'institution_id' => $institutionId,
            'meal_type_id' => $mealType->id,
            'is_active' => true,
            'is_parent_selectable' => false,
            'is_required' => true,
            'display_order' => 1,
        ]);

        InstitutionMealPrice::create([
            'institution_meal_type_id' => $institutionMealType->id,
            'price' => $price,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institutionId,
            'name' => 'Dolgozói csomag '.$price,
            'description' => null,
            'is_active' => true,
            'is_default' => true,
            'display_order' => 1,
            'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
            'created_by' => $userId,
        ]);

        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionMealType->id,
            'display_order' => 1,
        ]);

        return [
            'meal_type' => $mealType,
            'institution_meal_type' => $institutionMealType,
            'package' => $package,
        ];
    }

    private function assignEmployeeMealSetting(
        int $institutionId,
        int $employeeId,
        int $userId,
        int $mealPackageId,
        string $validFrom,
        ?string $validTo
    ): void {
        StudentMealSetting::create([
            'student_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employeeId,
            'institution_id' => $institutionId,
            'institution_meal_package_id' => $mealPackageId,
            'mode' => StudentMealSetting::MODE_PACKAGE,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'created_by' => $userId,
        ]);
    }
}
