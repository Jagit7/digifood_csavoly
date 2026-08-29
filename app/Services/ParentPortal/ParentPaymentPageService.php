<?php

namespace App\Services\ParentPortal;

use App\Models\Child;
use App\Models\InstitutionPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\Finance\InstitutionPaymentComponentService;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ParentPaymentPageService
{
    public function __construct(
        private readonly ParentMonthlySettlementService $monthlySettlementService,
        private readonly InstitutionPaymentComponentService $componentService,
    ) {
    }

    public function buildPageData(User $user): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $currentMonth = $today->startOfMonth();
        $guardianIds = $user->guardians()
            ->where('active', true)
            ->pluck('guardians.id');

        $children = Child::query()
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', $guardianIds))
            ->with(['institution.setting', 'discountType'])
            ->orderBy('name')
            ->get();

        $childIds = $children->pluck('id');
        $statements = $this->loadStatements($childIds, $currentMonth);
        $paymentsByStatement = $this->loadPaymentsByStatement($statements->pluck('id'));
        $history = $this->loadHistory($childIds);
        $focusMonth = $this->determineFocusMonth($statements, $paymentsByStatement, $currentMonth);
        $focusPage = $this->monthlySettlementService->buildPageData($user, $focusMonth);

        return [
            'stats' => $this->buildStats($statements, $paymentsByStatement, $childIds, $today),
            'current_obligation' => $this->buildCurrentObligation($focusPage['summary'], $focusPage['month_label']),
            'focus_month_query' => $focusPage['month_query'],
            'focus_month_label' => $focusPage['month_label'],
            'focus_summary' => $focusPage['summary'],
            'focus_child_cards' => $focusPage['child_cards'],
            'focus_merchant_profile' => $focusPage['merchant_profile'],
            'focus_bank_transfer' => $focusPage['bank_transfer'],
            'children_count' => $children->count(),
            'has_children' => $children->isNotEmpty(),
            'history' => $history,
        ];
    }

    private function loadStatements(Collection $childIds, CarbonImmutable $currentMonth): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        $from = $currentMonth->subMonths(12);
        $to = $currentMonth->addMonth();
        $fromKey = ((int) $from->year * 100) + (int) $from->month;
        $toKey = ((int) $to->year * 100) + (int) $to->month;

        return MonthlyPaymentStatement::query()
            ->whereIn('child_id', $childIds)
            ->whereRaw('(year * 100 + month) between ? and ?', [$fromKey, $toKey])
            ->with([
                'child.institution.setting',
                'discount',
                'mealPackage',
                'days.cancellation',
                'days.classCancellation',
                'days.schoolBreak',
                'days.workingDay',
            ])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('child_id')
            ->get();
    }

    private function loadPaymentsByStatement(Collection $statementIds): Collection
    {
        if ($statementIds->isEmpty()) {
            return collect();
        }

        $statements = MonthlyPaymentStatement::query()
            ->whereIn('id', $statementIds)
            ->get();

        return $this->componentService->buildStatementSummaries($statements)
            ->mapWithKeys(fn (array $summary, int $statementId) => [$statementId => (int) ($summary['paid_total'] ?? 0)]);
    }

    private function determineFocusMonth(
        Collection $statements,
        Collection $paymentsByStatement,
        CarbonImmutable $currentMonth
    ): CarbonImmutable {
        if ($statements->isEmpty()) {
            return $currentMonth;
        }

        $groups = $this->buildMonthGroups($statements, $paymentsByStatement);
        $currentKey = $currentMonth->format('Y-m');

        if ($groups->has($currentKey)) {
            return $currentMonth;
        }

        $priorityGroup = $groups
            ->filter(fn (array $group) => $group['remaining_total'] > 0 || $group['has_issues'] || ! $group['all_closed'])
            ->sortBy([
                fn (array $group) => $group['due_date']?->timestamp ?? PHP_INT_MAX,
                fn (array $group) => -1 * $group['period']->timestamp,
            ])
            ->first();

        if ($priorityGroup !== null) {
            return $priorityGroup['period'];
        }

        return $groups
            ->sortByDesc(fn (array $group) => $group['period']->timestamp)
            ->first()['period'];
    }

    private function buildMonthGroups(Collection $statements, Collection $paymentsByStatement): Collection
    {
        return $statements
            ->groupBy(fn (MonthlyPaymentStatement $statement) => sprintf('%04d-%02d', $statement->year, $statement->month))
            ->map(function (Collection $monthStatements) use ($paymentsByStatement) {
                $first = $monthStatements->first();
                $period = CarbonImmutable::create(
                    $first->year,
                    $first->month,
                    1,
                    0,
                    0,
                    0,
                    config('app.timezone')
                )->startOfMonth();
                $remainingTotal = (int) $monthStatements->sum(function (MonthlyPaymentStatement $statement) use ($paymentsByStatement) {
                    $paid = (int) ($paymentsByStatement->get($statement->id) ?? 0);

                    return max(0, (int) $statement->total_payable - $paid);
                });

                return [
                    'period' => $period,
                    'remaining_total' => $remainingTotal,
                    'has_issues' => $monthStatements->contains(fn (MonthlyPaymentStatement $statement) => ! empty($statement->issues ?? [])),
                    'all_closed' => $monthStatements->every(fn (MonthlyPaymentStatement $statement) => $statement->isClosed()),
                    'due_date' => $this->resolveDueDate($period, $monthStatements),
                ];
            });
    }

    private function buildStats(
        Collection $statements,
        Collection $paymentsByStatement,
        Collection $childIds,
        CarbonImmutable $today
    ): array {
        $remainingByStatement = $statements->map(function (MonthlyPaymentStatement $statement) use ($paymentsByStatement) {
            $paid = (int) ($paymentsByStatement->get($statement->id) ?? 0);

            return [
                'statement' => $statement,
                'remaining' => max(0, (int) $statement->total_payable - $paid),
            ];
        });

        $openItems = $remainingByStatement->filter(fn (array $item) => $item['remaining'] > 0)->values();
        $nextDueStatement = $openItems
            ->map(function (array $item) {
                $period = CarbonImmutable::create(
                    $item['statement']->year,
                    $item['statement']->month,
                    1,
                    0,
                    0,
                    0,
                    config('app.timezone')
                )->startOfMonth();

                return [
                    'due_date' => $this->resolveDueDate($period, collect([$item['statement']])),
                    'remaining' => $item['remaining'],
                ];
            })
            ->filter(fn (array $item) => $item['due_date'] !== null)
            ->sortBy(fn (array $item) => $item['due_date']->timestamp)
            ->first();

        $paidThisMonth = $childIds->isEmpty()
            ? 0
            : (int) InstitutionPayment::query()
                ->whereIn('child_id', $childIds)
                ->where('status', InstitutionPayment::STATUS_COMPLETED)
                ->whereBetween('paid_at', [$today->startOfMonth(), $today->endOfMonth()])
                ->sum('amount');

        $nextDueDate = $nextDueStatement['due_date'] ?? null;
        $openItemsCount = $openItems->count();

        return [
            'outstanding_total' => (int) $openItems->sum('remaining'),
            'next_due_date_label' => $nextDueDate?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs határidő',
            'next_due_helper' => $nextDueDate === null
                ? 'Nincs nyitott fizetési kötelezettség'
                : $this->formatDaysRemaining($today->diffInDays($nextDueDate->startOfDay(), false)),
            'paid_this_month' => $paidThisMonth,
            'open_items_count' => $openItemsCount,
            'open_items_label' => $openItemsCount === 1 ? '1 tétel' : $openItemsCount.' tétel',
        ];
    }

    private function buildCurrentObligation(array $summary, string $monthLabel): array
    {
        $statusCard = $summary['status_card'];
        $daysRemaining = $statusCard['days_remaining'];
        $badge = match (true) {
            in_array($statusCard['status'], ['ready_to_pay', 'partially_paid'], true) && $daysRemaining !== null && $daysRemaining < 0
                => ['label' => 'Lejárt', 'class' => 'bg-danger'],
            $statusCard['status'] === 'partially_paid'
                => ['label' => 'Részben fizetve', 'class' => 'bg-primary'],
            $statusCard['status'] === 'paid'
                => ['label' => 'Fizetve', 'class' => 'bg-success'],
            $statusCard['status'] === 'ready_to_pay'
                => ['label' => 'Fizetésre vár', 'class' => 'bg-danger'],
            $statusCard['status'] === 'no_amount_due'
                => ['label' => 'Nincs fizetendő összeg', 'class' => 'bg-light text-muted border'],
            default
                => ['label' => 'Előkészítés alatt', 'class' => 'bg-warning text-dark'],
        };

        $progressPercent = $summary['total_payable'] > 0
            ? (int) round(min(100, ($summary['paid_total'] / $summary['total_payable']) * 100))
            : 100;

        return [
            'month_label' => $monthLabel,
            'badge' => $badge,
            'status_card' => $statusCard,
            'show_payment_guidance' => in_array($statusCard['status'], ['ready_to_pay', 'partially_paid'], true)
                && ! $summary['payment_enabled']
                && $summary['remaining_total'] > 0,
            'progress_percent' => $progressPercent,
        ];
    }

    private function loadHistory(Collection $childIds): LengthAwarePaginator
    {
        $paginator = InstitutionPayment::query()
            ->whereIn('child_id', $childIds->all())
            ->with([
                'child',
                'monthlyPaymentStatement.invoice',
            ])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'history_page')
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(function (InstitutionPayment $payment) {
                $statement = $payment->monthlyPaymentStatement;
                $status = $this->historyStatusMeta($payment->status);
                $paymentMethod = InstitutionPayment::paymentMethodMeta($payment->payment_method);
                $periodLabel = $statement !== null
                    ? CarbonImmutable::create(
                        $statement->year,
                        $statement->month,
                        1,
                        0,
                        0,
                        0,
                        config('app.timezone')
                    )->locale('hu')->isoFormat('YYYY. MMMM')
                    : 'Nincs időszak';

                return [
                    'id' => $payment->id,
                    'paid_at_label' => $payment->paid_at?->timezone(config('app.timezone'))->locale('hu')->isoFormat('YYYY. MMMM D.')
                        ?? 'Nincs dátum',
                    'period_label' => $periodLabel,
                    'child_names' => collect([$payment->child?->name])->filter()->values(),
                    'amount' => (int) $payment->amount,
                    'payment_method' => $paymentMethod,
                    'reference' => $payment->reference ?: $payment->invoice_number ?: 'Nincs azonosító',
                    'status' => $status,
                    'receipt_url' => $statement?->invoice?->invoice_url,
                    'details' => [
                        'child_name' => $payment->child?->name ?? 'Ismeretlen gyermek',
                        'statement_total' => (int) ($statement?->total_payable ?? 0),
                        'reference' => $payment->reference,
                        'invoice_number' => $payment->invoice_number,
                    ],
                ];
            })
        );

        return $paginator;
    }

    private function historyStatusMeta(?string $status): array
    {
        return match ($status) {
            InstitutionPayment::STATUS_PENDING => ['label' => 'Feldolgozás alatt', 'class' => 'bg-warning text-dark'],
            InstitutionPayment::STATUS_COMPLETED => ['label' => 'Sikeres', 'class' => 'bg-success'],
            InstitutionPayment::STATUS_FAILED => ['label' => 'Sikertelen', 'class' => 'bg-danger'],
            InstitutionPayment::STATUS_REFUNDED => ['label' => 'Visszatérítve', 'class' => 'bg-info text-dark'],
            InstitutionPayment::STATUS_CANCELLED => ['label' => 'Megszakítva', 'class' => 'bg-secondary'],
            default => ['label' => 'Nincs megadva', 'class' => 'bg-light text-muted border'],
        };
    }

    private function resolveDueDate(CarbonImmutable $month, Collection $statements): ?CarbonImmutable
    {
        $dueDays = $statements
            ->map(fn (MonthlyPaymentStatement $statement) => (int) ($statement->child?->institution?->setting?->payment_due_day ?? 0))
            ->filter(fn (int $day) => $day > 0)
            ->map(fn (int $day) => min(28, $day));

        if ($dueDays->isEmpty()) {
            return null;
        }

        return $month->setDay($dueDays->min());
    }

    private function formatDaysRemaining(?int $daysRemaining): string
    {
        if ($daysRemaining === null) {
            return 'Nincs határidő';
        }

        if ($daysRemaining < 0) {
            return 'Lejárt';
        }

        if ($daysRemaining === 0) {
            return 'Ma jár le';
        }

        if ($daysRemaining === 1) {
            return '1 nap van hátra';
        }

        return $daysRemaining.' nap van hátra';
    }
}
