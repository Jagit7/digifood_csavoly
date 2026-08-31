<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionAdminInvitation;
use App\Models\InstitutionEmployee;
use App\Models\User;
use App\Services\EmployeePortal\EmployeeAccountActivationService;
use App\Services\ParentPortal\ParentAccountActivationService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthRedirectConsistencyFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
        config(['app.url' => 'https://app.example.test']);
    }

    public function test_parent_login_redirect_stays_relative(): void
    {
        [$user] = $this->createParentUser('AUTH001');

        $response = $this->onHost('app.example.test')->post(route('parent.login.store', [], false), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('parent.dashboard', [], false));
    }

    public function test_parent_activation_success_redirect_uses_current_host(): void
    {
        config(['app.url' => 'https://app.example.test']);

        $institution = $this->createInstitution('AUTH002');

        Guardian::query()->create([
            'institution_id' => $institution->id,
            'last_name' => 'Parent',
            'first_name' => 'Activate',
            'email' => 'activate-parent@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $url = app(ParentAccountActivationService::class)
            ->createManualActivationLink('activate-parent@example.test')['activation_url'];

        $path = (string) parse_url($url, PHP_URL_PATH);
        $host = (string) parse_url($url, PHP_URL_HOST);

        $this->onHost($host)->get($path)->assertOk();

        $response = $this->onHost($host)->post($path, [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('parent.dashboard', [], false));
    }

    public function test_institution_admin_invitation_completion_redirect_stays_relative(): void
    {
        $institution = $this->createInstitution('AUTH003');
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        InstitutionAdminInvitation::query()->create([
            'institution_id' => $institution->id,
            'invited_by' => $superAdmin->id,
            'name' => 'Admin User',
            'email' => 'tenant-admin@example.test',
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'token_hash' => hash('sha256', 'tenant-admin-token'),
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->onHost('app.example.test')->post(
            route('institution-invite.complete', ['token' => 'tenant-admin-token'], false),
            [
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]
        );

        $response->assertRedirect(route('dashboard.institution.home', [], false));
    }

    public function test_employee_activation_and_login_redirects_stay_relative(): void
    {
        config(['app.url' => 'https://app.example.test']);

        $institution = $this->createInstitution('AUTH004');

        InstitutionEmployee::query()->create([
            'institution_id' => $institution->id,
            'name' => 'Employee User',
            'email' => 'employee-activate@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $url = app(EmployeeAccountActivationService::class)
            ->createManualActivationLink('employee-activate@example.test')['activation_url'];

        $path = (string) parse_url($url, PHP_URL_PATH);
        $host = (string) parse_url($url, PHP_URL_HOST);

        $this->onHost($host)->get($path)->assertOk();

        $activationResponse = $this->onHost($host)->post($path, [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $activationResponse->assertRedirect(route('employee.dashboard', [], false));

        auth()->logout();

        $loginResponse = $this->onHost('app.example.test')->post(route('employee.login.store', [], false), [
            'email' => 'employee-activate@example.test',
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('employee.dashboard', [], false));
    }

    public function test_parent_logout_redirects_to_parent_login_on_same_host(): void
    {
        [$user] = $this->createParentUser('AUTH005');

        $response = $this->onHost('app.example.test')
            ->actingAs($user)
            ->post(route('parent.logout', [], false));

        $response->assertRedirect(route('parent.login', [], false));
    }

    public function test_admin_logout_redirects_to_general_login_on_same_host(): void
    {
        [, $admin] = $this->createInstitutionAdmin('AUTH006');

        $response = $this->onHost('app.example.test')
            ->actingAs($admin)
            ->post(route('auth.logout', [], false));

        $response->assertRedirect(route('auth.login', [], false));
    }

    public function test_guest_middleware_redirects_protected_parent_and_admin_pages_without_host_leak(): void
    {
        $this->onHost('app.example.test')
            ->get(route('parent.dashboard', [], false))
            ->assertRedirect(route('parent.login', [], false));

        $this->onHost('app.example.test')
            ->get(route('dashboard.institution.home', [], false))
            ->assertRedirect(route('auth.login', [], false));
    }

    public function test_authenticated_users_opening_login_pages_stay_on_same_host(): void
    {
        [$parent] = $this->createParentUser('AUTH007');
        [, $admin] = $this->createInstitutionAdmin('AUTH008');

        $this->onHost('app.example.test')
            ->actingAs($parent)
            ->get(route('parent.login', [], false))
            ->assertRedirect(route('parent.dashboard', [], false));

        auth()->logout();

        $this->onHost('app.example.test')
            ->actingAs($admin)
            ->get(route('auth.login', [], false))
            ->assertRedirect(route('dashboard.institution.home', [], false));
    }

    private function createInstitution(string $code): Institution
    {
        return Institution::query()->create([
            'name' => 'Institution '.$code,
            'institution_code' => $code,
            'type' => Institution::TYPE_SCHOOL,
            'active' => true,
        ]);
    }

    private function createParentUser(string $code): array
    {
        $institution = $this->createInstitution($code);
        $user = User::factory()->create([
            'email' => strtolower($code).'@parent.example.test',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        Guardian::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'last_name' => 'Parent',
            'first_name' => $code,
            'email' => $user->email,
            'source_type' => 'manual',
            'active' => true,
        ]);

        return [$user, $institution];
    }

    private function createInstitutionAdmin(string $code): array
    {
        $institution = $this->createInstitution($code);
        $user = User::factory()->create([
            'email' => strtolower($code).'@admin.example.test',
            'password' => bcrypt('password123'),
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

        return [$institution, $user];
    }

    private function onHost(string $host): self
    {
        return $this->withServerVariables([
            'HTTP_HOST' => $host,
            'HTTPS' => 'on',
        ]);
    }
}
