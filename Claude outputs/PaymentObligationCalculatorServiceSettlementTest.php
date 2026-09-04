<?php

namespace Tests\Feature\PaymentObligation;

use App\Models\Child;
use App\Models\Institution;
use App\Models\PaymentObligation\FinancialAdjustment;
use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use App\Support\Finance\SettlementAmountPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressziós tesztek a csávolyi (legacy, nem "split_manual_transfer") havi
 * elszámolás pénzügyi bontására - ld. felhasználói kérés.
 *
 * FONTOS: ezek a tesztek a MEGLÉVŐ PaymentObligationCalculatorService::
 * refreshStatementTotals() metódust hívják - NEM egy új, párhuzamos
 * elszámolási logikát. A tesztek a napi tételek (MonthlyPaymentDay::
 * payable_amount) és a kézi korrekciók (FinancialAdjustment) alapján
 * ellenőrzik, hogy:
 *
 *  - az "aktuális havi fizetendő" (invoiceable_amount) SOHA nem tartalmazza
 *    a korábbi tartozást/túlfizetést vagy a külön befizetéseket,
 *  - a "korábbi egyenleg" (previous_balance) és a "fizetendő összesen"
 *    (total_payable) a meglévő mezőkből helyesen adódik össze,
 *  - túlfizetés esetén a total_payable negatívba fordulhat, de a
 *    megjelenítési réteg (SettlementAmountPresenter) ezt sosem mutatja
 *    negatív "fizetendő"-ként,
 *  - nincs kétszeres beszámítás (ld. felhasználói kérés 7. pont).
 */
class PaymentObligationCalculatorServiceSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_1_only_current_month_no_history(): void
    {
        $statement = $this->makeStatement(mealAmount: 10000);

        $result = $this->refresh($statement);

        $this->assertSame(10000, $result->meal_amount);
        $this->assertSame(0, $result->previous_cancellation_credit);
        $this->assertSame(0, $result->billing_adjustment_amount);
        $this->assertSame(10000, $result->invoiceable_amount);
        $this->assertSame(0, $result->previous_balance);
        $this->assertSame(10000, $result->total_payable);
    }

    public function test_case_2_month_with_prior_debt(): void
    {
        $statement = $this->makeStatement(mealAmount: 10000);
        $this->addAdjustment($statement, FinancialAdjustment::TYPE_OPENING_DEBT, 4000, affectsInvoice: false);

        $result = $this->refresh($statement);

        $this->assertSame(10000, $result->invoiceable_amount, 'A havi díjat a korábbi tartozás nem módosíthatja.');
        $this->assertSame(4000, $result->previous_balance);
        $this->assertSame(14000, $result->total_payable);
    }

    public function test_case_3_month_with_prior_overpayment(): void
    {
        $statement = $this->makeStatement(mealAmount: 10000);
        $this->addAdjustment($statement, FinancialAdjustment::TYPE_OPENING_CREDIT, -3000, affectsInvoice: false);

        $result = $this->refresh($statement);

        $this->assertSame(10000, $result->invoiceable_amount);
        $this->assertSame(-3000, $result->previous_balance);
        $this->assertSame(7000, $result->total_payable);
        $this->assertSame('Korábbi túlfizetés', SettlementAmountPresenter::previousBalanceLabel($result->previous_balance));
        $this->assertSame(3000, SettlementAmountPresenter::previousBalanceDisplayAmount($result->previous_balance));
    }

    public function test_case_4_overpayment_larger_than_current_month_due(): void
    {
        $statement = $this->makeStatement(mealAmount: 5000);
        $this->addAdjustment($statement, FinancialAdjustment::TYPE_OPENING_CREDIT, -12000, affectsInvoice: false);

        $result = $this->refresh($statement);

        $this->assertSame(5000, $result->invoiceable_amount, 'A havi díj túlfizetés esetén sem változhat.');
        $this->assertSame(-7000, $result->total_payable, 'A total_payable belső mezőként negatív lehet.');

        // A megjelenítési rétegnek SOHA nem szabad negatív "fizetendő"-t
        // mutatnia - ld. felhasználói kérés 3. pont.
        $this->assertSame(0, SettlementAmountPresenter::payableDisplayAmount($result->total_payable));
        $this->assertSame(7000, SettlementAmountPresenter::overpaymentAmount($result->total_payable));
        $this->assertTrue(SettlementAmountPresenter::hasOverpayment($result->total_payable));
    }

    public function test_case_5_separate_payment_does_not_change_base_amount(): void
    {
        $statement = $this->makeStatement(mealAmount: 8000);
        $this->addAdjustment($statement, FinancialAdjustment::TYPE_CREDIT, -1000, affectsInvoice: false);

        $result = $this->refresh($statement);

        $this->assertSame(8000, $result->invoiceable_amount, 'A külön befizetés nem módosíthatja az aktuális havi fizetendőt.');
        $this->assertSame(-1000, $result->previous_balance);
        $this->assertSame(7000, $result->total_payable);
    }

    public function test_case_6_prior_debt_and_separate_payment_together(): void
    {
        $statement = $this->makeStatement(mealAmount: 8000);
        $this->addAdjustment($statement, FinancialAdjustment::TYPE_OPENING_DEBT, 4000, affectsInvoice: false);
        $this->addAdjustment($statement, FinancialAdjustment::TYPE_CREDIT, -1000, affectsInvoice: false);

        $result = $this->refresh($statement);

        $this->assertSame(8000, $result->invoiceable_amount);
        $this->assertSame(3000, $result->previous_balance, 'A tartozás és a befizetés nettósítva adja a korábbi egyenleget - nincs kétszeres beszámítás.');
        $this->assertSame(11000, $result->total_payable);
    }

    public function test_case_8_original_month_charge_unchanged_after_corrections(): void
    {
        $statement = $this->makeStatement(mealAmount: 15000);

        // Első számítás (még korrekciók nélkül) - ez adja az alapértéket,
        // amihez a korrekciók utáni állapotot hasonlítjuk.
        $before = $this->refresh($statement);
        $this->assertSame(15000, $before->meal_amount);
        $this->assertSame(15000, $before->invoiceable_amount);

        $this->addAdjustment($statement, FinancialAdjustment::TYPE_OPENING_DEBT, 9000, affectsInvoice: false);
        $this->addAdjustment($statement, FinancialAdjustment::TYPE_CREDIT, -2000, affectsInvoice: false);

        $after = $this->refresh($statement);

        $this->assertSame($before->meal_amount, $after->meal_amount, 'A "Következő havi étkezési díj" a korrekciók után sem változhat.');
        $this->assertSame($before->invoiceable_amount, $after->invoiceable_amount, 'Az "aktuális havi fizetendő" a korrekciók után sem változhat.');
        $this->assertSame(7000, $after->previous_balance);
        $this->assertSame(22000, $after->total_payable);
    }

    /**
     * A felhasználó által megadott KÖTELEZŐ teszteset (ld. "PONTOSÍTOTT
     * FIZETÉSI LOGIKA"): márciusi elszámolás.
     *  - áprilisi (M+1) előírt étkezési díj: 22 000 Ft
     *  - februári (M-1) lemondás jóváírása: 3 000 Ft
     *  - aktuális havi fizetendő (márciusi): 19 000 Ft
     *  - korábbi tartozás: 4 000 Ft
     *  - külön befizetés: 1 000 Ft
     *  - ténylegesen fizetendő: 22 000 Ft
     */
    public function test_mandatory_march_statement_scenario(): void
    {
        $statement = $this->makeStatement(mealAmount: 22000, year: 2026, month: 3);
        $this->addCancellationCredit($statement, 3000);
        $this->addAdjustment($statement, FinancialAdjustment::TYPE_OPENING_DEBT, 4000, affectsInvoice: false);
        $this->addAdjustment($statement, FinancialAdjustment::TYPE_CREDIT, -1000, affectsInvoice: false);

        $result = $this->refresh($statement);

        $this->assertSame(22000, $result->meal_amount, 'Következő havi (áprilisi) előírt étkezési díj.');
        $this->assertSame(3000, $result->previous_cancellation_credit, 'Korábbi (februári) lemondások jóváírása.');
        $this->assertSame(19000, $result->invoiceable_amount, 'Aktuális havi (márciusi) fizetendő.');
        $this->assertSame(3000, $result->previous_balance, 'Korábbi tartozás (4000) és külön befizetés (-1000) nettósítva.');
        $this->assertSame(22000, $result->total_payable, 'Ténylegesen fizetendő.');

        // Egyik szint sem írja felül a másikat - mindhárom szint egyszerre
        // olvasható vissza a statementből.
        $this->assertNotSame($result->meal_amount, $result->invoiceable_amount);
        $this->assertNotSame($result->invoiceable_amount, $result->total_payable);
    }

    private function makeStatement(int $mealAmount, int $year = 2026, int $month = 6): MonthlyPaymentStatement
    {
        $institution = Institution::create([
            'name' => 'Csávolyi Napközi Teszt',
            'institution_code' => 'CSAV'.$year.$month.random_int(100, 999),
            'type' => 'ovoda',
            'active' => true,
        ]);

        $child = Child::create([
            'institution_id' => $institution->id,
            'name' => 'Teszt Gyermek',
            'active' => true,
        ]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => $year,
            'month' => $month,
            'payment_model' => 'legacy',
            'status' => MonthlyPaymentStatement::STATUS_DRAFT,
        ]);

        MonthlyPaymentDay::create([
            'monthly_payment_statement_id' => $statement->id,
            'date' => sprintf('%04d-%02d-15', $year, $month),
            'status' => MonthlyPaymentDay::STATUS_PAY,
            'original_daily_price' => $mealAmount,
            'discount_percent' => 0,
            'payable_amount' => $mealAmount,
        ]);

        return $statement->fresh();
    }

    private function addAdjustment(
        MonthlyPaymentStatement $statement,
        string $type,
        int $signedAmount,
        bool $affectsInvoice
    ): FinancialAdjustment {
        return FinancialAdjustment::create([
            'institution_id' => $statement->institution_id,
            'child_id' => $statement->child_id,
            'monthly_payment_statement_id' => $statement->id,
            'type' => $type,
            'amount' => $signedAmount,
            'affects_invoice' => $affectsInvoice,
            'reference_year' => $statement->year,
            'reference_month' => $statement->month,
            'reason' => 'Teszt korrekció',
            'entry_date' => now(),
        ]);
    }

    private function addCancellationCredit(MonthlyPaymentStatement $statement, int $amount): FinancialAdjustment
    {
        return FinancialAdjustment::create([
            'institution_id' => $statement->institution_id,
            'child_id' => $statement->child_id,
            'monthly_payment_statement_id' => null,
            'type' => FinancialAdjustment::TYPE_CANCELLATION_CREDIT,
            'amount' => $amount,
            'affects_invoice' => true,
            'reference_year' => $statement->year,
            'reference_month' => $statement->month,
            'reason' => 'Teszt lemondási jóváírás',
            'entry_date' => now(),
        ]);
    }

    private function refresh(MonthlyPaymentStatement $statement): MonthlyPaymentStatement
    {
        return app(PaymentObligationCalculatorService::class)->refreshStatementTotals($statement->fresh());
    }
}
