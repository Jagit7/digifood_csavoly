<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Dashboard\SuperAdmin\ReportsController;
use App\Http\Middleware\CheckRole;
use App\Models\BillingPartner;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\MealCheckIn;
use App\Models\PartnerMonthlyBilling;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SuperAdminReportsFeatureTest extends TestCase
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

    public function test_superadmin_can_open_reports_page(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('Riport Iskola', 'RPT001');
        $parentUser = User::factory()->create([
            'role' => User::ROLE_PARENT,
            'is_active' => true,
        ]);

        Guardian::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $parentUser->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szülő',
            'email' => 'szulo@example.test',
            'active' => true,
        ]);

        $child = Child::query()->create([
            'institution_id' => $institution->id,
            'name' => 'Teszt Gyermek',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $partner = BillingPartner::query()->create([
            'name' => 'Riport Partner',
            'vat_rate' => '27.00',
            'active' => true,
        ]);

        $institution->update(['billing_partner_id' => $partner->id]);

        $billing = PartnerMonthlyBilling::query()->create([
            'billing_partner_id' => $partner->id,
            'billing_month' => '2026-08-01',
            'total_children' => 1,
            'net_amount' => '1000.00',
            'vat_amount' => '270.00',
            'gross_amount' => '1270.00',
            'status' => 'draft',
        ]);

        $billing->items()->create([
            'institution_id' => $institution->id,
            'institution_name_snapshot' => $institution->name,
            'child_count' => 1,
            'price_per_child' => '1000.00',
            'net_amount' => '1000.00',
            'calculation_description' => 'Teszt tétel',
        ]);

        \App\Models\MealCheckIn::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-08-01',
            'scanned_at' => '2026-08-01 12:00:00',
            'status' => \App\Models\MealCheckIn::STATUS_SUCCESS,
        ]);

        \App\Models\MealCancellation::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-08-02',
            'source' => \App\Models\MealCancellation::SOURCE_PARENT,
            'status' => \App\Models\MealCancellation::STATUS_ACTIVE,
        ]);

        $response = $this->renderReportsAs($superAdmin, '2026-08');
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Riportok', $content);
        $this->assertStringContainsString('2026. augusztus', $content);
        $this->assertStringContainsString('Riport Iskola', $content);
        $this->assertStringContainsString('Tervezet', $content);
        $this->assertStringContainsString('1 000 Ft', $content);
    }

    public function test_non_superadmin_cannot_access_reports_page(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $request = Request::create('/dashboard/superadmin/reports', 'GET');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Nincs jogosultsága az oldal megtekintéséhez.');

        app(CheckRole::class)->handle(
            $request,
            fn () => app(ReportsController::class)->index($request),
            User::ROLE_SUPER_ADMIN
        );
    }

    public function test_invalid_month_query_falls_back_to_current_month(): void
    {
        $response = $this->renderReportsAs($this->createSuperAdmin(), 'invalid');

        $this->assertStringContainsString('2026. augusztus', $response->getContent());
    }

    public function test_reports_page_loads_with_empty_data(): void
    {
        $response = $this->renderReportsAs($this->createSuperAdmin(), '2026-08');
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Riportok', $content);
        $this->assertStringContainsString('Nincs megjeleníthető adat', $content);
    }

    public function test_month_query_is_preserved_in_pagination_links(): void
    {
        $superAdmin = $this->createSuperAdmin();

        foreach (range(1, 16) as $index) {
            $this->createInstitution(sprintf('Riport Intézmény %02d', $index), sprintf('RP%03d', $index));
        }

        $response = $this->renderReportsAs($superAdmin, '2026-08');

        $this->assertStringContainsString('month=2026-08&amp;page=2', $response->getContent());
    }

    public function test_reports_page_counts_successful_monthly_meal_check_ins_without_database_error(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('Étkezés Riport Iskola', 'RPT002');

        $firstChild = Child::query()->create([
            'institution_id' => $institution->id,
            'name' => 'Első Gyermek',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $secondChild = Child::query()->create([
            'institution_id' => $institution->id,
            'name' => 'Második Gyermek',
            'source_type' => 'manual',
            'active' => true,
        ]);

        MealCheckIn::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $firstChild->id,
            'service_date' => '2026-08-01',
            'scanned_at' => '2026-08-01 12:00:00',
            'status' => MealCheckIn::STATUS_SUCCESS,
        ]);

        MealCheckIn::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $secondChild->id,
            'service_date' => '2026-08-02',
            'scanned_at' => '2026-08-02 12:00:00',
            'status' => MealCheckIn::STATUS_SUCCESS,
        ]);

        $response = $this->renderReportsAs($superAdmin, '2026-08');
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Étkezés Riport Iskola', $content);
        $this->assertMatchesRegularExpression('/<h2 class="text-white mt-2 mb-1">2<\/h2>/', $content);
    }

    private function renderReportsAs(User $user, ?string $month = null)
    {
        $this->actingAs($user);

        $uri = '/dashboard/superadmin/reports' . ($month ? '?month=' . $month : '');
        $request = Request::create($uri, 'GET');
        $this->app->instance('request', $request);
        URL::setRequest($request);

        $view = app(CheckRole::class)->handle(
            $request,
            fn () => app(ReportsController::class)->index($request),
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

    private function createInstitution(string $name, string $code): Institution
    {
        return Institution::query()->create([
            'name' => $name,
            'institution_code' => $code,
            'type' => 'iskola',
            'address_city' => 'Budapest',
            'active' => true,
        ]);
    }
}
