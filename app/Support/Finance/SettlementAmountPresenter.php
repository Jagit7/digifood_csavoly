<?php

namespace App\Support\Finance;

/**
 * Csak MEGJELENÍTÉSI segédosztály a havi elszámolás (MonthlyPaymentStatement)
 * már meglévő, kiszámolt mezőinek (total_payable, previous_balance) egységes
 * bemutatásához admin és szülői felületen egyaránt.
 *
 * FONTOS: ez az osztály NEM számol ki semmilyen ÚJ pénzügyi értéket - kizárólag
 * a PaymentObligationCalculatorService által már kiszámolt total_payable /
 * previous_balance mezőket formázza / bontja szét megjelenítésre, hogy:
 *
 *  - túlfizetés esetén a "fizetendő" összeg SOHA ne jelenjen meg negatív
 *    számként (helyette "Fizetendő összesen: 0 Ft" + külön "Fennmaradó
 *    túlfizetés" sor/mező), és
 *  - a "Korábbi egyenleg" előjelétől függően "Korábbi tartozás" / "Korábbi
 *    túlfizetés" címkével, mindig pozitív összeggel jelenjen meg (soha nem
 *    negatív "tartozás"-ként).
 *
 * Az admin és a szülői felület UGYANEZT a segédosztályt használja, hogy a két
 * felület mindig azonos összegeket és azonos szabály szerint mutasson.
 */
final class SettlementAmountPresenter
{
    /**
     * A "Fizetendő összesen" mezőben megjelenítendő összeg - túlfizetés esetén
     * (total_payable < 0) soha nem negatív, hanem 0.
     */
    public static function payableDisplayAmount(int $totalPayable): int
    {
        return max(0, $totalPayable);
    }

    /**
     * A jelen elszámolásban még fel nem használt, fennmaradó túlfizetés összege
     * (mindig >= 0). Csak akkor pozitív, ha total_payable negatív.
     */
    public static function overpaymentAmount(int $totalPayable): int
    {
        return max(0, -$totalPayable);
    }

    public static function hasOverpayment(int $totalPayable): bool
    {
        return $totalPayable < 0;
    }

    /**
     * "Korábbi tartozás" vagy "Korábbi túlfizetés" címke a previous_balance
     * előjelétől függően (pozitív = tartozás, negatív = túlfizetés).
     */
    public static function previousBalanceLabel(int $previousBalance): string
    {
        return $previousBalance < 0 ? 'Korábbi túlfizetés' : 'Korábbi tartozás';
    }

    /**
     * A "Korábbi tartozás/túlfizetés" mezőben megjelenítendő, mindig pozitív
     * (abszolút értékű) összeg - a címkét lásd previousBalanceLabel().
     */
    public static function previousBalanceDisplayAmount(int $previousBalance): int
    {
        return abs($previousBalance);
    }

    /**
     * Egy soros, előjel szerint helyesen címkézett összefoglaló, pl.:
     * "Korábbi tartozás: +12 000 Ft" vagy "Korábbi túlfizetés: -3 000 Ft".
     */
    public static function previousBalanceSignedLabel(int $previousBalance): string
    {
        if ($previousBalance === 0) {
            return 'Nincs korábbi egyenleg';
        }

        return $previousBalance > 0
            ? sprintf('Korábbi tartozás: +%s Ft', number_format($previousBalance, 0, ',', ' '))
            : sprintf('Korábbi túlfizetés: -%s Ft', number_format(abs($previousBalance), 0, ',', ' '));
    }
}
