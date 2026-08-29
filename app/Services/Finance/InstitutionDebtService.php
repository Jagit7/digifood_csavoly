<?php

namespace App\Services\Finance;

use App\Models\BillingProfile;
use App\Models\Institution;
use App\Models\InstitutionPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InstitutionDebtService
{
    public function query(Institution $institution, array $filters = []): Builder
    {
        $completedPayments = InstitutionPayment::query()
            ->selectRaw('monthly_payment_statement_id, SUM(amount) as completed_payments_total')
            ->where('institution_id', $institution->id)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->whereNotNull('monthly_payment_statement_id')
            ->groupBy('monthly_payment_statement_id');

        $dueDateExpression = $this->dueDateSql($institution);
        $timezone = config('digifood.business_timezone', 'Europe/Budapest');
        $now = now($timezone)->toDateTimeString();
        $today = now($timezone)->toDateString();

        return MonthlyPaymentStatement::query()
            ->with([
                'child.guardians' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name'),
            ])
            ->leftJoinSub($completedPayments, 'completed_payments', function ($join) {
                $join->on('completed_payments.monthly_payment_statement_id', '=', 'monthly_payment_statements.id');
            })
            ->select('monthly_payment_statements.*')
            ->selectRaw('COALESCE(completed_payments.completed_payments_total, 0) as completed_payments_total')
            ->selectRaw('(monthly_payment_statements.total_payable - COALESCE(completed_payments.completed_payments_total, 0)) as remaining_amount')
            ->selectRaw("{$dueDateExpression} as due_at")
            ->where('monthly_payment_statements.institution_id', $institution->id)
            ->where('monthly_payment_statements.total_payable', '>', 0)
            ->whereRaw('(monthly_payment_statements.total_payable - COALESCE(completed_payments.completed_payments_total, 0)) > 0')
            ->when(!empty($filters['child_search']), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['child_search']);

                $query->whereHas('child', function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                });
            })
            ->when(!empty($filters['guardian_search']), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['guardian_search']);

                $query->whereHas('child.guardians', function (Builder $query) use ($search) {
                    $query->where('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%");
                });
            })
            ->when(!empty($filters['group_name']), function (Builder $query) use ($filters) {
                $query->whereHas('child', fn (Builder $query) => $query->where('group_name', $filters['group_name']));
            })
            ->when(!empty($filters['month']), function (Builder $query) use ($filters) {
                [$year, $month] = explode('-', $filters['month']);

                $query->where('monthly_payment_statements.year', (int) $year)
                    ->where('monthly_payment_statements.month', (int) $month);
            })
            ->when(($filters['overdue_only'] ?? false), function (Builder $query) use ($dueDateExpression, $now) {
                $query->whereRaw("{$dueDateExpression} < ?", [$now]);
            })
            ->when(!empty($filters['status']), function (Builder $query) use ($filters, $dueDateExpression, $now, $today) {
                if ($filters['status'] === 'partial_paid') {
                    $query->whereRaw('COALESCE(completed_payments.completed_payments_total, 0) > 0');

                    return;
                }

                if ($filters['status'] === 'before_due') {
                    $query->whereRaw("{$dueDateExpression} > ?", [$now]);

                    return;
                }

                if ($filters['status'] === 'due_today') {
                    $query->whereRaw("DATE({$dueDateExpression}) = ?", [$today]);

                    return;
                }

                if ($filters['status'] === 'overdue') {
                    $query->whereRaw("{$dueDateExpression} < ?", [$now]);
                }
            });
    }

    public function summary(Institution $institution, Builder $query): array
    {
        $base = clone $query;
        $dueDateExpression = $this->dueDateSql($institution);
        $timezone = config('digifood.business_timezone', 'Europe/Budapest');
        $remainingAmountSql = '(monthly_payment_statements.total_payable - COALESCE(completed_payments.completed_payments_total, 0))';

        return [
            'total_remaining' => (clone $base)->sum(DB::raw($remainingAmountSql)),
            'children_count' => (clone $base)->distinct()->count('monthly_payment_statements.child_id'),
            'overdue_total' => (clone $base)
                ->whereRaw("{$dueDateExpression} < ?", [now($timezone)->toDateTimeString()])
                ->sum(DB::raw($remainingAmountSql)),
            'partial_paid_count' => (clone $base)
                ->whereRaw('COALESCE(completed_payments.completed_payments_total, 0) > 0')
                ->count(),
        ];
    }

    public function hydratePage(LengthAwarePaginator $paginator, Institution $institution): LengthAwarePaginator
    {
        $items = $paginator->getCollection()->map(
            fn (MonthlyPaymentStatement $statement) => $this->hydrateStatement($statement, $institution)
        );
        $paginator->setCollection($items);

        return $paginator;
    }

    public function hydrateStatement(MonthlyPaymentStatement $statement, Institution $institution): MonthlyPaymentStatement
    {
        $completedPayments = (int) ($statement->completed_payments_total ?? 0);
        $remainingAmount = max(0, (int) $statement->total_payable - $completedPayments);
        $timezone = config('digifood.business_timezone', 'Europe/Budapest');
        $dueAt = Carbon::create(
            $statement->year,
            $statement->month,
            $this->dueDay($institution),
            23,
            59,
            59,
            $timezone
        );
        $now = now($timezone);
        $lateDays = $remainingAmount > 0 && $dueAt->lt($now)
            ? $dueAt->copy()->startOfDay()->diffInDays($now->copy()->startOfDay())
            : 0;
        $partialPaid = $completedPayments > 0 && $remainingAmount > 0;

        $statement->setAttribute('completed_payments_total', $completedPayments);
        $statement->setAttribute('remaining_amount', $remainingAmount);
        $statement->setAttribute('due_at', $dueAt);
        $statement->setAttribute('late_days', $lateDays);
        $statement->setAttribute('partial_paid', $partialPaid);
        $statement->setAttribute('debt_status', $this->statusMeta($dueAt, $remainingAmount, $completedPayments));
        $statement->setAttribute('display_guardians', $this->displayGuardians($statement));
        $statement->setAttribute('prefill_guardian_id', $this->prefillGuardianId($statement));

        return $statement;
    }

    public function relatedPayments(MonthlyPaymentStatement $statement, Institution $institution): Collection
    {
        return InstitutionPayment::query()
            ->with(['guardian', 'recordedBy'])
            ->where('institution_id', $institution->id)
            ->where('monthly_payment_statement_id', $statement->id)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();
    }

    public function groupOptions(Institution $institution): Collection
    {
        return DB::table('children')
            ->where('institution_id', $institution->id)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');
    }

    public function statusMeta(Carbon $dueAt, int $remainingAmount, int $completedPayments): array
    {
        $now = now(config('digifood.business_timezone', 'Europe/Budapest'));
        $today = $now->toDateString();

        $primary = match (true) {
            $dueAt->toDateString() === $today => ['label' => 'Ma esedékes', 'class' => 'bg-warning text-dark'],
            $dueAt->lt($now) => ['label' => 'Késedelmes', 'class' => 'bg-danger'],
            default => ['label' => 'Fizetési határidő előtt', 'class' => 'bg-info text-white'],
        };

        $partial = $completedPayments > 0 && $remainingAmount > 0
            ? ['label' => 'Részben fizetve', 'class' => 'bg-primary']
            : null;

        return [
            'primary' => $primary,
            'partial' => $partial,
        ];
    }

    private function dueDay(?Institution $institution): int
    {
        if (!$institution) {
            return 5;
        }

        $settingDay = (int) optional($institution->setting)->payment_due_day;
        $billingDay = (int) $institution->billing_payment_due_days;

        return min(28, max(1, $settingDay > 0 ? $settingDay : ($billingDay > 0 ? $billingDay : 5)));
    }

    private function dueDateSql(?Institution $institution): string
    {
        $day = $this->dueDay($institution);

        return "STR_TO_DATE(CONCAT(monthly_payment_statements.year, '-', LPAD(monthly_payment_statements.month, 2, '0'), '-', LPAD({$day}, 2, '0'), ' 23:59:59'), '%Y-%m-%d %H:%i:%s')";
    }

    private function displayGuardians(MonthlyPaymentStatement $statement): string
    {
        $guardians = $statement->child?->guardians ?? collect();

        if ($guardians->isEmpty()) {
            return 'Nincs kapcsolt gondviselő';
        }

        return $guardians->map(fn ($guardian) => $guardian->full_name)->implode(', ');
    }

    private function prefillGuardianId(MonthlyPaymentStatement $statement): ?int
    {
        $guardians = $statement->child?->guardians ?? collect();

        if ($guardians->count() === 1) {
            return (int) $guardians->first()->id;
        }

        $primaryBillingGuardianId = BillingProfile::query()
            ->where('institution_id', $statement->institution_id)
            ->whereNotNull('guardian_id')
            ->whereHas('children', fn ($query) => $query
                ->where('children.id', $statement->child_id)
                ->where('billing_profile_child.is_primary', true))
            ->value('guardian_id');

        return $primaryBillingGuardianId ? (int) $primaryBillingGuardianId : null;
    }
}
