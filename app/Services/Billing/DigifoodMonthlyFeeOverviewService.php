<?php

namespace App\Services\Billing;

use App\Models\Institution;
use App\Models\StudentMealSetting;

/**
 * Az étkező gyermekek száma intézményenként szorozva az adott intézmény
 * "Digifood havidíj / aktív étkező" díjával (Institution::
 * saas_fee_per_active_eater - ld. institutions/edit.blade.php), majd
 * összesítve minden intézményre.
 *
 * FONTOS: ez NEM ugyanaz a szám, mint amit a SaasBillingSummaryService
 * a tényleges havi SaaS-számlázáshoz használ (az az AKTÍV gyermekek +
 * dolgozók összlétszámát nézi, kizárva a számlázási partnerhez rendelt
 * intézményeket) - ez itt egy egyszerűbb, tájékoztató jellegű kártya-
 * számítás a superadmin kezelőfelület stat kártyáihoz, ami kizárólag az
 * ÉPPEN AKTÍV ÉTKEZÉSI BEÁLLÍTÁSSAL rendelkező gyermekeket veszi számba
 * (ld. ChildController és SuperAdminOverviewController "gyerekszám x
 * havidíj" kártyája - mindkettő ugyanezt a szolgáltatást használja, hogy
 * a két felületen mindig ugyanaz a szám jelenjen meg).
 */
class DigifoodMonthlyFeeOverviewService
{
    public function calculate(?string $today = null): array
    {
        $today ??= now(config('digifood.business_timezone', config('app.timezone')))->toDateString();

        $eatingByInstitution = StudentMealSetting::query()
            ->whereDate('valid_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $today);
            })
            ->select('institution_id')
            ->selectRaw('COUNT(DISTINCT student_id) as eating_count')
            ->groupBy('institution_id')
            ->pluck('eating_count', 'institution_id');

        $rates = Institution::query()
            ->whereIn('id', $eatingByInstitution->keys())
            ->pluck('saas_fee_per_active_eater', 'id');

        $totalEating = 0;
        $totalFee = 0.0;
        $distinctRates = [];

        foreach ($eatingByInstitution as $institutionId => $count) {
            $rate = (float) ($rates[$institutionId] ?? 0);
            $totalEating += (int) $count;
            $totalFee += round($count * $rate, 2);

            if ($count > 0) {
                $distinctRates[round($rate, 2)] = true;
            }
        }

        return [
            'eating_count' => $totalEating,
            'total' => round($totalFee, 2),
            // Ha minden étkező gyermek intézménye ugyanazt a havidíjat
            // használja, kiírható az egyszerű "gyerekszám x havidíj"
            // képlet; eltérő díjak esetén csak az összesített összeget
            // mutatjuk (ld. a kártyát használó nézeteket).
            'uniform_rate' => count($distinctRates) === 1 ? (float) array_key_first($distinctRates) : null,
        ];
    }
}
