<?php

namespace Tests\Unit\Finance;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\Finance\InstitutionPaymentComponentService;
use App\Support\Finance\PaymentComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstitutionPaymentComponentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_payment_is_allocated_to_oldest_statement_of_same_component(): void
    {
        [$institution, $child, $user] = $this->seedInstitution();

        $older = $this->createStatement($institution, $child, 2026, 10, 5000, 0);
        $newer = $this->createStatement($institution, $child, 2026, 11, 3000, 0);

        $payment = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => null,
            'monthly_payment_statement_id' => $newer->id,
            'payment_component' => PaymentComponent::FOUNDATION,
            'amount' => 6200,
            'paid_at' => '2026-11-10 09:00:00',
            'payment_method' => InstitutionPayment::METHOD_BANK_TRANSFER,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => 'BANK-1',
            'recorded_by' => $user->id,
        ]);

        app(InstitutionPaymentComponentService::class)->syncAllocationsForPayment($payment);

        $allocations = $payment->allocations()->orderBy('id')->get();

        $this->assertCount(2, $allocations);
        $this->assertSame($older->id, $allocations[0]->monthly_payment_statement_id);
        $this->assertSame(5000, $allocations[0]->amount);
        $this->assertSame($newer->id, $allocations[1]->monthly_payment_statement_id);
        $this->assertSame(1200, $allocations[1]->amount);
    }

    public function test_overpayment_and_debt_are_tracked_separately_per_component(): void
    {
        [$institution, $child, $user] = $this->seedInstitution();

        $statement = $this->createStatement($institution, $child, 2026, 11, 5000, 12000);

        $foundationPayment = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => null,
            'monthly_payment_statement_id' => $statement->id,
            'payment_component' => PaymentComponent::FOUNDATION,
            'amount' => 6000,
            'paid_at' => '2026-11-11 09:00:00',
            'payment_method' => InstitutionPayment::METHOD_BANK_TRANSFER,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => 'BANK-2',
            'recorded_by' => $user->id,
        ]);
        $kindergartenPayment = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => null,
            'monthly_payment_statement_id' => $statement->id,
            'payment_component' => PaymentComponent::KINDERGARTEN,
            'amount' => 10000,
            'paid_at' => '2026-11-11 10:00:00',
            'payment_method' => InstitutionPayment::METHOD_BANK_TRANSFER,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => 'BANK-3',
            'recorded_by' => $user->id,
        ]);

        $service = app(InstitutionPaymentComponentService::class);
        $service->syncAllocationsForPayment($foundationPayment);
        $service->syncAllocationsForPayment($kindergartenPayment);

        $summary = $service->buildStatementSummaries(collect([$statement->fresh()]))->get($statement->id);

        $this->assertSame(-1000, $summary['foundation_balance']);
        $this->assertSame(2000, $summary['kindergarten_balance']);
        $this->assertSame(1000, $summary['net_balance']);
    }

    public function test_unapplied_overpayment_is_carried_forward_only_for_same_component(): void
    {
        [$institution, $child, $user] = $this->seedInstitution();

        $currentStatement = $this->createStatement($institution, $child, 2026, 11, 0, 0);

        $payment = InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => null,
            'monthly_payment_statement_id' => $currentStatement->id,
            'payment_component' => PaymentComponent::FOUNDATION,
            'amount' => 800,
            'paid_at' => '2026-11-11 09:00:00',
            'payment_method' => InstitutionPayment::METHOD_BANK_TRANSFER,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => 'BANK-4',
            'recorded_by' => $user->id,
        ]);

        $service = app(InstitutionPaymentComponentService::class);
        $service->syncAllocationsForPayment($payment);

        $foundationBalance = $service->sumComponentPreviousBalance(
            $institution->id,
            $child->id,
            PaymentComponent::FOUNDATION,
            Carbon::create(2026, 12, 1)
        );
        $kindergartenBalance = $service->sumComponentPreviousBalance(
            $institution->id,
            $child->id,
            PaymentComponent::KINDERGARTEN,
            Carbon::create(2026, 12, 1)
        );

        $this->assertSame(-800, $foundationBalance);
        $this->assertSame(0, $kindergartenBalance);
    }

    private function seedInstitution(): array
    {
        $institution = Institution::create([
            'name' => 'Teszt Intezmeny',
            'institution_code' => 'FIN001',
            'type' => 'ovoda',
            'active' => true,
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

        $discount = DiscountType::create([
            'institution_id' => $institution->id,
            'name' => '0%',
            'percentage' => 0,
            'active' => true,
            'sort_order' => 1,
        ]);

        $child = Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => 'Penzugyi Gyermek',
            'educational_identifier' => 'FIN001',
            'group_name' => 'Katica',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        return [$institution, $child, $user];
    }

    private function createStatement(Institution $institution, Child $child, int $year, int $month, int $foundation, int $kindergarten): MonthlyPaymentStatement
    {
        return MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => $year,
            'month' => $month,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'payment_model' => InstitutionPaymentComponentService::PAYMENT_MODEL_SPLIT_MANUAL_TRANSFER,
            'planned_meal_days' => 0,
            'previous_month_cancelled_days' => 0,
            'meal_amount' => $foundation + $kindergarten,
            'previous_cancellation_credit' => 0,
            'billing_adjustment_amount' => 0,
            'invoiceable_amount' => $foundation + $kindergarten,
            'previous_balance' => 0,
            'total_payable' => $foundation + $kindergarten,
            'foundation_gross_amount' => $foundation,
            'foundation_cancellation_credit' => 0,
            'foundation_billing_adjustment_amount' => 0,
            'foundation_invoiceable_amount' => $foundation,
            'foundation_previous_balance' => 0,
            'foundation_total_payable' => $foundation,
            'kindergarten_gross_amount' => $kindergarten,
            'kindergarten_discount_amount' => 0,
            'kindergarten_cancellation_credit' => 0,
            'kindergarten_billing_adjustment_amount' => 0,
            'kindergarten_invoiceable_amount' => $kindergarten,
            'kindergarten_previous_balance' => 0,
            'kindergarten_total_payable' => $kindergarten,
            'issues' => [],
        ]);
    }
}
