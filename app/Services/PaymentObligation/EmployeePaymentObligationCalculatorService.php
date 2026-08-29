<?php

namespace App\Services\PaymentObligation;

use App\Models\EmployeeMealCancellation;
use App\Models\EmployeeRecurringCancellationRule;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPrice;
use App\Models\PaymentObligation\EmployeeFinancialAdjustment;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentDay;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Models\WorkingDay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeePaymentObligationCalculatorService
{
    public function recalculateMonth(Institution $institution, Carbon $month, ?Collection $employeeIds = null): array
    {
        [
            'payment_period' => $paymentPeriod,
            'meal_period' => $mealPeriod,
            'meal_period_end' => $mealPeriodEnd,
        ] = $this->resolvePeriods($month);

        $employees = $this->employeesForMonth($institution, $mealPeriod, $mealPeriodEnd, $employeeIds);
        $supportData = $this->supportData($institution, $mealPeriod, $mealPeriodEnd);

        $results = DB::transaction(function () use (
            $employees,
            $institution,
            $paymentPeriod,
            $mealPeriod,
            $mealPeriodEnd,
            $supportData
        ) {
            $created = 0;
            $updated = 0;
            $issueCount = 0;

            foreach ($employees as $employee) {
                $statement = EmployeeMonthlyPaymentStatement::query()->firstOrNew([
                    'institution_id' => $institution->id,
                    'institution_employee_id' => $employee->id,
                    'year' => $paymentPeriod->year,
                    'month' => $paymentPeriod->month,
                ]);

                if ($statement->exists && $statement->isClosed()) {
                    $issueCount += count($statement->issues ?? []);

                    continue;
                }

                $existingDays = $statement->exists
                    ? $statement->days()->get()->keyBy(fn (EmployeeMonthlyPaymentDay $day) => $day->date->toDateString())
                    : collect();

                $calculation = $this->calculateEmployeeMonth(
                    $institution,
                    $employee,
                    $paymentPeriod,
                    $mealPeriod,
                    $mealPeriodEnd,
                    $supportData,
                    $existingDays
                );

                $statement->fill([
                    'meal_package_id' => $calculation['meal_package_id'],
                    'discount_id' => $calculation['statement_discount_id'],
                    'meal_amount' => $calculation['meal_amount'],
                    'previous_cancellation_credit' => 0,
                    'billing_adjustment_amount' => $calculation['billing_adjustment_amount'],
                    'invoiceable_amount' => $calculation['invoiceable_amount'],
                    'previous_balance' => $calculation['previous_balance'],
                    'total_payable' => $calculation['total_payable'],
                    'due_date' => $calculation['due_date'],
                    'status' => EmployeeMonthlyPaymentStatement::STATUS_DRAFT,
                    'issues' => $calculation['issues'],
                    'calculated_at' => now(),
                    'closed_at' => null,
                    'closed_by' => null,
                    'reopened_at' => null,
                    'reopened_by' => null,
                    'reopen_reason' => null,
                ]);

                $wasExisting = $statement->exists;
                $statement->save();

                $keptDayIds = [];

                foreach ($calculation['days'] as $dayPayload) {
                    $dayDate = Carbon::parse($dayPayload['date'])->toDateString();
                    $day = EmployeeMonthlyPaymentDay::query()
                        ->where('employee_monthly_payment_statement_id', $statement->id)
                        ->whereDate('date', $dayDate)
                        ->firstOrNew();

                    $day->fill([
                        ...$dayPayload,
                        'date' => $dayDate,
                    ]);
                    $day->employee_monthly_payment_statement_id = $statement->id;
                    $day->save();
                    $keptDayIds[] = $day->id;
                }

                // Lásd PaymentObligationCalculatorService::recalculateMonth()
                // megjegyzését: egy korábbi (hibás záró dátumú) számításból
                // visszamaradt, az új dátumtartományon kívül eső napi
                // rekordot itt takarítjuk le - id alapú kizárással, mert egy
                // dátum-string alapú összehasonlítás a driver eltérő
                // tárolási formátuma esetén tévesen mindent törölhetne.
                EmployeeMonthlyPaymentDay::query()
                    ->where('employee_monthly_payment_statement_id', $statement->id)
                    ->whereNotIn('id', $keptDayIds)
                    ->delete();

                if ($wasExisting) {
                    $updated++;
                } else {
                    $created++;
                }

                $issueCount += count($calculation['issues']);
            }

            return compact('created', 'updated', 'issueCount');
        });

        return [
            ...$results,
            'employees' => $employees->count(),
        ];
    }

    public function closeMonth(Institution $institution, Carbon $month, User $user): array
    {
        $period = $month->copy()->startOfMonth();
        $summary = $this->summarizeCloseMonth($institution, $period);
        $statementsQuery = EmployeeMonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month);
        $statementCount = (clone $statementsQuery)->count();
        $closedCount = (clone $statementsQuery)
            ->where('status', EmployeeMonthlyPaymentStatement::STATUS_CLOSED)
            ->count();

        if ($summary['issue_count'] > 0) {
            return [
                ...$summary,
                'closed' => 0,
                'already_closed' => false,
            ];
        }

        if ($statementCount > 0 && $closedCount === $statementCount) {
            return [
                ...$summary,
                'closed' => 0,
                'already_closed' => true,
            ];
        }

        $updated = EmployeeMonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->whereIn('status', [
                EmployeeMonthlyPaymentStatement::STATUS_DRAFT,
                EmployeeMonthlyPaymentStatement::STATUS_REVIEWED,
            ])
            ->update([
                'status' => EmployeeMonthlyPaymentStatement::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $user->id,
                'reopened_at' => null,
                'reopened_by' => null,
                'reopen_reason' => null,
                'updated_at' => now(),
            ]);

        return [
            ...$summary,
            'closed' => $updated,
            'already_closed' => false,
        ];
    }

    public function summarizeCloseMonth(Institution $institution, Carbon $month): array
    {
        [
            'payment_period' => $paymentPeriod,
            'meal_period' => $mealPeriod,
            'meal_period_end' => $mealPeriodEnd,
        ] = $this->resolvePeriods($month);

        $statements = EmployeeMonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $paymentPeriod->year)
            ->where('month', $paymentPeriod->month)
            ->with('employee')
            ->get();

        $missingStatements = $this->employeesForMonth($institution, $mealPeriod, $mealPeriodEnd, null)
            ->pluck('id')
            ->diff($statements->pluck('institution_employee_id'))
            ->count();
        $issueCount = $statements->sum(fn (EmployeeMonthlyPaymentStatement $statement) => count($statement->issues ?? []));
        $missingMealPackages = $statements->filter(function (EmployeeMonthlyPaymentStatement $statement) {
            $issues = $statement->issues ?? [];

            return collect($issues)->contains(fn (string $issue) => str_contains($issue, 'menücsomag'));
        })->count();
        $missingPrice = $statements->filter(function (EmployeeMonthlyPaymentStatement $statement) {
            $issues = $statement->issues ?? [];

            return collect($issues)->contains(fn (string $issue) => str_contains($issue, 'ár'));
        })->count();

        return [
            'closed' => 0,
            'employees' => $statements->count(),
            'invoiceable_total' => $statements->sum('invoiceable_amount'),
            'issue_count' => $issueCount + $missingStatements,
            'missing_meal_packages' => $missingMealPackages,
            'missing_price_or_discount' => $missingPrice,
        ];
    }

    public function reopenMonth(Institution $institution, Carbon $month, User $user, string $reason): int
    {
        $period = $month->copy()->startOfMonth();
        $closedCount = EmployeeMonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('status', EmployeeMonthlyPaymentStatement::STATUS_CLOSED)
            ->count();

        if ($closedCount < 1) {
            return 0;
        }

        return EmployeeMonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('status', EmployeeMonthlyPaymentStatement::STATUS_CLOSED)
            ->update([
                'status' => EmployeeMonthlyPaymentStatement::STATUS_DRAFT,
                'closed_at' => null,
                'closed_by' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'reopened_at' => now(),
                'reopened_by' => $user->id,
                'reopen_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    private function calculateEmployeeMonth(
        Institution $institution,
        InstitutionEmployee $employee,
        Carbon $paymentPeriod,
        Carbon $mealPeriod,
        Carbon $mealPeriodEnd,
        array $supportData,
        Collection $existingDays
    ): array {
        $issueData = [
            'missing_meal_package' => false,
            'missing_price_dates' => [],
        ];
        $days = [];
        $mealAmount = 0;
        $mealPackageId = null;

        // Lásd PaymentObligationCalculatorService::calculateChildMonth()
        // megjegyzését: a Carbon daysUntil() alapból tartalmazza a záró
        // dátumot is, a $mealPeriodEnd pedig már maga az étkezési hónap
        // utolsó napja - a korábbi ->addDay() ezért mindig egy plusz,
        // ténylegesen a következő hónapba tartozó napot (annak 1-jét) is
        // beleszámolt az alkalmazotti étkezési napok közé.
        $dates = collect(Carbon::parse($mealPeriod)->daysUntil($mealPeriodEnd));

        foreach ($dates as $date) {
            $daily = $this->calculateDay(
                $institution,
                $employee,
                $date,
                $supportData,
                $existingDays->get($date->toDateString())
            );

            $mealPackageId ??= $daily['meal_package_id'];
            $mealAmount += $daily['payable_amount'];
            $issueData = $this->mergeIssueData($issueData, $daily['issue_data']);
            unset($daily['issue_data'], $daily['meal_package_id']);
            $days[] = $daily;
        }

        $billingAdjustmentAmount = $this->sumBillingAdjustments($institution->id, $employee->id, $paymentPeriod);
        $previousBalance = $this->sumPreviousBalance($institution->id, $employee->id, $paymentPeriod);
        $invoiceableAmount = $mealAmount + $billingAdjustmentAmount;
        $dueDay = max(1, min(28, (int) ($institution->setting?->payment_due_day ?? 0)));
        $dueDate = $dueDay > 0 ? $paymentPeriod->copy()->day($dueDay)->toDateString() : null;

        return [
            'meal_package_id' => $mealPackageId,
            'statement_discount_id' => $employee->discount_type_id,
            'meal_amount' => $mealAmount,
            'billing_adjustment_amount' => $billingAdjustmentAmount,
            'invoiceable_amount' => $invoiceableAmount,
            'previous_balance' => $previousBalance,
            'total_payable' => $invoiceableAmount + $previousBalance,
            'due_date' => $dueDate,
            'issues' => $this->buildIssues($employee, $issueData),
            'days' => $days,
        ];
    }

    private function calculateDay(
        Institution $institution,
        InstitutionEmployee $employee,
        Carbon $date,
        array $supportData,
        ?EmployeeMonthlyPaymentDay $existingDay
    ): array {
        $dayKey = $date->toDateString();
        $resolvedSetting = $this->resolveMealSetting($institution, $employee, $date);
        $mealPackageId = $resolvedSetting['meal_package_id'];
        $discountPercent = (int) ($employee->discountType?->percentage ?? 0);
        $schoolBreak = $supportData['school_breaks']->first(
            fn (SchoolBreak $break) => $date->betweenIncluded($break->start_date, $break->end_date)
        );
        $workingDay = $supportData['working_days']->get($dayKey);
        $isWorkingSaturday = $workingDay !== null && $date->isSaturday();
        $cancellation = $supportData['cancellations'][$employee->id][$dayKey] ?? null;
        $recurringCancellation = $this->resolveRecurringCancellation($employee, $date, $supportData['recurring_rules']);
        $activeCancellation = $cancellation ?? $recurringCancellation;

        if ($resolvedSetting['setting'] === null || $resolvedSetting['meal_type_ids'] === []) {
            return $this->finalizeDayPayload(
                $dayKey,
                [
                    'status' => EmployeeMonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL,
                    'original_daily_price' => 0,
                    'discount_percent' => $discountPercent,
                    'payable_amount' => 0,
                    'employee_meal_cancellation_id' => $cancellation?->id,
                    'school_break_id' => $schoolBreak?->id,
                    'working_day_id' => $workingDay?->id,
                ],
                $existingDay,
                [
                    'missing_meal_package' => $resolvedSetting['setting'] !== null && $resolvedSetting['meal_type_ids'] === [],
                    'missing_price_dates' => [],
                ],
                $mealPackageId
            );
        }

        if ($date->isWeekend() && ! $isWorkingSaturday) {
            return $this->finalizeDayPayload(
                $dayKey,
                [
                    'status' => EmployeeMonthlyPaymentDay::STATUS_WEEKEND,
                    'original_daily_price' => 0,
                    'discount_percent' => $discountPercent,
                    'payable_amount' => 0,
                    'employee_meal_cancellation_id' => $cancellation?->id,
                    'school_break_id' => $schoolBreak?->id,
                    'working_day_id' => $workingDay?->id,
                ],
                $existingDay,
                $this->emptyIssueData(),
                $mealPackageId
            );
        }

        if ($schoolBreak !== null) {
            return $this->finalizeDayPayload(
                $dayKey,
                [
                    'status' => EmployeeMonthlyPaymentDay::STATUS_SCHOOL_BREAK,
                    'original_daily_price' => 0,
                    'discount_percent' => $discountPercent,
                    'payable_amount' => 0,
                    'employee_meal_cancellation_id' => $cancellation?->id,
                    'school_break_id' => $schoolBreak->id,
                    'working_day_id' => $workingDay?->id,
                ],
                $existingDay,
                $this->emptyIssueData(),
                $mealPackageId
            );
        }

        if ($activeCancellation !== null) {
            return $this->finalizeDayPayload(
                $dayKey,
                [
                    'status' => EmployeeMonthlyPaymentDay::STATUS_CANCELLED,
                    'original_daily_price' => 0,
                    'discount_percent' => $discountPercent,
                    'payable_amount' => 0,
                    'employee_meal_cancellation_id' => $cancellation?->id,
                    'school_break_id' => null,
                    'working_day_id' => $workingDay?->id,
                ],
                $existingDay,
                $this->emptyIssueData(),
                $mealPackageId
            );
        }

        $price = $this->resolveDailyPrice($resolvedSetting['meal_type_ids'], $date, $supportData['prices'], $resolvedSetting['meal_package']);

        if (! $price['complete']) {
            return $this->finalizeDayPayload(
                $dayKey,
                [
                    'status' => EmployeeMonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL,
                    'original_daily_price' => 0,
                    'discount_percent' => $discountPercent,
                    'payable_amount' => 0,
                    'employee_meal_cancellation_id' => $cancellation?->id,
                    'school_break_id' => $schoolBreak?->id,
                    'working_day_id' => $workingDay?->id,
                ],
                $existingDay,
                [
                    'missing_meal_package' => false,
                    'missing_price_dates' => [$dayKey],
                ],
                $mealPackageId
            );
        }

        $discountAmount = (int) round($price['amount'] * ($discountPercent / 100), 0, PHP_ROUND_HALF_UP);
        $payableAmount = max(0, $price['amount'] - $discountAmount);
        $status = $payableAmount > 0
            ? ($isWorkingSaturday ? EmployeeMonthlyPaymentDay::STATUS_WORKING_SATURDAY : EmployeeMonthlyPaymentDay::STATUS_PAYABLE)
            : EmployeeMonthlyPaymentDay::STATUS_FREE_MEAL;

        return $this->finalizeDayPayload(
            $dayKey,
            [
                'status' => $status,
                'original_daily_price' => $price['amount'],
                'discount_percent' => $discountPercent,
                'payable_amount' => $payableAmount,
                'employee_meal_cancellation_id' => null,
                'school_break_id' => null,
                'working_day_id' => $workingDay?->id,
            ],
            $existingDay,
            $this->emptyIssueData(),
            $mealPackageId
        );
    }

    private function employeesForMonth(
        Institution $institution,
        Carbon $period,
        Carbon $endOfMonth,
        ?Collection $employeeIds
    ): Collection {
        $employees = InstitutionEmployee::query()
            ->with('discountType')
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->when($employeeIds !== null && $employeeIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $employeeIds))
            ->whereExists(function ($query) use ($institution, $period, $endOfMonth) {
                $query->selectRaw('1')
                    ->from('student_meal_settings')
                    ->where('student_meal_settings.eater_type', 'institution_employee')
                    ->whereColumn('student_meal_settings.eater_id', 'institution_employees.id')
                    ->where('student_meal_settings.institution_id', $institution->id)
                    ->whereDate('student_meal_settings.valid_from', '<=', $endOfMonth->toDateString())
                    ->where(function ($query) use ($period) {
                        $query->whereNull('student_meal_settings.valid_to')
                            ->orWhereDate('student_meal_settings.valid_to', '>=', $period->toDateString());
                    });
            })
            ->orderBy('name')
            ->get();

        if ($employeeIds === null || $employeeIds->isEmpty()) {
            return $employees;
        }

        $selected = InstitutionEmployee::query()
            ->with('discountType')
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->whereIn('id', $employeeIds)
            ->orderBy('name')
            ->get();

        return $selected
            ->merge($employees)
            ->unique('id')
            ->values();
    }

    private function supportData(Institution $institution, Carbon $period, Carbon $endOfMonth): array
    {
        $prices = InstitutionMealPrice::query()
            ->whereIn('institution_meal_type_id', $institution->mealTypes()->pluck('id'))
            ->orderBy('valid_from')
            ->get()
            ->groupBy('institution_meal_type_id');

        $schoolBreaks = SchoolBreak::query()
            ->where('institution_id', $institution->id)
            ->whereDate('start_date', '<=', $endOfMonth->toDateString())
            ->whereDate('end_date', '>=', $period->toDateString())
            ->get();

        $workingDays = WorkingDay::query()
            ->where('institution_id', $institution->id)
            ->whereBetween('date', [$period->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->keyBy(fn (WorkingDay $day) => $day->date->toDateString());

        $cancellations = EmployeeMealCancellation::query()
            ->where('institution_id', $institution->id)
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$period->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->groupBy('institution_employee_id')
            ->map(fn (Collection $items) => $items->keyBy(fn (EmployeeMealCancellation $item) => $item->service_date->toDateString()))
            ->all();

        $recurringRules = EmployeeRecurringCancellationRule::query()
            ->where('institution_id', $institution->id)
            ->where('status', EmployeeRecurringCancellationRule::STATUS_ACTIVE)
            ->whereDate('starts_on', '<=', $endOfMonth->toDateString())
            ->where(function ($query) use ($period) {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $period->toDateString());
            })
            ->get()
            ->groupBy('institution_employee_id');

        return [
            'prices' => $prices,
            'school_breaks' => $schoolBreaks,
            'working_days' => $workingDays,
            'cancellations' => $cancellations,
            'recurring_rules' => $recurringRules,
        ];
    }

    private function resolveRecurringCancellation(
        InstitutionEmployee $employee,
        Carbon $date,
        Collection $recurringRules
    ): ?EmployeeRecurringCancellationRule {
        return collect($recurringRules->get($employee->id))->first(function (EmployeeRecurringCancellationRule $rule) use ($date) {
            return $rule->weekday === $date->dayOfWeekIso
                && $rule->starts_on->toDateString() <= $date->toDateString()
                && ($rule->ends_on === null || $rule->ends_on->toDateString() >= $date->toDateString());
        });
    }

    private function resolveMealSetting(Institution $institution, Model $eater, Carbon $date): array
    {
        $setting = StudentMealSetting::query()
            ->with(['mealPackage.items', 'items'])
            ->forEater($eater)
            ->where('institution_id', $institution->id)
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $date->toDateString());
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();

        if ($setting === null) {
            return [
                'setting' => null,
                'meal_type_ids' => [],
                'meal_package_id' => null,
                'meal_package' => null,
            ];
        }

        if ($setting->mode === StudentMealSetting::MODE_INSTITUTION_DEFAULT) {
            $package = InstitutionMealPackage::query()
                ->with('items')
                ->where('institution_id', $institution->id)
                ->where('is_active', true)
                ->where('is_default', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->first();

            return [
                'setting' => $setting,
                'meal_type_ids' => $package?->items->pluck('institution_meal_type_id')->all() ?? [],
                'meal_package_id' => $package?->id,
                'meal_package' => $package,
            ];
        }

        if ($setting->mode === StudentMealSetting::MODE_PACKAGE) {
            return [
                'setting' => $setting,
                'meal_type_ids' => $setting->mealPackage?->items->pluck('institution_meal_type_id')->all() ?? [],
                'meal_package_id' => $setting->institution_meal_package_id,
                'meal_package' => $setting->mealPackage,
            ];
        }

        return [
            'setting' => $setting,
            'meal_type_ids' => $setting->items->pluck('institution_meal_type_id')->all(),
            'meal_package_id' => null,
            'meal_package' => null,
        ];
    }

    private function resolveDailyPrice(
        array $mealTypeIds,
        Carbon $date,
        Collection $priceMap,
        ?InstitutionMealPackage $mealPackage = null
    ): array {
        if ($mealTypeIds === []) {
            return ['amount' => 0, 'complete' => false];
        }

        if ($mealPackage !== null && $mealPackage->usesCustomPrice()) {
            // Ld. PaymentObligationCalculatorService::resolveDailyPrice() -
            // ugyanaz a logika a dolgozói díjszámításnál is: egyedi
            // csomagár módban a csomag fix ára számít, nem az egyes
            // étkezéstípusok összege.
            if ($mealPackage->custom_price === null) {
                return ['amount' => 0, 'complete' => false];
            }

            return ['amount' => (int) $mealPackage->custom_price, 'complete' => true];
        }

        $amount = 0;
        $complete = true;

        foreach ($mealTypeIds as $mealTypeId) {
            $price = ($priceMap->get($mealTypeId) ?? collect())
                ->first(function (InstitutionMealPrice $price) use ($date) {
                    return $price->valid_from->toDateString() <= $date->toDateString()
                        && ($price->valid_to === null || $price->valid_to->toDateString() >= $date->toDateString());
                });

            if ($price === null) {
                $complete = false;

                continue;
            }

            $amount += (int) $price->price;
        }

        return compact('amount', 'complete');
    }

    private function finalizeDayPayload(
        string $date,
        array $payload,
        ?EmployeeMonthlyPaymentDay $existingDay,
        array $issueData,
        ?int $mealPackageId
    ): array {
        $finalPayload = [
            'date' => $date,
            ...$payload,
            'manually_modified' => false,
            'original_status' => null,
            'original_payable_amount' => null,
            'modification_reason' => null,
            'modified_by' => null,
            'modified_at' => null,
        ];

        if ($existingDay?->manually_modified) {
            $finalPayload['original_status'] = $existingDay->original_status ?? $payload['status'];
            $finalPayload['original_payable_amount'] = $existingDay->original_payable_amount ?? $payload['payable_amount'];
            $finalPayload['status'] = $existingDay->status;
            $finalPayload['payable_amount'] = $existingDay->payable_amount;
            $finalPayload['manually_modified'] = true;
            $finalPayload['modification_reason'] = $existingDay->modification_reason;
            $finalPayload['modified_by'] = $existingDay->modified_by;
            $finalPayload['modified_at'] = $existingDay->modified_at;
        }

        return [
            ...$finalPayload,
            'issue_data' => $issueData,
            'meal_package_id' => $mealPackageId,
        ];
    }

    private function emptyIssueData(): array
    {
        return [
            'missing_meal_package' => false,
            'missing_price_dates' => [],
        ];
    }

    private function mergeIssueData(array $carry, array $daily): array
    {
        $carry['missing_meal_package'] = $carry['missing_meal_package'] || ($daily['missing_meal_package'] ?? false);
        $carry['missing_price_dates'] = array_values(array_unique([
            ...$carry['missing_price_dates'],
            ...($daily['missing_price_dates'] ?? []),
        ]));

        return $carry;
    }

    private function buildIssues(InstitutionEmployee $employee, array $issueData): array
    {
        $issues = [];

        if ($issueData['missing_meal_package']) {
            $issues[] = "{$employee->name}: nincs aktív menücsomag vagy étkezési beállítás az érintett napokon.";
        }

        foreach ($this->buildMissingPriceIssues($employee->name, $issueData['missing_price_dates']) as $issue) {
            $issues[] = $issue;
        }

        return $issues;
    }

    private function buildMissingPriceIssues(string $employeeName, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        sort($dates);
        $ranges = [];
        $start = Carbon::parse($dates[0])->startOfDay();
        $end = $start->copy();

        foreach (array_slice($dates, 1) as $date) {
            $current = Carbon::parse($date)->startOfDay();

            if ($current->isSameDay($end->copy()->addDay())) {
                $end = $current;

                continue;
            }

            $ranges[] = [$start->copy(), $end->copy()];
            $start = $current;
            $end = $current;
        }

        $ranges[] = [$start, $end];

        return array_map(function (array $range) use ($employeeName) {
            [$from, $to] = $range;

            if ($from->isSameDay($to)) {
                return sprintf(
                    '%s: nincs érvényes ár %s napra.',
                    $employeeName,
                    $from->format('Y.m.d.')
                );
            }

            return sprintf(
                '%s: nincs érvényes ár %s–%s között.',
                $employeeName,
                $from->format('Y.m.d.'),
                $to->format('Y.m.d.')
            );
        }, $ranges);
    }

    private function sumBillingAdjustments(int $institutionId, int $employeeId, Carbon $period): int
    {
        return (int) EmployeeFinancialAdjustment::query()
            ->where('institution_id', $institutionId)
            ->where('institution_employee_id', $employeeId)
            ->where('affects_invoice', true)
            ->where('reference_year', $period->year)
            ->where('reference_month', $period->month)
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    private function sumPreviousBalance(int $institutionId, int $employeeId, Carbon $period): int
    {
        return (int) EmployeeFinancialAdjustment::query()
            ->where('institution_id', $institutionId)
            ->where('institution_employee_id', $employeeId)
            ->where(function ($query) use ($period) {
                $query->where('affects_invoice', false)
                    ->orWhere(function ($query) use ($period) {
                        $query->whereNull('reference_year')
                            ->whereNull('reference_month');
                    });
            })
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    // Felhasználói javítás (ld. PaymentObligationCalculatorService -
    // ugyanaz a hiba itt, a dolgozói elszámolásnál is): a fizetési hónap a
    // SAJÁT havi étkezéseket számlázza (meal_period = payment_period), nem
    // a következő hónapét.
    private function resolvePeriods(Carbon $month): array
    {
        $paymentPeriod = $month->copy()->startOfMonth();
        $mealPeriod = $paymentPeriod->copy();
        $creditPeriod = $paymentPeriod->copy()->subMonth();

        return [
            'payment_period' => $paymentPeriod,
            'meal_period' => $mealPeriod,
            'meal_period_end' => $mealPeriod->copy()->endOfMonth(),
            'credit_period' => $creditPeriod,
            'credit_period_end' => $creditPeriod->copy()->endOfMonth(),
        ];
    }
}
