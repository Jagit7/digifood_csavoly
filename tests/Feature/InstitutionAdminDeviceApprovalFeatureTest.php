<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\InstitutionAdminDevice;
use App\Models\InstitutionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstitutionAdminDeviceApprovalFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_restriction_off_allows_login_from_new_device_without_creating_pending_approval(): void
    {
        [$institution, $user] = $this->createInstitutionAdminWithPivotOnly('DEV001');

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'admin_browser_restriction_enabled' => false,
            ])
        );

        $response = $this->post(route('auth.login_form'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard.institution.home'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('institution_admin_devices', [
            'user_id' => $user->id,
        ]);
    }

    public function test_restriction_on_requires_approval_for_new_device(): void
    {
        [$institution, $user] = $this->createInstitutionAdminWithPivotOnly('DEV002');

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'admin_browser_restriction_enabled' => true,
            ])
        );

        $response = $this->from(route('auth.login'))->post(route('auth.login_form'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('auth.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('institution_admin_devices', [
            'user_id' => $user->id,
        ]);

        $device = InstitutionAdminDevice::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertNotNull($device->pending_token_hash);
        $this->assertNull($device->approved_token_hash);
        $this->assertNotNull($device->pending_requested_at);
    }

    public function test_restriction_on_allows_login_from_approved_device(): void
    {
        [$institution, $user] = $this->createInstitutionAdminWithPivotOnly('DEV003');

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'admin_browser_restriction_enabled' => true,
            ])
        );

        InstitutionAdminDevice::query()->create([
            'user_id' => $user->id,
            'approved_token_hash' => hash('sha256', 'approved-device-token'),
            'approved_ip' => '127.0.0.1',
            'approved_user_agent' => 'PHPUnit',
            'approved_at' => now()->subMinute(),
            'last_used_at' => now()->subMinute(),
        ]);

        $response = $this->withCookie('iad_device', 'approved-device-token')
            ->post(route('auth.login_form'), [
                'email' => $user->email,
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('dashboard.institution.home'));
        $this->assertAuthenticatedAs($user);

        $device = InstitutionAdminDevice::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNull($device->pending_token_hash);
        $this->assertNotNull($device->last_used_at);
    }

    public function test_approved_device_still_allows_login_after_second_institution_is_added_via_admin_update(): void
    {
        [$institutionA, $user] = $this->createInstitutionAdminWithLegacyInstitution('DEV004A');
        $institutionB = Institution::query()->create([
            'name' => 'Intezet DEV004B',
            'institution_code' => 'DEV004B',
            'type' => Institution::TYPE_SCHOOL,
            'active' => true,
        ]);
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institutionA->id],
            array_merge(InstitutionSetting::defaults(), [
                'admin_browser_restriction_enabled' => true,
            ])
        );

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institutionB->id],
            array_merge(InstitutionSetting::defaults(), [
                'admin_browser_restriction_enabled' => true,
            ])
        );

        InstitutionAdminDevice::query()->create([
            'user_id' => $user->id,
            'approved_token_hash' => hash('sha256', 'approved-device-token'),
            'approved_ip' => '127.0.0.1',
            'approved_user_agent' => 'PHPUnit',
            'approved_at' => now()->subMinute(),
            'last_used_at' => now()->subMinute(),
        ]);

        $this->actingAs($superAdmin)
            ->put(route('dashboard.admin-access.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => User::ROLE_INSTITUTION_ADMIN,
                'is_active' => '1',
                'institutions' => [$institutionA->id, $institutionB->id],
            ])
            ->assertRedirect(route('dashboard.admin-access.index'));

        auth()->logout();

        $response = $this->withCookie('iad_device', 'approved-device-token')
            ->post(route('auth.login_form'), [
                'email' => $user->email,
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('dashboard.institution.home'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    private function createInstitutionAdminWithPivotOnly(string $code): array
    {
        $institution = Institution::query()->create([
            'name' => 'Intezet ' . $code,
            'institution_code' => $code,
            'type' => Institution::TYPE_SCHOOL,
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => null,
            'is_active' => true,
            'email' => strtolower($code) . '@example.test',
            'password' => Hash::make('password123'),
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

    private function createInstitutionAdminWithLegacyInstitution(string $code): array
    {
        $institution = Institution::query()->create([
            'name' => 'Intezet ' . $code,
            'institution_code' => $code,
            'type' => Institution::TYPE_SCHOOL,
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
            'email' => strtolower($code) . '@example.test',
            'password' => Hash::make('password123'),
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
}
