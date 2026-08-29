<?php

namespace App\Services\Finance;

use App\Models\Institution;
use App\Models\InstitutionPayment;
use App\Models\InstitutionPaymentAllocation;
use App\Models\InstitutionPaymentComponentRate;
use App\Models\PaymentObligation\FinancialAdjustment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Support\Finance\PaymentComponent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InstitutionPaymentComponentService
{
    public const PAYMENT_MODEL_SPLIT_MANUAL_TRANSFER = 'split_manual_transfer';

    public const PAYMENT_MODEL_LEGACY = 'legacy';

    public function usesSplitManualTransfer(Institution $institution): bool
    {
        return (bool) ($institution->setting?->usesSplitManualTransfer() ?? false);
    }

    public function paymentModelForInstitution(Institution $institution): string
    {
        return $this->usesSplitManualTransfer($institution)
            ? self::PAYMENT_MODEL_SPLIT_MANUAL_TRANSFER
            : self::PAYMENT_MODEL_LEGACY;
    }

    public function rateForDate(int $institutionId, string $component, Carbon $date): ?InstitutionPaymentComponentRate
    {
        return InstitutionPaymentComponentRate::query()
            ->where('institution_id', $institutionId)
            ->where('component', $component)
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    public function createRate(int $institutionId, string $component, int $amount, Carbon $validFrom, ?int $createdBy = null): InstitutionPaymentComponentRate
    {
        return InstitutionPaymentComponentRate::query()->updateOrCreate(
            [
                'institution_id' => $institutionId,
                'component' => $component,
                'valid_from' => $validFrom->toDateString(),
            ],
            [
                'amount' => $amount,
                'created_by' => $createdBy,
            ]
        );
    }

    public function configurationIssues(Institution $institution): array
    {
        $issues = $institution->setting?->splitManualTransferConfigurationIssues() ?? [];

        if (! $this->usesSplitManualTransfer($institution)) {
            return $issues;
        }

        foreach (PaymentComponent::all() as $component) {
            if ($this->rateForDate($institution->id, $component, now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfDay()) === null) {
                $issues[] = (PaymentComponent::labels()[$component] ?? $component).': hiányzik legalább egy napi díj';
            }
        }

        return $issues;
    }

    public function buildStatementSummaries(Collection $statements): Collection
    {
        if ($statements->isEmpty()) {
            return collect();
        }

        $statementIds = $statements->pluck('id')->all();

        $allocations = InstitutionPaymentAllocation::query()
            ->selectRaw('monthly_payment_statement_id, payment_component, SUM(amount) as total_amount')
            ->whereIn('monthly_payment_statement_id', $statementIds)
            ->whereHas('payment', fn ($query) => $query->where('status', InstitutionPayment::STATUS_COMPLETED))
            ->groupBy('monthly_payment_statement_id', 'payment_component')
            ->get()
            ->groupBy('monthly_payment_statement_id');

        $unappliedCredits = InstitutionPaymentAllocation::query()
            ->with('payment:id,child_id,status')
            ->where('allocation_type', InstitutionPaymentAllocation::TYPE_UNAPPLIED)
            ->where('payment_component', '!=', PaymentComponent::LEGACY)
            ->whereHas('payment', fn ($query) => $query
                ->where('status', InstitutionPayment::STATUS_COMPLETED)
                ->whereIn('child_id', $statements->pluck('child_id')->unique()->all()))
            ->get();

        $legacyPayments = InstitutionPayment::query()
            ->selectRaw('monthly_payment_statement_id, SUM(amount) as total_amount')
            ->whereIn('monthly_payment_statement_id', $statementIds)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->groupBy('monthly_payment_statement_id')
            ->pluck('total_amount', 'monthly_payment_statement_id');

        return $statements->mapWithKeys(function (MonthlyPaymentStatement $statement) use ($allocations, $legacyPayments, $unappliedCredits) {
            $isSplit = $statement->usesSplitPaymentModel();
            $foundationPaid = 0;
            $kindergartenPaid = 0;

            if ($isSplit) {
                $group = $allocations->get($statement->id, collect());
                $foundationStatementPaid = (int) optional($group->firstWhere('payment_component', PaymentComponent::FOUNDATION))->total_amount;
                $kindergartenStatementPaid = (int) optional($group->firstWhere('payment_component', PaymentComponent::KINDERGARTEN))->total_amount;
                $foundationUnapplied = $this->sumUnappliedCreditsForStatement($unappliedCredits, $statement, PaymentComponent::FOUNDATION);
                $kindergartenUnapplied = $this->sumUnappliedCreditsForStatement($unappliedCredits, $statement, PaymentComponent::KINDERGARTEN);
                $foundationPaid = $foundationStatementPaid + $foundationUnapplied;
                $kindergartenPaid = $kindergartenStatementPaid + $kindergartenUnapplied;
            } else {
                $foundationPaid = (int) ($legacyPayments[$statement->id] ?? 0);
            }

            $foundationBalance = (int) $statement->foundation_total_payable - $foundationPaid;
            $kindergartenBalance = (int) $statement->kindergarten_total_payable - $kindergartenPaid;

            if (! $isSplit) {
                $foundationBalance = (int) $statement->total_payable - $foundationPaid;
            }

            $summary = [
                'is_split' => $isSplit,
                'foundation_paid' => $foundationPaid,
                'kindergarten_paid' => $kindergartenPaid,
                'foundation_remaining' => max(0, $foundationBalance),
                'kindergarten_remaining' => $isSplit ? max(0, $kindergartenBalance) : 0,
                'foundation_balance' => $foundationBalance,
                'kindergarten_balance' => $isSplit ? $kindergartenBalance : 0,
            ];

            $summary['paid_total'] = $summary['foundation_paid'] + $summary['kindergarten_paid'];
            $summary['remaining_total'] = $isSplit
                ? max(0, $summary['foundation_remaining']) + max(0, $summary['kindergarten_remaining'])
                : max(0, $summary['foundation_remaining']);
            $summary['net_balance'] = $isSplit
                ? $summary['foundation_balance'] + $summary['kindergarten_balance']
                : $summary['foundation_balance'];

            return [$statement->id => $summary];
        });
    }

    public function sumComponentPreviousBalance(int $institutionId, int $childId, string $component, Carbon $paymentPeriod): int
    {
        $adjustments = (int) FinancialAdjustment::query()
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
            ->where('payment_component', $component)
            ->where(function ($query) use ($paymentPeriod) {
                $query->where('affects_invoice', false)
                    ->orWhere(function ($query) {
                        $query->whereNull('reference_year')
                            ->whereNull('reference_month');
                    });
            })
            ->where(function ($query) use ($paymentPeriod) {
                $query->whereNull('entry_date')
                    ->orWhereDate('entry_date', '<=', $paymentPeriod->copy()->endOfMonth()->toDateString());
            })
            ->whereNull('reversed_at')
            ->sum('amount');

        return $adjustments
            + $this->sumOutstandingPriorStatements($institutionId, $childId, $component, $paymentPeriod)
            - $this->sumUnappliedCredits($institutionId, $childId, $component, $paymentPeriod);
    }

    public function sumComponentInvoiceAdjustments(int $institutionId, int $childId, string $component, Carbon $period): int
    {
        return (int) FinancialAdjustment::query()
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
            ->where('payment_component', $component)
            ->where('affects_invoice', true)
            ->where('reference_year', $period->year)
            ->where('reference_month', $period->month)
            ->where('type', '!=', FinancialAdjustment::TYPE_CANCELLATION_CREDIT)
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    public function sumCancellationCredits(int $institutionId, int $childId, string $component, Carbon $period): int
    {
        return (int) FinancialAdjustment::query()
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
            ->where('payment_component', $component)
            ->where('type', FinancialAdjustment::TYPE_CANCELLATION_CREDIT)
            ->where('affects_invoice', true)
            ->where('reference_year', $period->year)
            ->where('reference_month', $period->month)
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    public function syncAllocationsForPayment(InstitutionPayment $payment): void
    {
        $payment->loadMissing('institution.setting');
        $payment->allocations()->delete();

        if ($payment->status !== InstitutionPayment::STATUS_COMPLETED || ! filled($payment->payment_component)) {
            return;
        }

        $amountRemaining = (int) $payment->amount;
        $component = (string) $payment->payment_component;
        $paymentPeriodCutoff = optional($payment->paid_at)->copy()?->startOfDay();

        $statements = MonthlyPaymentStatement::query()
            ->where('institution_id', $payment->institution_id)
            ->where('child_id', $payment->child_id)
            ->whereIn('status', [
                MonthlyPaymentStatement::STATUS_CLOSED,
                MonthlyPaymentStatement::STATUS_SENT_TO_INVOICING,
                MonthlyPaymentStatement::STATUS_INVOICED,
            ])
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('id')
            ->get();

        $summaries = $this->buildStatementSummaries($statements);

        foreach ($statements as $statement) {
            if ($amountRemaining <= 0) {
                break;
            }

            $summary = $summaries->get($statement->id, []);
            $remaining = max(0, (int) ($summary[$component.'_remaining'] ?? 0));

            if ($remaining < 1) {
                continue;
            }

            $allocated = min($amountRemaining, $remaining);
            $payment->allocations()->create([
                'monthly_payment_statement_id' => $statement->id,
                'payment_component' => $component,
                'allocation_type' => InstitutionPaymentAllocation::TYPE_STATEMENT,
                'amount' => $allocated,
                'allocated_at' => $paymentPeriodCutoff ?? now(),
            ]);
            $amountRemaining -= $allocated;
        }

        if ($amountRemaining > 0) {
            $payment->allocations()->create([
                'monthly_payment_statement_id' => null,
                'payment_component' => $component,
                'allocation_type' => InstitutionPaymentAllocation::TYPE_UNAPPLIED,
                'amount' => $amountRemaining,
                'allocated_at' => $paymentPeriodCutoff ?? now(),
            ]);
        }
    }

    private function sumOutstandingPriorStatements(int $institutionId, int $childId, string $component, Carbon $paymentPeriod): int
    {
        $priorStatements = MonthlyPaymentStatement::query()
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
            ->where('payment_model', self::PAYMENT_MODEL_SPLIT_MANUAL_TRANSFER)
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
            ->get();

        if ($priorStatements->isEmpty()) {
            return 0;
        }

        $summaries = $this->buildStatementSummaries($priorStatements);

        return (int) $priorStatements->sum(function (MonthlyPaymentStatement $statement) use ($summaries, $component) {
            $summary = $summaries->get($statement->id, []);

            return max(0, (int) ($summary[$component.'_remaining'] ?? 0));
        });
    }

    private function sumUnappliedCredits(int $institutionId, int $childId, string $component, Carbon $paymentPeriod): int
    {
        return (int) InstitutionPaymentAllocation::query()
            ->where('payment_component', $component)
            ->where('allocation_type', InstitutionPaymentAllocation::TYPE_UNAPPLIED)
            ->whereDate('allocated_at', '<', $paymentPeriod->toDateString())
            ->whereHas('payment', function ($query) use ($institutionId, $childId) {
                $query->where('institution_id', $institutionId)
                    ->where('child_id', $childId)
                    ->where('status', InstitutionPayment::STATUS_COMPLETED);
            })
            ->sum('amount');
    }

    private function sumUnappliedCreditsForStatement(Collection $allocations, MonthlyPaymentStatement $statement, string $component): int
    {
        $periodEnd = Carbon::create($statement->year, $statement->month, 1, 23, 59, 59, config('digifood.business_timezone', 'Europe/Budapest'))
            ->endOfMonth();

        return (int) $allocations->filter(function (InstitutionPaymentAllocation $allocation) use ($statement, $component, $periodEnd) {
            return $allocation->payment_component === $component
                && (int) optional($allocation->payment)->child_id === (int) $statement->child_id
                && optional($allocation->allocated_at)?->lte($periodEnd);
        })->sum('amount');
    }
}
