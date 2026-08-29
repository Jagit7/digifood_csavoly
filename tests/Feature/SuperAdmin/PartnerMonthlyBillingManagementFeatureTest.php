<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Dashboard\SuperAdmin\PartnerMonthlyBillingController;
use App\Http\Middleware\CheckRole;
use App\Models\BillingPartner;
use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionBillingRate;
use App\Models\PartnerMonthlyBilling;
use App\Models\User;
use App\Services\Billing\PartnerMonthlyBillingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PartnerMonthlyBillingManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-03 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_superadmin_can_view_monthly_billing_index_and_snapshot_data_without_auto_creation(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $partnerWithoutSnapshot = $this->createPartner('Alfa Partner');
        $partnerWithSnapshot = $this->createPartner('Bravo Partner');
        $inactivePartner = $this->createPartner('Inaktív Partner', false);

        $this->createInstitution('Alfa Iskola', 'PMB101', $partnerWithoutSnapshot->id);
        $institutionB = $this->createInstitution('Bravo Iskola', 'PMB102', $partnerWithSnapshot->id);
        $institutionC = $this->createInstitution('Charlie Iskola', 'PMB103', $partnerWithSnapshot->id);
        $this->createInstitution('Delta Iskola', 'PMB104', $inactivePartner->id);

        $this->createChild($institutionB->id, 'Gyermek 01', true);
        $this->createRate($institutionB->id, '500.00');
        $this->createRate($institutionC->id, '0.00');

        app(PartnerMonthlyBillingService::class)->createSnapshot($partnerWithSnapshot, '2026-08');

        $view = $this->renderIndexAs($superAdmin, '2026-08');
        $response = $this->renderView($view);
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Ügyfél számlázás', $content);
        $this->assertStringContainsString('2026. augusztus', $content);
        $this->assertStringContainsString('Alfa Partner', $content);
        $this->assertStringContainsString('Bravo Partner', $content);
        $this->assertStringNotContainsString('Inaktív Partner', $content);
        $this->assertStringContainsString('Nincs létrehozva', $content);
        $this->assertStringContainsString('Tervezet', $content);
        $this->assertStringContainsString('635 Ft', $content);

        $this->assertDatabaseCount('partner_monthly_billings', 1);
    }

    public function test_non_superadmin_cannot_access_monthly_billing_index(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $request = Request::create('/dashboard/superadmin/partner-monthly-billings', 'GET');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Nincs jogosultsága az oldal megtekintéséhez.');

        app(CheckRole::class)->handle(
            $request,
            fn () => app(PartnerMonthlyBillingController::class)->index($request),
            User::ROLE_SUPER_ADMIN
        );
    }

    public function test_show_page_is_available_and_can_create_snapshot_from_detail_page(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $partner = $this->createPartner('Create Partner');
        $institution = $this->createInstitution('Create Iskola', 'PMB201', $partner->id);
        $this->createChild($institution->id, 'Gyermek 01', true);
        $this->createChild($institution->id, 'Gyermek 02', true);
        $this->createRate($institution->id, '400.00');

        $showView = $this->renderShowAs($superAdmin, $partner, '2026-08');
        $showResponse = $this->renderView($showView);
        $showContent = $showResponse->getContent();

        $this->assertSame(200, $showResponse->getStatusCode());
        $this->assertStringContainsString('Ehhez a partnerhez erre a hónapra még nem készült számlázási pillanatkép.', $showContent);
        $this->assertStringContainsString('Havi adatok létrehozása', $showContent);

        $request = $this->makeWebRequest('POST', route('dashboard.partner-monthly-billings.store', $partner), [
            'month' => '2026-08',
        ], $superAdmin);
        $response = app(PartnerMonthlyBillingController::class)->store($request, $partner);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(
            route('dashboard.partner-monthly-billings.show', ['billingPartner' => $partner, 'month' => '2026-08']),
            $response->getTargetUrl()
        );
        $this->assertDatabaseHas('partner_monthly_billings', [
            'billing_partner_id' => $partner->id,
            'billing_month' => '2026-08-01 00:00:00',
        ]);

        $detailView = $this->renderShowAs($superAdmin, $partner, '2026-08');
        $detailResponse = $this->renderView($detailView);
        $detailContent = $detailResponse->getContent();

        $this->assertStringContainsString('Intézményi tételsorok', $detailContent);
        $this->assertStringContainsString('Create Iskola', $detailContent);
        $this->assertStringContainsString('2 fő × 400,00 Ft = 800,00 Ft', $detailContent);
        $this->assertStringContainsString('Újraszámítás', $detailContent);
        $this->assertStringContainsString('Számlázottnak jelölés', $detailContent);
        $this->assertStringContainsString('Számlázási adatok másolása', $detailContent);
    }

    public function test_missing_rate_error_is_displayed_and_no_partial_snapshot_is_created(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $partner = $this->createPartner('Rate Partner');
        $institution = $this->createInstitution('Rate Nélküli Iskola', 'PMB301', $partner->id);
        $this->createChild($institution->id, 'Gyermek 01', true);

        $request = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.store', $partner),
            ['month' => '2026-08'],
            $superAdmin,
            route('dashboard.partner-monthly-billings.show', ['billingPartner' => $partner, 'month' => '2026-08'])
        );

        $response = app(PartnerMonthlyBillingController::class)->store($request, $partner);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertNotNull($request->session()->get('errors'));
        $this->assertDatabaseCount('partner_monthly_billings', 0);
        $this->assertDatabaseCount('partner_monthly_billing_items', 0);
    }

    public function test_draft_snapshot_can_be_recalculated_but_invoiced_and_paid_snapshots_cannot(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $partner = $this->createPartner('Recalc Partner');
        $institution = $this->createInstitution('Recalc Iskola', 'PMB401', $partner->id);
        $this->createChild($institution->id, 'Gyermek 01', true);
        $this->createRate($institution->id, '300.00');

        $service = app(PartnerMonthlyBillingService::class);
        $draftSnapshot = $service->createSnapshot($partner, '2026-08');

        $this->createChild($institution->id, 'Gyermek 02', true);

        $request = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.recalculate', $draftSnapshot),
            [],
            $superAdmin
        );
        $response = app(PartnerMonthlyBillingController::class)->recalculate($request, $draftSnapshot);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(
            route('dashboard.partner-monthly-billings.show', ['billingPartner' => $partner, 'month' => '2026-08']),
            $response->getTargetUrl()
        );

        $draftSnapshot->refresh();
        $this->assertSame(2, $draftSnapshot->total_children);

        $draftDetailContent = $this->renderView($this->renderShowAs($superAdmin, $partner, '2026-08'))->getContent();
        $this->assertStringContainsString('Újraszámítás', $draftDetailContent);

        $draftSnapshot->update([
            'status' => 'invoiced',
            'invoiced_at' => now(),
        ]);

        $invoicedDetailContent = $this->renderView($this->renderShowAs($superAdmin, $partner, '2026-08'))->getContent();
        $this->assertStringNotContainsString('Újraszámítás', $invoicedDetailContent);
        $this->assertStringContainsString('Fizetettnek jelölés', $invoicedDetailContent);

        $invoicedRequest = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.recalculate', $draftSnapshot),
            [],
            $superAdmin
        );
        $invoicedResponse = app(PartnerMonthlyBillingController::class)->recalculate($invoicedRequest, $draftSnapshot);

        $this->assertInstanceOf(RedirectResponse::class, $invoicedResponse);
        $this->assertNotNull($invoicedRequest->session()->get('errors'));

        $paidPartner = $this->createPartner('Paid Partner');
        $paidInstitution = $this->createInstitution('Paid Iskola', 'PMB402', $paidPartner->id);
        $this->createChild($paidInstitution->id, 'Gyermek 01', true);
        $this->createRate($paidInstitution->id, '300.00');
        $paidSnapshot = $service->createSnapshot($paidPartner, '2026-08');
        $paidSnapshot->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $paidDetailContent = $this->renderView($this->renderShowAs($superAdmin, $paidPartner, '2026-08'))->getContent();
        $this->assertStringNotContainsString('Újraszámítás', $paidDetailContent);
        $this->assertStringNotContainsString('Fizetettnek jelölés', $paidDetailContent);
        $this->assertStringNotContainsString('Számlázottnak jelölés', $paidDetailContent);

        $paidRequest = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.recalculate', $paidSnapshot),
            [],
            $superAdmin
        );
        $paidResponse = app(PartnerMonthlyBillingController::class)->recalculate($paidRequest, $paidSnapshot);

        $this->assertInstanceOf(RedirectResponse::class, $paidResponse);
        $this->assertNotNull($paidRequest->session()->get('errors'));
    }

    public function test_mark_as_invoiced_requires_invoice_number_and_persists_invoice_data(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $monthlyBilling = $this->createDraftSnapshot();

        $missingInvoiceRequest = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.mark-invoiced', $monthlyBilling),
            ['note' => 'Teszt megjegyzés'],
            $superAdmin
        );
        $missingInvoiceResponse = app(PartnerMonthlyBillingController::class)->markAsInvoiced($missingInvoiceRequest, $monthlyBilling);

        $this->assertInstanceOf(RedirectResponse::class, $missingInvoiceResponse);
        $this->assertNotNull($missingInvoiceRequest->session()->get('errors'));
        $this->assertNull($monthlyBilling->fresh()->invoice_number);

        $request = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.mark-invoiced', $monthlyBilling),
            [
                'invoice_number' => 'INV-2026-0001',
                'note' => 'Kézi számlázás',
            ],
            $superAdmin
        );
        $response = app(PartnerMonthlyBillingController::class)->markAsInvoiced($request, $monthlyBilling);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $monthlyBilling->refresh();
        $this->assertSame('invoiced', $monthlyBilling->status);
        $this->assertSame('INV-2026-0001', $monthlyBilling->invoice_number);
        $this->assertSame('Kézi számlázás', $monthlyBilling->note);
        $this->assertNotNull($monthlyBilling->invoiced_at);
        $this->assertNull($monthlyBilling->paid_at);

        $detailContent = $this->renderView($this->renderShowAs($superAdmin, $monthlyBilling->billingPartner, '2026-08'))->getContent();
        $this->assertStringContainsString('INV-2026-0001', $detailContent);
        $this->assertStringContainsString('Fizetettnek jelölés', $detailContent);
        $this->assertStringNotContainsString('Újraszámítás', $detailContent);
    }

    public function test_invoiced_or_paid_snapshot_cannot_be_marked_as_invoiced_again(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $monthlyBilling = $this->createDraftSnapshot(null, 'DRAFT002');
        $monthlyBilling->update([
            'status' => 'invoiced',
            'invoice_number' => 'INV-2026-0002',
            'invoiced_at' => now(),
        ]);

        $invoicedRequest = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.mark-invoiced', $monthlyBilling),
            ['invoice_number' => 'INV-2026-0003'],
            $superAdmin
        );
        $invoicedResponse = app(PartnerMonthlyBillingController::class)->markAsInvoiced($invoicedRequest, $monthlyBilling);

        $this->assertInstanceOf(RedirectResponse::class, $invoicedResponse);
        $this->assertNotNull($invoicedRequest->session()->get('errors'));
        $this->assertSame('INV-2026-0002', $monthlyBilling->fresh()->invoice_number);

        $paidBilling = $this->createDraftSnapshot(null, 'DRAFT003');
        $paidBilling->update([
            'status' => 'paid',
            'invoice_number' => 'INV-2026-0004',
            'invoiced_at' => now()->subDay(),
            'paid_at' => now(),
        ]);

        $paidRequest = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.mark-invoiced', $paidBilling),
            ['invoice_number' => 'INV-2026-0005'],
            $superAdmin
        );
        $paidResponse = app(PartnerMonthlyBillingController::class)->markAsInvoiced($paidRequest, $paidBilling);

        $this->assertInstanceOf(RedirectResponse::class, $paidResponse);
        $this->assertNotNull($paidRequest->session()->get('errors'));
        $this->assertSame('paid', $paidBilling->fresh()->status);
    }

    public function test_invoiced_snapshot_can_be_marked_as_paid_but_draft_or_paid_cannot(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $monthlyBilling = $this->createDraftSnapshot(null, 'DRAFT004');
        $monthlyBilling->update([
            'status' => 'invoiced',
            'invoice_number' => 'INV-2026-0100',
            'invoiced_at' => now(),
        ]);

        $request = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.mark-paid', $monthlyBilling),
            [],
            $superAdmin
        );
        $response = app(PartnerMonthlyBillingController::class)->markAsPaid($monthlyBilling);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('paid', $monthlyBilling->fresh()->status);
        $this->assertNotNull($monthlyBilling->fresh()->paid_at);

        $draftBilling = $this->createDraftSnapshot(null, 'DRAFT005');
        $draftRequest = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.mark-paid', $draftBilling),
            [],
            $superAdmin
        );
        $draftResponse = app(PartnerMonthlyBillingController::class)->markAsPaid($draftBilling);

        $this->assertInstanceOf(RedirectResponse::class, $draftResponse);
        $this->assertNotNull($draftRequest->session()->get('errors'));
        $this->assertSame('draft', $draftBilling->fresh()->status);

        $paidBilling = $this->createDraftSnapshot(null, 'DRAFT006');
        $paidBilling->update([
            'status' => 'paid',
            'invoice_number' => 'INV-2026-0101',
            'invoiced_at' => now()->subDay(),
            'paid_at' => now(),
        ]);
        $paidRequest = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.mark-paid', $paidBilling),
            [],
            $superAdmin
        );
        $paidResponse = app(PartnerMonthlyBillingController::class)->markAsPaid($paidBilling);

        $this->assertInstanceOf(RedirectResponse::class, $paidResponse);
        $this->assertNotNull($paidRequest->session()->get('errors'));
        $this->assertSame('paid', $paidBilling->fresh()->status);
        $this->assertNotNull($paidBilling->fresh()->paid_at);
    }

    public function test_non_superadmin_cannot_access_mark_invoiced_or_mark_paid_routes(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'is_active' => true,
        ]);
        $monthlyBilling = $this->createDraftSnapshot();

        $this->actingAs($user);

        $markInvoicedRequest = Request::create('/dashboard/superadmin/partner-monthly-billings/snapshots/' . $monthlyBilling->id . '/mark-invoiced', 'POST');
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Nincs jogosultsága az oldal megtekintéséhez.');

        app(CheckRole::class)->handle(
            $markInvoicedRequest,
            fn () => app(PartnerMonthlyBillingController::class)->markAsInvoiced($markInvoicedRequest, $monthlyBilling),
            User::ROLE_SUPER_ADMIN
        );
    }

    public function test_non_superadmin_cannot_access_mark_paid_route(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'is_active' => true,
        ]);
        $monthlyBilling = $this->createDraftSnapshot(null, 'DRAFT007');
        $monthlyBilling->update([
            'status' => 'invoiced',
            'invoice_number' => 'INV-2026-0200',
            'invoiced_at' => now(),
        ]);

        $this->actingAs($user);

        $markPaidRequest = Request::create('/dashboard/superadmin/partner-monthly-billings/snapshots/' . $monthlyBilling->id . '/mark-paid', 'POST');
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Nincs jogosultsága az oldal megtekintéséhez.');

        app(CheckRole::class)->handle(
            $markPaidRequest,
            fn () => app(PartnerMonthlyBillingController::class)->markAsPaid($monthlyBilling),
            User::ROLE_SUPER_ADMIN
        );
    }

    public function test_index_shows_invoiced_and_paid_statuses_and_invoice_number(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $invoicedPartner = $this->createPartner('Számlázott Partner');
        $paidPartner = $this->createPartner('Fizetett Partner');

        $invoicedBilling = $this->createDraftSnapshot($invoicedPartner, 'INVSTAT01');
        $paidBilling = $this->createDraftSnapshot($paidPartner, 'PAIDSTAT01');

        $invoicedBilling->update([
            'status' => 'invoiced',
            'invoice_number' => 'INV-2026-0300',
            'invoiced_at' => now(),
        ]);
        $paidBilling->update([
            'status' => 'paid',
            'invoice_number' => 'INV-2026-0301',
            'invoiced_at' => now()->subDay(),
            'paid_at' => now(),
        ]);

        $content = $this->renderView($this->renderIndexAs($superAdmin, '2026-08'))->getContent();

        $this->assertStringContainsString('Számlázva', $content);
        $this->assertStringContainsString('Fizetve', $content);
        $this->assertStringContainsString('INV-2026-0300', $content);
        $this->assertStringContainsString('INV-2026-0301', $content);
    }

    public function test_other_month_snapshot_is_not_shown_for_selected_month_and_invalid_or_missing_month_defaults_to_current(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $partner = $this->createPartner('Month Partner');
        $institution = $this->createInstitution('Month Iskola', 'PMB501', $partner->id);
        $this->createChild($institution->id, 'Gyermek 01', true);
        $this->createRate($institution->id, '350.00');

        app(PartnerMonthlyBillingService::class)->createSnapshot($partner, '2026-07');

        $augustContent = $this->renderView($this->renderShowAs($superAdmin, $partner, '2026-08'))->getContent();
        $this->assertStringContainsString('Ehhez a partnerhez erre a hónapra még nem készült számlázási pillanatkép.', $augustContent);
        $this->assertStringNotContainsString('350 Ft / gyermek', $augustContent);

        $missingMonthContent = $this->renderView($this->renderIndexAs($superAdmin))->getContent();
        $this->assertStringContainsString('2026. augusztus', $missingMonthContent);

        $invalidMonthContent = $this->renderView($this->renderIndexAs($superAdmin, 'hibas'))->getContent();
        $this->assertStringContainsString('2026. augusztus', $invalidMonthContent);
    }

    private function renderIndexAs(User $user, ?string $month = null)
    {
        $this->actingAs($user);
        $uri = '/dashboard/superadmin/partner-monthly-billings' . ($month ? '?month=' . $month : '');
        $request = Request::create($uri, 'GET');

        return app(CheckRole::class)->handle(
            $request,
            fn () => app(PartnerMonthlyBillingController::class)->index($request),
            User::ROLE_SUPER_ADMIN
        );
    }

    private function renderShowAs(User $user, BillingPartner $partner, ?string $month = null)
    {
        $this->actingAs($user);
        $uri = '/dashboard/superadmin/partner-monthly-billings/' . $partner->id . ($month ? '?month=' . $month : '');
        $request = Request::create($uri, 'GET');

        return app(CheckRole::class)->handle(
            $request,
            fn () => app(PartnerMonthlyBillingController::class)->show($request, $partner),
            User::ROLE_SUPER_ADMIN
        );
    }

    private function renderView($view)
    {
        view()->share('errors', new ViewErrorBag());

        return response()->view($view->name(), $view->getData());
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    private function createPartner(string $name, bool $active = true): BillingPartner
    {
        return BillingPartner::query()->create([
            'name' => $name,
            'billing_name' => $name . ' Kft.',
            'tax_number' => '12345678-1-42',
            'billing_zip' => '1051',
            'billing_city' => 'Budapest',
            'billing_address' => 'Teszt utca 1.',
            'billing_email' => 'billing@example.com',
            'payment_due_days' => 8,
            'vat_rate' => '27.00',
            'active' => $active,
        ]);
    }

    private function createInstitution(string $name, string $code, ?int $billingPartnerId = null): Institution
    {
        return Institution::query()->create([
            'name' => $name,
            'institution_code' => $code,
            'type' => 'iskola',
            'billing_partner_id' => $billingPartnerId,
            'active' => true,
        ]);
    }

    private function createChild(int $institutionId, string $name, bool $active): Child
    {
        return Child::query()->create([
            'institution_id' => $institutionId,
            'name' => $name,
            'educational_identifier' => substr(md5($institutionId . $name . $active . microtime(true)), 0, 11),
            'group_name' => '1.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => $active,
        ]);
    }

    private function createRate(int $institutionId, string $pricePerChild): InstitutionBillingRate
    {
        return InstitutionBillingRate::query()->create([
            'institution_id' => $institutionId,
            'price_per_child' => $pricePerChild,
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => null,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);
    }

    private function createDraftSnapshot(?BillingPartner $partner = null, string $code = 'DRAFT001'): PartnerMonthlyBilling
    {
        $partner ??= $this->createPartner('Draft Partner ' . $code);
        $institution = $this->createInstitution('Draft Iskola ' . $code, $code, $partner->id);
        $this->createChild($institution->id, 'Gyermek ' . $code, true);
        $this->createRate($institution->id, '300.00');

        return app(PartnerMonthlyBillingService::class)->createSnapshot($partner, '2026-08');
    }

    private function makeWebRequest(string $method, string $uri, array $payload, User $user, ?string $referer = null): FormRequest
    {
        $server = $referer ? ['HTTP_REFERER' => $referer] : [];
        $baseRequest = Request::create($uri, $method, $payload, [], [], $server);
        $request = FormRequest::createFromBase($baseRequest);
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->start();

        return $request;
    }
}
