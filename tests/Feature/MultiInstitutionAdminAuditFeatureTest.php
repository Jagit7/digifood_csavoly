<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionAdminInvitation;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiInstitutionAdminAuditFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_institution_admin_cannot_access_foreign_child_payment_invoice_or_statement(): void
    {
        [$institutionA, $admin] = $this->createInstitutionAdmin('AUDIT-A1');
        $institutionB = $this->createInstitution('AUDIT-B1');
        $recordsB = $this->createFinancialRecords($institutionB, $admin, 'AUDITB1');

        $this->actingAs($admin);

        $this->get(route('dashboard.institution.children.edit', $recordsB['child']))
            ->assertForbidden();

        $this->get(route('dashboard.institution.finance.payments.show', $recordsB['payment']))
            ->assertForbidden();

        $this->get(route('dashboard.institution.finance.invoices.show', $recordsB['invoice']))
            ->assertForbidden();

        $this->get(route('dashboard.institution.finance.debts.show', $recordsB['statement']))
            ->assertForbidden();

        $this->assertSame($institutionA->id, session('dashboard.selected_institution_id'));
    }

    public function test_multi_institution_admin_context_a_and_b_enforce_the_same_boundary_for_lists_and_direct_urls(): void
    {
        [$institutionA, $admin] = $this->createInstitutionAdmin('AUDIT-A2');
        $institutionB = $this->createInstitution('AUDIT-B2');
        $this->attachInstitutionRole($admin, $institutionB, User::ROLE_INSTITUTION_ADMIN);

        $recordsA = $this->createFinancialRecords($institutionA, $admin, 'AUDITA2');
        $recordsB = $this->createFinancialRecords($institutionB, $admin, 'AUDITB2');

        $this->actingAs($admin);

        $this->withSession($this->institutionSession($admin, $institutionA))
            ->get(route('dashboard.institution.children.index'))
            ->assertOk()
            ->assertSee('Gyermek AUDITA2')
            ->assertDontSee('Gyermek AUDITB2')
            ->assertViewHas('institution', fn (Institution $institution) => $institution->is($institutionA));

        $this->withSession($this->institutionSession($admin, $institutionA))
            ->get(route('dashboard.institution.children.edit', $recordsA['child']))
            ->assertOk();

        $this->withSession($this->institutionSession($admin, $institutionA))
            ->get(route('dashboard.institution.children.edit', $recordsB['child']))
            ->assertForbidden();

        $this->withSession($this->institutionSession($admin, $institutionA))
            ->get(route('dashboard.institution.finance.payments.show', $recordsA['payment']))
            ->assertOk();

        $this->withSession($this->institutionSession($admin, $institutionA))
            ->get(route('dashboard.institution.finance.payments.show', $recordsB['payment']))
            ->assertForbidden();

        $this->withSession($this->institutionSession($admin, $institutionB))
            ->get(route('dashboard.institution.children.index'))
            ->assertOk()
            ->assertSee('Gyermek AUDITB2')
            ->assertDontSee('Gyermek AUDITA2')
            ->assertViewHas('institution', fn (Institution $institution) => $institution->is($institutionB));

        $this->withSession($this->institutionSession($admin, $institutionB))
            ->get(route('dashboard.institution.children.edit', $recordsB['child']))
            ->assertOk();

        $this->withSession($this->institutionSession($admin, $institutionB))
            ->get(route('dashboard.institution.children.edit', $recordsA['child']))
            ->assertForbidden();

        $this->withSession($this->institutionSession($admin, $institutionB))
            ->get(route('dashboard.institution.finance.invoices.show', $recordsB['invoice']))
            ->assertOk();

        $this->withSession($this->institutionSession($admin, $institutionB))
            ->get(route('dashboard.institution.finance.invoices.show', $recordsA['invoice']))
            ->assertForbidden();
    }

    public function test_unassigned_institution_c_records_remain_inaccessible_for_multi_institution_admin(): void
    {
        [$institutionA, $admin] = $this->createInstitutionAdmin('AUDIT-A3');
        $institutionB = $this->createInstitution('AUDIT-B3');
        $institutionC = $this->createInstitution('AUDIT-C3');
        $this->attachInstitutionRole($admin, $institutionB, User::ROLE_INSTITUTION_ADMIN);

        $recordsC = $this->createFinancialRecords($institutionC, $admin, 'AUDITC3');

        $this->actingAs($admin);

        $this->withSession($this->institutionSession($admin, $institutionA))
            ->get(route('dashboard.institution.children.edit', $recordsC['child']))
            ->assertForbidden();

        $this->withSession($this->institutionSession($admin, $institutionA))
            ->get(route('dashboard.institution.finance.payments.show', $recordsC['payment']))
            ->assertForbidden();

        $this->withSession($this->institutionSession($admin, $institutionB))
            ->get(route('dashboard.institution.finance.invoices.show', $recordsC['invoice']))
            ->assertForbidden();

        $this->withSession($this->institutionSession($admin, $institutionB))
            ->get(route('dashboard.institution.finance.invoices.statements.preview', $recordsC['statement']))
            ->assertForbidden();
    }

    public function test_pivot_role_overrides_global_admin_role_per_institution(): void
    {
        [$institutionA, $admin] = $this->createInstitutionAdmin('AUDIT-A4');
        $institutionB = $this->createInstitution('AUDIT-B4');
        $this->attachInstitutionRole($admin, $institutionB, User::ROLE_INSTITUTION_SECRETARY);
        $recordsA = $this->createFinancialRecords($institutionA, $admin, 'AUDITA4');
        $recordsB = $this->createFinancialRecords($institutionB, $admin, 'AUDITB4');

        $this->actingAs($admin);

        $this->withSession($this->institutionSession($admin, $institutionA))
            ->get(route('dashboard.institution.finance.payments.show', $recordsA['payment']))
            ->assertOk();

        $this->withSession($this->institutionSession($admin, $institutionB))
            ->get(route('dashboard.institution.children.edit', $recordsB['child']))
            ->assertOk();

        $this->withSession($this->institutionSession($admin, $institutionB))
            ->get(route('dashboard.institution.finance.payments.show', $recordsB['payment']))
            ->assertForbidden();
    }

    public function test_existing_email_second_invitation_keeps_single_user_two_pivots_and_switches_active_context(): void
    {
        $institutionA = $this->createInstitution('AUDIT-A5');
        $institutionB = $this->createInstitution('AUDIT-B5');
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $inviteA = InstitutionAdminInvitation::query()->create([
            'institution_id' => $institutionA->id,
            'invited_by' => $superAdmin->id,
            'name' => 'Audit Admin',
            'email' => 'audit-admin@example.test',
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'token_hash' => hash('sha256', 'audit-first-token'),
            'expires_at' => now()->addDay(),
        ]);

        $this->post(route('institution-invite.complete', ['token' => 'audit-first-token']), [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard.institution.home'));

        $user = User::query()->where('email', 'audit-admin@example.test')->firstOrFail();

        $this->assertSame(1, User::query()->where('email', 'audit-admin@example.test')->count());
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institutionA->id,
            'user_id' => $user->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
        ]);
        $this->assertNotNull($inviteA->fresh()->accepted_at);

        $inviteB = InstitutionAdminInvitation::query()->create([
            'institution_id' => $institutionB->id,
            'invited_by' => $superAdmin->id,
            'name' => 'Audit Admin',
            'email' => 'audit-admin@example.test',
            'role' => User::ROLE_INSTITUTION_SECRETARY,
            'token_hash' => hash('sha256', 'audit-second-token'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user);

        $this->post(route('institution-invite.complete', ['token' => 'audit-second-token']))
            ->assertRedirect(route('dashboard.institution.home'));

        $user->refresh();

        $this->assertSame(1, User::query()->where('email', 'audit-admin@example.test')->count());
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institutionA->id,
            'user_id' => $user->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
        ]);
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institutionB->id,
            'user_id' => $user->id,
            'scope_role' => User::ROLE_INSTITUTION_SECRETARY,
        ]);
        $this->assertSame(2, DB::table('institution_user')->where('user_id', $user->id)->count());
        $this->assertSame($institutionB->id, session('dashboard.selected_institution_id'));
        $this->assertSame($user->id, session('dashboard.selected_institution_user_id'));
        $this->assertNotNull($inviteB->fresh()->accepted_at);
    }

    public function test_same_institution_repeat_invitation_does_not_duplicate_pivot(): void
    {
        [$institution, $admin] = $this->createInstitutionAdmin('AUDIT-A6');
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        InstitutionAdminInvitation::query()->create([
            'institution_id' => $institution->id,
            'invited_by' => $superAdmin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'token_hash' => hash('sha256', 'audit-repeat-token'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin);

        $this->post(route('institution-invite.complete', ['token' => 'audit-repeat-token']))
            ->assertRedirect(route('dashboard.institution.home'));

        $this->assertSame(1, DB::table('institution_user')
            ->where('institution_id', $institution->id)
            ->where('user_id', $admin->id)
            ->count());
    }

    public function test_stale_session_context_is_invalidated_and_replaced_when_another_membership_remains(): void
    {
        [$institutionA, $admin] = $this->createInstitutionAdmin('AUDIT-A7');
        $institutionB = $this->createInstitution('AUDIT-B7');
        $this->attachInstitutionRole($admin, $institutionB, User::ROLE_INSTITUTION_ADMIN);

        DB::table('institution_user')
            ->where('institution_id', $institutionB->id)
            ->where('user_id', $admin->id)
            ->delete();

        $this->actingAs($admin);

        $this->withSession($this->institutionSession($admin, $institutionB))
            ->get(route('dashboard.institution.children.index'))
            ->assertOk()
            ->assertViewHas('institution', fn (Institution $institution) => $institution->is($institutionA));

        $this->assertSame($institutionA->id, session('dashboard.selected_institution_id'));
    }

    public function test_stale_session_context_is_rejected_when_no_membership_remains(): void
    {
        [$institution, $admin] = $this->createInstitutionAdmin('AUDIT-A8');

        DB::table('institution_user')
            ->where('institution_id', $institution->id)
            ->where('user_id', $admin->id)
            ->delete();

        $this->actingAs($admin);

        $this->withSession($this->institutionSession($admin, $institution))
            ->get(route('dashboard.institution.children.index'))
            ->assertForbidden();
    }

    private function createInstitutionAdmin(string $code): array
    {
        $institution = $this->createInstitution($code);
        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $this->attachInstitutionRole($user, $institution, User::ROLE_INSTITUTION_ADMIN);

        return [$institution, $user];
    }

    private function attachInstitutionRole(User $user, Institution $institution, string $role): void
    {
        DB::table('institution_user')->updateOrInsert(
            [
                'institution_id' => $institution->id,
                'user_id' => $user->id,
            ],
            [
                'scope_role' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function createInstitution(string $code): Institution
    {
        return Institution::query()->create([
            'name' => 'Audit Intézmény '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);
    }

    private function createFinancialRecords(Institution $institution, User $recordedBy, string $suffix): array
    {
        $discount = DiscountType::query()->create([
            'institution_id' => $institution->id,
            'name' => 'Alap kedvezmény '.$suffix,
            'percentage' => 0,
            'active' => true,
            'sort_order' => 1,
        ]);

        $child = Child::query()->create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => 'Gyermek '.$suffix,
            'educational_identifier' => 'CH-'.$suffix,
            'source_type' => 'manual',
            'active' => true,
        ]);

        $guardian = Guardian::query()->create([
            'institution_id' => $institution->id,
            'last_name' => 'Szülő',
            'first_name' => $suffix,
            'email' => mb_strtolower($suffix).'@example.test',
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

        $statement = MonthlyPaymentStatement::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 8,
            'meal_amount' => 12000,
            'previous_cancellation_credit' => 0,
            'billing_adjustment_amount' => 0,
            'invoiceable_amount' => 12000,
            'previous_balance' => 0,
            'total_payable' => 12000,
            'status' => MonthlyPaymentStatement::STATUS_DRAFT,
        ]);

        $payment = InstitutionPayment::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => 12000,
            'paid_at' => now(),
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => 'PAY-'.$suffix,
            'recorded_by' => $recordedBy->id,
        ]);

        $invoice = InstitutionInvoice::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'institution_payment_id' => $payment->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'document_type' => InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL,
            'invoice_number' => 'INV-'.$suffix,
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'fulfillment_date' => '2026-08-01',
            'net_amount' => 12000,
            'vat_amount' => 0,
            'gross_amount' => 12000,
            'currency' => 'HUF',
            'payment_method' => InstitutionPayment::METHOD_CASH,
            'customer_name' => 'Szülő '.$suffix,
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fő utca 1.',
            'created_by' => $recordedBy->id,
        ]);

        return compact('child', 'guardian', 'statement', 'payment', 'invoice');
    }

    private function institutionSession(User $user, Institution $institution): array
    {
        return [
            'dashboard.selected_institution_id' => $institution->id,
            'dashboard.selected_institution_user_id' => $user->id,
        ];
    }
}
