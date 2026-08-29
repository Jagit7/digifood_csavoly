<?php

namespace Tests\Unit\PaymentObligations;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionPaymentComponentRate;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionMealType;
use App\Models\InstitutionSetting;
use App\Models\MealCancellation;
use App\Models\MealType;
use App\Models\PaymentObligation\FinancialAdjustment;
use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Models\WorkingDay;
use App\Services\Finance\InstitutionPaymentComponentService;
use App\Support\Finance\PaymentComponent;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentObligationCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_full_price_day_is_payable(): void
    {
        [$institution, $child] = $this->seedBasicParticipant(0);
        $service = app(PaymentObligationCalculatorService::class);

        $service->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $statement = $child->monthlyPaymentStatements()->firstOrFail();
        $day = $statement->days()->whereDate('date', '2026-08-03')->firstOrFail();

        $this->assertSame(MonthlyPaymentDay::STATUS_PAY, $day->status);
        $this->assertSame(1000, $day->payable_amount);
    }

    public function test_fifty_percent_discount_is_applied(): void
    {
        [$institution, $child] = $this->seedBasicParticipant(50);
        $service = app(PaymentObligationCalculatorService::class);

        $service->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $day = $child->monthlyPaymentStatements()->firstOrFail()
            ->days()
            ->whereDate('date', '2026-08-03')
            ->firstOrFail();

        $this->assertSame(500, $day->payable_amount);
    }

    public function test_hundred_percent_discount_becomes_free_meal(): void
    {
        [$institution, $child] = $this->seedBasicParticipant(100);
        $service = app(PaymentObligationCalculatorService::class);

        $service->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $day = $child->monthlyPaymentStatements()->firstOrFail()
            ->days()
            ->whereDate('date', '2026-08-03')
            ->firstOrFail();

        $this->assertSame(MonthlyPaymentDay::STATUS_FREE_MEAL, $day->status);
        $this->assertSame(0, $day->payable_amount);
    }

    public function test_advance_cancellation_sets_zero_amount(): void
    {
        [$institution, $child, $user] = $this->seedBasicParticipant(0);
        $cancellation = MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-08-04',
            'source' => 'admin',
            'status' => 'active',
            'reason' => 'Teszt lemondás',
            'created_by' => $user->id,
        ]);
        $cancellation->forceFill([
            'created_at' => '2026-08-01 09:00:00',
            'updated_at' => '2026-08-01 09:00:00',
        ])->save();

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $day = $child->monthlyPaymentStatements()->firstOrFail()
            ->days()
            ->whereDate('date', '2026-08-04')
            ->firstOrFail();

        $this->assertSame(MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE, $day->status);
        $this->assertSame(0, $day->payable_amount);
    }

    public function test_advance_cancellation_uses_existing_cancellation_deadline_instead_of_payment_due_day(): void
    {
        [$institution, $child, $user] = $this->seedBasicParticipant(0);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            ['payment_due_day' => 1]
        );

        $cancellation = MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-09-07',
            'source' => 'admin',
            'status' => 'active',
            'reason' => 'Határidő előtti lemondás',
            'created_by' => $user->id,
        ]);
        $cancellation->forceFill([
            'created_at' => '2026-09-03 12:00:00',
            'updated_at' => '2026-09-03 12:00:00',
        ])->save();

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $day = $child->monthlyPaymentStatements()
            ->where('year', 2026)
            ->where('month', 8)
            ->firstOrFail()
            ->days()
            ->whereDate('date', '2026-09-07')
            ->firstOrFail();

        $this->assertSame(MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE, $day->status);
        $this->assertSame(0, $day->payable_amount);
        $this->assertDatabaseMissing('financial_adjustments', [
            'child_id' => $child->id,
            'type' => FinancialAdjustment::TYPE_CANCELLATION_CREDIT,
            'source_id' => $cancellation->id,
        ]);
    }

    public function test_late_cancellation_creates_credit_only_once_for_next_month(): void
    {
        [$institution, $child, $user] = $this->seedBasicParticipant(0);
        InstitutionSetting::updateOrCreate(['institution_id' => $institution->id], ['payment_due_day' => 1]);

        $cancellation = MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-08-10',
            'source' => 'admin',
            'status' => 'active',
            'reason' => 'Késői lemondás',
            'created_by' => $user->id,
        ]);
        $cancellation->forceFill([
            'created_at' => '2026-08-07 09:00:00',
            'updated_at' => '2026-08-07 09:00:00',
        ])->save();

        $service = app(PaymentObligationCalculatorService::class);
        $service->recalculateMonth($institution, Carbon::create(2026, 7, 1));
        $service->recalculateMonth($institution, Carbon::create(2026, 9, 1));
        $service->recalculateMonth($institution, Carbon::create(2026, 9, 1));

        $statement = $child->monthlyPaymentStatements()->where('year', 2026)->where('month', 9)->firstOrFail();
        $credit = FinancialAdjustment::query()
            ->where('institution_id', $institution->id)
            ->where('child_id', $child->id)
            ->where('type', FinancialAdjustment::TYPE_CANCELLATION_CREDIT)
            ->get();

        $this->assertCount(1, $credit);
        $this->assertSame(1000, $statement->previous_cancellation_credit);
    }

    public function test_statement_month_stays_payment_month_while_days_use_next_meal_month(): void
    {
        [$institution, $child] = $this->seedBasicParticipant(0);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 10, 1));

        $statement = $child->monthlyPaymentStatements()->where('year', 2026)->where('month', 10)->firstOrFail();

        $this->assertSame(2026, $statement->year);
        $this->assertSame(10, $statement->month);
        $this->assertSame('2026-11-01', $statement->days()->firstOrFail()->date->toDateString());
    }

    public function test_school_break_and_working_saturday_are_handled(): void
    {
        [$institution, $child] = $this->seedBasicParticipant(0);

        SchoolBreak::create([
            'institution_id' => $institution->id,
            'title' => 'Nyári szünet',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'type' => 'school_break',
        ]);

        WorkingDay::create([
            'institution_id' => $institution->id,
            'date' => '2026-08-08',
            'name' => 'Ledolgozós szombat',
            'type' => 'extra_working_day',
        ]);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $statement = $child->monthlyPaymentStatements()->firstOrFail();
        $workingSaturday = $statement->days()->whereDate('date', '2026-08-08')->firstOrFail();
        $breakDay = $statement->days()->whereDate('date', '2026-08-10')->firstOrFail();

        $this->assertSame(MonthlyPaymentDay::STATUS_WORKING_SATURDAY, $workingSaturday->status);
        $this->assertSame(1000, $workingSaturday->payable_amount);
        $this->assertSame(MonthlyPaymentDay::STATUS_SCHOOL_BREAK, $breakDay->status);
        $this->assertSame(0, $breakDay->payable_amount);
    }

    public function test_weekend_without_price_does_not_create_missing_price_issue(): void
    {
        [$institution, $child] = $this->seedBasicParticipant(0);

        InstitutionMealPrice::query()->update(['valid_to' => '2026-08-07']);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $statement = $child->monthlyPaymentStatements()->firstOrFail();
        $weekendDay = $statement->days()->whereDate('date', '2026-08-09')->firstOrFail();

        $this->assertSame(MonthlyPaymentDay::STATUS_WEEKEND, $weekendDay->status);
        $this->assertFalse(collect($statement->issues ?? [])->contains(fn (string $issue) => str_contains($issue, '2026.08.09.')));
    }

    public function test_school_break_without_price_does_not_create_missing_price_issue(): void
    {
        [$institution, $child] = $this->seedBasicParticipant(0);

        InstitutionMealPrice::query()->update(['valid_to' => '2026-08-09']);

        SchoolBreak::create([
            'institution_id' => $institution->id,
            'title' => 'Rendkívüli szünet',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'type' => 'school_break',
        ]);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $statement = $child->monthlyPaymentStatements()->firstOrFail();
        $breakDay = $statement->days()->whereDate('date', '2026-08-10')->firstOrFail();

        $this->assertSame(MonthlyPaymentDay::STATUS_SCHOOL_BREAK, $breakDay->status);
        $this->assertFalse(collect($statement->issues ?? [])->contains(fn (string $issue) => str_contains($issue, '2026.08.10.')));
    }

    public function test_working_saturday_without_price_creates_missing_price_issue(): void
    {
        [$institution, $child] = $this->seedBasicParticipant(0);

        InstitutionMealPrice::query()->update(['valid_to' => '2026-08-07']);

        WorkingDay::create([
            'institution_id' => $institution->id,
            'date' => '2026-08-08',
            'name' => 'Ledolgozós szombat',
            'type' => 'extra_working_day',
        ]);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $statement = $child->monthlyPaymentStatements()->firstOrFail();
        $workingSaturday = $statement->days()->whereDate('date', '2026-08-08')->firstOrFail();

        $this->assertSame(MonthlyPaymentDay::STATUS_NO_VALID_PRICE, $workingSaturday->status);
        $this->assertTrue(collect($statement->issues ?? [])->contains(fn (string $issue) => str_contains($issue, '2026.08.08.')));
    }

    public function test_consecutive_missing_price_days_are_grouped_into_ranges(): void
    {
        [$institution, $child] = $this->seedBasicParticipant(0);

        InstitutionMealPrice::query()->update(['valid_to' => '2026-08-14']);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $statement = $child->monthlyPaymentStatements()->firstOrFail();
        $issues = collect($statement->issues ?? []);

        $this->assertTrue($issues->contains(fn (string $issue) => str_contains($issue, '2026.08.17.–2026.08.21. között.')));
        $this->assertFalse($issues->contains(fn (string $issue) => str_contains($issue, '2026.08.15.')));
        $this->assertFalse($issues->contains(fn (string $issue) => str_contains($issue, '2026.08.16.')));
    }

    public function test_august_payment_month_uses_september_meals_and_july_credits_only(): void
    {
        [$institution, $child, $user] = $this->seedBasicParticipant(0);
        $service = app(PaymentObligationCalculatorService::class);

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-07-15',
            'source' => 'admin',
            'status' => 'active',
            'reason' => 'Júliusi késői lemondás',
            'created_by' => $user->id,
            'created_at' => '2026-07-20 09:00:00',
            'updated_at' => '2026-07-20 09:00:00',
        ]);

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-06-12',
            'source' => 'admin',
            'status' => 'active',
            'reason' => 'Régi késői lemondás',
            'created_by' => $user->id,
            'created_at' => '2026-06-20 09:00:00',
            'updated_at' => '2026-06-20 09:00:00',
        ]);

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-08-12',
            'source' => 'admin',
            'status' => 'active',
            'reason' => 'Augusztusi késői lemondás',
            'created_by' => $user->id,
            'created_at' => '2026-08-20 09:00:00',
            'updated_at' => '2026-08-20 09:00:00',
        ]);

        $service->recalculateMonth($institution, Carbon::create(2026, 5, 1));
        $service->recalculateMonth($institution, Carbon::create(2026, 6, 1));
        $service->recalculateMonth($institution, Carbon::create(2026, 7, 1));
        $service->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $statement = $child->monthlyPaymentStatements()->where('year', 2026)->where('month', 8)->firstOrFail();

        $this->assertTrue($statement->days->contains(fn (MonthlyPaymentDay $day) => $day->date->isSameDay('2026-09-01')));
        $this->assertFalse($statement->days->contains(fn (MonthlyPaymentDay $day) => $day->date->month === 8));
        $this->assertSame(1000, $statement->previous_cancellation_credit);
        $this->assertDatabaseMissing('financial_adjustments', [
            'child_id' => $child->id,
            'type' => FinancialAdjustment::TYPE_CANCELLATION_CREDIT,
            'reference_year' => 2026,
            'reference_month' => 8,
            'reason' => 'Késői lemondás jóváírása: 2026.06.12.',
        ]);
        $this->assertDatabaseMissing('financial_adjustments', [
            'child_id' => $child->id,
            'type' => FinancialAdjustment::TYPE_CANCELLATION_CREDIT,
            'reference_year' => 2026,
            'reference_month' => 8,
            'reason' => 'Késői lemondás jóváírása: 2026.08.12.',
        ]);
    }

    public function test_first_recalculation_immediately_includes_previous_month_credit(): void
    {
        [$institution, $child, $user] = $this->seedBasicParticipant(0);
        $service = app(PaymentObligationCalculatorService::class);

        $cancellation = MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-09-07',
            'source' => 'admin',
            'status' => 'active',
            'reason' => 'Szeptemberi késői lemondás',
            'created_by' => $user->id,
        ]);
        $cancellation->forceFill([
            'created_at' => '2026-09-20 09:00:00',
            'updated_at' => '2026-09-20 09:00:00',
        ])->save();

        $service->recalculateMonth($institution, Carbon::create(2026, 8, 1));
        $service->recalculateMonth($institution, Carbon::create(2026, 10, 1));

        $statement = $child->monthlyPaymentStatements()->where('year', 2026)->where('month', 10)->firstOrFail();

        $this->assertSame(1000, $statement->previous_cancellation_credit);
        $this->assertSame(
            $statement->invoiceable_amount + $statement->previous_balance,
            $statement->total_payable
        );
    }

    public function test_august_payment_month_counts_meals_only_until_closed_valid_to_date(): void
    {
        [$institution, $child] = $this->seedBasicParticipant(0);

        $setting = StudentMealSetting::query()->where('student_id', $child->id)->firstOrFail();
        $setting->update([
            'valid_to' => '2026-09-18',
            'closure_reason' => StudentMealSetting::CLOSURE_REASON_CANCELLED,
            'closed_at' => now(),
        ]);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 8, 1));

        $statement = $child->monthlyPaymentStatements()->where('year', 2026)->where('month', 8)->firstOrFail();
        $lastActiveDay = $statement->days()->whereDate('date', '2026-09-18')->firstOrFail();
        $afterClosureDay = $statement->days()->whereDate('date', '2026-09-21')->firstOrFail();

        $this->assertSame(MonthlyPaymentDay::STATUS_PAY, $lastActiveDay->status);
        $this->assertSame(1000, $lastActiveDay->payable_amount);
        $this->assertSame(MonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL, $afterClosureDay->status);
        $this->assertSame(0, $afterClosureDay->payable_amount);
    }

    public function test_split_manual_transfer_calculates_components_and_previous_month_credit(): void
    {
        [$institution, $child, $user] = $this->seedSplitManualTransferParticipant(50, [
            ['component' => PaymentComponent::FOUNDATION, 'amount' => 300, 'valid_from' => '2026-09-01'],
            ['component' => PaymentComponent::KINDERGARTEN, 'amount' => 700, 'valid_from' => '2026-09-01'],
        ]);

        $cancellation = MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-10-05',
            'source' => 'admin',
            'status' => 'active',
            'reason' => 'Októberi késői lemondás',
            'created_by' => $user->id,
        ]);
        $cancellation->forceFill([
            'created_at' => '2026-10-05 10:00:00',
            'updated_at' => '2026-10-05 10:00:00',
        ])->save();

        $service = app(PaymentObligationCalculatorService::class);
        $service->recalculateMonth($institution, Carbon::create(2026, 9, 1));
        $service->recalculateMonth($institution, Carbon::create(2026, 11, 1));

        $statement = $child->monthlyPaymentStatements()->where('year', 2026)->where('month', 11)->firstOrFail();
        $creditedDay = FinancialAdjustment::query()
            ->where('institution_id', $institution->id)
            ->where('child_id', $child->id)
            ->where('type', FinancialAdjustment::TYPE_CANCELLATION_CREDIT)
            ->where('reference_year', 2026)
            ->where('reference_month', 11)
            ->orderBy('payment_component')
            ->pluck('amount', 'payment_component');
        $decemberDay = $statement->days()->whereDate('date', '2026-12-01')->firstOrFail();

        $this->assertSame(InstitutionPaymentComponentService::PAYMENT_MODEL_SPLIT_MANUAL_TRANSFER, $statement->payment_model);
        $this->assertSame(1, $statement->previous_month_cancelled_days);
        $this->assertSame(300, $decemberDay->foundation_payable_amount);
        $this->assertSame(350, $decemberDay->kindergarten_payable_amount);
        $this->assertSame(300, (int) $creditedDay[PaymentComponent::FOUNDATION]);
        $this->assertSame(350, (int) $creditedDay[PaymentComponent::KINDERGARTEN]);
        $this->assertSame(650, $statement->previous_cancellation_credit);
        $this->assertSame(300, $statement->foundation_cancellation_credit);
        $this->assertSame(350, $statement->kindergarten_cancellation_credit);
    }

    public function test_split_manual_transfer_hundred_percent_discount_keeps_foundation_component_payable(): void
    {
        [$institution, $child] = $this->seedSplitManualTransferParticipant(100, [
            ['component' => PaymentComponent::FOUNDATION, 'amount' => 300, 'valid_from' => '2026-09-01'],
            ['component' => PaymentComponent::KINDERGARTEN, 'amount' => 700, 'valid_from' => '2026-09-01'],
        ]);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 11, 1));

        $day = $child->monthlyPaymentStatements()->where('year', 2026)->where('month', 11)->firstOrFail()
            ->days()
            ->whereDate('date', '2026-12-01')
            ->firstOrFail();

        $this->assertSame(300, $day->foundation_payable_amount);
        $this->assertSame(0, $day->kindergarten_payable_amount);
        $this->assertSame(1000, $day->original_daily_price);
        $this->assertSame(300, $day->payable_amount);
    }

    public function test_split_manual_transfer_uses_effective_dated_rates_at_year_rollover_and_mid_month_change(): void
    {
        [$institution, $child] = $this->seedSplitManualTransferParticipant(0, [
            ['component' => PaymentComponent::FOUNDATION, 'amount' => 300, 'valid_from' => '2027-01-01'],
            ['component' => PaymentComponent::FOUNDATION, 'amount' => 350, 'valid_from' => '2027-01-16'],
            ['component' => PaymentComponent::KINDERGARTEN, 'amount' => 700, 'valid_from' => '2027-01-01'],
            ['component' => PaymentComponent::KINDERGARTEN, 'amount' => 760, 'valid_from' => '2027-01-16'],
        ]);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 12, 1));

        $statement = $child->monthlyPaymentStatements()->where('year', 2026)->where('month', 12)->firstOrFail();
        $beforeChange = $statement->days()->whereDate('date', '2027-01-15')->firstOrFail();
        $afterChange = $statement->days()->whereDate('date', '2027-01-18')->firstOrFail();

        $this->assertSame('2027-01-15', $beforeChange->date->toDateString());
        $this->assertSame(300, $beforeChange->foundation_daily_fee);
        $this->assertSame(700, $beforeChange->kindergarten_daily_fee);
        $this->assertSame(350, $afterChange->foundation_daily_fee);
        $this->assertSame(760, $afterChange->kindergarten_daily_fee);
        $this->assertTrue($statement->days->contains(fn (MonthlyPaymentDay $day) => $day->date->year === 2027));
    }

    private function seedBasicParticipant(int $discountPercent): array
    {
        $institution = Institution::create([
            'name' => 'Teszt Intézmény',
            'institution_code' => 'TESZT01',
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => 'institution_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $discount = DiscountType::create([
            'institution_id' => $institution->id,
            'name' => 'Teszt kedvezmény '.$discountPercent,
            'percentage' => $discountPercent,
            'active' => true,
            'sort_order' => 1,
        ]);

        $child = Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => 'Teszt Gyermek',
            'educational_identifier' => 'AZ123456',
            'group_name' => '1.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $mealType = MealType::create([
            'code' => 'lunch',
            'name' => 'Ebéd',
            'default_order' => 1,
        ]);

        $institutionMealType = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $mealType->id,
            'is_active' => true,
            'is_parent_selectable' => false,
            'is_required' => true,
            'display_order' => 1,
        ]);

        InstitutionMealPrice::create([
            'institution_meal_type_id' => $institutionMealType->id,
            'price' => 1000,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'created_by' => $user->id,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institution->id,
            'name' => 'Normál csomag',
            'is_active' => true,
            'is_default' => true,
            'display_order' => 1,
            'pricing_mode' => 'component_sum',
            'created_by' => $user->id,
        ]);

        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionMealType->id,
            'display_order' => 1,
        ]);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'created_by' => $user->id,
        ]);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            [
                'send_kitchen_email' => false,
                'payment_notification_enabled' => false,
                'payment_notification_day' => 5,
                'payment_due_day' => 5,
                'ab_menu_choice_deadline_day' => 20,
            ]
        );

        InstitutionMealSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            [
                'cancellation_hour' => 8,
                'cancellation_minute' => 30,
            ]
        );

        return [$institution, $child, $user];
    }

    private function seedSplitManualTransferParticipant(int $discountPercent, array $rates): array
    {
        [$institution, $child, $user] = $this->seedBasicParticipant($discountPercent);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'split_manual_transfer_enabled' => true,
                'foundation_account_holder' => 'Zsarica Alapitvany',
                'foundation_account_number' => '11700000-11111111',
                'foundation_transfer_reference' => 'ZSARICA',
                'kindergarten_account_holder' => 'Teszt Ovoda',
                'kindergarten_account_number' => '11700000-22222222',
                'kindergarten_transfer_reference' => 'OVODA',
                'payment_due_day' => 5,
            ])
        );

        foreach ($rates as $rate) {
            InstitutionPaymentComponentRate::create([
                'institution_id' => $institution->id,
                'component' => $rate['component'],
                'amount' => $rate['amount'],
                'valid_from' => $rate['valid_from'],
                'created_by' => $user->id,
            ]);
        }

        return [$institution, $child, $user];
    }
}
