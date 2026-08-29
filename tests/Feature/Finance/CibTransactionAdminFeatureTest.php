<?php

namespace Tests\Feature\Finance;

use App\Models\Child;
use App\Models\CibTransaction;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\ParentMonthlySettlementPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CibTransactionAdminFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_admin_can_search_cib_transactions_with_required_fields(): void
    {
        $institution = Institution::create([
            'name' => 'Admin Intezmeny',
            'institution_code' => 'CIB100',
            'type' => 'iskola',
            'active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);
        $admin->institutions()->attach($institution->id, [
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Kiss',
            'first_name' => 'Eva',
            'email' => 'kiss.eva@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $child = $this->createChild($institution->id, 'Admin Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 9800,
            'invoiceable_amount' => 9800,
            'previous_balance' => 0,
            'total_payable' => 9800,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $payment = ParentMonthlySettlementPayment::create([
            'user_id' => $admin->id,
            'guardian_id' => $guardian->id,
            'year' => 2026,
            'month' => 7,
            'reference' => 'CSAL-202607-ADMIN01',
            'idempotency_key' => 'cib-admin-2026-07',
            'payment_method' => 'online',
            'status' => ParentMonthlySettlementPayment::STATUS_COMPLETED,
            'total_amount' => 9800,
            'transaction_reference' => '1234567890123456',
        ]);

        $payment->items()->create([
            'child_id' => $child->id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => 9800,
            'paid_amount' => 9800,
        ]);

        CibTransaction::create([
            'institution_id' => $institution->id,
            'parent_monthly_settlement_payment_id' => $payment->id,
            'user_id' => $admin->id,
            'guardian_id' => $guardian->id,
            'pid' => 'SNL0001',
            'trid' => '1234567890123456',
            'order_ref' => $payment->reference,
            'amount' => 9800,
            'currency' => 'HUF',
            'status' => CibTransaction::STATUS_SUCCESSFUL,
            'init_rc' => '00',
            'init_rt' => 'OK',
            'final_rc' => '00',
            'final_rt' => 'Lezarva',
            'anum' => 'AUTH123456',
            'merchant_url' => 'https://ekit.cib.hu/market.saki',
            'customer_url' => 'https://ekit.cib.hu/customer.saki',
            'return_url' => route('parent.monthly-settlements.index', ['month' => '2026-07']),
            'init_requested_at' => now(),
            'init_completed_at' => now(),
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.institution.finance.cib-transactions', [
            'search' => 'AUTH123456',
        ], false));

        $response->assertOk();
        $response->assertSee('1234567890123456');
        $response->assertSee('AUTH123456');
        $response->assertSee('Lezarva');
        $response->assertSee('9 800 Ft');
        $response->assertSee('Admin Gyermek');
        $response->assertSee('Kiss Eva');
    }

    private function createChild(int $institutionId, string $name): Child
    {
        $discount = DiscountType::firstOrCreate(
            [
                'institution_id' => $institutionId,
                'name' => 'Kedvezmeny nelkul',
                'percentage' => 0,
            ],
            [
                'active' => true,
                'sort_order' => 1,
            ]
        );

        return Child::create([
            'institution_id' => $institutionId,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId), 0, 10),
            'group_name' => '1.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }
}
