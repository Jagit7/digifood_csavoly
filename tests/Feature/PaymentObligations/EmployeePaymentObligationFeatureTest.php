<?php

namespace Tests\Feature\PaymentObligations;

use App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation\EmployeePaymentObligationController;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\EmployeePaymentObligationIndexRequest;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionMealType;
use App\Models\InstitutionSetting;
use App\Models\MealType;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentDay;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\PaymentObligation\EmployeePaymentObligationCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EmployeePaymentObligationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_monthly_statement_is_created_with_expected_amount_and_due_date(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMPST01', 12);
        $context = $this->createMealContext($institution->id, $user->id, 1500);
        $employee = $this->createEmployee($institution->id, 'Teszt Dolgozó');

        $this->assignEmployeeMealSetting(
            institutionId: $institution->id,
            employeeId: $employee->id,
            userId: $user->id,
            mealPackageId: $context['package']->id,
            validFrom: '2026-09-10',
            validTo: '2026-09-10'
        );

        $result = app(EmployeePaymentObligationCalculatorService::class)
            ->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $statement = EmployeeMonthlyPaymentStatement::query()
            ->with('days')
            ->firstOrFail();

        $this->assertSame(1, $result['employees']);
        $this->assertSame($institution->id, $statement->institution_id);
        $this->assertSame($employee->id, $statement->institution_employee_id);
        $this->assertSame(2026, $statement->year);
        $this->assertSame(8, $statement->month);
        $this->assertSame(1500, $statement->meal_amount);
        $this->assertSame(1500, $statement->invoiceable_amount);
        $this->assertSame(1500, $statement->total_payable);
        $this->assertSame('2026-08-12', $statement->due_date?->toDateString());
        $this->assertSame(1, $statement->days->where('payable_amount', '>', 0)->count());
    }

    public function test_recalculation_does_not_create_duplicate_employee_statement_for_same_month(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMPST02');
        $context = $this->createMealContext($institution->id, $user->id, 1200);
        $employee = $this->createEmployee($institution->id, 'Dupla Dolgozó');

        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-10', '2026-09-10');

        $service = app(EmployeePaymentObligationCalculatorService::class);
        $service->recalculateMonth($institution, Carbon::create(2026, 8, 1));
        $service->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $this->assertDatabaseCount('employee_monthly_payment_statements', 1);
    }

    public function test_multiple_employees_receive_separate_statements_and_do_not_mix_with_child_statements(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMPST03');
        $context = $this->createMealContext($institution->id, $user->id, 990);
        $first = $this->createEmployee($institution->id, 'Első Dolgozó');
        $second = $this->createEmployee($institution->id, 'Második Dolgozó');

        $this->assignEmployeeMealSetting($institution->id, $first->id, $user->id, $context['package']->id, '2026-09-10', '2026-09-10');
        $this->assignEmployeeMealSetting($institution->id, $second->id, $user->id, $context['package']->id, '2026-09-11', '2026-09-11');

        app(EmployeePaymentObligationCalculatorService::class)
            ->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $this->assertDatabaseCount('employee_monthly_payment_statements', 2);
        $this->assertDatabaseCount('monthly_payment_statements', 0);
    }

    public function test_employee_statement_generation_is_isolated_per_institution(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMPST04');
        [$otherInstitution, $otherUser] = $this->seedInstitutionAdmin('EMPST04X');
        $context = $this->createMealContext($institution->id, $user->id, 1100);
        $otherContext = $this->createMealContext($otherInstitution->id, $otherUser->id, 1300);
        $employee = $this->createEmployee($institution->id, 'Saját Dolgozó');
        $foreignEmployee = $this->createEmployee($otherInstitution->id, 'Idegen Dolgozó');

        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-10', '2026-09-10');
        $this->assignEmployeeMealSetting($otherInstitution->id, $foreignEmployee->id, $otherUser->id, $otherContext['package']->id, '2026-09-10', '2026-09-10');

        app(EmployeePaymentObligationCalculatorService::class)
            ->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $this->assertDatabaseCount('employee_monthly_payment_statements', 1);
        $this->assertDatabaseHas('employee_monthly_payment_statements', [
            'institution_id' => $institution->id,
            'institution_employee_id' => $employee->id,
        ]);
    }

    public function test_employee_statement_can_be_closed_and_reopened(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMPST05');
        $context = $this->createMealContext($institution->id, $user->id, 1000);
        $employee = $this->createEmployee($institution->id, 'Lezárható Dolgozó');

        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-10', '2026-09-10');

        $service = app(EmployeePaymentObligationCalculatorService::class);
        $service->recalculateMonth($institution, Carbon::create(2026, 8, 1));
        $close = $service->closeMonth($institution, Carbon::create(2026, 8, 1), $user);

        $statement = EmployeeMonthlyPaymentStatement::query()->firstOrFail();
        $this->assertSame(1, $close['closed']);
        $this->assertSame(EmployeeMonthlyPaymentStatement::STATUS_CLOSED, $statement->fresh()->status);

        $reopened = $service->reopenMonth($institution, Carbon::create(2026, 8, 1), $user, 'Teszt újranyitás');
        $this->assertSame(1, $reopened);
        $this->assertSame(EmployeeMonthlyPaymentStatement::STATUS_DRAFT, $statement->fresh()->status);
    }

    public function test_employee_payment_obligations_admin_page_renders_created_statement(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMPST06');
        $context = $this->createMealContext($institution->id, $user->id, 1450);
        $employee = $this->createEmployee($institution->id, 'Admin Oldal Dolgozó', 'admin-oldal@example.com');

        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-09-10', '2026-09-10');

        app(EmployeePaymentObligationCalculatorService::class)
            ->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $this->actingAs($user);

        $baseRequest = Request::create(
            route('dashboard.institution.employee-payment-obligations.index', ['month' => '2026-08']),
            'GET',
            ['month' => '2026-08']
        );
        $baseRequest->setUserResolver(fn () => $user);
        $request = EmployeePaymentObligationIndexRequest::createFromBase($baseRequest);
        $request->setUserResolver(fn () => $user);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setValidator(Validator::make($request->all(), $request->rules()));

        $response = app(EmployeePaymentObligationController::class)->index($request);

        $this->assertSame('dashboard.institution_admin.employee_payment_obligations.index', $response->name());
        $data = $response->getData();
        $this->assertSame('Dolgozói Intézmény EMPST06', $data['institution']->name);
        $this->assertSame(1, $data['statements']->count());
        $this->assertSame('Admin Oldal Dolgozó', $data['statements']->first()->employee->name);
        $this->assertSame('admin-oldal@example.com', $data['statements']->first()->employee->email);
        $this->assertSame(1450, $data['statements']->first()->total_payable);
    }

    /**
     * Regresszió teszt arra a hibára, hogy a havi elszámolás az étkezési
     * hónap utolsó napja UTÁN egy plusz napot (a következő hónap 1-jét)
     * is beleszámolt az étkezési napok közé - lásd
     * EmployeePaymentObligationCalculatorService::calculateEmployeeMonth()
     * korábbi ->addDay() hívását. Ugyanaz a hiba, mint a gyermek oldali
     * PaymentObligationCalculatorService-ben.
     */
    public function test_recalculate_month_does_not_include_first_day_of_next_month(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMPST07');
        $context = $this->createMealContext($institution->id, $user->id, 1000);
        $employee = $this->createEmployee($institution->id, 'Egész Hónapos Dolgozó');

        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-01-01', null);

        app(EmployeePaymentObligationCalculatorService::class)
            ->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $statement = EmployeeMonthlyPaymentStatement::query()
            ->with('days')
            ->firstOrFail();

        $dates = $statement->days->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->sort()
            ->values();

        $this->assertCount(30, $dates);
        $this->assertSame('2026-09-01', $dates->first());
        $this->assertSame('2026-09-30', $dates->last());
        $this->assertNotContains('2026-10-01', $dates->all());
    }

    /**
     * Lásd PaymentObligationFeatureTest::
     * test_recalculate_month_removes_stale_day_left_over_from_previous_wider_range()
     * megjegyzését: egy korábbi (hibás záró dátumú) számításból
     * visszamaradt, az új dátumtartományon kívül eső napi rekordot az
     * újraszámolásnak törölnie kell a dolgozói oldalon is.
     */
    public function test_recalculate_month_removes_stale_employee_day_left_over_from_previous_wider_range(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMPST08');
        $context = $this->createMealContext($institution->id, $user->id, 1000);
        $employee = $this->createEmployee($institution->id, 'Takarítandó Dolgozó');

        $this->assignEmployeeMealSetting($institution->id, $employee->id, $user->id, $context['package']->id, '2026-01-01', null);

        app(EmployeePaymentObligationCalculatorService::class)
            ->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $statement = EmployeeMonthlyPaymentStatement::query()->firstOrFail();
        $countBeforeStaleDay = $statement->days()->where('payable_amount', '>', 0)->count();

        // Szimuláljuk a korábbi hibás állapotot: egy 2026-10-01-i napi
        // rekord, ami a régi (->addDay()-es) számításból maradt ott.
        EmployeeMonthlyPaymentDay::create([
            'employee_monthly_payment_statement_id' => $statement->id,
            'date' => '2026-10-01',
            'status' => EmployeeMonthlyPaymentDay::STATUS_PAYABLE,
            'original_daily_price' => 1000,
            'discount_percent' => 0,
            'payable_amount' => 1000,
        ]);

        $statement->refresh();
        $this->assertSame($countBeforeStaleDay + 1, $statement->days()->where('payable_amount', '>', 0)->count());

        app(EmployeePaymentObligationCalculatorService::class)
            ->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $statement->refresh();
        $dates = $statement->days()->get()->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->sort()
            ->values();

        $this->assertNotContains('2026-10-01', $dates->all());
        $this->assertSame($countBeforeStaleDay, $statement->days()->where('payable_amount', '>', 0)->count());
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
            'code' => 'employee-lunch-'.$institutionId.'-'.$price,
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
