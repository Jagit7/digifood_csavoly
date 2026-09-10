<?php

namespace App\Services\Billing;

use App\Models\Institution;
use App\Models\InstitutionBillingRate;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;

/** Shared historical pricing; the first day of the billing month selects the rate. */
class InstitutionMonthlyPricingService
{
    public function ratesForMonth(Collection $institutionIds, CarbonImmutable $billingMonth): Collection
    {
        $billingMonth = $billingMonth->startOfMonth();

        return InstitutionBillingRate::query()
            ->whereIn('institution_id', $institutionIds)
            ->whereDate('valid_from', '<=', $billingMonth->toDateString())
            ->where(function ($query) use ($billingMonth) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $billingMonth->toDateString());
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('institution_id')
            ->map(fn (Collection $group) => $group->first());

    }

    public function buildInstitutionItem(
        Institution $institution,
        InstitutionBillingRate $rate,
        int $childCount
    ): array {
        $pricePerChildCents = $this->decimalToCents($rate->price_per_child);
        $fixedMonthlyFeeCents = $this->decimalToCents($rate->fixed_monthly_fee);
        $minimumMonthlyFeeCents = $this->decimalToCents($rate->minimum_monthly_fee);

        if ($fixedMonthlyFeeCents !== null) {
            $baseAmountCents = $fixedMonthlyFeeCents;
            $description = 'Fix havi díj: '.$this->formatMoney($fixedMonthlyFeeCents);
        } else {
            $baseAmountCents = $childCount * ($pricePerChildCents ?? 0);
            $description = sprintf(
                '%d fő × %s = %s',
                $childCount,
                $this->formatMoney($pricePerChildCents ?? 0),
                $this->formatMoney($baseAmountCents)
            );
        }

        $netAmountCents = $baseAmountCents;

        if ($minimumMonthlyFeeCents !== null && $minimumMonthlyFeeCents > $netAmountCents) {
            $netAmountCents = $minimumMonthlyFeeCents;
            $description .= ', alkalmazott minimumdíj: '.$this->formatMoney($minimumMonthlyFeeCents);
        }

        return [
            'institution_id' => $institution->id,
            'institution_name_snapshot' => $institution->name,
            'child_count' => $childCount,
            'price_per_child' => $this->centsToDatabaseDecimal($pricePerChildCents),
            'fixed_monthly_fee' => $this->centsToDatabaseDecimal($fixedMonthlyFeeCents),
            'minimum_monthly_fee' => $this->centsToDatabaseDecimal($minimumMonthlyFeeCents),
            'net_amount' => $this->centsToDatabaseDecimal($netAmountCents),
            'calculation_description' => $description,
            'net_amount_cents' => $netAmountCents,
        ];
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
}
