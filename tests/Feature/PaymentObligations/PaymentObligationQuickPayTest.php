<?php

namespace Tests\Feature\PaymentObligations;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionMealType;
use App\Models\InstitutionPayment;
use App\Models\MealType;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\Finance\InstitutionPaymentComponentService;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use App\Support\Finance\PaymentComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A "pontos összegű befizetés gyors rögzítése" funkció (payment-obligations
 * lista -> pipa gomb -> modal) tesztjei. A funkció a MEGLÉVŐ, kézi
 * befizetés-rögzítéshez használt InstitutionPayment modellt és
 * InstitutionPaymentComponentService::buildStatementSummaries()/
 * syncAllocationsForPayment() logikát használja - nincs külön/párhuzamos
 * fizetési struktúra.
 */
class PaymentObligationQuickPayTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_pay_records_full_remaining_amount_when_nothing_paid_yet(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        $remaining = $this->remainingAmount($statement);
        $this->assertGreaterThan(0, $remaining);

        $response = $this->actingAs($user)->post(route('dashboard.institution.payment-obligations.quick-pay', $statement));

        $response->assertRedirect(route('dashboard.institution.payment-obligations.index', [
            'month' => sprintf('%04d-%02d', $statement->year, $statement->month),
        ]));
        $response->assertSessionHas('success');

        $this->assertSame(1, InstitutionPayment::query()->where('monthly_payment_statement_id', $statement->id)->count());
        $payment = InstitutionPayment::query()->where('monthly_payment_statement_id', $statement->id)->firstOrFail();
        $this->assertSame($remaining, (int) $payment->amount);
        $this->assertSame(InstitutionPayment::STATUS_COMPLETED, $payment->status);
        $this->assertSame(InstitutionPayment::METHOD_BANK_TRANSFER, $payment->payment_method);
        $this->assertSame($institution->id, $payment->institution_id);
        $this->assertSame($user->id, $payment->recorded_by);
    }

    public function test_quick_pay_only_records_the_remaining_balance_after_a_partial_payment(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        $total = $this->remainingAmount($statement);
        $partial = max(1, intdiv($total, 3));

        $this->createCompletedPayment($institution, $statement, $partial);
        $expectedRemaining = $total - $partial;
        $this->assertSame($expectedRemaining, $this->remainingAmount($statement));

        $response = $this->actingAs($user)->post(route('dashboard.institution.payment-obligations.quick-pay', $statement));

        $response->assertSessionHas('success');

        $newPayment = InstitutionPayment::query()
            ->where('monthly_payment_statement_id', $statement->id)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->orderByDesc('id')
            ->first();

        $this->assertSame($expectedRemaining, (int) $newPayment->amount);
        $this->assertSame(2, InstitutionPayment::query()->where('monthly_payment_statement_id', $statement->id)->count());
    }

    public function test_quick_pay_creates_no_payment_when_statement_is_already_fully_settled(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        $total = $this->remainingAmount($statement);
        $this->createCompletedPayment($institution, $statement, $total);
        $this->assertSame(0, $this->remainingAmount($statement));

        $response = $this->actingAs($user)->post(route('dashboard.institution.payment-obligations.quick-pay', $statement));

        $response->assertSessionHas('error');
        $this->assertSame(1, InstitutionPayment::query()->where('monthly_payment_statement_id', $statement->id)->count());
    }

    public function test_quick_pay_creates_no_payment_when_statement_is_overpaid(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        $total = $this->remainingAmount($statement);
        $this->createCompletedPayment($institution, $statement, $total + 5000);
        $this->assertSame(0, $this->remainingAmount($statement));

        $response = $this->actingAs($user)->post(route('dashboard.institution.payment-obligations.quick-pay', $statement));

        $response->assertSessionHas('error');
        $this->assertSame(1, InstitutionPayment::query()->where('monthly_payment_statement_id', $statement->id)->count());
    }

    public function test_quick_pay_cannot_be_used_on_another_institutions_statement(): void
    {
        [, , $statement] = $this->seedStatement();
        [, $otherUser] = $this->seedUserWithInstitution('QPMASIK');

        $response = $this->actingAs($otherUser)->post(route('dashboard.institution.payment-obligations.quick-pay', $statement));

        $response->assertStatus(403);
        $this->assertSame(0, InstitutionPayment::query()->where('monthly_payment_statement_id', $statement->id)->count());
    }

    public function test_quick_pay_route_rejects_unauthorized_role(): void
    {
        [$institution, , $statement] = $this->seedStatement();

        $secretary = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_SECRETARY,
            'institution_id' => $institution->id,
        ]);
        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $secretary->id,
            'scope_role' => 'institution_secretary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($secretary)->post(route('dashboard.institution.payment-obligations.quick-pay', $statement));

        $response->assertStatus(403);
        $this->assertSame(0, InstitutionPayment::query()->where('monthly_payment_statement_id', $statement->id)->count());
    }

    public function test_quick_pay_route_redirects_guests_to_login_and_creates_no_payment(): void
    {
        [, , $statement] = $this->seedStatement();

        $response = $this->post(route('dashboard.institution.payment-obligations.quick-pay', $statement));

        $response->assertRedirect();
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(0, InstitutionPayment::query()->where('monthly_payment_statement_id', $statement->id)->count());
    }

    public function test_quick_pay_result_uses_the_same_institution_payment_model_as_manual_recording(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        $this->actingAs($user)->post(route('dashboard.institution.payment-obligations.quick-pay', $statement));

        $payment = InstitutionPayment::query()->where('monthly_payment_statement_id', $statement->id)->firstOrFail();

        // Ugyanaz a tábla/modell, mint a kézi befizetés-rögzítésnél - nincs
        // külön "gyors fizetés" tábla vagy jóváhagyott-státusz mező.
        $this->assertDatabaseHas('institution_payments', ['id' => $payment->id]);
        $this->assertSame(PaymentComponent::FOUNDATION, $payment->payment_component);
        $this->assertSame($statement->child_id, $payment->child_id);

        // A meglévő allokáció-szinkronizálás is lefutott (ugyanaz a service hívás,
        // mint a kézi/"Tartozások" oldali gyors fizetésnél).
        $summary = app(InstitutionPaymentComponentService::class)
            ->buildStatementSummaries(collect([$statement->fresh()]))
            ->get($statement->id);
        $this->assertSame(0, $summary['foundation_remaining']);
    }

    public function test_quick_pay_preserves_the_selected_statement_month_on_redirect(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        $response = $this->actingAs($user)->post(route('dashboard.institution.payment-obligations.quick-pay', $statement));

        $expectedMonth = sprintf('%04d-%02d', $statement->year, $statement->month);
        $response->assertRedirect(route('dashboard.institution.payment-obligations.index', ['month' => $expectedMonth]));
    }

    private function remainingAmount(MonthlyPaymentStatement $statement): int
    {
        $summary = app(InstitutionPaymentComponentService::class)
            ->buildStatementSummaries(collect([$statement->fresh()]))
            ->get($statement->id, []);

        return (int) ($summary['foundation_remaining'] ?? 0);
    }

    private function createCompletedPayment(Institution $institution, MonthlyPaymentStatement $statement, int $amount): InstitutionPayment
    {
        $recordedBy = User::query()->where('institution_id', $institution->id)->value('id');

        $payment = new InstitutionPayment;
        $payment->fill([
            'child_id' => $statement->child_id,
            'monthly_payment_statement_id' => $statement->id,
            'payment_component' => PaymentComponent::FOUNDATION,
            'amount' => $amount,
            'paid_at' => now(),
            'payment_method' => InstitutionPayment::METHOD_BANK_TRANSFER,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'note' => 'Teszt előzetes befizetés',
        ]);
        $payment->institution_id = $institution->id;
        $payment->recorded_by = $recordedBy;
        $payment->save();

        return $payment;
    }

    private function seedStatement(): array
    {
        [$institution, $user] = $this->seedUserWithInstitution('QPTESZT');

        $discount = DiscountType::create([
            'institution_id' => $institution->id,
            'name' => 'Alap',
            'percentage' => 0,
            'active' => true,
            'sort_order' => 1,
        ]);

        $child = Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => 'Gyorsfizetes Gyermek',
            'educational_identifier' => 'QPGY001',
            'group_name' => '2.B',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $mealType = MealType::create([
            'code' => 'lunch-qp',
            'name' => 'Ebed QP',
            'default_order' => 1,
        ]);

        $institutionMealType = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $mealType->id,
            'is_active' => true,
            'is_required' => true,
            'display_order' => 1,
        ]);

        InstitutionMealPrice::create([
            'institution_meal_type_id' => $institutionMealType->id,
            'price' => 1200,
            'valid_from' => '2026-01-01',
            'created_by' => $user->id,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institution->id,
            'name' => 'QP csomag',
            'is_active' => true,
            'is_default' => true,
            'display_order' => 1,
            'pricing_mode' => 'component_sum',
            'created_by' => $user->id,
        ]);

        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionMealType->id,
            'display_order' => 1,
        ]);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-01-01',
            'created_by' => $user->id,
        ]);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $statement = MonthlyPaymentStatement::query()->where('institution_id', $institution->id)->firstOrFail();

        return [$institution, $user, $statement];
    }

    private function seedUserWithInstitution(string $code): array
    {
        $institution = Institution::create([
            'name' => 'QuickPay Intezmeny '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
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

        return [$institution, $user];
    }
}
