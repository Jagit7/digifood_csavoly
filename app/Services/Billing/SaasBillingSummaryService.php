<?php

namespace App\Services\Billing;

use App\Mail\SaasBillingSummaryMail;
use App\Models\Institution;
use App\Models\SaasBillingSummaryItem;
use App\Models\SaasBillingSummaryRun;
use App\Models\StudentMealSetting;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SaasBillingSummaryService
{
    public function __construct(private readonly InstitutionMonthlyPricingService $pricing) {}

    public function billingMonth(): CarbonImmutable
    {
        return CarbonImmutable::now(config('digifood.business_timezone'))->startOfMonth();
    }

    /** Historical participation, not today's active flag or number of settings. */
    private function childCounts(CarbonImmutable $month): Collection
    {
        return StudentMealSetting::query()
            ->where(fn ($query) => $query->where('eater_type', 'child')->orWhereNull('eater_type'))
            ->whereNotNull('student_id')
            ->whereDate('valid_from', '<=', $month->endOfMonth()->toDateString())
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $month->startOfMonth()->toDateString()))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereColumn('valid_to', '>=', 'valid_from'))
            ->select('institution_id')
            ->selectRaw('COUNT(DISTINCT student_id) as child_count')
            ->groupBy('institution_id')
            ->pluck('child_count', 'institution_id');
    }

    /** All direct SaaS institutions; partner billing remains a separate obligation. */
    private function calculation(CarbonImmutable $month): array
    {
        $counts = $this->childCounts($month);
        $institutions = Institution::query()->whereNull('billing_partner_id')->orderBy('name')->get();
        $rates = $this->pricing->ratesForMonth($institutions->pluck('id'), $month);
        $rows = collect();
        $missing = collect();

        foreach ($institutions as $institution) {
            $rate = $rates->get($institution->id);
            $count = (int) $counts->get($institution->id, 0);
            if ($rate === null) {
                if ($institution->active || $count > 0) {
                    $missing->push($institution);
                }

                continue;
            }
            $item = $this->pricing->buildInstitutionItem($institution, $rate, $count);
            $rows->push([
                'institution_id' => $institution->id,
                'institution_name' => $institution->name,
                'billing_name' => $institution->billing_name ?: $institution->name,
                'billing_tax_number' => $institution->billing_tax_number,
                'billing_address' => collect([$institution->billing_zip, $institution->billing_city, $institution->billing_address])->filter()->implode(' '),
                'children_count' => $count,
                'employees_count' => 0,
                'eaters_count' => $count,
                'rate' => $item['price_per_child'],
                'fixed_monthly_fee' => $item['fixed_monthly_fee'],
                'minimum_monthly_fee' => $item['minimum_monthly_fee'],
                'calculation_description' => $item['calculation_description'],
                'total_amount' => $item['net_amount'],
                'amount_cents' => $item['net_amount_cents'],
            ]);
        }

        return ['rows' => $rows, 'missing' => $missing];
    }

    /** Read-only: existing months always use their stored snapshot. */
    public function summary(CarbonImmutable $month): array
    {
        $run = SaasBillingSummaryRun::query()->where('year', $month->year)->where('month', $month->month)->first();
        if ($run !== null) {
            return $this->snapshotData($run) + ['run' => $run, 'missing' => collect()];
        }
        $calculation = $this->calculation($month);

        return $this->payload($month, $calculation['rows']) + ['run' => null, 'missing' => $calculation['missing']];
    }

    private function payload(CarbonImmutable $month, Collection $rows): array
    {
        return [
            'monthLabel' => $this->formatMonthLabel($month),
            'generatedAt' => CarbonImmutable::now(config('digifood.business_timezone'))->toIso8601String(),
            'recipientEmail' => config('digifood.saas_billing_summary_recipient', 'info@digifood.hu'),
            'rows' => $rows->all(),
            'totalAmount' => number_format($rows->sum('amount_cents') / 100, 2, '.', ''),
            'totalInstitutions' => $rows->count(),
            'totalEaters' => $rows->sum('eaters_count'),
        ];
    }

    private function snapshotData(SaasBillingSummaryRun $run): array
    {
        if ($run->snapshot_payload !== null) {
            return $run->snapshot_payload;
        }

        // Legacy snapshots are displayed as stored, never repaired or repriced.
        $rows = $run->items()->orderBy('institution_name_snapshot')->get()->map(fn ($item) => [
            'institution_id' => $item->institution_id,
            'institution_name' => $item->institution_name_snapshot,
            'billing_name' => $item->institution_name_snapshot,
            'billing_tax_number' => $item->billing_tax_number_snapshot,
            'billing_address' => $item->billing_address_snapshot,
            'children_count' => $item->children_count,
            'employees_count' => $item->employees_count,
            'eaters_count' => $item->eaters_count,
            'rate' => $item->rate,
            'total_amount' => $item->amount,
            'calculation_description' => 'Korábbi mentett elszámolás (változatlan).',
        ])->all();

        return [
            'monthLabel' => $run->month_label,
            'generatedAt' => ($run->sent_at ?? $run->created_at)->toIso8601String(),
            'rows' => $rows,
            'totalAmount' => $run->total_amount,
            'totalInstitutions' => $run->institution_count,
            'totalEaters' => collect($rows)->sum('eaters_count'),
        ];
    }

    public function institutionsHandledByPartner(): Collection
    {
        return Institution::query()->where('active', true)->whereNotNull('billing_partner_id')
            ->with('billingPartner:id,name')->orderBy('name')->get();
    }

    public function alreadySentThisMonth(CarbonImmutable $month): bool
    {
        return SaasBillingSummaryRun::query()->where('year', $month->year)->where('month', $month->month)
            ->where('status', SaasBillingSummaryRun::STATUS_SENT)->exists();
    }

    public function lastRuns(int $limit = 12): Collection
    {
        return SaasBillingSummaryRun::query()->with('triggeredByUser:id,name')
            ->orderByDesc('year')->orderByDesc('month')->limit($limit)->get();
    }

    public function items(int $perPage = 30)
    {
        return SaasBillingSummaryItem::query()->with(['run:id,year,month', 'institution:id,name'])
            ->orderByDesc('id')->paginate($perPage);
    }

    public function send(CarbonImmutable $month, string $triggeredBy, ?int $triggeredByUserId = null): SaasBillingSummaryRun
    {
        $month = $month->startOfMonth();
        if ($month->gt($this->billingMonth())) {
            throw new DomainException('Jövőbeli hónap összesítője nem küldhető el.');
        }

        [$run, $claimed] = DB::transaction(function () use ($month, $triggeredBy, $triggeredByUserId) {
            // The existing unique(year, month) serializes concurrent first attempts.
            SaasBillingSummaryRun::query()->insertOrIgnore([
                'year' => $month->year, 'month' => $month->month,
                'status' => SaasBillingSummaryRun::STATUS_PENDING,
                'triggered_by' => $triggeredBy, 'triggered_by_user_id' => $triggeredByUserId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $run = SaasBillingSummaryRun::query()->where('year', $month->year)->where('month', $month->month)
                ->lockForUpdate()->firstOrFail();

            // Failed/uncertain sends need investigation, never an automatic retry.
            if ($run->status !== SaasBillingSummaryRun::STATUS_PENDING) {
                return [$run, false];
            }
            $calculation = $this->calculation($month);
            if ($calculation['missing']->isNotEmpty()) {
                throw new DomainException('Hiányzó havi díjszabás: '.$calculation['missing']->pluck('name')->implode(', '));
            }
            if ($calculation['rows']->isEmpty()) {
                throw new DomainException('Ebben a hónapban nincs számlázható intézmény.');
            }
            $data = $this->payload($month, $calculation['rows']);
            foreach ($data['rows'] as $row) {
                $run->items()->create([
                    'institution_id' => $row['institution_id'],
                    'institution_name_snapshot' => $row['institution_name'],
                    'billing_tax_number_snapshot' => $row['billing_tax_number'],
                    'billing_address_snapshot' => $row['billing_address'],
                    'children_count' => $row['children_count'], 'employees_count' => 0,
                    'eaters_count' => $row['eaters_count'], 'rate' => $row['rate'] ?? 0,
                    'amount' => $row['total_amount'], 'status' => SaasBillingSummaryItem::STATUS_PENDING,
                ]);
            }
            $run->forceFill([
                'snapshot_payload' => $data, 'institution_count' => $data['totalInstitutions'],
                'total_amount' => $data['totalAmount'], 'status' => SaasBillingSummaryRun::STATUS_SENDING,
            ])->save();

            return [$run, true];
        }, 3);

        if (! $claimed) {
            return $run;
        }
        try {
            $data = $this->snapshotData($run->fresh());
            Mail::to($data['recipientEmail'])->send(new SaasBillingSummaryMail($data));
            $run->forceFill(['status' => SaasBillingSummaryRun::STATUS_SENT, 'sent_at' => now(), 'error_message' => null])->save();
        } catch (Throwable $exception) {
            $run->forceFill(['status' => SaasBillingSummaryRun::STATUS_FAILED, 'error_message' => $exception->getMessage()])->save();
            Log::error('SaaS havi összesítő küldése sikertelen; újraküldés előtt ellenőrzendő.', ['run_id' => $run->id, 'exception' => $exception->getMessage()]);
        }

        return $run;
    }

    public function markItemInvoiced(SaasBillingSummaryItem $item, string $invoiceNumber, ?string $note = null): SaasBillingSummaryItem
    {
        return DB::transaction(function () use ($item, $invoiceNumber, $note) {
            $lockedItem = SaasBillingSummaryItem::query()
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->status !== SaasBillingSummaryItem::STATUS_PENDING) {
                throw new DomainException('Csak számlázásra váró tétel jelölhető számlázottnak.');
            }

            $lockedItem->forceFill([
                'status' => SaasBillingSummaryItem::STATUS_INVOICED,
                'invoice_number' => $invoiceNumber,
                'invoiced_at' => CarbonImmutable::now(),
                'note' => $note,
            ])->save();

            return $lockedItem;
        });
    }

    /**
     * Ugyanazon okból zárolt/tranzakciós, mint markItemInvoiced() - lásd ott.
     */
    public function markItemPaid(SaasBillingSummaryItem $item): SaasBillingSummaryItem
    {
        return DB::transaction(function () use ($item) {
            $lockedItem = SaasBillingSummaryItem::query()
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->status !== SaasBillingSummaryItem::STATUS_INVOICED) {
                throw new DomainException('Csak számlázott tétel jelölhető fizetettnek.');
            }

            $lockedItem->forceFill([
                'status' => SaasBillingSummaryItem::STATUS_PAID,
                'paid_at' => CarbonImmutable::now(),
            ])->save();

            return $lockedItem;
        });
    }

    public function formatMonthLabel(CarbonImmutable $month): string
    {
        $months = [
            1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
            5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
            9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
        ];

        return sprintf('%d. %s', $month->year, $months[$month->month]);
    }
}
