<?php

namespace App\Services\Billing;

use App\Models\PartnerMonthlyBilling;
use App\Models\SaasBillingSummaryItem;
use Carbon\CarbonImmutable;

class RevenueOverviewService
{
    /**
     * Összesíti a két számlázási rendszer (partneri ügyfél számlázás és
     * a közvetlen intézményi SaaS számlázás) bevételét: mindösszesen
     * befolyt bevétel, kintlévőség (számlázva, de még nem fizetve), és
     * egy havi bontású grafikonhoz való adatsor az utolsó N hónapra.
     */
    public function summary(int $months = 12): array
    {
        $now = CarbonImmutable::now(config('digifood.business_timezone', config('app.timezone')))->startOfMonth();

        $monthsRange = collect(range($months - 1, 0))
            ->map(fn (int $offset) => $now->subMonths($offset));

        $partnerPaidByMonth = PartnerMonthlyBilling::query()
            ->where('status', 'paid')
            ->selectRaw("DATE_FORMAT(billing_month, '%Y-%m') as month_key, SUM(gross_amount) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        $saasPaidByMonth = SaasBillingSummaryItem::query()
            ->join('saas_billing_summary_runs', 'saas_billing_summary_runs.id', '=', 'saas_billing_summary_items.saas_billing_summary_run_id')
            ->where('saas_billing_summary_items.status', SaasBillingSummaryItem::STATUS_PAID)
            ->selectRaw("CONCAT(saas_billing_summary_runs.year, '-', LPAD(saas_billing_summary_runs.month, 2, '0')) as month_key, SUM(saas_billing_summary_items.amount) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        $categories = [];
        $partnerSeries = [];
        $saasSeries = [];

        foreach ($monthsRange as $month) {
            $key = $month->format('Y-m');
            $categories[] = $this->formatMonthLabel($month);
            $partnerSeries[] = (float) ($partnerPaidByMonth[$key] ?? 0);
            $saasSeries[] = (float) ($saasPaidByMonth[$key] ?? 0);
        }

        $partnerTotalPaid = (float) PartnerMonthlyBilling::query()->where('status', 'paid')->sum('gross_amount');
        $saasTotalPaid = (float) SaasBillingSummaryItem::query()->where('status', SaasBillingSummaryItem::STATUS_PAID)->sum('amount');

        $partnerOutstanding = (float) PartnerMonthlyBilling::query()->where('status', 'invoiced')->sum('gross_amount');
        $saasOutstanding = (float) SaasBillingSummaryItem::query()->where('status', SaasBillingSummaryItem::STATUS_INVOICED)->sum('amount');

        $currentMonthKey = $now->format('Y-m');

        return [
            'categories' => $categories,
            'partner_series' => $partnerSeries,
            'saas_series' => $saasSeries,
            'partner_total_paid' => $partnerTotalPaid,
            'saas_total_paid' => $saasTotalPaid,
            'total_paid' => $partnerTotalPaid + $saasTotalPaid,
            'partner_outstanding' => $partnerOutstanding,
            'saas_outstanding' => $saasOutstanding,
            'total_outstanding' => $partnerOutstanding + $saasOutstanding,
            'current_month_paid' => (float) ($partnerPaidByMonth[$currentMonthKey] ?? 0) + (float) ($saasPaidByMonth[$currentMonthKey] ?? 0),
        ];
    }

    private function formatMonthLabel(CarbonImmutable $month): string
    {
        $months = [
            1 => 'jan', 2 => 'feb', 3 => 'márc', 4 => 'ápr',
            5 => 'máj', 6 => 'jún', 7 => 'júl', 8 => 'aug',
            9 => 'szept', 10 => 'okt', 11 => 'nov', 12 => 'dec',
        ];

        return sprintf('%d. %s', $month->year, $months[$month->month]);
    }
}
