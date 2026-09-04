<?php

namespace Tests\Feature\PaymentObligation;

use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regressziós teszt: a csávolyi (és minden más, kizárólag "legacy" fizetési
 * modellt használó) intézmény admin pénzügyi kimutatásán NEM jelenhet meg a
 * másik intézmény kétkomponensű (Zsárica Alapítvány / óvodai) számlázási
 * logikájából származó oszlop vagy címke - ld. felhasználói kérés 9. és
 * "NEM SZABAD" pontja, valamint a mandatory teszteset (8) 9. pontja.
 *
 * A split_manual_transfer_enabled = true esetet is lefedjük, hogy igazoljuk:
 * a MEGLÉVŐ, más intézmény által ténylegesen használt logikát a javítás nem
 * törölte, csak intézményi szinten helyesen elrejti a nem odatartozó
 * intézményeknél.
 */
class PaymentObligationsZsaricaVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_institution_admin_table_has_no_zsarica_labels(): void
    {
        $response = $this->renderPaymentObligationsIndex(splitManualTransferEnabled: false);

        $response->assertOk();
        $response->assertDontSee('Zsárica');
        $response->assertDontSee('Óvodai rész');
        $response->assertSee('Aktuális havi fizetendő');
        $response->assertSee('Korábbi tartozás/túlfizetés');
    }

    public function test_split_institution_admin_table_still_shows_zsarica_labels(): void
    {
        $response = $this->renderPaymentObligationsIndex(splitManualTransferEnabled: true);

        $response->assertOk();
        $response->assertSee('Zsárica');
    }

    private function renderPaymentObligationsIndex(bool $splitManualTransferEnabled)
    {
        $institution = Institution::create([
            'name' => 'Csávolyi Napközi Teszt',
            'institution_code' => 'CSAV'.($splitManualTransferEnabled ? 'SPLIT' : 'LEGACY').random_int(1000, 9999),
            'type' => 'iskola',
            'active' => true,
        ]);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'split_manual_transfer_enabled' => $splitManualTransferEnabled,
            ])
        );

        $child = Child::create([
            'institution_id' => $institution->id,
            'name' => 'Teszt Gyermek',
            'active' => true,
        ]);

        $period = now()->startOfMonth();

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => $period->year,
            'month' => $period->month,
            'payment_model' => $splitManualTransferEnabled ? 'split_manual_transfer' : 'legacy',
            'status' => MonthlyPaymentStatement::STATUS_DRAFT,
            'meal_amount' => 12000,
            'invoiceable_amount' => 12000,
            'total_payable' => 12000,
            'foundation_total_payable' => 12000,
            'kindergarten_total_payable' => 0,
        ]);

        MonthlyPaymentDay::create([
            'monthly_payment_statement_id' => $statement->id,
            'date' => $period->copy()->addMonth()->day(10)->toDateString(),
            'status' => MonthlyPaymentDay::STATUS_PAY,
            'original_daily_price' => 12000,
            'discount_percent' => 0,
            'payable_amount' => 12000,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => 'institution_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->actingAs($user)->get(route('dashboard.institution.payment-obligations.index', [
            'month' => $period->format('Y-m'),
        ]));
    }
}
