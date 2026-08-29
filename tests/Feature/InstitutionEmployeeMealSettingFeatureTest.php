<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildMealSettingController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionEmployeeMealSettingController;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InstitutionEmployeeMealSettingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_meal_setting_dual_write_fields_are_filled(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMS001');
        $child = $this->createChild($institution->id, 'Teszt Gyerek');
        $context = $this->createMealContext($institution->id, $user->id);

        $this->actingAs($user);

        $response = $this->childController()->store(
            $this->makeRequest($user, '/dashboard/institution-admin/children/'.$child->id.'/meal-settings', 'POST', [
                'mode' => StudentMealSetting::MODE_PACKAGE,
                'valid_from' => '2026-08-11',
                'institution_meal_package_id' => $context['package']->id,
            ]),
            $child
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $setting = StudentMealSetting::query()->firstOrFail();
        $this->assertSame($child->id, $setting->student_id);
        $this->assertSame('child', $setting->eater_type);
        $this->assertSame($child->id, $setting->eater_id);
    }

    public function test_employee_meal_setting_can_be_created_with_null_student_id(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMS002');
        $employee = $this->createEmployee($institution->id, 'Teszt Dolgozo');
        $context = $this->createMealContext($institution->id, $user->id);

        $this->actingAs($user);

        $response = $this->employeeController()->store(
            $this->makeRequest($user, '/dashboard/institution-admin/employees/'.$employee->id.'/meal-settings', 'POST', [
                'mode' => StudentMealSetting::MODE_CUSTOM,
                'valid_from' => '2026-08-11',
                'institution_meal_type_ids' => [$context['institution_meal_type']->id],
                'note' => 'Dolgozoi egyedi beallitas',
            ]),
            $employee
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $setting = StudentMealSetting::query()->firstOrFail();
        $this->assertNull($setting->student_id);
        $this->assertSame('institution_employee', $setting->eater_type);
        $this->assertSame($employee->id, $setting->eater_id);
        $this->assertSame(StudentMealSetting::MODE_CUSTOM, $setting->mode);
        $this->assertSame([$context['institution_meal_type']->id], $setting->mealTypes->pluck('id')->all());
    }

    public function test_employee_can_use_same_package_records_as_child(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMS003');
        $employee = $this->createEmployee($institution->id, 'Csomagos Dolgozo');
        $context = $this->createMealContext($institution->id, $user->id);

        $this->actingAs($user);

        $this->employeeController()->store(
            $this->makeRequest($user, '/dashboard/institution-admin/employees/'.$employee->id.'/meal-settings', 'POST', [
                'mode' => StudentMealSetting::MODE_PACKAGE,
                'valid_from' => '2026-08-11',
                'institution_meal_package_id' => $context['package']->id,
            ]),
            $employee
        );

        $setting = StudentMealSetting::query()->firstOrFail();
        $this->assertSame($context['package']->id, $setting->institution_meal_package_id);
    }

    public function test_other_institution_employee_meal_setting_is_forbidden(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMS004');
        [$otherInstitution, $otherUser] = $this->seedInstitutionAdmin('EMS004X');
        $employee = $this->createEmployee($institution->id, 'Tiltott Dolgozo');
        $context = $this->createMealContext($otherInstitution->id, $otherUser->id);

        $this->actingAs($otherUser);

        try {
            $this->employeeController()->store(
                $this->makeRequest($otherUser, '/dashboard/institution-admin/employees/'.$employee->id.'/meal-settings', 'POST', [
                    'mode' => StudentMealSetting::MODE_PACKAGE,
                    'valid_from' => '2026-08-11',
                    'institution_meal_package_id' => $context['package']->id,
                ]),
                $employee
            );

            $this->fail('403-as kivetelt vartunk.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_overlapping_employee_meal_setting_cannot_be_created(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMS005');
        $employee = $this->createEmployee($institution->id, 'Atfedo Dolgozo');
        $context = $this->createMealContext($institution->id, $user->id);

        StudentMealSetting::create([
            'institution_id' => $institution->id,
            'student_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employee->id,
            'institution_meal_package_id' => $context['package']->id,
            'mode' => StudentMealSetting::MODE_PACKAGE,
            'valid_from' => '2026-08-01',
            'valid_to' => '2026-08-10',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        try {
            $this->employeeController()->store(
                $this->makeRequest($user, '/dashboard/institution-admin/employees/'.$employee->id.'/meal-settings', 'POST', [
                    'mode' => StudentMealSetting::MODE_CUSTOM,
                    'valid_from' => '2026-08-05',
                    'institution_meal_type_ids' => [$context['institution_meal_type']->id],
                ]),
                $employee
            );

            $this->fail('Atfedo idoszakra validacios hibat vartunk.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('valid_from', $exception->errors());
        }
    }

    public function test_non_overlapping_employee_periods_can_be_created(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMS006');
        $employee = $this->createEmployee($institution->id, 'Nem Atfedo Dolgozo');
        $context = $this->createMealContext($institution->id, $user->id);

        StudentMealSetting::create([
            'institution_id' => $institution->id,
            'student_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employee->id,
            'institution_meal_package_id' => $context['package']->id,
            'mode' => StudentMealSetting::MODE_PACKAGE,
            'valid_from' => '2026-08-01',
            'valid_to' => null,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $response = $this->employeeController()->store(
            $this->makeRequest($user, '/dashboard/institution-admin/employees/'.$employee->id.'/meal-settings', 'POST', [
                'mode' => StudentMealSetting::MODE_CUSTOM,
                'valid_from' => '2026-09-01',
                'institution_meal_type_ids' => [$context['institution_meal_type']->id],
            ]),
            $employee
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertCount(2, StudentMealSetting::query()->get());

        $first = StudentMealSetting::query()->orderBy('valid_from')->firstOrFail();
        $second = StudentMealSetting::query()->orderByDesc('valid_from')->firstOrFail();

        $this->assertSame('2026-08-31', $first->valid_to?->toDateString());
        $this->assertSame('2026-09-01', $second->valid_from->toDateString());
    }

    public function test_child_meal_relationship_can_be_closed_with_audit_fields_and_reopened(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMS007');
        $child = $this->createChild($institution->id, 'Lezarhato Gyerek');
        $this->createMealContext($institution->id, $user->id);

        $setting = StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'eater_type' => 'child',
            'eater_id' => $child->id,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-09-01',
            'valid_to' => null,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $closeResponse = $this->childController()->close(
            $this->makeRequest($user, '/dashboard/institution-admin/children/'.$child->id.'/meal-settings/'.$setting->id.'/close', 'POST', [
                'last_meal_day' => '2026-09-18',
                'closure_reason' => StudentMealSetting::CLOSURE_REASON_TRANSFER,
            ]),
            $child,
            $setting
        );

        $this->assertInstanceOf(RedirectResponse::class, $closeResponse);

        $setting->refresh();
        $this->assertSame('2026-09-18', $setting->valid_to?->toDateString());
        $this->assertSame(StudentMealSetting::CLOSURE_REASON_TRANSFER, $setting->closure_reason);
        $this->assertSame($user->id, $setting->closed_by);
        $this->assertNotNull($setting->closed_at);

        $reopenResponse = $this->childController()->reopen($child, $setting);

        $this->assertInstanceOf(RedirectResponse::class, $reopenResponse);

        $setting->refresh();
        $this->assertNull($setting->valid_to);
        $this->assertNull($setting->closure_reason);
        $this->assertNull($setting->closure_note);
        $this->assertNull($setting->closed_by);
        $this->assertNull($setting->closed_at);
    }

    public function test_valid_to_is_inclusive_for_the_last_meal_day_and_excludes_the_next_day(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMS008');
        $child = $this->createChild($institution->id, 'Datumozott Gyerek');

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'eater_type' => 'child',
            'eater_id' => $child->id,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-09-01',
            'valid_to' => '2026-09-18',
            'created_by' => $user->id,
            'closed_by' => $user->id,
            'closed_at' => now(),
            'closure_reason' => StudentMealSetting::CLOSURE_REASON_CANCELLED,
        ]);

        $service = app(StudentMealSettingService::class);

        $this->assertNotNull($service->currentSettingForEater($child, $institution, '2026-09-18'));
        $this->assertNull($service->currentSettingForEater($child, $institution, '2026-09-19'));
    }

    private function childController(): ChildMealSettingController
    {
        return app(ChildMealSettingController::class);
    }

    private function employeeController(): InstitutionEmployeeMealSettingController
    {
        return app(InstitutionEmployeeMealSettingController::class);
    }

    private function makeRequest(User $user, string $uri, string $method, array $data): Request
    {
        $request = Request::create($uri, $method, $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function seedInstitutionAdmin(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Etkezesi Intezmeny '.$code,
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

    private function createChild(int $institutionId, string $name): Child
    {
        $discount = DiscountType::firstOrCreate(
            [
                'institution_id' => $institutionId,
                'name' => 'Kedvezmeny nelkul',
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

    private function createEmployee(int $institutionId, string $name): InstitutionEmployee
    {
        return InstitutionEmployee::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function createMealContext(int $institutionId, int $userId): array
    {
        $mealType = MealType::create([
            'code' => 'lunch-'.$institutionId,
            'name' => 'Ebed',
            'default_order' => 1,
        ]);

        $institutionMealType = InstitutionMealType::create([
            'institution_id' => $institutionId,
            'meal_type_id' => $mealType->id,
            'is_active' => true,
            'is_parent_selectable' => true,
            'is_required' => true,
            'display_order' => 1,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institutionId,
            'name' => 'Alap csomag',
            'description' => null,
            'is_active' => true,
            'is_default' => true,
            'display_order' => 1,
            'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
            'created_by' => $userId,
        ]);

        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionMealType->id,
            'display_order' => 1,
        ]);

        return [
            'meal_type' => $mealType,
            'institution_meal_type' => $institutionMealType,
            'package' => $package,
        ];
    }
}
