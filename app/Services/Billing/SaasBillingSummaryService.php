<?php

namespace App\Services\Billing;

use App\Mail\SaasBillingSummaryMail;
use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\SaasBillingSummaryItem;
use App\Models\SaasBillingSummaryRun;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SaasBillingSummaryService
{
    /**
     * Azok az aktív intézmények, amelyeknél be van állítva a Digifood
     * havidíj / aktív étkező érték, ÉS nincsenek számlázási partnerhez
     * rendelve (azokat a partneri "Ügyfél számlázás" kezeli, hogy ne
     * legyen kétszeres számlázás ugyanarra az intézményre).
     */
    public function billableInstitutions(): Collection
    {
        return $this->baseEligibleQuery()
            ->whereNotNull('saas_fee_per_active_eater')
            ->where('saas_fee_per_active_eater', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn (Institution $institution) => $this->buildRow($institution));
    }

    /**
     * Aktív, partnerhez NEM rendelt intézmények, amelyeknél még nincs
     * beállítva a díj - ezeket figyelmeztetésként jelenítjük meg, hogy
     * a superadmin tudja pótolni a beállítást.
     */
    public function institutionsMissingRate(): Collection
    {
        return $this->baseEligibleQuery()
            ->where(function ($query) {
                $query->whereNull('saas_fee_per_active_eater')
                    ->orWhere('saas_fee_per_active_eater', '<=', 0);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Aktív intézmények, amelyek számlázási partnerhez vannak rendelve -
     * ezeket a "Partneri ügyfél számlázás" kezeli, ezért itt csak
     * tájékoztató jelleggel jelenítjük meg őket (nem szerepelnek sem a
     * számlázható, sem a hiányzó díjszabású listában).
     */
    public function institutionsHandledByPartner(): Collection
    {
        return Institution::query()
            ->where('active', true)
            ->whereNotNull('billing_partner_id')
            ->with('billingPartner:id,name')
            ->orderBy('name')
            ->get();
    }

    private function baseEligibleQuery()
    {
        return Institution::query()
            ->where('active', true)
            ->whereNull('billing_partner_id');
    }

    /**
     * A számlázható intézmények listája, kizárva azokat, amelyeknél EBBEN
     * a hónapban már van számlázott/fizetett tétel - így egy újraküldés
     * (előnézet vagy tényleges kiküldés) nem tünteti fel újra számlázásra
     * várónak azt, ami már el van intézve.
     */
    public function pendingBillableInstitutions(CarbonImmutable $month): Collection
    {
        $rows = $this->billableInstitutions();

        $run = SaasBillingSummaryRun::query()
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->first();

        if ($run === null) {
            return $rows;
        }

        $handledInstitutionIds = $run->items()
            ->where('status', '!=', SaasBillingSummaryItem::STATUS_PENDING)
            ->pluck('institution_id')
            ->all();

        if ($handledInstitutionIds === []) {
            return $rows;
        }

        return $rows
            ->reject(fn (array $row) => in_array($row['institution_id'], $handledInstitutionIds, true))
            ->values();
    }

    public function activeEaterCounts(Institution $institution): array
    {
        $childrenCount = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->count();

        $employeesCount = InstitutionEmployee::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->count();

        return [
            'children_count' => $childrenCount,
            'employees_count' => $employeesCount,
            'eaters_count' => $childrenCount + $employeesCount,
        ];
    }

    public function alreadySentThisMonth(CarbonImmutable $month): bool
    {
        return SaasBillingSummaryRun::query()
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->where('status', SaasBillingSummaryRun::STATUS_SENT)
            ->exists();
    }

    public function lastRuns(int $limit = 12): Collection
    {
        return SaasBillingSummaryRun::query()
            ->with('triggeredByUser:id,name')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit($limit)
            ->get();
    }

    /**
     * Az összes számlázási tétel (intézményenként, hónaponként), a
     * legfrissebbtől visszafelé - ez az a lista, amin a superadmin
     * végig tudja vezetni az egyes intézmények számlázási állapotát.
     */
    public function items(int $perPage = 30)
    {
        return SaasBillingSummaryItem::query()
            ->with(['run:id,year,month', 'institution:id,name'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Összeállítja és elküldi a havi számlázási összesítő e-mailt
     * az info@digifood.hu (vagy konfigurált) címre, létrehozza/frissíti
     * az intézményenkénti számlázási tételeket, majd naplózza a
     * futtatást (sikeres vagy sikertelen státusszal).
     */
    public function send(CarbonImmutable $month, string $triggeredBy, ?int $triggeredByUserId = null): SaasBillingSummaryRun
    {
        $rows = $this->pendingBillableInstitutions($month);
        $missingRateInstitutions = $this->institutionsMissingRate();
        $totalAmount = $rows->sum('total_amount');

        $data = [
            'monthLabel' => $this->formatMonthLabel($month),
            'generatedAt' => CarbonImmutable::now(config('digifood.business_timezone', config('app.timezone'))),
            'rows' => $rows->all(),
            'missingRateInstitutions' => $missingRateInstitutions->pluck('name')->all(),
            'totalAmount' => $totalAmount,
            'totalInstitutions' => $rows->count(),
        ];

        $run = DB::transaction(function () use ($month, $rows, $totalAmount, $triggeredBy, $triggeredByUserId) {
            $run = SaasBillingSummaryRun::query()->updateOrCreate(
                ['year' => $month->year, 'month' => $month->month],
                [
                    'institution_count' => $rows->count(),
                    'total_amount' => $totalAmount,
                    'status' => SaasBillingSummaryRun::STATUS_SENT,
                    'triggered_by' => $triggeredBy,
                    'triggered_by_user_id' => $triggeredByUserId,
                    'sent_at' => null,
                    'error_message' => null,
                ]
            );

            $this->syncItems($run, $rows);

            return $run;
        });

        try {
            Mail::to($this->recipientEmail())->send(new SaasBillingSummaryMail($data));

            $run->forceFill([
                'status' => SaasBillingSummaryRun::STATUS_SENT,
                'sent_at' => CarbonImmutable::now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::error('SaaS havi számlázási összesítő küldése sikertelen.', [
                'year' => $month->year,
                'month' => $month->month,
                'exception' => $exception->getMessage(),
            ]);

            $run->forceFill([
                'status' => SaasBillingSummaryRun::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();
        }

        return $run;
    }

    /**
     * Csak "pending" (még nem számlázott) tétel jelölhető számlázottnak -
     * a zárolt (lockForUpdate) újraolvasás és az állapot-ellenőrzés nélkül
     * egy véletlen dupla-submit (vagy versenyhelyzet két admin között)
     * visszaállíthatná egy már kifizetett ("paid") tétel státuszát
     * "invoiced"-re, miközben a paid_at mező tévesen a régi értéken marad.
     */
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

    /**
     * Létrehozza az új intézményekhez tartozó tételeket, és frissíti a
     * még "pending" (nem számlázott) tételek pillanatnyi adatait -
     * a már számlázott/fizetett tételeket viszont nem írjuk felül, hogy
     * egy újraküldés ne rontsa el a már véglegesített számlázási adatot.
     */
    private function syncItems(SaasBillingSummaryRun $run, Collection $rows): void
    {
        foreach ($rows as $row) {
            $existingItem = SaasBillingSummaryItem::query()
                ->where('saas_billing_summary_run_id', $run->id)
                ->where('institution_id', $row['institution_id'])
                ->first();

            if ($existingItem !== null && $existingItem->status !== SaasBillingSummaryItem::STATUS_PENDING) {
                continue;
            }

            SaasBillingSummaryItem::query()->updateOrCreate(
                [
                    'saas_billing_summary_run_id' => $run->id,
                    'institution_id' => $row['institution_id'],
                ],
                [
                    'institution_name_snapshot' => $row['institution_name'],
                    'billing_tax_number_snapshot' => $row['billing_tax_number'],
                    'billing_address_snapshot' => collect([
                        $row['billing_zip'], $row['billing_city'], $row['billing_address'],
                    ])->filter()->implode(' ') ?: null,
                    'children_count' => $row['children_count'],
                    'employees_count' => $row['employees_count'],
                    'eaters_count' => $row['eaters_count'],
                    'rate' => $row['rate'],
                    'amount' => $row['total_amount'],
                    'status' => SaasBillingSummaryItem::STATUS_PENDING,
                ]
            );
        }
    }

    private function buildRow(Institution $institution): array
    {
        $counts = $this->activeEaterCounts($institution);
        $rate = (float) $institution->saas_fee_per_active_eater;
        $totalAmount = round($counts['eaters_count'] * $rate, 2);

        return [
            'institution_id' => $institution->id,
            'institution_name' => $institution->name,
            'billing_name' => $institution->billing_name ?: $institution->name,
            'billing_tax_number' => $institution->billing_tax_number,
            'billing_zip' => $institution->billing_zip,
            'billing_city' => $institution->billing_city,
            'billing_address' => $institution->billing_address,
            'children_count' => $counts['children_count'],
            'employees_count' => $counts['employees_count'],
            'eaters_count' => $counts['eaters_count'],
            'rate' => $rate,
            'total_amount' => $totalAmount,
        ];
    }

    private function recipientEmail(): string
    {
        return config('digifood.saas_billing_summary_recipient', 'info@digifood.hu');
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
