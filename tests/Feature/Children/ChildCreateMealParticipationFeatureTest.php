<?php

namespace Tests\Feature\Children;

use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildController;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionMealType;
use App\Models\MealType;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\Meals\StudentMealSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ChildCreateMealParticipationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();

        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('IF', fn ($condition, $yes, $no) => $condition ? $yes : $no, 3);
        }
    }

    public function test_new_child_can_be_saved_as_non_eater(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CCMP001');
        $discount = $this->createDiscountType($institution->id);

        $this->actingAs($user);

        $response = $this->controller()->store($this->makeRequest($user, [
            'name' => 'Nem Étkező Nóra',
            'educational_identifier' => 'CCMP001-CHILD',
            'discount_type_id' => $discount->id,
            'discount_valid_from' => '2026-08-24',
            'is_eater' => '0',
            'guardian_mode' => 'none',
            'active' => '1',
        ]));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.children.index'), $response->getTargetUrl());

        $child = Child::query()
            ->where('institution_id', $institution->id)
            ->where('educational_identifier', 'CCMP001-CHILD')
            ->firstOrFail();

        $this->assertDatabaseCount('student_meal_settings', 0);
        $this->assertSame('Nem Étkező Nóra', $child->name);
    }

    public function test_new_child_can_be_saved_as_eater_with_start_date(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CCMP002');
        $discount = $this->createDiscountType($institution->id);
        $context = $this->createDefaultMealPackageContext($institution->id, $user->id);

        $this->actingAs($user);

        $response = $this->controller()->store($this->makeRequest($user, [
            'name' => 'Étkező Emma',
            'educational_identifier' => 'CCMP002-CHILD',
            'discount_type_id' => $discount->id,
            'discount_valid_from' => '2026-08-24',
            'is_eater' => '1',
            'meal_valid_from' => '2026-08-25',
            'guardian_mode' => 'none',
            'active' => '1',
        ]));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.children.index'), $response->getTargetUrl());

        $child = Child::query()
            ->where('institution_id', $institution->id)
            ->where('educational_identifier', 'CCMP002-CHILD')
            ->firstOrFail();

        $setting = StudentMealSetting::query()->firstOrFail();

        $this->assertSame($child->id, $setting->student_id);
        $this->assertSame($child->getMorphClass(), $setting->eater_type);
        $this->assertSame($child->id, $setting->eater_id);
        $this->assertSame($institution->id, $setting->institution_id);
        $this->assertSame($context['package']->id, $setting->institution_meal_package_id);
        $this->assertSame(StudentMealSetting::MODE_PACKAGE, $setting->mode);
        $this->assertSame('2026-08-25', $setting->valid_from->toDateString());
        $this->assertNull($setting->valid_to);

        foreach ($context['institution_meal_types'] as $institutionMealType) {
            $this->assertDatabaseHas('student_meal_setting_items', [
                'student_meal_setting_id' => $setting->id,
                'institution_meal_type_id' => $institutionMealType->id,
            ]);
        }
    }

    public function test_eater_child_requires_start_date(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CCMP003');
        $discount = $this->createDiscountType($institution->id);
        $this->createDefaultMealPackageContext($institution->id, $user->id);

        $this->actingAs($user);

        try {
            $this->controller()->store($this->makeRequest($user, [
                'name' => 'Dátum Nélküli Domi',
                'educational_identifier' => 'CCMP003-CHILD',
                'discount_type_id' => $discount->id,
                'discount_valid_from' => '2026-08-24',
                'is_eater' => '1',
                'guardian_mode' => 'none',
                'active' => '1',
            ]));

            $this->fail('Validációs hibát vártunk.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('meal_valid_from', $exception->errors());
        }

        $this->assertDatabaseMissing('children', [
            'institution_id' => $institution->id,
            'educational_identifier' => 'CCMP003-CHILD',
        ]);
    }

    public function test_eater_child_cannot_be_saved_without_active_default_package(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CCMP004');
        $discount = $this->createDiscountType($institution->id);

        $this->actingAs($user);

        try {
            $this->controller()->store($this->makeRequest($user, [
                'name' => 'Csomag Nélküli Cili',
                'educational_identifier' => 'CCMP004-CHILD',
                'discount_type_id' => $discount->id,
                'discount_valid_from' => '2026-08-24',
                'is_eater' => '1',
                'meal_valid_from' => '2026-08-25',
                'guardian_mode' => 'none',
                'active' => '1',
            ]));

            $this->fail('Validációs hibát vártunk.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_eater', $exception->errors());
        }

        $this->assertDatabaseMissing('children', [
            'institution_id' => $institution->id,
            'educational_identifier' => 'CCMP004-CHILD',
        ]);
        $this->assertDatabaseCount('student_meal_settings', 0);
    }

    public function test_child_creation_is_rolled_back_when_meal_setting_save_fails(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CCMP005');
        $discount = $this->createDiscountType($institution->id);
        $context = $this->createDefaultMealPackageContext($institution->id, $user->id);

        $this->mock(StudentMealSettingService::class, function (MockInterface $mock) use ($institution, $context) {
            $mock->shouldReceive('defaultPackage')
                ->once()
                ->withArgs(fn (Institution $resolvedInstitution) => $resolvedInstitution->is($institution))
                ->andReturn($context['package']);

            $mock->shouldReceive('createSetting')
                ->once()
                ->andThrow(new RuntimeException('Szimulált étkezési mentési hiba.'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user);

            $this->controller()->store($this->makeRequest($user, [
                'name' => 'Rollback Robi',
                'educational_identifier' => 'CCMP005-CHILD',
                'discount_type_id' => $discount->id,
                'discount_valid_from' => '2026-08-24',
                'is_eater' => '1',
                'meal_valid_from' => '2026-08-25',
                'guardian_mode' => 'none',
                'active' => '1',
            ]));

            $this->fail('Étkezési mentési hibát vártunk.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Szimulált étkezési mentési hiba.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('children', [
            'institution_id' => $institution->id,
            'educational_identifier' => 'CCMP005-CHILD',
        ]);
        $this->assertDatabaseCount('student_meal_settings', 0);
    }

    public function test_other_institution_package_cannot_be_used_during_child_creation(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CCMP006');
        [$otherInstitution, $otherUser] = $this->seedInstitutionAdmin('CCMP006X');
        $discount = $this->createDiscountType($institution->id);
        $this->createDefaultMealPackageContext($institution->id, $user->id);
        $foreignContext = $this->createDefaultMealPackageContext($otherInstitution->id, $otherUser->id);

        $this->actingAs($user);

        try {
            $this->controller()->store($this->makeRequest($user, [
                'name' => 'Tiltott Csomag Teri',
                'educational_identifier' => 'CCMP006-CHILD',
                'discount_type_id' => $discount->id,
                'discount_valid_from' => '2026-08-24',
                'is_eater' => '1',
                'meal_valid_from' => '2026-08-25',
                'institution_meal_package_id' => $foreignContext['package']->id,
                'guardian_mode' => 'none',
                'active' => '1',
            ]));

            $this->fail('Validációs hibát vártunk.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('institution_meal_package_id', $exception->errors());
        }

        $this->assertDatabaseMissing('children', [
            'institution_id' => $institution->id,
            'educational_identifier' => 'CCMP006-CHILD',
        ]);
    }

    private function controller(): ChildController
    {
        return app(ChildController::class);
    }

    private function makeRequest(User $user, array $data): Request
    {
        $request = Request::create(route('dashboard.institution.children.store'), 'POST', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function seedInstitutionAdmin(string $code): array
    {
        $institution = Institution::query()->create([
            'name' => 'Gyermek Intézmény '.$code,
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
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institution, $user];
    }

    private function createDiscountType(int $institutionId): DiscountType
    {
        return DiscountType::query()->create([
            'institution_id' => $institutionId,
            'name' => 'Teljes ár',
            'percentage' => 0,
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createDefaultMealPackageContext(int $institutionId, int $createdBy): array
    {
        $breakfastMealType = MealType::query()->create([
            'name' => 'Reggeli '.$institutionId,
            'code' => 'reggeli-'.$institutionId,
            'default_order' => 1,
            'is_active' => true,
        ]);

        $lunchMealType = MealType::query()->create([
            'name' => 'Ebéd '.$institutionId,
            'code' => 'ebed-'.$institutionId,
            'default_order' => 2,
            'is_active' => true,
        ]);

        $breakfast = InstitutionMealType::query()->create([
            'institution_id' => $institutionId,
            'meal_type_id' => $breakfastMealType->id,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $lunch = InstitutionMealType::query()->create([
            'institution_id' => $institutionId,
            'meal_type_id' => $lunchMealType->id,
            'is_active' => true,
            'display_order' => 2,
        ]);

        $package = InstitutionMealPackage::query()->create([
            'institution_id' => $institutionId,
            'name' => 'Alap csomag '.$institutionId,
            'is_active' => true,
            'is_default' => true,
            'display_order' => 1,
            'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
            'created_by' => $createdBy,
        ]);

        InstitutionMealPackageItem::query()->create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $breakfast->id,
            'display_order' => 1,
        ]);

        InstitutionMealPackageItem::query()->create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $lunch->id,
            'display_order' => 2,
        ]);

        return [
            'package' => $package,
            'institution_meal_types' => [$breakfast, $lunch],
        ];
    }
}
