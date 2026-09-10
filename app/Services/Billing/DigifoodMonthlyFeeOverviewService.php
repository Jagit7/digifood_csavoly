<?php

namespace App\Services\Billing;

use Carbon\CarbonImmutable;

/** The dashboard uses the same current-month SaaS snapshot as the monthly page. */
class DigifoodMonthlyFeeOverviewService
{
    public function __construct(private readonly SaasBillingSummaryService $summary) {}

    public function calculate(?string $today = null): array
    {
        $month = $today === null
            ? $this->summary->billingMonth()
            : CarbonImmutable::parse($today, config('digifood.business_timezone'))->startOfMonth();
        $data = $this->summary->summary($month);

        return [
            'eating_count' => $data['totalEaters'],
            'total' => $data['totalAmount'],
            'uniform_rate' => null,
            'month_label' => $data['monthLabel'],
            'is_snapshot' => $data['run'] !== null,
            'missing_rate_count' => $data['missing']->count(),
        ];
    }
}
