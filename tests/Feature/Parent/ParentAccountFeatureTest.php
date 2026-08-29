<?php

namespace Tests\Feature\Parent;

use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ParentAccountFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_parent_account_page_shows_real_stats(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        $user->forceFill([
            'phone' => '+36 20 123 4567',
            'last_login_at' => '2026-07-31 08:12:00',
        ])->save();

        $guardian->update([
            'postal_code' => '7621',
            'city' => 'Pécs',
            'street_name' => 'Fő',
            'street_type' => 'utca',
            'house_number' => '10',
        ]);

        $firstChild = $this->createChild($institution->id, 'Első Gyermek');
        $secondChild = $this->createChild($institution->id, 'Második Gyermek');
        $firstChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $secondChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->get($this->parentUrl('/fiokom'));

        $response->assertOk();
        $response->assertSee('2 gyermek');
        $response->assertSee('100%');
        $response->assertSee('Hiányos');
        $response->assertSee('Ma, 08:12');
    }

    public function test_parent_can_update_personal_and_address_data_in_separate_forms(): void
    {
        [$user, , $guardian] = $this->createParentContext();

        $personalResponse = $this->actingAs($user)->put($this->parentUrl('/fiokom/szemelyes-adatok'), [
            'full_name' => 'Kovács Anna',
            'email' => ' Anna.Kovacs@example.com ',
            'phone' => '+36 30 123 4567',
        ]);

        $addressResponse = $this->actingAs($user)->put($this->parentUrl('/fiokom/lakcim'), [
            'postal_code' => '2092',
            'city' => 'Budakeszi',
            'street_name' => 'Fő',
            'street_type' => 'utca',
            'house_number' => '12',
            'floor' => '2',
            'door' => '5',
            'country' => 'Magyarország',
        ]);

        $personalResponse->assertRedirect(route('parent.account').'#personal-card');
        $addressResponse->assertRedirect(route('parent.account').'#address-card');

        $user->refresh();
        $guardian->refresh();

        $this->assertSame('Kovács Anna', $user->name);
        $this->assertSame('anna.kovacs@example.com', $user->email);
        $this->assertSame('+36 30 123 4567', $user->phone);
        $this->assertSame('Kovács', $guardian->last_name);
        $this->assertSame('Anna', $guardian->first_name);
        $this->assertSame('2092', $guardian->postal_code);
        $this->assertSame('Budakeszi', $guardian->city);
        $this->assertSame('Magyarország', $guardian->country);
    }

    public function test_parent_can_save_billing_profile_using_home_address(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        $guardian->update([
            'postal_code' => '7621',
            'city' => 'Pécs',
            'street_name' => 'Király',
            'street_type' => 'utca',
            'house_number' => '8',
        ]);

        $response = $this->actingAs($user)->put($this->parentUrl('/fiokom/szamlazasi-adatok'), [
            'billing_same_as_address' => '1',
            'billing_name' => 'Teszt Szülő',
            'billing_postal_code' => '',
            'billing_city' => '',
            'billing_address' => '',
            'billing_tax_number' => '',
        ]);

        $response->assertRedirect(route('parent.account').'#billing-card');

        $billingProfile = BillingProfile::where('guardian_id', $guardian->id)->latest()->first();

        $this->assertNotNull($billingProfile);
        $this->assertSame($institution->id, $billingProfile->institution_id);
        $this->assertSame('Teszt Szülő', $billingProfile->billing_name);
        $this->assertSame('7621', $billingProfile->postal_code);
        $this->assertSame('Pécs', $billingProfile->city);
        $this->assertSame('Király utca 8', $billingProfile->address);
        $this->assertNull($billingProfile->tax_number);
    }

    public function test_parent_account_rejects_taken_email_and_keeps_entered_values(): void
    {
        [$user, $institution] = $this->createParentContext();

        User::factory()->create([
            'name' => 'Másik Szülő',
            'email' => 'masik.szulo@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $response = $this->from($this->parentUrl('/fiokom'))
            ->actingAs($user)
            ->put($this->parentUrl('/fiokom/szemelyes-adatok'), [
                'full_name' => 'Teszt Szülő',
                'email' => 'masik.szulo@example.com',
                'phone' => '+36 20 555 1234',
            ]);

        $response->assertRedirect($this->parentUrl('/fiokom#personal-card'));
        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHasInput('phone', '+36 20 555 1234');
    }

    public function test_parent_can_change_password_only_with_valid_current_password(): void
    {
        [$user] = $this->createParentContext();

        $invalidResponse = $this->from($this->parentUrl('/fiokom'))
            ->actingAs($user)
            ->put($this->parentUrl('/fiokom/jelszo'), [
                'current_password' => 'hibas-jelszo',
                'password' => 'ujtitkosjelszo',
                'password_confirmation' => 'ujtitkosjelszo',
            ]);

        $invalidResponse->assertRedirect($this->parentUrl('/fiokom#security-card'));
        $invalidResponse->assertSessionHasErrors(['current_password']);

        $validResponse = $this->actingAs($user)->put($this->parentUrl('/fiokom/jelszo'), [
            'current_password' => 'password',
            'password' => 'ujtitkosjelszo',
            'password_confirmation' => 'ujtitkosjelszo',
        ]);

        $validResponse->assertRedirect(route('parent.account').'#security-card');
        $this->assertTrue(Hash::check('ujtitkosjelszo', $user->fresh()->password));
    }

    private function createParentContext(): array
    {
        $institution = Institution::create([
            'name' => 'Szülői intézmény',
            'institution_code' => 'PAR100',
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Teszt Szülő',
            'email' => 'szulo@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szülő',
            'email' => $user->email,
            'source_type' => 'manual',
            'active' => true,
        ]);

        return [$user, $institution, $guardian];
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
}
