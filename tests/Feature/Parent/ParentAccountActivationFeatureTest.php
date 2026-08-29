<?php

namespace Tests\Feature\Parent;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\ParentAccountActivationToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ParentAccountActivationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_unknown_guardian_email_cannot_request_activation(): void
    {
        $response = $this->from($this->parentUrl('/aktivalas'))->post($this->parentUrl('/aktivalas'), [
            'email' => 'ismeretlen@example.com',
        ]);

        $response->assertRedirect($this->parentUrl('/aktivalas'));
        $response->assertSessionHasErrors([
            'email' => 'A megadott e-mail címhez nem található aktiválható szülői hozzáférés.',
        ]);
    }

    public function test_inactive_guardian_cannot_request_activation(): void
    {
        [$institution] = $this->createInstitutionContext();

        Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Inaktiv',
            'first_name' => 'Szulo',
            'email' => 'inaktiv@example.com',
            'source_type' => 'manual',
            'active' => false,
        ]);

        $response = $this->from($this->parentUrl('/aktivalas'))->post($this->parentUrl('/aktivalas'), [
            'email' => 'inaktiv@example.com',
        ]);

        $response->assertRedirect($this->parentUrl('/aktivalas'));
        $response->assertSessionHasErrors([
            'email' => 'A megadott e-mail címhez nem található aktiválható szülői hozzáférés.',
        ]);
    }

    public function test_local_activation_request_creates_token_and_shows_debug_link_without_sending_email(): void
    {
        Mail::fake();
        config(['mail.parent_activation_debug_link_for_tests' => true]);

        [$institution] = $this->createInstitutionContext();
        Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szulo',
            'email' => 'szulo@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $response = $this->from($this->parentUrl('/aktivalas'))->post($this->parentUrl('/aktivalas'), [
            'email' => '  Szulo@example.com ',
        ]);

        $response->assertRedirect($this->parentUrl('/aktivalas'));
        $response->assertSessionHas('activation_debug_url');
        $this->assertDatabaseCount('parent_account_activation_tokens', 1);
        $token = ParentAccountActivationToken::query()->firstOrFail();
        $this->assertSame('szulo@example.com', $token->email);
        Mail::assertNothingSent();
    }

    public function test_production_request_does_not_show_debug_link(): void
    {
        [$institution] = $this->createInstitutionContext();
        Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szulo',
            'email' => 'szulo@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $response = $this->from($this->parentUrl('/aktivalas'))->post($this->parentUrl('/aktivalas'), [
            'email' => 'szulo@example.com',
        ]);

        $response->assertRedirect($this->parentUrl('/aktivalas'));
        $response->assertSessionMissing('activation_debug_url');
    }

    public function test_existing_active_parent_user_is_not_duplicated(): void
    {
        [$institution] = $this->createInstitutionContext();
        $user = User::factory()->create([
            'name' => 'Meglevo Szulo',
            'email' => 'szulo@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
            'accepted_invitation_at' => now(),
            'email_verified_at' => now(),
        ]);

        Guardian::create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'last_name' => 'Meglevo',
            'first_name' => 'Szulo',
            'email' => 'szulo@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $response = $this->from($this->parentUrl('/aktivalas'))->post($this->parentUrl('/aktivalas'), [
            'email' => 'szulo@example.com',
        ]);

        $response->assertRedirect($this->parentUrl('/aktivalas'));
        $response->assertSessionHas('existing_account_message', 'Ehhez az e-mail címhez már tartozik szülői fiók. Kérjük, jelentkezzen be.');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('parent_account_activation_tokens', 0);
    }

    public function test_non_parent_user_email_cannot_be_activated(): void
    {
        [$institution] = $this->createInstitutionContext();

        User::factory()->create([
            'email' => 'titkar@example.com',
            'role' => User::ROLE_INSTITUTION_SECRETARY,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Titkar',
            'first_name' => 'Email',
            'email' => 'titkar@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $response = $this->from($this->parentUrl('/aktivalas'))->post($this->parentUrl('/aktivalas'), [
            'email' => 'titkar@example.com',
        ]);

        $response->assertRedirect($this->parentUrl('/aktivalas'));
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', [
            'email' => 'titkar@example.com',
            'role' => User::ROLE_PARENT,
        ]);
    }

    public function test_same_email_multiple_guardians_activate_into_single_user_and_link_all_assignable_records(): void
    {
        config(['mail.parent_activation_debug_link_for_tests' => true]);
        [$institution] = $this->createInstitutionContext();

        $firstGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Teszt',
            'first_name' => 'Anya',
            'email' => 'anya@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);
        $secondGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Teszt',
            'first_name' => 'Anya',
            'email' => 'anya@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $firstChild = $this->createChild($institution->id, 'Péter');
        $secondChild = $this->createChild($institution->id, 'Anna');
        $firstChild->guardians()->attach($firstGuardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $secondChild->guardians()->attach($secondGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $activationResponse = $this->post($this->parentUrl('/aktivalas/'.$this->requestActivationAndExtractToken('anya@example.com')), [
            'password' => 'titkosjelszo',
            'password_confirmation' => 'titkosjelszo',
        ]);

        $activationResponse->assertRedirect(route('parent.dashboard'));

        $user = User::query()->where('email', 'anya@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_PARENT, $user->role);
        $this->assertTrue(Hash::check('titkosjelszo', $user->password));
        $this->assertDatabaseHas('guardians', ['id' => $firstGuardian->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('guardians', ['id' => $secondGuardian->id, 'user_id' => $user->id]);
        $this->assertSame(2, $user->fresh()->guardians()->where('active', true)->count());
        $this->assertCount(2, Child::query()
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', [$firstGuardian->id, $secondGuardian->id]))
            ->get());
    }

    public function test_same_child_can_be_activated_for_two_separate_guardian_users(): void
    {
        config(['mail.parent_activation_debug_link_for_tests' => true]);
        [$institution] = $this->createInstitutionContext();

        $motherGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Teszt',
            'first_name' => 'Anya',
            'email' => 'anya@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);
        $fatherGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Teszt',
            'first_name' => 'Apa',
            'email' => 'apa@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $child = $this->createChild($institution->id, 'Közös Gyermek');
        $child->guardians()->attach($motherGuardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $child->guardians()->attach($fatherGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $motherToken = $this->requestActivationAndExtractToken('anya@example.com');
        $fatherToken = $this->requestActivationAndExtractToken('apa@example.com');

        $this->post($this->parentUrl('/aktivalas/'.$motherToken), [
            'password' => 'titkosjelszo',
            'password_confirmation' => 'titkosjelszo',
        ])->assertRedirect(route('parent.dashboard'));

        auth()->logout();

        $this->post($this->parentUrl('/aktivalas/'.$fatherToken), [
            'password' => 'masiktitok',
            'password_confirmation' => 'masiktitok',
        ])->assertRedirect(route('parent.dashboard'));

        $motherUser = User::query()->where('email', 'anya@example.com')->firstOrFail();
        $fatherUser = User::query()->where('email', 'apa@example.com')->firstOrFail();

        $this->assertNotSame($motherUser->id, $fatherUser->id);
        $this->assertDatabaseHas('guardians', ['id' => $motherGuardian->id, 'user_id' => $motherUser->id]);
        $this->assertDatabaseHas('guardians', ['id' => $fatherGuardian->id, 'user_id' => $fatherUser->id]);
    }

    public function test_expired_used_and_invalid_tokens_are_rejected(): void
    {
        config(['mail.parent_activation_debug_link_for_tests' => true]);
        [$institution] = $this->createInstitutionContext();

        Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szulo',
            'email' => 'szulo@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $expiredToken = $this->requestActivationAndExtractToken('szulo@example.com');
        ParentAccountActivationToken::query()->update(['expires_at' => now()->subMinute()]);

        $expiredResponse = $this->get($this->parentUrl('/aktivalas/'.$expiredToken));
        $expiredResponse->assertRedirect(route('parent.activation.create'));

        $freshToken = $this->requestActivationAndExtractToken('szulo@example.com');
        $this->post($this->parentUrl('/aktivalas/'.$freshToken), [
            'password' => 'titkosjelszo',
            'password_confirmation' => 'titkosjelszo',
        ])->assertRedirect(route('parent.dashboard'));

        auth()->logout();

        $usedResponse = $this->get($this->parentUrl('/aktivalas/'.$freshToken));
        $usedResponse->assertRedirect(route('parent.activation.create'));

        $invalidResponse = $this->get($this->parentUrl('/aktivalas/'.str_repeat('a', 64)));
        $invalidResponse->assertRedirect(route('parent.activation.create'));
    }

    public function test_preexisting_incomplete_parent_user_is_completed_instead_of_creating_duplicate(): void
    {
        config(['mail.parent_activation_debug_link_for_tests' => true]);
        [$institution] = $this->createInstitutionContext();

        $user = User::factory()->create([
            'name' => 'Felkesz Szulo',
            'email' => 'felkesz@example.com',
            'password' => bcrypt('ideiglenes'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => false,
            'accepted_invitation_at' => null,
            'email_verified_at' => null,
        ]);

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Felkesz',
            'first_name' => 'Szulo',
            'email' => 'felkesz@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $token = $this->requestActivationAndExtractToken('felkesz@example.com');
        $response = $this->post($this->parentUrl('/aktivalas/'.$token), [
            'password' => 'véglegesjelszo',
            'password_confirmation' => 'véglegesjelszo',
        ]);

        $response->assertRedirect(route('parent.dashboard'));

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->accepted_invitation_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('véglegesjelszo', $user->password));
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'user_id' => $user->id]);
        $this->assertDatabaseCount('users', 1);
    }

    private function requestActivationAndExtractToken(string $email): string
    {
        $response = $this->from($this->parentUrl('/aktivalas'))->post($this->parentUrl('/aktivalas'), [
            'email' => $email,
        ]);

        $response->assertSessionHas('activation_debug_url');
        preg_match('/\/szulo\/aktivalas\/([a-f0-9]{64})/', (string) session('activation_debug_url'), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        return $matches[1];
    }

    private function createInstitutionContext(): array
    {
        $institution = Institution::create([
            'name' => 'Szülői intézmény',
            'institution_code' => 'PAR100',
            'type' => 'iskola',
            'active' => true,
        ]);

        return [$institution];
    }

    private function createChild(int $institutionId, string $name): Child
    {
        $discount = DiscountType::firstOrCreate(
            [
                'institution_id' => $institutionId,
                'name' => 'Kedvezmény nélkül',
                'percentage' => 0,
            ],
            [
                'active' => true,
                'sort_order' => 1,
            ]
        );

        return Child::create([
            'institution_id' => $institutionId,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId), 0, 10),
            'group_name' => '1.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function parentUrl(string $path): string
    {
        $appUrl = config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'http';
        $host = parse_url($appUrl, PHP_URL_HOST) ?? 'localhost';
        $port = parse_url($appUrl, PHP_URL_PORT);
        $authority = $port ? $host.':'.$port : $host;

        return $scheme.'://'.$authority.'/szulo'.$path;
    }
}
