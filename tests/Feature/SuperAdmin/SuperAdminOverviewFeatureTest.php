<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Dashboard\SuperAdmin\SuperAdminOverviewController;
use App\Http\Middleware\CheckRole;
use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionAdminInvitation;
use App\Models\StudentMealSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SuperAdminOverviewFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_view_data_driven_overview_dashboard(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $institutionA = $this->createInstitution('Teszt Intézmény A', 'TSTA01', true, 'Budapest', now()->subDays(3));
        $institutionB = $this->createInstitution('Teszt Intézmény B', 'TSTB01', false, 'Szeged', now()->subDays(12));

        $institutionAdmin = User::factory()->create([
            'name' => 'Intézményi Admin',
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'is_active' => true,
        ]);
        $institutionA->users()->attach($institutionAdmin->id);

        User::factory()->create([
            'role' => User::ROLE_PARENT,
            'is_active' => true,
        ]);

        $child = Child::query()->create([
            'institution_id' => $institutionA->id,
            'name' => 'Minta Gyermek',
            'educational_identifier' => 'EDU-001',
            'group_name' => '1.A',
            'source_type' => 'manual',
            'active' => true,
        ]);

        StudentMealSetting::query()->create([
            'student_id' => $child->id,
            'institution_id' => $institutionA->id,
            'mode' => StudentMealSetting::MODE_CUSTOM,
            'valid_from' => now()->subDays(1)->toDateString(),
            'valid_to' => now()->addDays(30)->toDateString(),
        ]);

        InstitutionAdminInvitation::query()->create([
            'institution_id' => $institutionA->id,
            'invited_by' => $superAdmin->id,
            'name' => 'Meghívott Admin',
            'email' => 'meghivott@example.com',
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'token_hash' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($superAdmin);
        $request = Request::create('/dashboard/superadmin', 'GET');

        $view = app(CheckRole::class)->handle(
            $request,
            fn () => app(SuperAdminOverviewController::class)->index(),
            User::ROLE_SUPER_ADMIN
        );

        view()->share('errors', new ViewErrorBag());
        $response = response()->view($view->name(), $view->getData());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('SuperAdmin áttekintés', $response->getContent());
        $this->assertStringContainsString('Intézmények összesen', $response->getContent());
        $this->assertStringContainsString('Legutóbb létrehozott intézmények', $response->getContent());
        $this->assertStringContainsString('Teszt Intézmény A', $response->getContent());
        $this->assertStringContainsString('Adminisztrátori hozzáférések', $response->getContent());
        $this->assertStringContainsString(route('dashboard.institutions.index'), $response->getContent());
        $this->assertStringContainsString(route('dashboard.admin-access.index'), $response->getContent());
        $this->assertStringContainsString(route('dashboard.admin-access.invite.create'), $response->getContent());
    }

    public function test_non_superadmin_user_cannot_access_superadmin_overview(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $request = Request::create('/dashboard/superadmin', 'GET');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Nincs jogosultsága az oldal megtekintéséhez.');

        app(CheckRole::class)->handle(
            $request,
            fn () => app(SuperAdminOverviewController::class)->index(),
            User::ROLE_SUPER_ADMIN
        );
    }

    public function test_overview_loads_with_empty_database_state(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin);
        $request = Request::create('/dashboard/superadmin', 'GET');

        $view = app(CheckRole::class)->handle(
            $request,
            fn () => app(SuperAdminOverviewController::class)->index(),
            User::ROLE_SUPER_ADMIN
        );

        view()->share('errors', new ViewErrorBag());
        $response = response()->view($view->name(), $view->getData());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('SuperAdmin áttekintés', $response->getContent());
        $this->assertStringContainsString('Még nincs rögzített intézmény.', $response->getContent());
    }

    private function createInstitution(
        string $name,
        string $code,
        bool $active,
        string $city,
        $createdAt
    ): Institution {
        return Institution::query()->create([
            'name' => $name,
            'institution_code' => $code,
            'type' => 'iskola',
            'address_city' => $city,
            'active' => $active,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
