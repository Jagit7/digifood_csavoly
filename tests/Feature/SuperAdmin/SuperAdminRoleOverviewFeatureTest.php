<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Dashboard\SuperAdmin\RoleController;
use App\Http\Middleware\CheckRole;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SuperAdminRoleOverviewFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_institution_admin_lists_connected_institutions_in_role_overview(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $institutionA = $this->createInstitution('Szerepkor Intezet A', 'ROLEA1');
        $institutionB = $this->createInstitution('Szerepkor Intezet B', 'ROLEB1');

        $user = User::factory()->create([
            'name' => 'Tobb Intezmenyes Admin',
            'email' => 'role-multi@example.test',
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => null,
            'is_active' => true,
        ]);

        DB::table('institution_user')->insert([
            [
                'institution_id' => $institutionA->id,
                'user_id' => $user->id,
                'scope_role' => User::ROLE_INSTITUTION_ADMIN,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'institution_id' => $institutionB->id,
                'user_id' => $user->id,
                'scope_role' => User::ROLE_INSTITUTION_ADMIN,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($superAdmin);
        $request = Request::create('/dashboard/superadmin/roles', 'GET');
        $request->setUserResolver(fn () => $superAdmin);

        $view = app(CheckRole::class)->handle(
            $request,
            fn () => app(RoleController::class)->index(),
            User::ROLE_SUPER_ADMIN
        );

        view()->share('errors', new ViewErrorBag());
        $response = response()->view($view->name(), $view->getData());
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('role-multi@example.test', $content);
        $this->assertStringContainsString('Szerepkor Intezet A', $content);
        $this->assertStringContainsString('Szerepkor Intezet B', $content);
    }

    private function createInstitution(string $name, string $code): Institution
    {
        return Institution::query()->create([
            'name' => $name,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);
    }
}
