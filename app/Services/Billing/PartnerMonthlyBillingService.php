<?php

namespace App\Services\Billing;

use App\Models\BillingPartner;
use App\Models\Child;
use App\Models\PartnerMonthlyBilling;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class PartnerMonthlyBillingService
{
    public function createSnapshot(
        BillingPartner $billingPartner,
        CarbonInterface|string $month
    ): PartnerMonthlyBilling {
        $billingMonth = $this->normalizeMonth($month);

        if (PartnerMonthlyBilling::query()
            ->where('billing_partner_id', $billingPartner->id)
            ->whereDate('billing_month', $billingMonth->toDateString())
            ->exists()) {
            throw new DomainException('Ehhez a partnerhez erre a hónapra már létezik havi számlázási adat.');
        }

        $calculation = $this->buildCalculation($billingPartner, $billingMonth);

        $monthlyBilling = DB::transaction(function () use ($billingPartner, $billingMonth, $calculation) {
            $monthlyBilling = PartnerMonthlyBilling::query()->create([
                'billing_partner_id' => $billingPartner->id,
                'billing_month' => $billingMonth->toDateString(),
                'total_children' => $calculation['total_children'],
                'net_amount' => $this->centsToDatabaseDecimal($calculation['net_amount_cents']),
                'vat_amount' => $this->centsToDatabaseDecimal($calculation['vat_amount_cents']),
                'gross_amount' => $this->centsToDatabaseDecimal($calculation['gross_amount_cents']),
                'status' => 'draft',
            ]);

            $monthlyBilling->items()->createMany($calculation['items']);

            return $monthlyBilling;
        });

        return $monthlyBilling->load('items');
    }

    public function recalculateSnapshot(PartnerMonthlyBilling $monthlyBilling): PartnerMonthlyBilling
    {
        if ($monthlyBilling->status !== 'draft') {
            throw new DomainException('A számlázott vagy fizetett havi adat már nem számolható újra.');
        }

        $billingPartner = BillingPartner::withTrashed()->find($monthlyBilling->billing_partner_id);

        if ($billingPartner === null) {
            throw new DomainException('A havi számlázási adathoz tartozó partner nem található.');
        }

        $billingMonth = $this->normalizeMonth($monthlyBilling->billing_month);
        $calculation = $this->buildCalculation($billingPartner, $billingMonth);

        DB::transaction(function () use ($monthlyBilling, $calculation) {
            $monthlyBilling->items()->delete();

            $monthlyBilling->update([
                'total_children' => $calculation['total_children'],
                'net_amount' => $this->centsToDatabaseDecimal($calculation['net_amount_cents']),
                'vat_amount' => $this->centsToDatabaseDecimal($calculation['vat_amount_cents']),
                'gross_amount' => $this->centsToDatabaseDecimal($calculation['gross_amount_cents']),
                'status' => 'draft',
            ]);

            $monthlyBilling->items()->createMany($calculation['items']);
        });

        return $monthlyBilling->fresh()->load('items');
    }

    private function buildCalculation(BillingPartner $billingPartner, CarbonImmutable $billingMonth): array
    {
        $institutions = $billingPartner->institutions()
            ->orderBy('name')
            ->get(['id', 'name', 'billing_partner_id', 'active']);

        if ($institutions->isEmpty()) {
            throw new DomainException('A partnerhez jelenleg nincs hozzárendelt intézmény, ezért nem hozható létre havi számlázási adat.');
        }

        $institutionIds = $institutions->pluck('id');
        $childCounts = Child::query()
            ->selectRaw('institution_id, COUNT(*) as total')
            ->whereIn('institution_id', $institutionIds)
            ->where('active', true)
            ->groupBy('institution_id')
            ->pluck('total', 'institution_id');

        $rates = app(InstitutionMonthlyPricingService::class)->ratesForMonth($institutionIds, $billingMonth);

        $items = [];
        $totalChildren = 0;
        $netAmountCents = 0;

        foreach ($institutions as $institution) {
            $rate = $rates->get($institution->id);

            if ($rate === null) {
                throw new DomainException(sprintf(
                    'A(z) „%s” intézményhez nincs érvényes díjszabás %s hónapra.',
                    $institution->name,
                    $this->formatBillingMonthLabel($billingMonth)
                ));
            }

            $childCount = (int) ($childCounts[$institution->id] ?? 0);
            $itemCalculation = app(InstitutionMonthlyPricingService::class)->buildInstitutionItem($institution, $rate, $childCount);

            $items[] = $itemCalculation;
            $totalChildren += $childCount;
            $netAmountCents += $itemCalculation['net_amount_cents'];
        }

        $vatAmountCents = $this->calculateVatCents($netAmountCents, (string) $billingPartner->vat_rate);

        return [
            'total_children' => $totalChildren,
            'net_amount_cents' => $netAmountCents,
            'vat_amount_cents' => $vatAmountCents,
            'gross_amount_cents' => $netAmountCents + $vatAmountCents,
            'items' => array_map(function (array $item) {
                unset($item['net_amount_cents']);

                return $item;
            }, $items),
        ];
    }

    private function normalizeMonth(CarbonInterface|string $month): CarbonImmutable
    {
        if ($month instanceof CarbonInterface) {
            return CarbonImmutable::instance($month)->startOfMonth();
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new DomainException('A hónap formátuma érvénytelen. A várt formátum: YYYY-MM.');
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', config('app.timezone'))
            ->startOfMonth();
    }

    private function calculateVatCents(int $netAmountCents, string $vatRate): int
    {
        $vatRateBasisPoints = $this->decimalToScaledInteger($vatRate, 2);

        return intdiv(($netAmountCents * $vatRateBasisPoints) + 5000, 10000);
    }

    private function decimalToCents(string|int|float|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->decimalToScaledInteger((string) $value, 2);
    }

    private function decimalToScaledInteger(string $value, int $scale): int
    {
        $normalized = trim(str_replace(',', '.', $value));

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            throw new DomainException(sprintf('Érvénytelen pénzösszeg: %s', $value));
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$wholePart, $fractionPart] = array_pad(explode('.', $normalized, 2), 2, '');
        $fractionPart = str_pad(substr($fractionPart, 0, $scale), $scale, '0');

        $result = ((int) $wholePart * (10 ** $scale)) + (int) $fractionPart;

        return $negative ? -$result : $result;
    }

    private function centsToDatabaseDecimal(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        $negative = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $negative, intdiv($cents, 100), $cents % 100);
    }

    private function formatMoney(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' Ft';
    }

    private function formatBillingMonthLabel(CarbonImmutable $billingMonth): string
    {
        $months = [
            1 => 'január',
            2 => 'február',
            3 => 'március',
            4 => 'április',
            5 => 'május',
            6 => 'június',
            7 => 'július',
            8 => 'augusztus',
            9 => 'szeptember',
            10 => 'október',
            11 => 'november',
            12 => 'december',
        ];

        return sprintf('%d. %s', $billingMonth->year, $months[$billingMonth->month]);
    }
}
