<?php

namespace App\Services\EmployeePortal;

use App\Models\InstitutionEmployeePayment;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * A dolgozói portál "Befizetéseim" oldala - az
 * App\Services\ParentPortal\ParentPaymentPageService egyszerűsített
 * megfelelője. A szülői verzió a ParentMonthlySettlementService-re épül,
 * ami a CIB bankkártyás fizetés-indítás állapotgépét
 * (ready_to_pay/partially_paid/paid/no_amount_due) is kezeli - a dolgozói
 * V1 scope-ja explicit kizárja az online fizetés-indítást (ld. a
 * jóváhagyott terv "Scope-határ" szakaszát), ezért ez a szolgáltatás csak
 * megtekintést nyújt: a legutóbbi havi elszámolás egyenlegét és a korábbi
 * befizetések előzményét.
 */
class EmployeePaymentPageService
{
    public function buildPageData(User $user): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $employeeIds = $user->employees()
            ->where('active', true)
            ->pluck('institution_employees.id');

        $statements = $this->loadStatements($employeeIds);
        $paymentsByStatement = $this->loadPaymentsByStatement($statements->pluck('id'));
        $history = $this->loadHistory($employeeIds);

        return [
            'stats' => $this->buildStats($statements, $paymentsByStatement, $employeeIds, $today),
            'current_statement' => $this->buildCurrentStatement($statements, $paymentsByStatement),
            'employees_count' => $employeeIds->count(),
            'has_employees' => $employeeIds->isNotEmpty(),
            'history' => $history,
        ];
    }

    private function loadStatements(Collection $employeeIds): Collection
    {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        return EmployeeMonthlyPaymentStatement::query()
            ->whereIn('institution_employee_id', $employeeIds->all())
            ->with(['employee', 'institution'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }

    private function loadPaymentsByStatement(Collection $statementIds): Collection
    {
        if ($statementIds->isEmpty()) {
            return collect();
        }

        return InstitutionEmployeePayment::query()
            ->selectRaw('employee_monthly_payment_statement_id, SUM(amount) as total_paid')
            ->whereIn('employee_monthly_payment_statement_id', $statementIds)
            ->where('status', InstitutionEmployeePayment::STATUS_COMPLETED)
            ->groupBy('employee_monthly_payment_statement_id')
            ->get()
            ->mapWithKeys(fn ($payment) => [(int) $payment->employee_monthly_payment_statement_id => (int) $payment->total_paid]);
    }

    private function buildStats(
        Collection $statements,
        Collection $paymentsByStatement,
        Collection $employeeIds,
        CarbonImmutable $today
    ): array {
        $remainingByStatement = $statements->map(function (EmployeeMonthlyPaymentStatement $statement) use ($paymentsByStatement) {
            $paid = (int) ($paymentsByStatement->get($statement->id) ?? 0);

            return [
                'statement' => $statement,
                'remaining' => max(0, (int) $statement->total_payable - $paid),
            ];
        });

        $openItems = $remainingByStatement->filter(fn (array $item) => $item['remaining'] > 0)->values();
        $nextDueItem = $openItems
            ->filter(fn (array $item) => $item['statement']->due_date !== null)
            ->sortBy(fn (array $item) => $item['statement']->due_date->timestamp)
            ->first();

        $paidThisMonth = $employeeIds->isEmpty()
            ? 0
            : (int) InstitutionEmployeePayment::query()
                ->whereIn('institution_employee_id', $employeeIds->all())
                ->where('status', InstitutionEmployeePayment::STATUS_COMPLETED)
                ->whereBetween('paid_at', [$today->startOfMonth(), $today->endOfMonth()])
                ->sum('amount');

        $nextDueDate = $nextDueItem['statement']->due_date ?? null;
        $openItemsCount = $openItems->count();

        return [
            'outstanding_total' => (int) $openItems->sum('remaining'),
            'outstanding_total_label' => $this->formatForint((int) $openItems->sum('remaining')),
            'next_due_date_label' => $nextDueDate?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs határidő',
            'next_due_helper' => $nextDueDate === null
                ? 'Nincs nyitott fizetési kötelezettség'
                : $this->formatDaysRemaining($today->diffInDays($nextDueDate->startOfDay(), false)),
            'paid_this_month' => $paidThisMonth,
            'paid_this_month_label' => $this->formatForint($paidThisMonth),
            'open_items_count' => $openItemsCount,
            'open_items_label' => $openItemsCount === 1 ? '1 tétel' : $openItemsCount.' tétel',
        ];
    }

    private function buildCurrentStatement(Collection $statements, Collection $paymentsByStatement): ?array
    {
        $latest = $statements->first();

        if ($latest === null) {
            return null;
        }

        $paid = (int) ($paymentsByStatement->get($latest->id) ?? 0);
        $remaining = max(0, (int) $latest->total_payable - $paid);
        $isOverdue = $remaining > 0
            && $latest->due_date !== null
            && $latest->due_date->startOfDay()->lt(CarbonImmutable::now(config('app.timezone'))->startOfDay());

        $badge = match (true) {
            $remaining <= 0 => ['label' => 'Fizetve', 'class' => 'bg-success'],
            $isOverdue => ['label' => 'Lejárt', 'class' => 'bg-danger'],
            $paid > 0 => ['label' => 'Részben fizetve', 'class' => 'bg-primary'],
            default => ['label' => 'Fizetésre vár', 'class' => 'bg-warning text-dark'],
        };

        // A "status_card" mező kizárólag megjelenítési célú kiegészítés (a
        // ParentPaymentPageService/ParentMonthlySettlementService status_card
        // mintáját követi) - nem vezet be új üzleti logikát, csak ugyanazt a
        // badge-számítást tükrözi vissza egy ikonos/kereten kiemelt dobozhoz.
        // A dolgozói V1 scope nem tartalmaz online kártyás fizetés-indítást,
        // ezért itt nincs "can_pay" vagy hasonló mező.
        $statusCard = match (true) {
            $remaining <= 0 => [
                'status' => 'paid',
                'icon' => 'fa-solid fa-circle-check',
                'color_class' => 'border-success',
                'title' => 'Az elszámolás rendezve',
                'description' => 'A legutóbbi havi elszámolás teljes összege ki van fizetve.',
            ],
            $isOverdue => [
                'status' => 'overdue',
                'icon' => 'fa-solid fa-triangle-exclamation',
                'color_class' => 'border-danger',
                'title' => 'A fizetési határidő lejárt',
                'description' => 'Kérjük, mielőbb rendezze a hátralévő összeget az intézménynél.',
            ],
            $paid > 0 => [
                'status' => 'partially_paid',
                'icon' => 'fa-solid fa-circle-half-stroke',
                'color_class' => 'border-primary',
                'title' => 'Részben fizetve',
                'description' => 'A hátralévő összeg befizetése még folyamatban van.',
            ],
            default => [
                'status' => 'ready_to_pay',
                'icon' => 'fa-solid fa-hourglass-half',
                'color_class' => 'border-warning',
                'title' => 'Fizetésre vár',
                'description' => 'Az összeg befizetése még nem történt meg.',
            ],
        };

        return [
            'period_label' => CarbonImmutable::create($latest->year, $latest->month, 1, 0, 0, 0, config('app.timezone'))
                ->locale('hu')
                ->isoFormat('YYYY. MMMM'),
            'total_payable_label' => $this->formatForint((int) $latest->total_payable),
            'paid_label' => $this->formatForint($paid),
            'remaining' => $remaining,
            'remaining_label' => $this->formatForint($remaining),
            'due_date_label' => $latest->due_date?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs határidő',
            'badge' => $badge,
            'status_card' => $statusCard,
            'progress_percent' => $latest->total_payable > 0
                ? (int) round(min(100, ($paid / $latest->total_payable) * 100))
                : 100,
            'employee_name' => $latest->employee?->name,
        ];
    }

    private function loadHistory(Collection $employeeIds): LengthAwarePaginator
    {
        $paginator = InstitutionEmployeePayment::query()
            ->whereIn('institution_employee_id', $employeeIds->all())
            ->with(['employee', 'statement'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'history_page')
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(function (InstitutionEmployeePayment $payment) {
                $statement = $payment->statement;
                $status = $this->historyStatusMeta($payment->status);
                $periodLabel = $statement !== null
                    ? CarbonImmutable::create($statement->year, $statement->month, 1, 0, 0, 0, config('app.timezone'))
                        ->locale('hu')->isoFormat('YYYY. MMMM')
                    : 'Nincs időszak';

                return [
                    'id' => $payment->id,
                    'paid_at_label' => $payment->paid_at?->timezone(config('app.timezone'))->locale('hu')->isoFormat('YYYY. MMMM D.')
                        ?? 'Nincs dátum',
                    'period_label' => $periodLabel,
                    'amount_label' => $this->formatForint((int) $payment->amount),
                    'payment_method_label' => InstitutionEmployeePayment::paymentMethodOptions()[$payment->payment_method] ?? 'Nincs megadva',
                    'reference' => $payment->reference ?: ($payment->invoice_number ?: 'Nincs azonosító'),
                    'status' => $status,
                ];
            })
        );

        return $paginator;
    }

    private function historyStatusMeta(?string $status): array
    {
        return match ($status) {
            InstitutionEmployeePayment::STATUS_PENDING => ['label' => 'Feldolgozás alatt', 'class' => 'bg-warning text-dark'],
            InstitutionEmployeePayment::STATUS_COMPLETED => ['label' => 'Sikeres', 'class' => 'bg-success'],
            InstitutionEmployeePayment::STATUS_FAILED => ['label' => 'Sikertelen', 'class' => 'bg-danger'],
            InstitutionEmployeePayment::STATUS_REFUNDED => ['label' => 'Visszatérítve', 'class' => 'bg-info text-dark'],
            default => ['label' => 'Nincs megadva', 'class' => 'bg-light text-muted border'],
        };
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

    private function formatForint(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Ft';
    }
}
