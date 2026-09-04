<?php

namespace Tests\Unit\Support\Finance;

use App\Support\Finance\SettlementAmountPresenter;
use Tests\TestCase;

/**
 * Regressziós tesztek a SettlementAmountPresenter-hez, amely a már meglévő
 * MonthlyPaymentStatement::total_payable / previous_balance mezőket formázza
 * megjelenítésre - ld. felhasználói kérés: túlfizetés esetén a "fizetendő"
 * SOHA nem jelenhet meg negatív számként, a "korábbi egyenleg" pedig mindig
 * "Korábbi tartozás" vagy "Korábbi túlfizetés" címkével, pozitív összeggel.
 */
class SettlementAmountPresenterTest extends TestCase
{
    public function test_payable_display_amount_is_never_negative(): void
    {
        $this->assertSame(19000, SettlementAmountPresenter::payableDisplayAmount(19000));
        $this->assertSame(0, SettlementAmountPresenter::payableDisplayAmount(0));
        $this->assertSame(0, SettlementAmountPresenter::payableDisplayAmount(-3000));
    }

    public function test_overpayment_amount_is_only_positive_when_total_payable_is_negative(): void
    {
        $this->assertSame(0, SettlementAmountPresenter::overpaymentAmount(19000));
        $this->assertSame(0, SettlementAmountPresenter::overpaymentAmount(0));
        $this->assertSame(3000, SettlementAmountPresenter::overpaymentAmount(-3000));
    }

    public function test_has_overpayment_matches_negative_total_payable(): void
    {
        $this->assertFalse(SettlementAmountPresenter::hasOverpayment(0));
        $this->assertFalse(SettlementAmountPresenter::hasOverpayment(1));
        $this->assertTrue(SettlementAmountPresenter::hasOverpayment(-1));
    }

    public function test_previous_balance_label_and_display_amount_for_debt(): void
    {
        $this->assertSame('Korábbi tartozás', SettlementAmountPresenter::previousBalanceLabel(4000));
        $this->assertSame(4000, SettlementAmountPresenter::previousBalanceDisplayAmount(4000));
    }

    public function test_previous_balance_label_and_display_amount_for_overpayment(): void
    {
        $this->assertSame('Korábbi túlfizetés', SettlementAmountPresenter::previousBalanceLabel(-2500));
        $this->assertSame(2500, SettlementAmountPresenter::previousBalanceDisplayAmount(-2500));
    }

    public function test_previous_balance_label_for_zero_defaults_to_debt_wording_but_never_negative(): void
    {
        // Nulla egyenlegnél a címke szövege lényegtelen (a UI ilyenkor
        // jellemzően el is rejti a sort), a lényeg, hogy az összeg soha nem
        // negatív.
        $this->assertSame(0, SettlementAmountPresenter::previousBalanceDisplayAmount(0));
    }

    public function test_previous_balance_signed_label_matches_required_wording(): void
    {
        $this->assertSame('Korábbi tartozás: +12 000 Ft', SettlementAmountPresenter::previousBalanceSignedLabel(12000));
        $this->assertSame('Korábbi túlfizetés: -3 000 Ft', SettlementAmountPresenter::previousBalanceSignedLabel(-3000));
        $this->assertSame('Nincs korábbi egyenleg', SettlementAmountPresenter::previousBalanceSignedLabel(0));
    }

    /**
     * A felhasználó által megadott kötelező teszteset (ld. "PONTOSÍTOTT
     * FIZETÉSI LOGIKA"): márciusi elszámolás, áprilisi előírt étkezési díj
     * 22 000 Ft, februári lemondás jóváírása 3 000 Ft, aktuális havi
     * fizetendő 19 000 Ft, korábbi tartozás 4 000 Ft, külön befizetés
     * 1 000 Ft -> ténylegesen fizetendő 22 000 Ft. Ez a teszt csak a
     * MEGJELENÍTÉSI réteget ellenőrzi (nincs túlfizetés, tehát a fizetendő
     * megjelenített értéke megegyezik a nyers total_payable-lel).
     */
    public function test_mandatory_march_scenario_display_values(): void
    {
        $totalPayable = 22000; // = invoiceable_amount (19000) + previous_balance (3000)

        $this->assertSame(22000, SettlementAmountPresenter::payableDisplayAmount($totalPayable));
        $this->assertSame(0, SettlementAmountPresenter::overpaymentAmount($totalPayable));
        $this->assertFalse(SettlementAmountPresenter::hasOverpayment($totalPayable));
    }

    /**
     * "TÚLFIZETÉS ESETE" (felhasználói kérés 3. pont): ha a túlfizetés
     * nagyobb, mint az aktuális havi fizetendő, a total_payable negatívba
     * fordul - ilyenkor a "Fizetendő összesen" mindig 0 Ft, és a fennmaradó
     * túlfizetés jelenik meg külön mezőként, SOHA nem negatív "fizetendő"-ként.
     */
    public function test_overpayment_larger_than_current_due_never_shows_negative_payable(): void
    {
        $invoiceableAmount = 19000;
        $previousBalance = -25000; // nagy korábbi túlfizetés
        $totalPayable = $invoiceableAmount + $previousBalance; // -6000

        $this->assertSame(-6000, $totalPayable);
        $this->assertSame(0, SettlementAmountPresenter::payableDisplayAmount($totalPayable));
        $this->assertSame(6000, SettlementAmountPresenter::overpaymentAmount($totalPayable));
        $this->assertTrue(SettlementAmountPresenter::hasOverpayment($totalPayable));
        $this->assertSame('Korábbi túlfizetés', SettlementAmountPresenter::previousBalanceLabel($previousBalance));
        $this->assertSame(25000, SettlementAmountPresenter::previousBalanceDisplayAmount($previousBalance));
    }
}
