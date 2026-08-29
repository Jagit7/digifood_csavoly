<?php

namespace App\Services\PaymentObligation;

use App\Models\Child;
use App\Models\ClassCancellation;
use App\Models\ClassGroup;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionPayment;
use App\Models\MealCancellation;
use App\Models\RecurringCancellationRule;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Models\WorkingDay;
use App\Models\PaymentObligation\FinancialAdjustment;
use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\Finance\InstitutionPaymentComponentService;
use App\Support\Finance\PaymentComponent;
use App\Services\InstitutionCalendarService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentObligationCalculatorService
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly InstitutionPaymentComponentService $componentService,
    ) {}

    public function recalculateMonth(Institution $institution, Carbon $month, ?Collection $childIds = null): array
    {
        [
            'payment_period' => $paymentPeriod,
            'meal_period' => $mealPeriod,
            'meal_period_end' => $mealPeriodEnd,
            'credit_period' => $creditPeriod,
            'credit_period_end' => $creditPeriodEnd,
        ] = $this->resolvePeriods($month);

        $children = $this->childrenForMonth($institution, $mealPeriod, $mealPeriodEnd, $childIds);
        $supportData = $this->supportData($institution, $mealPeriod, $mealPeriodEnd);

        $results = DB::transaction(function () use (
            $children,
            $institution,
            $paymentPeriod,
            $mealPeriod,
            $mealPeriodEnd,
            $creditPeriod,
            $creditPeriodEnd,
            $supportData
        ) {
            $created = 0;
            $updated = 0;
            $issueCount = 0;

            foreach ($children as $child) {
                $statement = MonthlyPaymentStatement::query()->firstOrNew([
                    'child_id' => $child->id,
                    'year' => $paymentPeriod->year,
                    'month' => $paymentPeriod->month,
                ]);

                if ($statement->exists && $statement->isClosed()) {
                    $issueCount += count($statement->issues ?? []);

                    continue;
                }

                $existingDays = $statement->exists
                    ? $statement->days()->get()->keyBy(fn (MonthlyPaymentDay $day) => $day->date->toDateString())
                    : collect();

                $this->syncLateCancellationCredits(
                    $institution,
                    $child,
                    $paymentPeriod,
                    $creditPeriod,
                    $creditPeriodEnd
                );

                $calculation = $this->calculateChildMonth(
                    $institution,
                    $child,
                    $paymentPeriod,
                    $mealPeriod,
                    $mealPeriodEnd,
                    $supportData,
                    $existingDays
                );

                $statement->fill([
                    'institution_id' => $institution->id,
                    'meal_package_id' => $calculation['meal_package_id'],
                    'discount_id' => $calculation['statement_discount_id'] ?? $child->discount_type_id,
                    'payment_model' => $calculation['payment_model'],
                    'planned_meal_days' => $calculation['planned_meal_days'],
                    'previous_month_cancelled_days' => $calculation['previous_month_cancelled_days'],
                    'meal_amount' => $calculation['meal_amount'],
                    'previous_cancellation_credit' => $calculation['previous_cancellation_credit'],
                    'billing_adjustment_amount' => $calculation['billing_adjustment_amount'],
                    'invoiceable_amount' => $calculation['invoiceable_amount'],
                    'previous_balance' => $calculation['previous_balance'],
                    'total_payable' => $calculation['total_payable'],
                    'foundation_gross_amount' => $calculation['foundation_gross_amount'],
                    'foundation_cancellation_credit' => $calculation['foundation_cancellation_credit'],
                    'foundation_billing_adjustment_amount' => $calculation['foundation_billing_adjustment_amount'],
                    'foundation_invoiceable_amount' => $calculation['foundation_invoiceable_amount'],
                    'foundation_previous_balance' => $calculation['foundation_previous_balance'],
                    'foundation_total_payable' => $calculation['foundation_total_payable'],
                    'kindergarten_gross_amount' => $calculation['kindergarten_gross_amount'],
                    'kindergarten_discount_amount' => $calculation['kindergarten_discount_amount'],
                    'kindergarten_cancellation_credit' => $calculation['kindergarten_cancellation_credit'],
                    'kindergarten_billing_adjustment_amount' => $calculation['kindergarten_billing_adjustment_amount'],
                    'kindergarten_invoiceable_amount' => $calculation['kindergarten_invoiceable_amount'],
                    'kindergarten_previous_balance' => $calculation['kindergarten_previous_balance'],
                    'kindergarten_total_payable' => $calculation['kindergarten_total_payable'],
                    'status' => MonthlyPaymentStatement::STATUS_DRAFT,
                    'issues' => $calculation['issues'],
                    'calculation_snapshot' => $calculation['calculation_snapshot'],
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
                    $day = MonthlyPaymentDay::query()
                        ->where('monthly_payment_statement_id', $statement->id)
                        ->whereDate('date', $dayDate)
                        ->firstOrNew();

                    $day->fill([
                        ...$dayPayload,
                        'date' => $dayDate,
                    ]);
                    $day->monthly_payment_statement_id = $statement->id;
                    $day->save();
                    $keptDayIds[] = $day->id;
                }

                // Ha egy korábbi számítás (pl. a záró dátum hibás kezelése
                // miatt, ld. a daysUntil()-lal kapcsolatos javítást) olyan
                // napi rekordot hozott létre, ami az ÚJ, most kiszámított
                // dátumtartományban már nincs benne (pl. a következő hónap
                // 1-je), azt itt takarítjuk le - különben egy ilyen "árva"
                // nap örökre ott ragadna a statement napjai között, és
                // torzítaná az étkezési napok összesítését, még akkor is,
                // ha a napi bontás táblázat (ami a hónap tényleges
                // napjaihoz van kötve) már helyesen nem jeleníti meg. Az
                // azonosító (id) alapú kizárást használjuk a dátum-string
                // alapú összehasonlítás helyett, mert az adatbázis-driver
                // eltérő dátum/idő tárolási formátuma esetén egy
                // string-alapú whereNotIn hamisan minden sort "nem
                // egyezőnek" ítélhetne, és tévesen az összes napot törölné.
                MonthlyPaymentDay::query()
                    ->where('monthly_payment_statement_id', $statement->id)
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
            'children' => $children->count(),
        ];
    }

    public function closeMonth(Institution $institution, Carbon $month, User $user): array
    {
        $period = $month->copy()->startOfMonth();
        $summary = $this->summarizeCloseMonth($institution, $period);
        $statementsQuery = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month);
        $statementCount = (clone $statementsQuery)->count();
        $closedCount = (clone $statementsQuery)
            ->where('status', MonthlyPaymentStatement::STATUS_CLOSED)
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

        $updated = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->whereIn('status', [
                MonthlyPaymentStatement::STATUS_DRAFT,
                MonthlyPaymentStatement::STATUS_REVIEWED,
            ])
            ->update([
                'status' => MonthlyPaymentStatement::STATUS_CLOSED,
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
        $statements = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $paymentPeriod->year)
            ->where('month', $paymentPeriod->month)
            ->with('child')
            ->get();

        $missingStatements = $this->childrenForMonth($institution, $mealPeriod, $mealPeriodEnd, null)
            ->pluck('id')
            ->diff($statements->pluck('child_id'))
            ->count();
        $issueCount = $statements->sum(fn (MonthlyPaymentStatement $statement) => count($statement->issues ?? []));
        $missingMealPackages = $statements->filter(function (MonthlyPaymentStatement $statement) {
            $issues = $statement->issues ?? [];

            return collect($issues)->contains(fn (string $issue) => str_contains($issue, 'menücsomag'));
        })->count();
        $missingPriceOrDiscount = $statements->filter(function (MonthlyPaymentStatement $statement) {
            $issues = $statement->issues ?? [];

            return collect($issues)->contains(fn (string $issue) => str_contains($issue, 'ár') || str_contains($issue, 'kedvezmény'));
        })->count();

        if ($missingStatements > 0 || $issueCount > 0) {
            return [
                'closed' => 0,
                'children' => $statements->count(),
                'invoiceable_total' => $statements->sum('invoiceable_amount'),
                'issue_count' => $issueCount + $missingStatements,
                'missing_meal_packages' => $missingMealPackages,
                'missing_price_or_discount' => $missingPriceOrDiscount,
            ];
        }

        return [
            'closed' => 0,
            'children' => $statements->count(),
            'invoiceable_total' => $statements->sum('invoiceable_amount'),
            'issue_count' => 0,
            'missing_meal_packages' => 0,
            'missing_price_or_discount' => 0,
        ];
    }

    public function updateManualDay(MonthlyPaymentDay $day, string $status, int $payableAmount, string $reason, User $user): MonthlyPaymentDay
    {
        $statement = $day->statement;

        if ($statement->status !== MonthlyPaymentStatement::STATUS_DRAFT) {
            abort(422, 'Lezárt hónap napjai nem módosíthatók.');
        }

        $day->update([
            'status' => $status,
            'payable_amount' => max(0, $payableAmount),
            'manually_modified' => true,
            'original_status' => $day->original_status ?? $day->status,
            'original_payable_amount' => $day->original_payable_amount ?? $day->payable_amount,
            'modification_reason' => $reason,
            'modified_by' => $user->id,
            'modified_at' => now(),
        ]);

        $this->refreshStatementTotals($statement->fresh(['days', 'child']));

        return $day->fresh(['statement', 'modifiedBy']);
    }

    public function refreshStatementTotals(MonthlyPaymentStatement $statement): MonthlyPaymentStatement
    {
        $statement->loadMissing(['days', 'child']);
        $paymentPeriod = Carbon::create($statement->year, $statement->month, 1)->startOfMonth();

        if ($statement->usesSplitPaymentModel()) {
            $foundationGrossAmount = (int) $statement->days->sum('foundation_payable_amount');
            $kindergartenGrossAmount = (int) $statement->days->sum('kindergarten_daily_fee');
            $kindergartenDiscountAmount = (int) $statement->days->sum('kindergarten_discount_amount');
            $mealAmount = $foundationGrossAmount + ((int) $statement->days->sum('kindergarten_payable_amount'));
            $foundationCancellationCredit = $this->componentService->sumCancellationCredits($statement->institution_id, $statement->child_id, PaymentComponent::FOUNDATION, $paymentPeriod);
            $kindergartenCancellationCredit = $this->componentService->sumCancellationCredits($statement->institution_id, $statement->child_id, PaymentComponent::KINDERGARTEN, $paymentPeriod);
            $foundationAdjustment = $this->componentService->sumComponentInvoiceAdjustments($statement->institution_id, $statement->child_id, PaymentComponent::FOUNDATION, $paymentPeriod);
            $kindergartenAdjustment = $this->componentService->sumComponentInvoiceAdjustments($statement->institution_id, $statement->child_id, PaymentComponent::KINDERGARTEN, $paymentPeriod);
            $foundationPreviousBalance = $this->componentService->sumComponentPreviousBalance($statement->institution_id, $statement->child_id, PaymentComponent::FOUNDATION, $paymentPeriod);
            $kindergartenPreviousBalance = $this->componentService->sumComponentPreviousBalance($statement->institution_id, $statement->child_id, PaymentComponent::KINDERGARTEN, $paymentPeriod);
            $foundationInvoiceable = $foundationGrossAmount - $foundationCancellationCredit + $foundationAdjustment;
            $kindergartenInvoiceable = ((int) $statement->days->sum('kindergarten_payable_amount')) - $kindergartenCancellationCredit + $kindergartenAdjustment;
            $previousCancellationCredit = $foundationCancellationCredit + $kindergartenCancellationCredit;
            $billingAdjustmentAmount = $foundationAdjustment + $kindergartenAdjustment;
            $previousBalance = $foundationPreviousBalance + $kindergartenPreviousBalance;
            $invoiceableAmount = $foundationInvoiceable + $kindergartenInvoiceable;

            $statement->update([
                'meal_amount' => $mealAmount,
                'previous_cancellation_credit' => $previousCancellationCredit,
                'billing_adjustment_amount' => $billingAdjustmentAmount,
                'invoiceable_amount' => $invoiceableAmount,
                'previous_balance' => $previousBalance,
                'total_payable' => $invoiceableAmount + $previousBalance,
                'foundation_gross_amount' => $foundationGrossAmount,
                'foundation_cancellation_credit' => $foundationCancellationCredit,
                'foundation_billing_adjustment_amount' => $foundationAdjustment,
                'foundation_invoiceable_amount' => $foundationInvoiceable,
                'foundation_previous_balance' => $foundationPreviousBalance,
                'foundation_total_payable' => $foundationInvoiceable + $foundationPreviousBalance,
                'kindergarten_gross_amount' => $kindergartenGrossAmount,
                'kindergarten_discount_amount' => $kindergartenDiscountAmount,
                'kindergarten_cancellation_credit' => $kindergartenCancellationCredit,
                'kindergarten_billing_adjustment_amount' => $kindergartenAdjustment,
                'kindergarten_invoiceable_amount' => $kindergartenInvoiceable,
                'kindergarten_previous_balance' => $kindergartenPreviousBalance,
                'kindergarten_total_payable' => $kindergartenInvoiceable + $kindergartenPreviousBalance,
            ]);

            return $statement->fresh(['days', 'child']);
        }

        $mealAmount = (int) $statement->days->sum('payable_amount');
        $previousCancellationCredit = $this->sumCancellationCredits($statement->institution_id, $statement->child_id, $paymentPeriod);
        $billingAdjustmentAmount = $this->sumBillingAdjustments($statement->institution_id, $statement->child_id, $paymentPeriod);
        $previousBalance = $this->sumPreviousBalance($statement->institution_id, $statement->child_id, $paymentPeriod)
            + $this->sumUnpaidPriorStatements($statement->institution_id, $statement->child_id, $paymentPeriod);
        $invoiceableAmount = $mealAmount - $previousCancellationCredit + $billingAdjustmentAmount;

        $statement->update([
            'meal_amount' => $mealAmount,
            'previous_cancellation_credit' => $previousCancellationCredit,
            'billing_adjustment_amount' => $billingAdjustmentAmount,
            'invoiceable_amount' => $invoiceableAmount,
            'previous_balance' => $previousBalance,
            'total_payable' => $invoiceableAmount + $previousBalance,
        ]);

        return $statement->fresh(['days', 'child']);
    }

    public function resetManualDay(MonthlyPaymentDay $day): MonthlyPaymentDay
    {
        $statement = $day->statement;

        if ($statement->status !== MonthlyPaymentStatement::STATUS_DRAFT) {
            abort(422, 'Lezárt hónap napjai nem módosíthatók.');
        }

        abort_if(!$day->manually_modified, 422, 'Ez a nap nem kézzel módosított.');

        $day->update([
            'status' => $day->original_status ?? $day->status,
            'payable_amount' => $day->original_payable_amount ?? $day->payable_amount,
            'manually_modified' => false,
            'modification_reason' => null,
            'modified_by' => null,
            'modified_at' => null,
        ]);

        $this->refreshStatementTotals($statement->fresh(['days', 'child']));

        return $day->fresh(['statement']);
    }

    public function reopenMonth(Institution $institution, Carbon $month, User $user, string $reason): int
    {
        $period = $month->copy()->startOfMonth();
        $closedCount = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('status', MonthlyPaymentStatement::STATUS_CLOSED)
            ->count();

        if ($closedCount < 1) {
            return 0;
        }

        return MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('status', MonthlyPaymentStatement::STATUS_CLOSED)
            ->update([
                'status' => MonthlyPaymentStatement::STATUS_DRAFT,
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

    private function calculateChildMonth(
        Institution $institution,
        Child $child,
        Carbon $paymentPeriod,
        Carbon $mealPeriod,
        Carbon $mealPeriodEnd,
        array $supportData,
        Collection $existingDays
    ): array {
        $issueData = [
            'missing_discount' => false,
            'missing_meal_package' => false,
            'missing_price_dates' => [],
        ];
        $days = [];
        $mealAmount = 0;
        $mealPackageId = null;
        $plannedMealDays = 0;
        $paymentModel = $this->componentService->paymentModelForInstitution($institution);
        $foundationGrossAmount = 0;
        $kindergartenGrossAmount = 0;
        $kindergartenDiscountAmount = 0;

        // A Carbon daysUntil() (mint minden Carbon-periódus) alapból
        // TARTALMAZZA a záró dátumot is (az EXCLUDE_END_DATE opció
        // alapból ki van kapcsolva) - a $mealPeriodEnd pedig már maga is
        // az étkezési hónap utolsó napja. A korábbi ->addDay() ezért egy
        // plusz nappal (a KÖVETKEZŐ hónap 1-jével) tolta túl a
        // tartományt, ami minden hónapnál egy extra, ténylegesen a
        // következő hónapba tartozó napot is beleszámolt az étkezési
        // napok közé (pl. szeptemberi elszámolásba október 1-jét).
        $dates = collect(Carbon::parse($mealPeriod)->daysUntil($mealPeriodEnd));

        foreach ($dates as $date) {
            $daily = $this->calculateDay(
                $institution,
                $child,
                $date,
                $supportData,
                $existingDays->get($date->toDateString())
            );

            $mealPackageId ??= $daily['meal_package_id'];
            $mealAmount += (int) $daily['payable_amount'];
            $foundationGrossAmount += (int) $daily['foundation_payable_amount'];
            $kindergartenGrossAmount += (int) $daily['kindergarten_daily_fee'];
            $kindergartenDiscountAmount += (int) $daily['kindergarten_discount_amount'];
            if (((int) $daily['foundation_payable_amount']) > 0 || ((int) $daily['kindergarten_daily_fee']) > 0 || ((int) $daily['payable_amount']) > 0) {
                $plannedMealDays++;
            }
            $issueData = $this->mergeIssueData($issueData, $daily['issue_data']);
            unset($daily['issue_data'], $daily['meal_package_id']);
            $days[] = $daily;
        }

        if ($paymentModel === InstitutionPaymentComponentService::PAYMENT_MODEL_SPLIT_MANUAL_TRANSFER) {
            $foundationCancellationCredit = $this->componentService->sumCancellationCredits($institution->id, $child->id, PaymentComponent::FOUNDATION, $paymentPeriod);
            $kindergartenCancellationCredit = $this->componentService->sumCancellationCredits($institution->id, $child->id, PaymentComponent::KINDERGARTEN, $paymentPeriod);
            $foundationAdjustmentAmount = $this->componentService->sumComponentInvoiceAdjustments($institution->id, $child->id, PaymentComponent::FOUNDATION, $paymentPeriod);
            $kindergartenAdjustmentAmount = $this->componentService->sumComponentInvoiceAdjustments($institution->id, $child->id, PaymentComponent::KINDERGARTEN, $paymentPeriod);
            $foundationPreviousBalance = $this->componentService->sumComponentPreviousBalance($institution->id, $child->id, PaymentComponent::FOUNDATION, $paymentPeriod);
            $kindergartenPreviousBalance = $this->componentService->sumComponentPreviousBalance($institution->id, $child->id, PaymentComponent::KINDERGARTEN, $paymentPeriod);
            $foundationInvoiceableAmount = $foundationGrossAmount - $foundationCancellationCredit + $foundationAdjustmentAmount;
            $kindergartenInvoiceableAmount = ($mealAmount - $foundationGrossAmount) - $kindergartenCancellationCredit + $kindergartenAdjustmentAmount;
            $previousCancellationCredit = $foundationCancellationCredit + $kindergartenCancellationCredit;
            $billingAdjustmentAmount = $foundationAdjustmentAmount + $kindergartenAdjustmentAmount;
            $previousBalance = $foundationPreviousBalance + $kindergartenPreviousBalance;
            $invoiceableAmount = $foundationInvoiceableAmount + $kindergartenInvoiceableAmount;

            return [
                'payment_model' => $paymentModel,
                'meal_package_id' => $mealPackageId,
                'statement_discount_id' => $child->discountTypeForDate($mealPeriod)?->id ?? $child->discount_type_id,
                'planned_meal_days' => $plannedMealDays,
                'previous_month_cancelled_days' => $this->countCreditedCancellationDays($institution, $child, $paymentPeriod),
                'meal_amount' => $mealAmount,
                'previous_cancellation_credit' => $previousCancellationCredit,
                'billing_adjustment_amount' => $billingAdjustmentAmount,
                'invoiceable_amount' => $invoiceableAmount,
                'previous_balance' => $previousBalance,
                'total_payable' => $invoiceableAmount + $previousBalance,
                'foundation_gross_amount' => $foundationGrossAmount,
                'foundation_cancellation_credit' => $foundationCancellationCredit,
                'foundation_billing_adjustment_amount' => $foundationAdjustmentAmount,
                'foundation_invoiceable_amount' => $foundationInvoiceableAmount,
                'foundation_previous_balance' => $foundationPreviousBalance,
                'foundation_total_payable' => $foundationInvoiceableAmount + $foundationPreviousBalance,
                'kindergarten_gross_amount' => $kindergartenGrossAmount,
                'kindergarten_discount_amount' => $kindergartenDiscountAmount,
                'kindergarten_cancellation_credit' => $kindergartenCancellationCredit,
                'kindergarten_billing_adjustment_amount' => $kindergartenAdjustmentAmount,
                'kindergarten_invoiceable_amount' => $kindergartenInvoiceableAmount,
                'kindergarten_previous_balance' => $kindergartenPreviousBalance,
                'kindergarten_total_payable' => $kindergartenInvoiceableAmount + $kindergartenPreviousBalance,
                'issues' => $this->buildIssues($child, $issueData),
                'calculation_snapshot' => $this->buildSplitSnapshot($institution, $child, $paymentPeriod, $days, [
                    'planned_meal_days' => $plannedMealDays,
                    'foundation_gross_amount' => $foundationGrossAmount,
                    'foundation_cancellation_credit' => $foundationCancellationCredit,
                    'foundation_invoiceable_amount' => $foundationInvoiceableAmount,
                    'kindergarten_gross_amount' => $kindergartenGrossAmount,
                    'kindergarten_discount_amount' => $kindergartenDiscountAmount,
                    'kindergarten_cancellation_credit' => $kindergartenCancellationCredit,
                    'kindergarten_invoiceable_amount' => $kindergartenInvoiceableAmount,
                ]),
                'days' => $days,
            ];
        }

        $previousCancellationCredit = $this->sumCancellationCredits($institution->id, $child->id, $paymentPeriod);
        $billingAdjustmentAmount = $this->sumBillingAdjustments($institution->id, $child->id, $paymentPeriod);
        $previousBalance = $this->sumPreviousBalance($institution->id, $child->id, $paymentPeriod)
            + $this->sumUnpaidPriorStatements($institution->id, $child->id, $paymentPeriod);
        $invoiceableAmount = $mealAmount - $previousCancellationCredit + $billingAdjustmentAmount;

        return [
            'payment_model' => $paymentModel,
            'meal_package_id' => $mealPackageId,
            'statement_discount_id' => $child->discountTypeForDate($mealPeriod)?->id ?? $child->discount_type_id,
            'planned_meal_days' => (int) collect($days)->where('payable_amount', '>', 0)->count(),
            'previous_month_cancelled_days' => $this->countCreditedCancellationDays($institution, $child, $paymentPeriod),
            'meal_amount' => $mealAmount,
            'previous_cancellation_credit' => $previousCancellationCredit,
            'billing_adjustment_amount' => $billingAdjustmentAmount,
            'invoiceable_amount' => $invoiceableAmount,
            'previous_balance' => $previousBalance,
            'total_payable' => $invoiceableAmount + $previousBalance,
            'foundation_gross_amount' => $mealAmount,
            'foundation_cancellation_credit' => $previousCancellationCredit,
            'foundation_billing_adjustment_amount' => $billingAdjustmentAmount,
            'foundation_invoiceable_amount' => $invoiceableAmount,
            'foundation_previous_balance' => $previousBalance,
            'foundation_total_payable' => $invoiceableAmount + $previousBalance,
            'kindergarten_gross_amount' => 0,
            'kindergarten_discount_amount' => 0,
            'kindergarten_cancellation_credit' => 0,
            'kindergarten_billing_adjustment_amount' => 0,
            'kindergarten_invoiceable_amount' => 0,
            'kindergarten_previous_balance' => 0,
            'kindergarten_total_payable' => 0,
            'issues' => $this->buildIssues($child, $issueData),
            'calculation_snapshot' => null,
            'days' => $days,
        ];
    }

    private function calculateDay(
        Institution $institution,
        Child $child,
        Carbon $date,
        array $supportData,
        ?MonthlyPaymentDay $existingDay
    ): array {
        $dayKey = $date->toDateString();
        $resolvedSetting = $this->resolveMealSetting($institution, $child, $date);
        $mealPackageId = $resolvedSetting['meal_package_id'];
        $discountType = $child->discountTypeForDate($date);
        $discountPercent = (int) ($discountType?->percentage ?? 0);
        $schoolBreak = $supportData['school_breaks']->first(fn (SchoolBreak $break) => $date->betweenIncluded($break->start_date, $break->end_date));
        $workingDay = $supportData['working_days']->get($dayKey);
        $classCancellation = $this->resolveClassCancellation($child, $date, $supportData['class_cancellations'], $supportData['memberships']);
        $cancellation = $supportData['cancellations'][$child->id][$dayKey] ?? null;
        $recurringCancellation = $this->resolveRecurringCancellation($child, $date, $supportData['recurring_rules']);
        $activeCancellation = $cancellation ?? $recurringCancellation;
        $isWorkingSaturday = $workingDay !== null && $date->isSaturday();
        $isAdvanceCancellation = $activeCancellation !== null
            ? $this->isAdvanceCancellation($institution, $activeCancellation, $date)
            : false;

        if ($resolvedSetting['setting'] === null || $resolvedSetting['meal_type_ids'] === []) {
            $payload = [
                'status' => MonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL,
                'original_daily_price' => 0,
                'discount_percent' => $discountPercent,
                'payable_amount' => 0,
                'cancellation_id' => $cancellation?->id,
                'school_break_id' => $schoolBreak?->id,
                'class_cancellation_id' => $classCancellation?->id,
                'working_day_id' => $workingDay?->id,
            ];

            return $this->finalizeDayPayload(
                $dayKey,
                $payload,
                $existingDay,
                [
                    'missing_discount' => false,
                    'missing_meal_package' => $resolvedSetting['setting'] !== null && $resolvedSetting['meal_type_ids'] === [],
                    'missing_price_dates' => [],
                ],
                $mealPackageId
            );
        }

        if ($date->isWeekend() && !$isWorkingSaturday) {
            $payload = [
                'status' => MonthlyPaymentDay::STATUS_WEEKEND,
                'original_daily_price' => 0,
                'discount_percent' => $discountPercent,
                'payable_amount' => 0,
                'cancellation_id' => $cancellation?->id,
                'school_break_id' => $schoolBreak?->id,
                'class_cancellation_id' => $classCancellation?->id,
                'working_day_id' => $workingDay?->id,
            ];

            return $this->finalizeDayPayload($dayKey, $payload, $existingDay, $this->emptyIssueData(), $mealPackageId);
        }

        if ($schoolBreak !== null) {
            $payload = [
                'status' => MonthlyPaymentDay::STATUS_SCHOOL_BREAK,
                'original_daily_price' => 0,
                'discount_percent' => $discountPercent,
                'payable_amount' => 0,
                'cancellation_id' => $cancellation?->id,
                'school_break_id' => $schoolBreak?->id,
                'class_cancellation_id' => $classCancellation?->id,
                'working_day_id' => $workingDay?->id,
            ];

            return $this->finalizeDayPayload($dayKey, $payload, $existingDay, $this->emptyIssueData(), $mealPackageId);
        }

        if ($classCancellation !== null) {
            $payload = [
                'status' => MonthlyPaymentDay::STATUS_CLASS_CANCELLATION,
                'original_daily_price' => 0,
                'discount_percent' => $discountPercent,
                'payable_amount' => 0,
                'cancellation_id' => $cancellation?->id,
                'school_break_id' => $schoolBreak?->id,
                'class_cancellation_id' => $classCancellation?->id,
                'working_day_id' => $workingDay?->id,
            ];

            return $this->finalizeDayPayload($dayKey, $payload, $existingDay, $this->emptyIssueData(), $mealPackageId);
        }

        if ($isAdvanceCancellation) {
            $payload = [
                'status' => MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE,
                'original_daily_price' => 0,
                'discount_percent' => $discountPercent,
                'payable_amount' => 0,
                'cancellation_id' => $cancellation?->id,
                'school_break_id' => $schoolBreak?->id,
                'class_cancellation_id' => $classCancellation?->id,
                'working_day_id' => $workingDay?->id,
            ];

            return $this->finalizeDayPayload($dayKey, $payload, $existingDay, $this->emptyIssueData(), $mealPackageId);
        }

        if ($this->componentService->usesSplitManualTransfer($institution)) {
            $foundationRate = $this->componentService->rateForDate($institution->id, PaymentComponent::FOUNDATION, $date);
            $kindergartenRate = $this->componentService->rateForDate($institution->id, PaymentComponent::KINDERGARTEN, $date);
            $hasRates = $foundationRate !== null && $kindergartenRate !== null;
            $foundationDailyFee = $hasRates ? (int) $foundationRate->amount : 0;
            $kindergartenDailyFee = $hasRates ? (int) $kindergartenRate->amount : 0;
            $kindergartenDiscountAmount = $hasRates
                ? $this->roundCurrency((int) round($kindergartenDailyFee * ($discountPercent / 100)))
                : 0;
            $kindergartenPayableAmount = max(0, $kindergartenDailyFee - $kindergartenDiscountAmount);
            $foundationPayableAmount = $foundationDailyFee;
            $status = $isWorkingSaturday
                ? MonthlyPaymentDay::STATUS_WORKING_SATURDAY
                : MonthlyPaymentDay::STATUS_PAY;

            if (! $hasRates) {
                $status = MonthlyPaymentDay::STATUS_NO_VALID_PRICE;
                $foundationPayableAmount = 0;
                $kindergartenPayableAmount = 0;
                $kindergartenDiscountAmount = 0;
            } elseif ($activeCancellation !== null) {
                $status = MonthlyPaymentDay::STATUS_CANCELLED_AFTER_CLOSING;
            }

            $payload = [
                'status' => $status,
                'original_daily_price' => $foundationDailyFee + $kindergartenDailyFee,
                'discount_percent' => $discountPercent,
                'payable_amount' => $foundationPayableAmount + $kindergartenPayableAmount,
                'foundation_daily_fee' => $foundationDailyFee,
                'foundation_payable_amount' => $foundationPayableAmount,
                'kindergarten_daily_fee' => $kindergartenDailyFee,
                'kindergarten_discount_percent' => $discountPercent,
                'kindergarten_discount_amount' => $kindergartenDiscountAmount,
                'kindergarten_payable_amount' => $kindergartenPayableAmount,
                'cancellation_id' => $cancellation?->id,
                'school_break_id' => $schoolBreak?->id,
                'class_cancellation_id' => $classCancellation?->id,
                'working_day_id' => $workingDay?->id,
            ];

            return $this->finalizeDayPayload(
                $dayKey,
                $payload,
                $existingDay,
                [
                    'missing_discount' => $discountType === null,
                    'missing_meal_package' => false,
                    'missing_price_dates' => $hasRates ? [] : [$dayKey],
                ],
                $mealPackageId
            );
        }

        $priceResult = $this->resolveDailyPrice($resolvedSetting['meal_type_ids'], $date, $supportData['prices'], $resolvedSetting['meal_package']);
        $originalDailyPrice = $priceResult['amount'];
        $basePayableAmount = $this->roundCurrency((int) round($originalDailyPrice * ((100 - $discountPercent) / 100)));
        $payableAmount = $basePayableAmount;

        $issueData = [
            'missing_discount' => $discountType === null,
            'missing_meal_package' => false,
            'missing_price_dates' => $priceResult['complete'] ? [] : [$dayKey],
        ];

        $status = $isWorkingSaturday
            ? MonthlyPaymentDay::STATUS_WORKING_SATURDAY
            : MonthlyPaymentDay::STATUS_PAY;

        if (!$priceResult['complete']) {
            // Ha a gyermek csomagjában szereplő étkezéstípusok közül
            // legalább egyhez nincs érvényes ár beállítva, a teljes napi
            // díjat nullázzuk (nem csak a hiányzó összetevőt hagyjuk ki a
            // kiszámolt összegből) - a dolgozói kalkulátorral
            // (EmployeePaymentObligationCalculatorService::calculateDay())
            // összhangban NEM terheljük a szülőt egy hiányos/hibás
            // árazás miatt téves összeggel. Az érintett nap
            // "missing_price_dates" issue-ként (ld. lejjebb) megjelenik,
            // hogy az intézmény pótolhassa az árat, a hónap pedig emiatt
            // nem zárható le automatikusan (ld. summarizeCloseMonth()).
            $status = MonthlyPaymentDay::STATUS_NO_VALID_PRICE;
            $payableAmount = 0;
        }

        if ($activeCancellation !== null) {
            $status = MonthlyPaymentDay::STATUS_CANCELLED_AFTER_CLOSING;
        }

        if ($discountPercent >= 100 && in_array($status, [
            MonthlyPaymentDay::STATUS_PAY,
            MonthlyPaymentDay::STATUS_WORKING_SATURDAY,
        ], true)) {
            $status = MonthlyPaymentDay::STATUS_FREE_MEAL;
            $payableAmount = 0;
        }

        $payload = [
            'status' => $status,
            'original_daily_price' => $originalDailyPrice,
            'discount_percent' => $discountPercent,
            'payable_amount' => max(0, $payableAmount),
            'foundation_daily_fee' => $originalDailyPrice,
            'foundation_payable_amount' => max(0, $payableAmount),
            'kindergarten_daily_fee' => 0,
            'kindergarten_discount_percent' => 0,
            'kindergarten_discount_amount' => 0,
            'kindergarten_payable_amount' => 0,
            'cancellation_id' => $cancellation?->id,
            'school_break_id' => $schoolBreak?->id,
            'class_cancellation_id' => $classCancellation?->id,
            'working_day_id' => $workingDay?->id,
        ];

        return $this->finalizeDayPayload($dayKey, $payload, $existingDay, $issueData, $mealPackageId);
    }

    private function finalizeDayPayload(
        string $date,
        array $payload,
        ?MonthlyPaymentDay $existingDay,
        array $issueData,
        ?int $mealPackageId
    ): array {
        $finalPayload = [
            'date' => $date,
            ...$payload,
            'foundation_daily_fee' => $payload['foundation_daily_fee'] ?? 0,
            'foundation_payable_amount' => $payload['foundation_payable_amount'] ?? 0,
            'kindergarten_daily_fee' => $payload['kindergarten_daily_fee'] ?? 0,
            'kindergarten_discount_percent' => $payload['kindergarten_discount_percent'] ?? 0,
            'kindergarten_discount_amount' => $payload['kindergarten_discount_amount'] ?? 0,
            'kindergarten_payable_amount' => $payload['kindergarten_payable_amount'] ?? 0,
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
            'missing_discount' => false,
            'missing_meal_package' => false,
            'missing_price_dates' => [],
        ];
    }

    private function mergeIssueData(array $carry, array $daily): array
    {
        $carry['missing_discount'] = $carry['missing_discount'] || ($daily['missing_discount'] ?? false);
        $carry['missing_meal_package'] = $carry['missing_meal_package'] || ($daily['missing_meal_package'] ?? false);
        $carry['missing_price_dates'] = array_values(array_unique([
            ...$carry['missing_price_dates'],
            ...($daily['missing_price_dates'] ?? []),
        ]));

        return $carry;
    }

    private function buildIssues(Child $child, array $issueData): array
    {
        $issues = [];

        if ($issueData['missing_meal_package']) {
            $issues[] = "{$child->name}: nincs aktív menücsomag vagy étkezési beállítás az érintett napokon.";
        }

        if ($issueData['missing_discount']) {
            $issues[] = "{$child->name}: nincs érvényes kedvezmény.";
        }

        foreach ($this->buildMissingPriceIssues($child->name, $issueData['missing_price_dates']) as $issue) {
            $issues[] = $issue;
        }

        return $issues;
    }

    private function buildMissingPriceIssues(string $childName, array $dates): array
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

        return array_map(function (array $range) use ($childName) {
            [$from, $to] = $range;

            if ($from->isSameDay($to)) {
                return sprintf(
                    '%s: nincs érvényes ár %s napra.',
                    $childName,
                    $from->format('Y.m.d.')
                );
            }

            return sprintf(
                '%s: nincs érvényes ár %s–%s között.',
                $childName,
                $from->format('Y.m.d.'),
                $to->format('Y.m.d.')
            );
        }, $ranges);
    }

    private function childrenForMonth(Institution $institution, Carbon $period, Carbon $endOfMonth, ?Collection $childIds): Collection
    {
        $children = Child::query()
            ->with([
                'discountType',
                'discountPeriods' => fn ($query) => $query
                    ->with('discountType')
                    ->whereDate('valid_from', '<=', $endOfMonth->toDateString())
                    ->where(function ($query) use ($period) {
                        $query->whereNull('valid_to')
                            ->orWhereDate('valid_to', '>=', $period->toDateString());
                    })
                    ->orderByDesc('valid_from')
                    ->orderByDesc('id'),
            ])
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->when($childIds !== null && $childIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $childIds))
            ->whereExists(function ($query) use ($institution, $period, $endOfMonth) {
                $query->selectRaw('1')
                    ->from('student_meal_settings')
                    ->whereColumn('student_meal_settings.student_id', 'children.id')
                    ->where('student_meal_settings.institution_id', $institution->id)
                    ->whereDate('student_meal_settings.valid_from', '<=', $endOfMonth->toDateString())
                    ->where(function ($query) use ($period) {
                        $query->whereNull('student_meal_settings.valid_to')
                            ->orWhereDate('student_meal_settings.valid_to', '>=', $period->toDateString());
                    });
            })
            ->orderBy('name')
            ->get();

        if ($childIds === null || $childIds->isEmpty()) {
            return $children;
        }

        $selectedChildren = Child::query()
            ->with([
                'discountType',
                'discountPeriods' => fn ($query) => $query
                    ->with('discountType')
                    ->whereDate('valid_from', '<=', $endOfMonth->toDateString())
                    ->where(function ($query) use ($period) {
                        $query->whereNull('valid_to')
                            ->orWhereDate('valid_to', '>=', $period->toDateString());
                    })
                    ->orderByDesc('valid_from')
                    ->orderByDesc('id'),
            ])
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->whereIn('id', $childIds)
            ->orderBy('name')
            ->get();

        return $selectedChildren
            ->merge($children)
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

        $classCancellations = ClassCancellation::query()
            ->where('institution_id', $institution->id)
            ->whereDate('date_from', '<=', $endOfMonth->toDateString())
            ->whereDate('date_to', '>=', $period->toDateString())
            ->get();

        $cancellations = MealCancellation::query()
            ->where('institution_id', $institution->id)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$period->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->groupBy('child_id')
            ->map(fn (Collection $items) => $items->keyBy(fn (MealCancellation $item) => $item->service_date->toDateString()))
            ->all();

        $recurringRules = RecurringCancellationRule::query()
            ->where('institution_id', $institution->id)
            ->where('status', RecurringCancellationRule::STATUS_ACTIVE)
            ->whereDate('starts_on', '<=', $endOfMonth->toDateString())
            ->where(function ($query) use ($period) {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $period->toDateString());
            })
            ->get()
            ->groupBy('child_id');

        $memberships = DB::table('class_group_memberships')
            ->select(['class_group_id', 'child_id', 'joined_on', 'left_on', 'status'])
            ->where('status', 'active')
            ->get()
            ->groupBy('child_id');

        return [
            'prices' => $prices,
            'school_breaks' => $schoolBreaks,
            'working_days' => $workingDays,
            'class_cancellations' => $classCancellations,
            'cancellations' => $cancellations,
            'recurring_rules' => $recurringRules,
            'memberships' => $memberships,
        ];
    }

    private function resolveMealSetting(Institution $institution, Child $child, Carbon $date): array
    {
        $setting = StudentMealSetting::query()
            ->with(['mealPackage.items', 'items'])
            ->where('student_id', $child->id)
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
            // Egyedi csomagár módban a csomaghoz rögzített fix ár számít az
            // adott napra, NEM az egyes étkezéstípusok árának összege (ld.
            // component_sum mód lejjebb). Ha a mód be van állítva, de az ár
            // valamiért mégis hiányzik (pl. régi, migráció előtti rekord),
            // ugyanúgy hiányos napként jelöljük, mint a hiányzó
            // étkezéstípus-árakat - lásd calculateDay() STATUS_NO_VALID_PRICE
            // ága - hogy az intézmény pótolhassa, a hónap pedig ne záruljon
            // le csendben téves (0 Ft-os) összeggel.
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

    private function resolveClassCancellation(
        Child $child,
        Carbon $date,
        Collection $classCancellations,
        Collection $memberships
    ): ?ClassCancellation {
        $childMemberships = collect($memberships->get($child->id));

        if ($childMemberships->isEmpty()) {
            return null;
        }

        $activeGroupIds = $childMemberships->filter(function ($membership) use ($date) {
            $joinedOn = $membership->joined_on ? Carbon::parse($membership->joined_on) : null;
            $leftOn = $membership->left_on ? Carbon::parse($membership->left_on) : null;

            return ($joinedOn === null || $joinedOn->toDateString() <= $date->toDateString())
                && ($leftOn === null || $leftOn->toDateString() >= $date->toDateString());
        })->pluck('class_group_id');

        return $classCancellations->first(function (ClassCancellation $cancellation) use ($activeGroupIds, $date) {
            return $activeGroupIds->contains($cancellation->class_group_id)
                && $date->betweenIncluded($cancellation->date_from, $cancellation->date_to);
        });
    }

    private function resolveRecurringCancellation(Child $child, Carbon $date, Collection $recurringRules): ?RecurringCancellationRule
    {
        return collect($recurringRules->get($child->id))->first(function (RecurringCancellationRule $rule) use ($date) {
            return $rule->weekday === $date->dayOfWeekIso
                && $rule->starts_on->toDateString() <= $date->toDateString()
                && ($rule->ends_on === null || $rule->ends_on->toDateString() >= $date->toDateString());
        });
    }

    private function isAdvanceCancellation(Institution $institution, object $cancellation, Carbon $serviceDate): bool
    {
        $deadline = $this->calendar->cancellationDeadline($institution->id, $serviceDate);

        if ($deadline === null) {
            return false;
        }

        $recordedAt = Carbon::parse($cancellation->created_at ?? $cancellation->starts_on ?? $serviceDate)
            ->setTimezone($deadline->getTimezone());

        return $recordedAt->lte($deadline);
    }

    private function syncLateCancellationCredits(
        Institution $institution,
        Child $child,
        Carbon $paymentPeriod,
        Carbon $creditPeriod,
        Carbon $creditPeriodEnd
    ): void
    {
        $lateCancellations = MealCancellation::query()
            ->where('institution_id', $institution->id)
            ->where('child_id', $child->id)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$creditPeriod->toDateString(), $creditPeriodEnd->toDateString()])
            ->get();

        $sourcePaymentPeriod = $creditPeriod->copy()->subMonth()->startOfMonth();

        foreach ($lateCancellations as $cancellation) {
            if ($this->isAdvanceCancellation($institution, $cancellation, $cancellation->service_date)) {
                continue;
            }

            $day = MonthlyPaymentDay::query()
                ->whereDate('date', $cancellation->service_date->toDateString())
                ->whereHas('statement', function ($query) use ($institution, $child, $sourcePaymentPeriod) {
                    $query->where('institution_id', $institution->id)
                        ->where('child_id', $child->id)
                        ->where('year', $sourcePaymentPeriod->year)
                        ->where('month', $sourcePaymentPeriod->month);
                })
                ->first();

            if ($day === null || $day->payable_amount <= 0) {
                continue;
            }

            $day->loadMissing('statement');

            if ($day->statement?->usesSplitPaymentModel()) {
                foreach ([
                    PaymentComponent::FOUNDATION => (int) $day->foundation_payable_amount,
                    PaymentComponent::KINDERGARTEN => (int) $day->kindergarten_payable_amount,
                ] as $component => $amount) {
                    if ($amount < 1) {
                        continue;
                    }

                    FinancialAdjustment::query()->updateOrCreate(
                        [
                            'institution_id' => $institution->id,
                            'child_id' => $child->id,
                            'type' => FinancialAdjustment::TYPE_CANCELLATION_CREDIT,
                            'payment_component' => $component,
                            'source_type' => MealCancellation::class,
                            'source_id' => $cancellation->id,
                        ],
                        [
                            'monthly_payment_statement_id' => null,
                            'amount' => $amount,
                            'affects_invoice' => true,
                            'reference_year' => $paymentPeriod->year,
                            'reference_month' => $paymentPeriod->month,
                            'reason' => 'Késői lemondás jóváírása: '.$cancellation->service_date->format('Y.m.d.'),
                            'entry_date' => $paymentPeriod->toDateString(),
                            'created_by' => $cancellation->created_by,
                            'reversed_at' => null,
                            'reversed_by' => null,
                            'reversal_reason' => null,
                        ]
                    );
                }

                continue;
            }

            FinancialAdjustment::query()->updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'child_id' => $child->id,
                    'type' => FinancialAdjustment::TYPE_CANCELLATION_CREDIT,
                    'source_type' => MealCancellation::class,
                    'source_id' => $cancellation->id,
                ],
                [
                    'amount' => $day->payable_amount,
                    'affects_invoice' => true,
                    'reference_year' => $paymentPeriod->year,
                    'reference_month' => $paymentPeriod->month,
                    'reason' => 'Késői lemondás jóváírása: '.$cancellation->service_date->format('Y.m.d.'),
                    'entry_date' => $paymentPeriod->toDateString(),
                    'created_by' => $cancellation->created_by,
                    'reversed_at' => null,
                    'reversed_by' => null,
                    'reversal_reason' => null,
                ]
            );
        }
    }

    private function sumCancellationCredits(int $institutionId, int $childId, Carbon $period): int
    {
        return (int) FinancialAdjustment::query()
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
            ->where('type', FinancialAdjustment::TYPE_CANCELLATION_CREDIT)
            ->where('affects_invoice', true)
            ->where('reference_year', $period->year)
            ->where('reference_month', $period->month)
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    private function sumBillingAdjustments(int $institutionId, int $childId, Carbon $period): int
    {
        return (int) FinancialAdjustment::query()
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
            ->where('affects_invoice', true)
            ->where('reference_year', $period->year)
            ->where('reference_month', $period->month)
            ->where('type', '!=', FinancialAdjustment::TYPE_CANCELLATION_CREDIT)
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    private function sumPreviousBalance(int $institutionId, int $childId, Carbon $period): int
    {
        return (int) FinancialAdjustment::query()
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
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

    // Automatikus elmaradás-göngyölítés (ld. felhasználói kérés: "ellenőrizd,
    // hogy az előző havi elmaradásokat ahol nincs befizetés, ott az összeg
    // automatikusan hozzáadódjon a fizetendő összeghez"). Korábban a
    // "Korábbi egyenleg" (previous_balance) KIZÁRÓLAG kézzel felvitt
    // FinancialAdjustment rekordokból (ld. sumPreviousBalance() feljebb)
    // állt össze - egy korábbi, lezárt hónap ki nem fizetett tartozása
    // magától NEM került át a következő hónapra, csak ha az intézmény
    // adminja kézzel felvett rá egy korrekciót. Ez a metódus minden, a
    // vizsgált fizetési hónapnál KORÁBBI és már LEZÁRT (vagy azon túli
    // állapotú) statementet megnéz, és a ténylegesen befolyt (completed)
    // befizetésekkel csökkentett, még fennmaradó összegüket adja hozzá -
    // mindig az AKTUÁLIS állapot alapján (ha időközben történt egy
    // részbefizetés, a következő újraszámoláskor ez automatikusan
    // frissül). A még LEZÁRATLAN (draft/reviewed) hónapok tartozását
    // szándékosan NEM számítjuk bele, mert azok összege még változhat.
    private function sumUnpaidPriorStatements(int $institutionId, int $childId, Carbon $paymentPeriod): int
    {
        $priorStatements = MonthlyPaymentStatement::query()
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
            ->whereIn('status', [
                MonthlyPaymentStatement::STATUS_CLOSED,
                MonthlyPaymentStatement::STATUS_SENT_TO_INVOICING,
                MonthlyPaymentStatement::STATUS_INVOICED,
            ])
            ->where(function ($query) use ($paymentPeriod) {
                $query->where('year', '<', $paymentPeriod->year)
                    ->orWhere(function ($query) use ($paymentPeriod) {
                        $query->where('year', $paymentPeriod->year)
                            ->where('month', '<', $paymentPeriod->month);
                    });
            })
            ->get(['id', 'total_payable']);

        if ($priorStatements->isEmpty()) {
            return 0;
        }

        $completedByStatement = InstitutionPayment::query()
            ->selectRaw('monthly_payment_statement_id, SUM(amount) as completed_total')
            ->where('institution_id', $institutionId)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->whereIn('monthly_payment_statement_id', $priorStatements->pluck('id'))
            ->groupBy('monthly_payment_statement_id')
            ->pluck('completed_total', 'monthly_payment_statement_id');

        return (int) $priorStatements->sum(function (MonthlyPaymentStatement $priorStatement) use ($completedByStatement) {
            $completed = (int) ($completedByStatement[$priorStatement->id] ?? 0);

            return max(0, (int) $priorStatement->total_payable - $completed);
        });
    }

    private function roundCurrency(int|float $amount): int
    {
        return (int) round($amount, 0, PHP_ROUND_HALF_UP);
    }

    private function resolvePeriods(Carbon $month): array
    {
        $paymentPeriod = $month->copy()->startOfMonth();
        $mealPeriod = $paymentPeriod->copy()->addMonth();
        $creditPeriod = $paymentPeriod->copy()->subMonth();

        return [
            'payment_period' => $paymentPeriod,
            'meal_period' => $mealPeriod,
            'meal_period_end' => $mealPeriod->copy()->endOfMonth(),
            'credit_period' => $creditPeriod,
            'credit_period_end' => $creditPeriod->copy()->endOfMonth(),
        ];
    }

    private function countCreditedCancellationDays(Institution $institution, Child $child, Carbon $paymentPeriod): int
    {
        return (int) FinancialAdjustment::query()
            ->where('institution_id', $institution->id)
            ->where('child_id', $child->id)
            ->where('type', FinancialAdjustment::TYPE_CANCELLATION_CREDIT)
            ->where('reference_year', $paymentPeriod->year)
            ->where('reference_month', $paymentPeriod->month)
            ->whereNull('reversed_at')
            ->distinct('source_id')
            ->count('source_id');
    }

    private function buildSplitSnapshot(
        Institution $institution,
        Child $child,
        Carbon $paymentPeriod,
        array $days,
        array $totals
    ): array {
        return [
            'payment_model' => InstitutionPaymentComponentService::PAYMENT_MODEL_SPLIT_MANUAL_TRANSFER,
            'payment_period' => $paymentPeriod->format('Y-m'),
            'child_id' => $child->id,
            'foundation_account_holder' => $institution->setting?->foundation_account_holder,
            'foundation_account_number' => $institution->setting?->foundation_account_number,
            'foundation_transfer_reference' => $institution->setting?->foundation_transfer_reference,
            'kindergarten_account_holder' => $institution->setting?->kindergarten_account_holder,
            'kindergarten_account_number' => $institution->setting?->kindergarten_account_number,
            'kindergarten_transfer_reference' => $institution->setting?->kindergarten_transfer_reference,
            'totals' => $totals,
            'days' => collect($days)->map(fn (array $day) => [
                'date' => $day['date'],
                'foundation_daily_fee' => $day['foundation_daily_fee'],
                'foundation_payable_amount' => $day['foundation_payable_amount'],
                'kindergarten_daily_fee' => $day['kindergarten_daily_fee'],
                'kindergarten_discount_percent' => $day['kindergarten_discount_percent'],
                'kindergarten_discount_amount' => $day['kindergarten_discount_amount'],
                'kindergarten_payable_amount' => $day['kindergarten_payable_amount'],
            ])->values()->all(),
        ];
    }
}
