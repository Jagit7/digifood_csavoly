<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminInstitutionAccessController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildMealSettingController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionPaymentController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ParentController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation\FinancialAdjustmentController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation\PaymentObligationController;
use App\Http\Middleware\CheckRole;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\PaymentObligationIndexRequest;
use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionAdminInvitation;
use App\Models\InstitutionMealPackage;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InstitutionSecretaryFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();

        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('IF', fn ($condition, $yes, $no) => $condition ? $yes : $no, 3);
        }
    }

    public function test_superadmin_can_create_institution_secretary_invitation(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $institution = $this->createInstitution('SECINV1');

        $this->actingAs($superAdmin);

        $response = $this->adminAccessController()->storeInvite($this->makeRequest(
            $superAdmin,
            route('dashboard.admin-access.invite.store'),
            'POST',
            [
                'institution_id' => $institution->id,
                'name' => 'Titkar Anna',
                'email' => 'titkar@example.test',
                'role' => User::ROLE_INSTITUTION_SECRETARY,
            ]
        ));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.admin-access.index'), $response->getTargetUrl());
        $this->assertDatabaseHas('institution_admin_invitations', [
            'institution_id' => $institution->id,
            'email' => 'titkar@example.test',
            'role' => User::ROLE_INSTITUTION_SECRETARY,
        ]);
    }

    public function test_invited_user_receives_institution_secretary_role_after_acceptance(): void
    {
        $institution = $this->createInstitution('SECINV2');
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $invitation = InstitutionAdminInvitation::query()->create([
            'institution_id' => $institution->id,
            'invited_by' => $superAdmin->id,
            'name' => 'Titkar Bela',
            'email' => 'bela@example.test',
            'role' => User::ROLE_INSTITUTION_SECRETARY,
            'token_hash' => hash('sha256', 'known-secretary-token'),
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->adminAccessController()->completeInvite(
            $this->makeRequest(
                null,
                route('institution-invite.complete', ['token' => 'known-secretary-token']),
                'POST',
                [
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                ]
            ),
            'known-secretary-token'
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.home'), $response->getTargetUrl());

        $user = User::query()->where('email', 'bela@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_INSTITUTION_SECRETARY, $user->role);
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => User::ROLE_INSTITUTION_SECRETARY,
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_existing_user_invited_to_second_institution_reuses_same_account(): void
    {
        $firstInstitution = $this->createInstitution('SECINV3');
        $secondInstitution = $this->createInstitution('SECINV4');
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Tobbintezmenyes Admin',
            'email' => 'multi-admin@example.test',
            'role' => User::ROLE_INSTITUTION_SECRETARY,
            'institution_id' => $firstInstitution->id,
            'is_active' => true,
            'accepted_invitation_at' => now()->subDay(),
            'email_verified_at' => now()->subDay(),
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $firstInstitution->id,
            'user_id' => $user->id,
            'scope_role' => User::ROLE_INSTITUTION_SECRETARY,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        InstitutionAdminInvitation::query()->create([
            'institution_id' => $secondInstitution->id,
            'invited_by' => $superAdmin->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'token_hash' => hash('sha256', 'known-multi-admin-token'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user);

        $response = $this->adminAccessController()->completeInvite(
            $this->makeRequest(
                $user,
                route('institution-invite.complete', ['token' => 'known-multi-admin-token']),
                'POST'
            ),
            'known-multi-admin-token'
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(1, User::query()->where('email', $user->email)->count());
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $secondInstitution->id,
            'user_id' => $user->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
        ]);
    }

    public function test_existing_membership_is_not_duplicated_when_same_institution_invite_is_accepted(): void
    {
        $institution = $this->createInstitution('SECINV5');
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Duplikacio Teszt',
            'email' => 'duplicate-membership@example.test',
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        InstitutionAdminInvitation::query()->create([
            'institution_id' => $institution->id,
            'invited_by' => $superAdmin->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'token_hash' => hash('sha256', 'known-duplicate-token'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user);

        $this->adminAccessController()->completeInvite(
            $this->makeRequest(
                $user,
                route('institution-invite.complete', ['token' => 'known-duplicate-token']),
                'POST'
            ),
            'known-duplicate-token'
        );

        $this->assertSame(1, DB::table('institution_user')
            ->where('institution_id', $institution->id)
            ->where('user_id', $user->id)
            ->count());
    }

    public function test_secretary_can_manage_children_and_meal_settings_in_own_institution(): void
    {
        [$institution, $secretary] = $this->seedInstitutionUser(User::ROLE_INSTITUTION_SECRETARY, 'SECCAN1');
        $discount = $this->createDiscountType($institution->id);
        $this->createDefaultMealPackage($institution->id, $secretary->id);
        $this->actingAs($secretary);

        $indexRequest = $this->makeRequest($secretary, route('dashboard.institution.children.index'), 'GET');
        $view = app(CheckRole::class)->handle(
            $indexRequest,
            fn () => $this->childController()->index($indexRequest),
            User::ROLE_INSTITUTION_ADMIN,
            User::ROLE_INSTITUTION_SECRETARY
        );

        $this->assertInstanceOf(View::class, $view);
        $sidebar = file_get_contents(resource_path('views/layouts/partials/sidebar.blade.php'));
        $this->assertIsString($sidebar);
        $this->assertStringContainsString("['institution_admin', 'institution_secretary']", $sidebar);
        $this->assertStringContainsString("['institution_admin', 'municipality']", $sidebar);

        $storeResponse = $this->childController()->store($this->makeRequest(
            $secretary,
            route('dashboard.institution.children.store'),
            'POST',
            [
                'name' => 'Titkari Gyermek',
                'educational_identifier' => 'SECGY001',
                'discount_type_id' => $discount->id,
                'discount_valid_from' => '2026-08-14',
                'is_eater' => '0',
                'guardian_mode' => 'none',
                'active' => '1',
            ]
        ));

        $this->assertInstanceOf(RedirectResponse::class, $storeResponse);
        $this->assertSame(route('dashboard.institution.children.index'), $storeResponse->getTargetUrl());

        $child = Child::query()->where('institution_id', $institution->id)
            ->where('educational_identifier', 'SECGY001')
            ->firstOrFail();

        $updateResponse = $this->childController()->update($this->makeRequest(
            $secretary,
            route('dashboard.institution.children.update', $child),
            'PUT',
            [
                'name' => 'Titkari Gyermek Frissítve',
                'educational_identifier' => 'SECGY001',
                'discount_type_id' => $discount->id,
                'discount_valid_from' => '2026-08-14',
                'is_eater' => '0',
                'active' => '1',
            ]
        ), $child);

        $this->assertInstanceOf(RedirectResponse::class, $updateResponse);
        $this->assertDatabaseHas('children', [
            'id' => $child->id,
            'name' => 'Titkari Gyermek Frissítve',
        ]);

        $mealSettingResponse = $this->childMealSettingController()->store(
            $this->makeRequest(
                $secretary,
                route('dashboard.institution.children.meal-settings.store', $child),
                'POST',
                [
                    'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
                    'valid_from' => '2026-08-14',
                ]
            ),
            $child
        );

        $this->assertInstanceOf(RedirectResponse::class, $mealSettingResponse);

        $mealSetting = StudentMealSetting::query()
            ->where('institution_id', $institution->id)
            ->where('student_id', $child->id)
            ->latest('id')
            ->firstOrFail();

        $closeResponse = $this->childMealSettingController()->close(
            $this->makeRequest(
                $secretary,
                route('dashboard.institution.children.meal-settings.close', [$child, $mealSetting]),
                'POST',
                [
                    'last_meal_day' => '2026-09-20',
                    'closure_reason' => StudentMealSetting::CLOSURE_REASON_TRANSFER,
                ]
            ),
            $child,
            $mealSetting
        );

        $this->assertInstanceOf(RedirectResponse::class, $closeResponse);
        $this->assertDatabaseHas('student_meal_settings', [
            'id' => $mealSetting->id,
            'valid_to' => '2026-09-20 00:00:00',
            'closure_reason' => StudentMealSetting::CLOSURE_REASON_TRANSFER,
        ]);
    }

    public function test_secretary_cannot_open_financial_routes(): void
    {
        [$institution, $secretary] = $this->seedInstitutionUser(User::ROLE_INSTITUTION_SECRETARY, 'SECFIN1');
        $child = $this->createChild($institution->id, 'Penzugyi Tiltas', 'SECF001');
        $statement = MonthlyPaymentStatement::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 8,
            'meal_amount' => 0,
            'previous_cancellation_credit' => 0,
            'billing_adjustment_amount' => 0,
            'invoiceable_amount' => 0,
            'previous_balance' => 0,
            'total_payable' => 0,
            'status' => MonthlyPaymentStatement::STATUS_DRAFT,
        ]);

        $this->expectForbidden(fn () => app(CheckRole::class)->handle(
            $this->makeRequest($secretary, route('dashboard.institution.payment-obligations.index'), 'GET'),
            fn () => $this->paymentObligationController()->index($this->makePaymentObligationIndexRequest($secretary, '2026-08')),
            User::ROLE_INSTITUTION_ADMIN
        ));

        $this->expectForbidden(fn () => app(CheckRole::class)->handle(
            $this->makeRequest($secretary, route('dashboard.institution.finance.payments'), 'GET'),
            fn () => app(InstitutionPaymentController::class)->index($this->makeRequest($secretary, route('dashboard.institution.finance.payments'), 'GET')),
            User::ROLE_INSTITUTION_ADMIN
        ));

        $this->expectForbidden(fn () => app(CheckRole::class)->handle(
            $this->makeRequest($secretary, route('dashboard.institution.finance.invoices'), 'GET'),
            fn () => app(InstitutionInvoiceController::class)->index($this->makeRequest($secretary, route('dashboard.institution.finance.invoices'), 'GET')),
            User::ROLE_INSTITUTION_ADMIN
        ));

        $this->expectForbidden(fn () => app(CheckRole::class)->handle(
            $this->makeRequest($secretary, route('dashboard.institution.payment-obligations.adjustments.index', $statement), 'GET'),
            fn () => app(FinancialAdjustmentController::class)->index($statement),
            User::ROLE_INSTITUTION_ADMIN
        ));
    }

    public function test_secretary_cannot_access_child_from_another_institution(): void
    {
        [, $secretary] = $this->seedInstitutionUser(User::ROLE_INSTITUTION_SECRETARY, 'SECIDO1');
        $otherInstitution = $this->createInstitution('SECIDO2');
        $foreignChild = $this->createChild($otherInstitution->id, 'Masik Intezmeny', 'SECX001');

        $this->actingAs($secretary);

        $this->expectForbidden(fn () => app(CheckRole::class)->handle(
            $this->makeRequest($secretary, route('dashboard.institution.children.edit', $foreignChild), 'GET'),
            fn () => $this->childController()->edit(
                $this->makeRequest($secretary, route('dashboard.institution.children.edit', $foreignChild), 'GET'),
                $foreignChild
            ),
            User::ROLE_INSTITUTION_ADMIN,
            User::ROLE_INSTITUTION_SECRETARY
        ));
    }

    public function test_secretary_guardian_pages_hide_financial_fields(): void
    {
        [$institution, $secretary] = $this->seedInstitutionUser(User::ROLE_INSTITUTION_SECRETARY, 'SECGRD1');
        $child = $this->createChild($institution->id, 'Kapcsolt Gyermek', 'SECG001');
        $guardian = Guardian::query()->create([
            'institution_id' => $institution->id,
            'last_name' => 'Gondviselo',
            'first_name' => 'Erika',
            'email' => 'guardian@example.test',
            'bank_account_holder' => 'Rejtett Tulaj',
            'bank_account_number' => '11111111-22222222-33333333',
            'active' => true,
        ]);
        $guardian->children()->attach($child->id, [
            'relationship_type' => 'Édesanya',
            'is_legal_representative' => true,
            'has_no_custody' => false,
            'is_emergency_contact' => true,
            'receives_family_allowance' => true,
        ]);

        $billingProfile = BillingProfile::query()->create([
            'institution_id' => $institution->id,
            'guardian_id' => $guardian->id,
            'payer_type' => 'guardian',
            'billing_name' => 'Rejtett Szamlazasi Nev',
            'active' => true,
        ]);
        $billingProfile->children()->attach($child->id, [
            'is_primary' => true,
            'valid_from' => '2026-08-01',
        ]);

        $this->actingAs($secretary);

        $view = app(CheckRole::class)->handle(
            $this->makeRequest($secretary, route('dashboard.institution.parents.edit', $guardian), 'GET'),
            fn () => $this->parentController()->edit($guardian),
            User::ROLE_INSTITUTION_ADMIN,
            User::ROLE_INSTITUTION_SECRETARY
        );
        $this->assertInstanceOf(View::class, $view);
        $this->assertFalse($view->getData()['canManageBilling']);

        $parentEditView = file_get_contents(resource_path('views/dashboard/institution_admin/parents/edit.blade.php'));
        $this->assertIsString($parentEditView);
        $this->assertStringContainsString('@if($canManageBilling)', $parentEditView);
        $this->assertStringContainsString('Számlázási adatok', $parentEditView);
    }

    public function test_institution_admin_keeps_existing_financial_access(): void
    {
        [, $institutionAdmin] = $this->seedInstitutionUser(User::ROLE_INSTITUTION_ADMIN, 'SECADM1');
        $this->actingAs($institutionAdmin);

        $view = app(CheckRole::class)->handle(
            $this->makeRequest($institutionAdmin, route('dashboard.institution.payment-obligations.index'), 'GET'),
            fn () => $this->paymentObligationController()->index($this->makePaymentObligationIndexRequest($institutionAdmin, '2026-08')),
            User::ROLE_INSTITUTION_ADMIN
        );

        $this->assertInstanceOf(View::class, $view);
    }

    public function test_selected_institution_context_controls_effective_role_and_dashboard_data(): void
    {
        [$firstInstitution, $user] = $this->seedInstitutionUser(User::ROLE_INSTITUTION_SECRETARY, 'SECMUL1');
        $secondInstitution = $this->createInstitution('SECMUL2');

        DB::table('institution_user')->insert([
            'institution_id' => $secondInstitution->id,
            'user_id' => $user->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $switchResponse = $this->post(route('dashboard.institution.context.update'), [
            'institution_id' => $secondInstitution->id,
        ]);

        $switchResponse->assertRedirect(route('dashboard.institution.home'));
        $switchResponse->assertSessionHas('dashboard.selected_institution_id', $secondInstitution->id);
        $switchResponse->assertSessionHas('dashboard.selected_institution_user_id', $user->id);

        $this->get(route('dashboard.institution.children.index'))
            ->assertOk()
            ->assertViewHas('institution', fn (Institution $institution) => $institution->is($secondInstitution));

        $this->get(route('dashboard.institution.finance.payments'))
            ->assertOk();

        $this->post(route('dashboard.institution.context.update'), [
            'institution_id' => $firstInstitution->id,
        ])->assertRedirect(route('dashboard.institution.home'));

        $this->get(route('dashboard.institution.children.index'))
            ->assertOk()
            ->assertViewHas('institution', fn (Institution $institution) => $institution->is($firstInstitution));

        $this->get(route('dashboard.institution.finance.payments'))
            ->assertForbidden();
    }

    private function childController(): ChildController
    {
        return app(ChildController::class);
    }

    private function childMealSettingController(): ChildMealSettingController
    {
        return app(ChildMealSettingController::class);
    }

    private function parentController(): ParentController
    {
        return app(ParentController::class);
    }

    private function paymentObligationController(): PaymentObligationController
    {
        return app(PaymentObligationController::class);
    }

    private function adminAccessController(): AdminInstitutionAccessController
    {
        return app(AdminInstitutionAccessController::class);
    }

    private function makeRequest(?User $user, string $uri, string $method, array $data = []): Request
    {
        $request = Request::create($uri, $method, $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function makePaymentObligationIndexRequest(User $user, string $month): PaymentObligationIndexRequest
    {
        $request = PaymentObligationIndexRequest::create(
            route('dashboard.institution.payment-obligations.index'),
            'GET',
            ['month' => $month]
        );
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $validator = $this->app['validator']->make($request->all(), $request->rules());
        $request->setValidator($validator);

        return $request;
    }

    private function expectForbidden(callable $callback): void
    {
        try {
            $callback();
            $this->fail('403-as kivetelt vartunk.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function seedInstitutionUser(string $role, string $code): array
    {
        $institution = $this->createInstitution($code);
        $user = User::factory()->create([
            'role' => $role,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institution, $user];
    }

    private function createInstitution(string $code): Institution
    {
        return Institution::query()->create([
            'name' => 'Titkari Intezmeny '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);
    }

    private function createDiscountType(int $institutionId): DiscountType
    {
        return DiscountType::query()->create([
            'institution_id' => $institutionId,
            'name' => 'Alap kedvezmeny',
            'percentage' => 0,
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createDefaultMealPackage(int $institutionId, int $createdBy): InstitutionMealPackage
    {
        return InstitutionMealPackage::query()->create([
            'institution_id' => $institutionId,
            'name' => 'Alap menu',
            'is_active' => true,
            'is_default' => true,
            'display_order' => 1,
            'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
            'created_by' => $createdBy,
        ]);
    }

    private function createChild(int $institutionId, string $name, string $identifier): Child
    {
        $discount = DiscountType::query()->firstOrCreate(
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

        return Child::query()->create([
            'institution_id' => $institutionId,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'educational_identifier' => $identifier,
            'source_type' => 'manual',
            'active' => true,
        ]);
    }
}
