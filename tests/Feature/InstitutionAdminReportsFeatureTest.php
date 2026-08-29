<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\InstitutionAdmin\ReportController;
use App\Http\Middleware\CheckRole;
use App\Models\Child;
use App\Models\Institution;
use App\Models\MealCancellation;
use App\Models\MealCheckIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class InstitutionAdminReportsFeatureTest extends TestCase
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

    public function test_institution_admin_can_open_reports_and_only_see_own_institution_data(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('RPTA1');
        $otherInstitution = Institution::create([
            'name' => 'Másik Intézmény',
            'institution_code' => 'RPTB1',
            'type' => 'iskola',
            'active' => true,
        ]);

        $ownFirstChild = $this->createChild($institution->id, 'Saját Első', '1.A');
        $ownSecondChild = $this->createChild($institution->id, 'Saját Második', '1.A');
        $otherChild = $this->createChild($otherInstitution->id, 'Másik Gyermek', '9.Z');

        MealCheckIn::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $ownFirstChild->id,
            'service_date' => '2026-08-01',
            'scanned_at' => '2026-08-01 12:00:00',
            'status' => MealCheckIn::STATUS_SUCCESS,
        ]);
        MealCheckIn::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $ownSecondChild->id,
            'service_date' => '2026-08-02',
            'scanned_at' => '2026-08-02 12:00:00',
            'status' => MealCheckIn::STATUS_SUCCESS,
        ]);
        MealCheckIn::query()->create([
            'institution_id' => $otherInstitution->id,
            'child_id' => $otherChild->id,
            'service_date' => '2026-08-01',
            'scanned_at' => '2026-08-01 12:00:00',
            'status' => MealCheckIn::STATUS_SUCCESS,
        ]);

        MealCancellation::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $ownFirstChild->id,
            'service_date' => '2026-08-02',
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);
        MealCancellation::query()->create([
            'institution_id' => $otherInstitution->id,
            'child_id' => $otherChild->id,
            'service_date' => '2026-08-02',
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);

        $response = $this->renderReportsAs($user, '2026-08');
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Riportok', $content);
        $this->assertStringContainsString($institution->name, $content);
        $this->assertStringContainsString('1.A', $content);
        $this->assertStringNotContainsString('Másik Intézmény', $content);
        $this->assertStringNotContainsString('9.Z', $content);
    }

    public function test_invalid_month_does_not_cause_error(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('RPTA2');

        $response = $this->renderReportsAs($user, 'hibas');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($institution->name, $response->getContent());
        $this->assertStringContainsString('2026. augusztus', $response->getContent());
    }

    public function test_reports_page_loads_with_empty_month(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('RPTA3');

        $response = $this->renderReportsAs($user, '2026-08');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($institution->name, $response->getContent());
        $this->assertStringContainsString('Nincs megjeleníthető adat', $response->getContent());
    }

    public function test_successful_meals_and_cancellations_are_shown_correctly(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('RPTA4');
        $child = $this->createChild($institution->id, 'Riport Gyermek', '2.B');

        MealCheckIn::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-08-03',
            'scanned_at' => '2026-08-03 12:00:00',
            'status' => MealCheckIn::STATUS_SUCCESS,
        ]);

        MealCancellation::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-08-04',
            'source' => MealCancellation::SOURCE_PARENT,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);

        $response = $this->renderReportsAs($user, '2026-08');
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('2026. 08. 03.', $content);
        $this->assertStringContainsString('2026. 08. 04.', $content);
        $this->assertStringContainsString('2.B', $content);
    }

    private function seedUserWithInstitution(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Teszt Intézmény '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
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

    private function renderReportsAs(User $user, ?string $month = null)
    {
        $this->actingAs($user);

        $uri = '/dashboard/institution/reports' . ($month ? '?month=' . $month : '');
        $request = Request::create($uri, 'GET');
        $this->app->instance('request', $request);
        URL::setRequest($request);

        $view = app(CheckRole::class)->handle(
            $request,
            fn () => app(ReportController::class)->index($request),
            User::ROLE_INSTITUTION_ADMIN,
            User::ROLE_KITCHEN,
            User::ROLE_MUNICIPALITY
        );

        view()->share('errors', new ViewErrorBag());

        return response()->view($view->name(), $view->getData());
    }

    private function createChild(int $institutionId, string $name, string $groupName): Child
    {
        return Child::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId), 0, 10),
            'group_name' => $groupName,
            'school_year' => '2025/2026',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }
}
