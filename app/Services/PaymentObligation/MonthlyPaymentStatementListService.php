<?php

namespace App\Services\PaymentObligation;

use App\Models\Institution;
use App\Models\InstitutionPayment;
use App\Models\InstitutionPaymentAllocation;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Support\Finance\PaymentComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class MonthlyPaymentStatementListService
{
    public function query(Institution $institution, Carbon $period, array $filters = []): Builder
    {
        $legacyPayments = InstitutionPayment::query()
            ->selectRaw('monthly_payment_statement_id, SUM(amount) as total_amount')
            ->where('institution_id', $institution->id)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->whereNotNull('monthly_payment_statement_id')
            ->groupBy('monthly_payment_statement_id');

        $foundationAllocations = InstitutionPaymentAllocation::query()
            ->selectRaw('monthly_payment_statement_id, SUM(amount) as total_amount')
            ->where('payment_component', PaymentComponent::FOUNDATION)
            ->whereHas('payment', fn (Builder $query) => $query
                ->where('institution_id', $institution->id)
                ->where('status', InstitutionPayment::STATUS_COMPLETED))
            ->whereNotNull('monthly_payment_statement_id')
            ->groupBy('monthly_payment_statement_id');

        $kindergartenAllocations = InstitutionPaymentAllocation::query()
            ->selectRaw('monthly_payment_statement_id, SUM(amount) as total_amount')
            ->where('payment_component', PaymentComponent::KINDERGARTEN)
            ->whereHas('payment', fn (Builder $query) => $query
                ->where('institution_id', $institution->id)
                ->where('status', InstitutionPayment::STATUS_COMPLETED))
            ->whereNotNull('monthly_payment_statement_id')
            ->groupBy('monthly_payment_statement_id');

        $query = MonthlyPaymentStatement::query()
            ->with([
                'child.discountType',
                'child.billingProfiles',
                'mealPackage',
            ])
            ->leftJoinSub($legacyPayments, 'legacy_payments', function ($join) {
                $join->on('legacy_payments.monthly_payment_statement_id', '=', 'monthly_payment_statements.id');
            })
            ->leftJoinSub($foundationAllocations, 'foundation_allocations', function ($join) {
                $join->on('foundation_allocations.monthly_payment_statement_id', '=', 'monthly_payment_statements.id');
            })
            ->leftJoinSub($kindergartenAllocations, 'kindergarten_allocations', function ($join) {
                $join->on('kindergarten_allocations.monthly_payment_statement_id', '=', 'monthly_payment_statements.id');
            })
            ->select('monthly_payment_statements.*')
            ->selectRaw("
                CASE
                    WHEN monthly_payment_statements.payment_model = ? THEN COALESCE(foundation_allocations.total_amount, 0)
                    ELSE COALESCE(legacy_payments.total_amount, 0)
                END as foundation_paid_amount
            ", [\App\Services\Finance\InstitutionPaymentComponentService::PAYMENT_MODEL_SPLIT_MANUAL_TRANSFER])
            ->selectRaw("
                CASE
                    WHEN monthly_payment_statements.payment_model = ? THEN COALESCE(kindergarten_allocations.total_amount, 0)
                    ELSE 0
                END as kindergarten_paid_amount
            ", [\App\Services\Finance\InstitutionPaymentComponentService::PAYMENT_MODEL_SPLIT_MANUAL_TRANSFER])
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->when($this->filled($filters, 'search'), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['search']);

                $query->whereHas('child', function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('educational_identifier', 'like', "%{$search}%");
                });
            })
            ->when($this->filled($filters, 'class_group_id'), function (Builder $query) use ($filters) {
                $classGroupId = (int) $filters['class_group_id'];

                $query->whereHas('child.classGroups', fn (Builder $query) => $query->where('class_groups.id', $classGroupId));
            })
            ->when($this->filled($filters, 'meal_package_id'), fn (Builder $query) => $query->where('meal_package_id', (int) $filters['meal_package_id']))
            ->when($this->filled($filters, 'discount_type_id'), fn (Builder $query) => $query->where('discount_id', (int) $filters['discount_type_id']))
            ->when(($filters['meal_status'] ?? null) === 'participant', fn (Builder $query) => $query->where('meal_amount', '>', 0))
            ->when(($filters['meal_status'] ?? null) === 'non_participant', fn (Builder $query) => $query->where('meal_amount', 0))
            ->when(($filters['payment_status'] ?? null) === 'draft', fn (Builder $query) => $query->where('status', MonthlyPaymentStatement::STATUS_DRAFT))
            ->when(($filters['payment_status'] ?? null) === 'closed', fn (Builder $query) => $query->where('status', MonthlyPaymentStatement::STATUS_CLOSED))
            ->when(($filters['payment_status'] ?? null) === 'payable', fn (Builder $query) => $query->where('total_payable', '>', 0))
            ->when(($filters['payment_status'] ?? null) === 'zero', fn (Builder $query) => $query->where('invoiceable_amount', '<=', 0))
            ->orderBy('child_id');

        return $this->applyFinancialStatusFilter($query, $filters['payment_status'] ?? null);
    }

    private function applyFinancialStatusFilter(Builder $query, ?string $paymentStatus): Builder
    {
        if (! $paymentStatus) {
            return $query;
        }

        $foundationBalanceSql = '(monthly_payment_statements.foundation_total_payable - foundation_paid_amount)';
        $kindergartenBalanceSql = '(monthly_payment_statements.kindergarten_total_payable - kindergarten_paid_amount)';
        $totalPaidSql = '(foundation_paid_amount + kindergarten_paid_amount)';
        $totalBalanceSql = "({$foundationBalanceSql} + {$kindergartenBalanceSql})";

        return match ($paymentStatus) {
            'settled' => $query->havingRaw("{$foundationBalanceSql} = 0 AND {$kindergartenBalanceSql} = 0"),
            'debt' => $query->havingRaw("{$foundationBalanceSql} > 0 OR {$kindergartenBalanceSql} > 0"),
            'overpayment' => $query->havingRaw("{$foundationBalanceSql} < 0 OR {$kindergartenBalanceSql} < 0"),
            'foundation_debt' => $query->havingRaw("{$foundationBalanceSql} > 0"),
            'kindergarten_debt' => $query->havingRaw("{$kindergartenBalanceSql} > 0"),
            'foundation_overpayment' => $query->havingRaw("{$foundationBalanceSql} < 0"),
            'kindergarten_overpayment' => $query->havingRaw("{$kindergartenBalanceSql} < 0"),
            'partial_paid' => $query->havingRaw("{$totalPaidSql} > 0 AND {$totalBalanceSql} > 0"),
            'unpaid' => $query->havingRaw("{$totalPaidSql} = 0 AND {$totalBalanceSql} > 0"),
            default => $query,
        };
    }

    private function filled(array $filters, string $key): bool
    {
        return array_key_exists($key, $filters) && $filters[$key] !== null && $filters[$key] !== '';
    }
}
