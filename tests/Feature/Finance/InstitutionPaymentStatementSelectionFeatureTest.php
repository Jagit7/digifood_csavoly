<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionPaymentController;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionPaymentUpsertRequest;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstitutionPaymentStatementSelectionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_results_include_original_paid_and_remaining_amounts(): void
    {
        [$institution, $user, $child, $guardian, $statement] = $this->seedPaymentContext('PSEL01', 12450);
        $this->createCompletedPayment($institution, $child, $guardian, $statement, $user, 4000);

        $payload = $this->statementResults($user, $child);
        $result = collect($payload['results'])->firstWhere('id', $statement->id);

        $this->assertNotNull($result);
        $this->assertSame('Szamla Gyermek PSEL01', $result['child_name']);
        $this->assertSame(12450, $result['total_payable']);
        $this->assertSame(4000, $result['completed_payments_total']);
        $this->assertSame(8450, $result['remaining_amount']);
        $this->assertFalse($result['is_settled']);
        $this->assertFalse($result['disabled']);
    }

    public function test_statement_results_use_compact_single_line_labels_for_open_and_settled_items(): void
    {
        [$institution, $user, $child, $guardian, $statement] = $this->seedPaymentContext('PSEL01B', 12450);
        $this->createCompletedPayment($institution, $child, $guardian, $statement, $user, 4000);

        $payload = $this->statementResults($user, $child);
        $result = collect($payload['results'])->firstWhere('id', $statement->id);

        $this->assertSame('Szamla Gyermek PSEL01B – 2026. július – 8 450 Ft', $result['text']);

        $this->createCompletedPayment($institution, $child, $guardian, $statement, $user, 8450);

        $settledPayload = $this->statementResults($user, $child);
        $settledResult = collect($settledPayload['results'])->firstWhere('id', $statement->id);

        $this->assertSame('Szamla Gyermek PSEL01B – 2026. július – Rendezve', $settledResult['text']);
        $this->assertTrue($settledResult['disabled']);
    }

    public function test_fully_settled_statement_is_marked_disabled_and_cannot_be_paid_again(): void
    {
        [$institution, $user, $child, $guardian, $statement] = $this->seedPaymentContext('PSEL02', 6000);
        $this->createCompletedPayment($institution, $child, $guardian, $statement, $user, 6000);

        $payload = $this->statementResults($user, $child);
        $result = collect($payload['results'])->firstWhere('id', $statement->id);

        $this->assertTrue($result['is_settled']);
        $this->assertTrue($result['disabled']);

        $errors = $this->attemptInvalidStore($user, $this->paymentPayload($child, $guardian, $statement, [
            'amount' => 1,
        ]));

        $this->assertSame(
            'A kiválasztott havi kötelezettség teljesen rendezett, ehhez új befizetés nem rögzíthető.',
            $errors['monthly_payment_statement_id'] ?? null
        );
    }

    public function test_zero_and_negative_amounts_are_rejected(): void
    {
        [$institution, $user, $child, $guardian, $statement] = $this->seedPaymentContext('PSEL03', 5000);

        $zeroErrors = $this->attemptInvalidStore($user, $this->paymentPayload($child, $guardian, $statement, [
            'amount' => 0,
        ]));
        $this->assertArrayHasKey('amount', $zeroErrors);

        $negativeErrors = $this->attemptInvalidStore($user, $this->paymentPayload($child, $guardian, $statement, [
            'amount' => -200,
        ]));
        $this->assertArrayHasKey('amount', $negativeErrors);
    }

    public function test_overpayment_is_rejected_when_statement_has_smaller_remaining_amount(): void
    {
        [$institution, $user, $child, $guardian, $statement] = $this->seedPaymentContext('PSEL04', 10000);
        $this->createCompletedPayment($institution, $child, $guardian, $statement, $user, 2500);

        $errors = $this->attemptInvalidStore($user, $this->paymentPayload($child, $guardian, $statement, [
            'amount' => 8000,
        ]));

        $this->assertSame(
            'A befizetés összege nem lehet nagyobb a fennmaradó tartozásnál (7 500 Ft).',
            $errors['amount'] ?? null
        );
    }

    public function test_payment_cannot_be_recorded_for_other_institutions_statement(): void
    {
        [$institution, $user, $child, $guardian, $statement] = $this->seedPaymentContext('PSEL05', 9000);
        [$foreignInstitution, $foreignUser, $foreignChild, $foreignGuardian, $foreignStatement] = $this->seedPaymentContext('PSEL05X', 9000);

        $errors = $this->attemptInvalidStore($user, $this->paymentPayload($child, $guardian, $foreignStatement, [
            'amount' => 1000,
        ]));

        $this->assertArrayHasKey('monthly_payment_statement_id', $errors);
    }

    public function test_remaining_amount_is_reduced_after_partial_payment_for_next_payment_attempt(): void
    {
        [$institution, $user, $child, $guardian, $statement] = $this->seedPaymentContext('PSEL06', 12450);
        $this->createCompletedPayment($institution, $child, $guardian, $statement, $user, 3000);

        $firstPayload = $this->statementResults($user, $child);
        $firstResult = collect($firstPayload['results'])->firstWhere('id', $statement->id);
        $this->assertSame(9450, $firstResult['remaining_amount']);

        $storeResponse = $this->storePayment($user, $this->paymentPayload($child, $guardian, $statement, [
            'amount' => 2000,
            'reference' => 'PARTIAL-PSEL06',
        ]));

        $this->assertSame(
            route('dashboard.institution.finance.payments.show', InstitutionPayment::query()->latest('id')->firstOrFail()),
            $storeResponse->getTargetUrl()
        );

        $secondPayload = $this->statementResults($user, $child);
        $secondResult = collect($secondPayload['results'])->firstWhere('id', $statement->id);
        $this->assertSame(7450, $secondResult['remaining_amount']);
        $this->assertSame(5000, $secondResult['completed_payments_total']);
    }

    private function statementResults(User $user, Child $child): array
    {
        $this->actingAs($user);
        /** @var JsonResponse $response */
        $response = app(InstitutionPaymentController::class)->childStatements($child);

        return $response->getData(true);
    }

    private function attemptInvalidStore(User $user, array $payload): array
    {
        try {
            $this->actingAs($user);
            $request = $this->makeStoreRequest($user, $payload);
            $request->validateResolved();
            $this->fail('A kerest a validacionak el kellett volna utasitania.');
        } catch (ValidationException $exception) {
            return collect($exception->errors())
                ->map(fn (array $messages) => $messages[0] ?? null)
                ->filter()
                ->all();
        }

        return [];
    }

    private function storePayment(User $user, array $payload): RedirectResponse
    {
        $this->actingAs($user);
        $request = $this->makeStoreRequest($user, $payload);
        $request->validateResolved();

        /** @var RedirectResponse $response */
        $response = app(InstitutionPaymentController::class)->store($request);

        return $response;
    }

    private function makeStoreRequest(User $user, array $payload): InstitutionPaymentUpsertRequest
    {
        $request = InstitutionPaymentUpsertRequest::create(
            route('dashboard.institution.finance.payments.store'),
            'POST',
            $payload
        );
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);

        return $request;
    }

    private function seedPaymentContext(string $code, int $totalPayable): array
    {
        $institution = Institution::create([
            'name' => 'Intezmeny '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create();
        $user->forceFill([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
        ])->save();

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => 'institution_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            'name' => 'Szamla Gyermek '.$code,
            'educational_identifier' => 'EDU-'.$code,
            'group_name' => '3.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Szulo',
            'first_name' => 'Payer',
            'email' => 'szulo-'.$code.'@example.test',
            'postal_code' => '1111',
            'city' => 'Budapest',
            'street_name' => 'Fo',
            'street_type' => 'utca',
            'house_number' => '1',
            'source_type' => 'manual',
            'active' => true,
        ]);

        DB::table('child_guardian')->insert([
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'anya',
            'is_legal_representative' => true,
            'has_no_custody' => false,
            'is_emergency_contact' => true,
            'receives_family_allowance' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => $totalPayable,
            'previous_cancellation_credit' => 0,
            'billing_adjustment_amount' => 0,
            'invoiceable_amount' => $totalPayable,
            'previous_balance' => 0,
            'total_payable' => $totalPayable,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
        ]);

        return [$institution, $user, $child, $guardian, $statement];
    }

    private function createCompletedPayment(
        Institution $institution,
        Child $child,
        Guardian $guardian,
        MonthlyPaymentStatement $statement,
        User $user,
        int $amount
    ): InstitutionPayment {
        return InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => $amount,
            'paid_at' => '2026-08-12 09:15:00',
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => 'PAY-'.$statement->id.'-'.$amount,
            'note' => 'Teszt befizetes',
            'recorded_by' => $user->id,
        ]);
    }

    private function paymentPayload(Child $child, Guardian $guardian, MonthlyPaymentStatement $statement, array $overrides = []): array
    {
        return array_merge([
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'invoice_number' => null,
            'amount' => 1000,
            'paid_at' => '2026-08-23T10:30',
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => 'REF-'.$statement->id,
            'note' => 'Teszt befizetes',
        ], $overrides);
    }
}
