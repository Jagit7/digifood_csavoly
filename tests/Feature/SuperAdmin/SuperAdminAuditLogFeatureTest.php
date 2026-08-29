<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Dashboard\SuperAdmin\AuditLogController;
use App\Http\Controllers\Dashboard\SuperAdmin\PartnerMonthlyBillingController;
use App\Http\Middleware\CheckRole;
use App\Models\AuditLog;
use App\Models\BillingPartner;
use App\Models\Institution;
use App\Models\PartnerMonthlyBilling;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SuperAdminAuditLogFeatureTest extends TestCase
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

    public function test_superadmin_can_open_audit_log_page(): void
    {
        $response = $this->renderAuditLogsAs($this->createSuperAdmin());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Műveleti napló', $response->getContent());
        $this->assertStringContainsString('Még nincs naplózott művelet', $response->getContent());
    }

    public function test_non_superadmin_cannot_access_audit_log_page(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $request = Request::create('/dashboard/superadmin/audit-logs', 'GET');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Nincs jogosultsága az oldal megtekintéséhez.');

        app(CheckRole::class)->handle(
            $request,
            fn () => app(AuditLogController::class)->index($request),
            User::ROLE_SUPER_ADMIN
        );
    }

    public function test_partner_monthly_billing_status_change_creates_audit_log_entry(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $partner = BillingPartner::query()->create([
            'name' => 'Audit Partner',
            'vat_rate' => '27.00',
            'active' => true,
        ]);

        $institution = Institution::query()->create([
            'name' => 'Audit Iskola',
            'institution_code' => 'ADT001',
            'type' => 'iskola',
            'address_city' => 'Budapest',
            'billing_partner_id' => $partner->id,
            'active' => true,
        ]);

        $monthlyBilling = PartnerMonthlyBilling::query()->create([
            'billing_partner_id' => $partner->id,
            'billing_month' => '2026-08-01',
            'total_children' => 12,
            'net_amount' => '1000.00',
            'vat_amount' => '270.00',
            'gross_amount' => '1270.00',
            'status' => 'draft',
        ]);

        $request = $this->makeWebRequest(
            'POST',
            route('dashboard.partner-monthly-billings.mark-invoiced', $monthlyBilling),
            [
                'invoice_number' => 'INV-2026-001',
                'note' => 'Audit teszt',
            ],
            $superAdmin
        );

        $response = app(PartnerMonthlyBillingController::class)->markAsInvoiced($request, $monthlyBilling);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_PARTNER_BILLING_STATUS_CHANGED,
            'user_id' => $superAdmin->id,
            'description' => 'Partneri havi számlázási státusz módosítva',
        ]);

        $auditLog = AuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame('draft', $auditLog->old_values['old_status'] ?? null);
        $this->assertSame('invoiced', $auditLog->new_values['new_status'] ?? null);

        $logPageResponse = $this->renderAuditLogsAs($superAdmin);
        $content = $logPageResponse->getContent();

        $this->assertStringContainsString('Partneri havi számlázási státusz módosítása', $content);
        $this->assertStringContainsString('old_status', $content);
        $this->assertStringContainsString('new_status', $content);
        $this->assertStringContainsString('draft', $content);
        $this->assertStringContainsString('invoiced', $content);
    }

    private function renderAuditLogsAs(User $user)
    {
        $this->actingAs($user);

        $request = Request::create('/dashboard/superadmin/audit-logs', 'GET');
        $this->app->instance('request', $request);
        URL::setRequest($request);

        $view = app(CheckRole::class)->handle(
            $request,
            fn () => app(AuditLogController::class)->index($request),
            User::ROLE_SUPER_ADMIN
        );

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

    private function makeWebRequest(string $method, string $uri, array $data, User $user): Request
    {
        $request = Request::create($uri, $method, $data);
        $request->setLaravelSession($this->app['session']->driver());
        $request->headers->set('User-Agent', 'Codex Audit Test');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $this->actingAs($user);

        return $request;
    }
}
