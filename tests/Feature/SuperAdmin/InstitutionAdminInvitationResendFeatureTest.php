<?php

namespace Tests\Feature\SuperAdmin;

use App\Mail\InstitutionAdminInviteMail;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\InstitutionAdminInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InstitutionAdminInvitationResendFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-30 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_active_invitation_can_be_accepted(): void
    {
        $institution = $this->createInstitution('INV001');
        $this->createInvitation($institution, 'active-token');

        $response = $this->post(route('institution-invite.complete', ['token' => 'active-token']), [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard.institution.home', [], false));
        $this->assertDatabaseHas('users', [
            'email' => 'invite@example.test',
            'role' => User::ROLE_INSTITUTION_ADMIN,
        ]);
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institution->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
        ]);
        $this->assertNotNull(InstitutionAdminInvitation::query()->firstOrFail()->accepted_at);
    }

    public function test_expired_invitation_cannot_be_accepted_and_shows_friendly_page(): void
    {
        $institution = $this->createInstitution('INV002');
        $this->createInvitation($institution, 'expired-token', expiresAt: now()->subMinute());

        $this->get(route('institution-invite.accept', ['token' => 'expired-token']))
            ->assertStatus(410)
            ->assertSee('Ez a meghívó lejárt.')
            ->assertSee('A meghívó 24 órán keresztül használható.');

        $this->post(route('institution-invite.complete', ['token' => 'expired-token']), [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(410);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_invitation_can_be_resent(): void
    {
        Mail::fake();

        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('INV003');
        $invitation = $this->createInvitation($institution, 'old-expired-token', expiresAt: now()->subMinute(), invitedBy: $superAdmin->id);
        $oldTokenHash = $invitation->token_hash;

        $response = $this->actingAs($superAdmin)
            ->post(route('dashboard.admin-access.invite.resend', $invitation));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $invitation->refresh();

        $this->assertNotSame($oldTokenHash, $invitation->token_hash);
        $this->assertNull($invitation->accepted_at);
        Mail::assertSent(InstitutionAdminInviteMail::class, 1);
    }

    public function test_resend_sets_a_fresh_24_hour_expiration(): void
    {
        Mail::fake();

        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('INV004');
        $invitation = $this->createInvitation($institution, 'refresh-token', expiresAt: now()->subHour(), invitedBy: $superAdmin->id);

        $this->actingAs($superAdmin)
            ->post(route('dashboard.admin-access.invite.resend', $invitation))
            ->assertRedirect();

        $invitation->refresh();

        $this->assertTrue($invitation->expires_at->equalTo(now()->addHours(24)));
    }

    public function test_old_link_becomes_invalid_after_resend(): void
    {
        Mail::fake();

        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('INV005');
        $invitation = $this->createInvitation($institution, 'stale-token', expiresAt: now()->subMinute(), invitedBy: $superAdmin->id);

        $this->actingAs($superAdmin)
            ->post(route('dashboard.admin-access.invite.resend', $invitation))
            ->assertRedirect();

        $this->get(route('institution-invite.accept', ['token' => 'stale-token']))
            ->assertNotFound();
    }

    public function test_accepted_invitation_cannot_be_resent(): void
    {
        Mail::fake();

        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('INV006');
        $invitation = $this->createInvitation($institution, 'accepted-token', acceptedAt: now()->subMinute(), invitedBy: $superAdmin->id);

        $response = $this->actingAs($superAdmin)
            ->from(route('dashboard.admin-access.index'))
            ->post(route('dashboard.admin-access.invite.resend', $invitation));

        $response->assertRedirect(route('dashboard.admin-access.index'));
        $response->assertSessionHasErrors('invitation');
        Mail::assertNothingSent();
    }

    public function test_non_superadmin_cannot_resend_invitation(): void
    {
        Mail::fake();

        $institution = $this->createInstitution('INV007');
        $invitation = $this->createInvitation($institution, 'forbidden-token', expiresAt: now()->subMinute());
        $admin = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('dashboard.admin-access.invite.resend', $invitation))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_resend_does_not_create_duplicate_user_or_institution_membership(): void
    {
        Mail::fake();

        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('INV008');
        $user = User::factory()->create([
            'email' => 'invite@example.test',
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

        $invitation = $this->createInvitation($institution, 'dup-token', expiresAt: now()->subMinute(), invitedBy: $superAdmin->id);

        $this->actingAs($superAdmin)
            ->post(route('dashboard.admin-access.invite.resend', $invitation))
            ->assertRedirect();

        $this->assertSame(1, User::query()->where('email', 'invite@example.test')->count());
        $this->assertSame(1, DB::table('institution_user')
            ->where('institution_id', $institution->id)
            ->where('user_id', $user->id)
            ->count());
    }

    public function test_resend_writes_audit_log_without_storing_plain_token(): void
    {
        Mail::fake();

        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('INV009');
        $invitation = $this->createInvitation($institution, 'audit-token', expiresAt: now()->subMinute(), invitedBy: $superAdmin->id);

        $this->actingAs($superAdmin)
            ->post(route('dashboard.admin-access.invite.resend', $invitation))
            ->assertRedirect();

        $auditLog = AuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame(AuditLog::ACTION_INSTITUTION_ADMIN_INVITATION_RESENT, $auditLog->action);
        $this->assertSame($superAdmin->id, $auditLog->user_id);
        $this->assertSame($institution->id, $auditLog->institution_id);
        $this->assertSame($invitation->id, $auditLog->subject_id);
        $this->assertSame('invite@example.test', $auditLog->new_values['email'] ?? null);
        $this->assertArrayNotHasKey('token', $auditLog->new_values ?? []);
        $this->assertArrayNotHasKey('plain_token', $auditLog->new_values ?? []);
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    private function createInstitution(string $code): Institution
    {
        return Institution::query()->create([
            'name' => 'Meghívó Intézmény '.$code,
            'institution_code' => $code,
            'type' => Institution::TYPE_SCHOOL,
            'active' => true,
        ]);
    }

    private function createInvitation(
        Institution $institution,
        string $plainToken,
        ?\Illuminate\Support\Carbon $expiresAt = null,
        ?\Illuminate\Support\Carbon $acceptedAt = null,
        ?int $invitedBy = null
    ): InstitutionAdminInvitation {
        return InstitutionAdminInvitation::query()->create([
            'institution_id' => $institution->id,
            'invited_by' => $invitedBy,
            'name' => 'Invite User',
            'email' => 'invite@example.test',
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt ?? now()->addDay(),
            'accepted_at' => $acceptedAt,
        ]);
    }
}
