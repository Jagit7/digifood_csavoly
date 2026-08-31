<?php

namespace Tests\Feature\Parent;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionMealSetting;
use App\Models\Menu;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Support\AdminInstitutionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParentMultiInstitutionAccessFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_sees_own_child_within_the_same_institution(): void
    {
        [$user, $guardian, $institution] = $this->createParentContext('PTI001', 'Csavoly Intezmeny');

        $child = $this->attachChildToGuardian($guardian, $institution, 'Sajat Gyermek');

        $response = $this->getParentAsHost($user, 'app.example.test', route('parent.dashboard'));

        $response->assertOk();
        $response->assertViewHas('childrenCount', 1);
        $response->assertViewHas('childCards', function (Collection $childCards) use ($child) {
            return $childCards->pluck('model.id')->contains($child->id);
        });
    }

    public function test_parent_also_sees_own_child_from_another_institution_in_same_deployment(): void
    {
        [$user, $primaryGuardian, $primaryInstitution] = $this->createParentContext('PTI002', 'Csavoly Intezmeny');
        [, $secondaryGuardian, $secondaryInstitution] = $this->addGuardianForUserInInstitution($user, 'PTI003', 'Bacsalmas Intezmeny');

        $childA = $this->attachChildToGuardian($primaryGuardian, $primaryInstitution, 'Csavoly Gyermek');
        $childB = $this->attachChildToGuardian($secondaryGuardian, $secondaryInstitution, 'Bacsalmas Gyermek');

        $response = $this->getParentAsHost($user, 'app.example.test', route('parent.children.index'));

        $response->assertOk();
        $response->assertViewHas('children', fn ($children) => $children->total() === 2);
        $response->assertViewHas('childCards', function (Collection $childCards) use ($childA, $childB) {
            $ids = $childCards->pluck('child.id')->all();
            sort($ids);

            return $ids === [$childA->id, $childB->id];
        });
    }

    public function test_parent_sees_monthly_settlements_for_children_across_multiple_institutions(): void
    {
        [$user, $primaryGuardian, $primaryInstitution] = $this->createParentContext('PTI004', 'Csavoly Intezmeny');
        [, $secondaryGuardian, $secondaryInstitution] = $this->addGuardianForUserInInstitution($user, 'PTI005', 'Bacsalmas Intezmeny');

        $childA = $this->attachChildToGuardian($primaryGuardian, $primaryInstitution, 'Csavoly Elszamolas');
        $childB = $this->attachChildToGuardian($secondaryGuardian, $secondaryInstitution, 'Bacsalmas Elszamolas');

        $this->createMonthlyStatement($childA, $primaryInstitution, 2026, 8);
        $this->createMonthlyStatement($childB, $secondaryInstitution, 2026, 8);

        $response = $this->getParentAsHost(
            $user,
            'app.example.test',
            route('parent.monthly-settlements.index', ['month' => '2026-08'])
        );

        $response->assertOk();
        $response->assertViewHas('children_count', 2);
        $response->assertSee($childA->name);
        $response->assertSee($childB->name);
    }

    public function test_parent_cannot_see_other_guardians_child_in_same_institution(): void
    {
        [$user, $guardian, $institution] = $this->createParentContext('PTI006', 'Csavoly Intezmeny');

        $this->attachChildToGuardian($guardian, $institution, 'Sajat Gyermek');

        $foreignGuardian = Guardian::query()->create([
            'institution_id' => $institution->id,
            'last_name' => 'Masik',
            'first_name' => 'Szulo',
            'email' => 'foreign-same@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);
        $foreignChild = $this->attachChildToGuardian($foreignGuardian, $institution, 'Tiltott Same Institution');

        $response = $this->getParentAsHost($user, 'app.example.test', route('parent.children.show', $foreignChild->id));

        $response->assertNotFound();
    }

    public function test_parent_cannot_see_other_guardians_child_in_another_institution(): void
    {
        [$user, $guardian, $institution] = $this->createParentContext('PTI007', 'Csavoly Intezmeny');
        $this->attachChildToGuardian($guardian, $institution, 'Sajat Gyermek');

        $otherInstitution = $this->createInstitution('PTI008', 'Masik Intezmeny');
        $foreignGuardian = Guardian::query()->create([
            'institution_id' => $otherInstitution->id,
            'last_name' => 'Idegen',
            'first_name' => 'Szulo',
            'email' => 'foreign-cross@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);
        $foreignChild = $this->attachChildToGuardian($foreignGuardian, $otherInstitution, 'Tiltott Cross Institution');

        $response = $this->getParentAsHost($user, 'app.example.test', route('parent.children.show', $foreignChild->id));

        $response->assertNotFound();
    }

    public function test_admin_available_institutions_contains_all_permitted_institutions(): void
    {
        $institutionA = $this->createInstitution('PTI009', 'Csavoly Admin');
        $institutionB = $this->createInstitution('PTI010', 'Bacsalmas Admin');

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institutionA->id,
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

        $this->bindRequestHost('app.example.test');

        $availableInstitutions = app(AdminInstitutionContext::class)->availableInstitutions($user);

        $actualIds = $availableInstitutions->pluck('id')->all();
        $expectedIds = [$institutionA->id, $institutionB->id];

        sort($actualIds);
        sort($expectedIds);

        $this->assertSame($expectedIds, $actualIds);
    }

    public function test_parent_stale_institution_session_does_not_hide_children_or_menus(): void
    {
        Storage::fake('public');

        [$user, $primaryGuardian, $primaryInstitution] = $this->createParentContext('PTI011', 'Csavoly Intezmeny');
        [, $secondaryGuardian, $secondaryInstitution] = $this->addGuardianForUserInInstitution($user, 'PTI012', 'Bacsalmas Intezmeny');

        $childA = $this->attachChildToGuardian($primaryGuardian, $primaryInstitution, 'Session Csavoly');
        $childB = $this->attachChildToGuardian($secondaryGuardian, $secondaryInstitution, 'Session Bacsalmas');

        $this->createMenu($primaryInstitution, $user, 'Csavoly Menu', 'menus/csavoly.pdf');
        $this->createMenu($secondaryInstitution, $user, 'Bacsalmas Menu', 'menus/bacsalmas.pdf');

        $response = $this->withSession([
            'dashboard.selected_institution_id' => $secondaryInstitution->id,
            'dashboard.selected_institution_user_id' => $user->id,
        ])->getParentAsHost($user, 'app.example.test', route('parent.children.index'));

        $response->assertOk();
        $response->assertViewHas('children', fn ($children) => $children->total() === 2);
        $response->assertViewHas('childCards', function (Collection $childCards) use ($childA, $childB) {
            $ids = $childCards->pluck('child.id')->all();
            sort($ids);

            return $ids === [$childA->id, $childB->id];
        });

        $menuResponse = $this->withSession([
            'dashboard.selected_institution_id' => $secondaryInstitution->id,
            'dashboard.selected_institution_user_id' => $user->id,
        ])->getParentAsHost($user, 'app.example.test', route('parent.menus.index'));

        $menuResponse->assertOk();
        $menuResponse->assertSee('Csavoly Menu');
        $menuResponse->assertSee('Bacsalmas Menu');
    }

    private function getParentAsHost(User $user, string $host, string $url)
    {
        config(['app.url' => 'http://'.$host]);

        return $this->withServerVariables([
            'HTTP_HOST' => $host,
        ])->actingAs($user)->get($url);
    }

    private function createParentContext(string $code, string $institutionName): array
    {
        $institution = $this->createInstitution($code, $institutionName);
        $user = User::factory()->create([
            'name' => 'Teszt Szulo',
            'email' => strtolower($code).'@example.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);
        $guardian = Guardian::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szulo',
            'email' => $user->email,
            'source_type' => 'manual',
            'active' => true,
        ]);

        return [$user, $guardian, $institution];
    }

    private function addGuardianForUserInInstitution(User $user, string $code, string $institutionName): array
    {
        $institution = $this->createInstitution($code, $institutionName);
        $guardian = Guardian::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'last_name' => 'Masodik',
            'first_name' => 'Szulo',
            'email' => 'second-'.$code.'-'.$user->id.'@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        return [$user, $guardian, $institution];
    }

    private function createInstitution(string $code, string $name): Institution
    {
        $institution = Institution::query()->create([
            'name' => $name,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        InstitutionMealSetting::query()->create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 9,
            'cancellation_minute' => 0,
        ]);

        return $institution;
    }

    private function attachChildToGuardian(Guardian $guardian, Institution $institution, string $name): Child
    {
        $discount = DiscountType::query()->firstOrCreate(
            [
                'institution_id' => $institution->id,
                'name' => 'Kedvezmeny nelkul',
                'percentage' => 0,
            ],
            [
                'active' => true,
                'sort_order' => 1,
            ]
        );

        $child = Child::query()->create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institution->id.$guardian->id), 0, 10),
            'group_name' => '1.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $child->guardians()->attach($guardian->id, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $child;
    }

    private function createMonthlyStatement(Child $child, Institution $institution, int $year, int $month): MonthlyPaymentStatement
    {
        return MonthlyPaymentStatement::query()->create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => $year,
            'month' => $month,
            'meal_amount' => 4000,
            'invoiceable_amount' => 4000,
            'previous_balance' => 0,
            'total_payable' => 4000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    private function createMenu(Institution $institution, User $user, string $title, string $filePath): Menu
    {
        Storage::disk('public')->put($filePath, 'menu-content');

        $menu = new Menu();
        $menu->forceFill([
            'institution_id' => $institution->id,
            'type' => 'weekly',
            'title' => $title,
            'week_start' => '2026-08-24',
            'week_end' => '2026-08-28',
            'file_path' => $filePath,
            'file_name' => basename($filePath),
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'active' => true,
            'published_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $menu->save();

        return $menu->fresh();
    }

    private function bindRequestHost(string $host): void
    {
        app()->forgetInstance('request');

        $request = Request::create('http://'.$host.'/dashboard/institution');
        $request->setLaravelSession(app('session')->driver());

        app()->instance('request', $request);
    }
}
