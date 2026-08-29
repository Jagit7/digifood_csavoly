<?php

namespace Tests\Feature\Children;

use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildBarcodeController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\Kiosk\MealKioskController;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureBarcodeEntryEnabled;
use App\Models\AbMenuPlan;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\EmployeeMealCancellation;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealType;
use App\Models\InstitutionSetting;
use App\Models\MealCancellation;
use App\Models\MealCheckIn;
use App\Models\MealType;
use App\Models\MenuChoice;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\Barcodes\EaterBarcodeService;
use App\Services\Meals\MealKioskService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MealKioskFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 10:00:00', 'Europe/Budapest'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_superadmin_can_enable_barcode_entry_module_for_institution(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'institution_id' => null,
        ]);
        $institution = $this->createInstitution('KIOSK001');
        $request = Request::create('/dashboard/institutions/'.$institution->id, 'PUT', [
            'name' => $institution->name,
            'type' => $institution->type,
            'active' => 1,
            'barcode_entry_enabled' => 1,
        ]);
        $this->actingAs($superAdmin);

        $response = app(CheckRole::class)->handle(
            $request,
            fn ($request) => app(InstitutionController::class)->update($request, $institution),
            User::ROLE_SUPER_ADMIN
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue((bool) InstitutionSetting::where('institution_id', $institution->id)->value('barcode_entry_enabled'));
    }

    public function test_institution_admin_cannot_modify_subscription_level_kiosk_flag(): void
    {
        [$institution, $admin] = $this->seedInstitutionAdmin('KIOSK002');
        $request = Request::create('/dashboard/institutions/'.$institution->id, 'PUT');
        $this->actingAs($admin);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Nincs jogosultsága az oldal megtekintéséhez.');

        app(CheckRole::class)->handle(
            $request,
            fn () => app(InstitutionController::class)->update($request, $institution),
            User::ROLE_SUPER_ADMIN
        );
    }

    public function test_disabled_module_blocks_scan_middleware_but_barcode_generation_still_works(): void
    {
        [$institution, $admin, $kioskUser, $mealType] = $this->seedKioskContext('KIOSK003', false);
        $child = $this->createParticipantChild($institution->id, 'Tiltott Modulos');
        $request = Request::create('/kiosk/scan', 'POST', ['barcode_token' => 'DF-NINCS']);
        $request->headers->set('Accept', 'application/json');
        $this->actingAs($kioskUser);
        $request->setUserResolver(fn () => $kioskUser);

        $response = app(EnsureBarcodeEntryEnabled::class)->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('module_disabled', $response->getData(true)['code']);

        $this->actingAs($admin);
        $storeResponse = app(ChildBarcodeController::class)->store($child);

        $this->assertInstanceOf(RedirectResponse::class, $storeResponse);
        $this->assertNotNull($child->fresh()->barcode_token);
    }

    public function test_kiosk_user_is_blocked_from_institution_admin_role_group(): void
    {
        [, , $kioskUser] = $this->seedKioskContext('KIOSK004', true);
        $request = Request::create('/dashboard/institution-admin/children', 'GET');
        $this->actingAs($kioskUser);

        try {
            app(CheckRole::class)->handle($request, fn () => response('ok'), User::ROLE_INSTITUTION_ADMIN);
            $this->fail('403-as kivételre számítottunk.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_valid_child_scan_is_recorded_successfully(): void
    {
        [$institution, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK005', true);
        $child = $this->createParticipantChild($institution->id, 'Sikeres Gyermek');
        $this->assignActiveBarcode($child, 'DFSUCCESS1');

        $result = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFSUCCESS1');

        $this->assertSame('success', $result['result']);
        $this->assertSame('Sikeres Gyermek', $result['child']->name);
        $this->assertDatabaseHas('meal_check_ins', [
            'child_id' => $child->id,
            'eater_type' => 'child',
            'eater_id' => $child->id,
            'status' => MealCheckIn::STATUS_SUCCESS,
        ]);
    }

    public function test_cancelled_meal_is_rejected(): void
    {
        [$institution, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK006', true);
        $child = $this->createParticipantChild($institution->id, 'Lemondott Gyermek');
        $this->assignActiveBarcode($child, 'DFCANCEL1');

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => now()->toDateString(),
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);

        $result = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFCANCEL1');

        $this->assertSame('rejected', $result['result']);
        $this->assertSame('cancelled', $result['code']);
    }

    public function test_child_after_meal_relationship_end_is_rejected_in_kiosk(): void
    {
        [$institution, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK006A', true);
        $child = $this->createParticipantChild($institution->id, 'Lezart Gyermek');
        $this->assignActiveBarcode($child, 'DFCLOSED1');

        StudentMealSetting::query()
            ->where('student_id', $child->id)
            ->update([
                'valid_to' => now()->subDay()->toDateString(),
                'closure_reason' => StudentMealSetting::CLOSURE_REASON_CANCELLED,
                'closed_at' => now(),
            ]);

        $result = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFCLOSED1');

        $this->assertSame('rejected', $result['result']);
        $this->assertSame('no_active_meal', $result['code']);
    }

    public function test_foreign_institution_barcode_is_rejected(): void
    {
        [$institution, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK007', true);
        $foreignInstitution = $this->createInstitution('KIOSK007X');
        $foreignChild = $this->createParticipantChild($foreignInstitution->id, 'Idegen Gyermek');
        $this->assignActiveBarcode($foreignChild, 'DFFOREIGN1');

        $result = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFFOREIGN1');

        $this->assertSame('rejected', $result['result']);
        $this->assertSame('foreign_institution', $result['code']);
    }

    public function test_duplicate_scan_is_not_recorded_twice(): void
    {
        [$institution, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK008', true);
        $child = $this->createParticipantChild($institution->id, 'Dupla Gyermek');
        $this->assignActiveBarcode($child, 'DFDUPL1');

        $first = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFDUPL1');
        $second = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFDUPL1');

        $this->assertSame('success', $first['result']);
        $this->assertSame('duplicate', $second['result']);
        $this->assertSame(1, DB::table('meal_check_ins')->where('child_id', $child->id)->where('status', MealCheckIn::STATUS_SUCCESS)->count());
        $this->assertDatabaseHas('meal_check_ins', [
            'child_id' => $child->id,
            'eater_type' => 'child',
            'eater_id' => $child->id,
            'status' => MealCheckIn::STATUS_DUPLICATE,
        ]);
    }

    public function test_employee_barcode_is_recognized_and_recorded_in_kiosk(): void
    {
        [$institution, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK010', true);
        $employee = $this->createParticipantEmployee($institution->id, 'Teszt Dolgozó');
        $this->assignActiveEmployeeBarcode($employee, 'DFEMP010');

        $result = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFEMP010');

        $this->assertSame('success', $result['result']);
        $this->assertSame('Teszt Dolgozó', $result['eater']->name);
        $this->assertNull($result['child']);
        $this->assertDatabaseHas('meal_check_ins', [
            'child_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employee->id,
            'status' => MealCheckIn::STATUS_SUCCESS,
        ]);
    }

    public function test_employee_without_active_meal_setting_is_rejected(): void
    {
        [$institution, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK011', true);
        $employee = $this->createEmployee($institution->id, 'Jogosulatlan Dolgozó');
        $this->assignActiveEmployeeBarcode($employee, 'DFEMP011');

        $result = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFEMP011');

        $this->assertSame('rejected', $result['result']);
        $this->assertSame('no_active_meal', $result['code']);
        $this->assertDatabaseHas('meal_check_ins', [
            'child_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employee->id,
            'status' => MealCheckIn::STATUS_REJECTED,
        ]);
    }

    public function test_employee_duplicate_scan_is_detected(): void
    {
        [$institution, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK012', true);
        $employee = $this->createParticipantEmployee($institution->id, 'Dupla Dolgozó');
        $this->assignActiveEmployeeBarcode($employee, 'DFEMP012');

        $first = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFEMP012');
        $second = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFEMP012');

        $this->assertSame('success', $first['result']);
        $this->assertSame('duplicate', $second['result']);
        $this->assertDatabaseHas('meal_check_ins', [
            'child_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employee->id,
            'status' => MealCheckIn::STATUS_DUPLICATE,
        ]);
    }

    public function test_cancelled_employee_meal_is_rejected_in_kiosk(): void
    {
        [$institution, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK012A', true);
        $employee = $this->createParticipantEmployee($institution->id, 'Lemondott Dolgozó');
        $this->assignActiveEmployeeBarcode($employee, 'DFEMP012A');

        EmployeeMealCancellation::create([
            'institution_id' => $institution->id,
            'institution_employee_id' => $employee->id,
            'service_date' => now()->toDateString(),
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'status' => EmployeeMealCancellation::STATUS_ACTIVE,
        ]);

        $result = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFEMP012A');

        $this->assertSame('rejected', $result['result']);
        $this->assertSame('cancelled', $result['code']);
        $this->assertDatabaseHas('meal_check_ins', [
            'child_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employee->id,
            'status' => MealCheckIn::STATUS_REJECTED,
            'rejection_reason' => 'cancelled',
        ]);
    }

    public function test_foreign_employee_barcode_is_rejected(): void
    {
        [$institution, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK013', true);
        $foreignInstitution = $this->createInstitution('KIOSK013X');
        $employee = $this->createParticipantEmployee($foreignInstitution->id, 'Idegen Dolgozó');
        $this->assignActiveEmployeeBarcode($employee, 'DFEMP013');

        $result = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFEMP013');

        $this->assertSame('rejected', $result['result']);
        $this->assertSame('foreign_institution', $result['code']);
    }

    public function test_kiosk_show_auto_selects_the_only_active_meal_type_when_session_is_missing(): void
    {
        [, , $kioskUser, $mealType] = $this->seedKioskContext('KIOSK008A', true);
        $this->actingAs($kioskUser);
        $request = $this->kioskRequest($kioskUser, 'GET', '/kiosk');

        $response = app(MealKioskController::class)->show($request);

        $this->assertSame($mealType->id, $request->session()->get('meal_kiosk.active_meal_type_id'));
        $this->assertSame($mealType->id, $response->getData()['selectedMealTypeId']);
    }

    public function test_kiosk_show_prefers_remembered_active_meal_type_over_lunch_default(): void
    {
        [$institution, , $kioskUser, $lunchMealType] = $this->seedKioskContext('KIOSK008B', true);
        $breakfastMealType = $this->createInstitutionMealType($institution->id, 'Reggeli', 'breakfast_kiosk008b', 2);

        InstitutionSetting::where('institution_id', $institution->id)->update([
            'barcode_kiosk_meal_type_id' => $breakfastMealType->id,
        ]);
        $this->actingAs($kioskUser);
        $request = $this->kioskRequest($kioskUser, 'GET', '/kiosk');

        $response = app(MealKioskController::class)->show($request);

        $this->assertSame($breakfastMealType->id, $request->session()->get('meal_kiosk.active_meal_type_id'));
        $this->assertSame($breakfastMealType->id, $response->getData()['selectedMealTypeId']);
        $this->assertNotSame($lunchMealType->id, $request->session()->get('meal_kiosk.active_meal_type_id'));
    }

    public function test_kiosk_show_falls_back_to_lunch_when_remembered_meal_type_is_invalid(): void
    {
        [$institution, , $kioskUser, $lunchMealType] = $this->seedKioskContext('KIOSK008C', true);
        $this->createInstitutionMealType($institution->id, 'Reggeli', 'breakfast_kiosk008c', 2);
        [$foreignInstitution] = $this->seedInstitutionAdmin('KIOSK008CX');
        $foreignMealType = $this->createInstitutionMealType($foreignInstitution->id, 'Uzsonna', 'snack_kiosk008cx', 1);

        InstitutionSetting::where('institution_id', $institution->id)->update([
            'barcode_kiosk_meal_type_id' => $foreignMealType->id,
        ]);
        $this->actingAs($kioskUser);
        $request = $this->kioskRequest($kioskUser, 'GET', '/kiosk');

        $response = app(MealKioskController::class)->show($request);

        $this->assertSame($lunchMealType->id, $request->session()->get('meal_kiosk.active_meal_type_id'));
        $this->assertSame($lunchMealType->id, $response->getData()['selectedMealTypeId']);
    }

    public function test_updating_meal_type_persists_choice_and_scan_uses_that_institution_meal_type_id(): void
    {
        [$institution, , $kioskUser, $lunchMealType] = $this->seedKioskContext('KIOSK008D', true);
        $breakfastMealType = $this->createInstitutionMealType($institution->id, 'Reggeli', 'breakfast_kiosk008d', 2);
        $child = $this->createParticipantChild($institution->id, 'Mentett Étkezés');
        $this->assignActiveBarcode($child, 'DFMEALTYPE1');
        $this->actingAs($kioskUser);
        $session = app('session.store');
        $session->start();
        $updateRequest = $this->kioskRequest(
            $kioskUser,
            'POST',
            '/kiosk/session/meal-type',
            ['meal_type_id' => $breakfastMealType->id],
            $session
        );

        $updateResponse = app(MealKioskController::class)->updateMealType($updateRequest);

        $this->assertSame(200, $updateResponse->getStatusCode());
        $this->assertSame($breakfastMealType->id, $session->get('meal_kiosk.active_meal_type_id'));
        $this->assertSame($breakfastMealType->id, InstitutionSetting::where('institution_id', $institution->id)->value('barcode_kiosk_meal_type_id'));

        $scanRequest = $this->kioskRequest(
            $kioskUser,
            'POST',
            '/kiosk/scan',
            ['barcode_token' => 'DFMEALTYPE1'],
            $session
        );
        $scanResponse = app(MealKioskController::class)->scan($scanRequest);

        $this->assertSame(200, $scanResponse->getStatusCode());
        $this->assertDatabaseHas('meal_check_ins', [
            'child_id' => $child->id,
            'status' => MealCheckIn::STATUS_SUCCESS,
            'institution_meal_type_id' => $breakfastMealType->id,
        ]);
        $this->assertDatabaseMissing('meal_check_ins', [
            'child_id' => $child->id,
            'status' => MealCheckIn::STATUS_SUCCESS,
            'institution_meal_type_id' => $lunchMealType->id,
        ]);
    }

    public function test_ab_menu_children_are_stored_with_separate_menu_choices(): void
    {
        [$institution, $admin, $kioskUser, $mealType] = $this->seedKioskContext('KIOSK009', true);
        $childA = $this->createParticipantChild($institution->id, 'A Menus');
        $childB = $this->createParticipantChild($institution->id, 'B Menus');

        $this->assignActiveBarcode($childA, 'DFMENUA1');
        $this->assignActiveBarcode($childB, 'DFMENUB1');
        $this->seedAbMenuForToday($institution->id, $admin->id);

        MenuChoice::create([
            'institution_id' => $institution->id,
            'child_id' => $childA->id,
            'menu_date' => now()->toDateString(),
            'choice' => MenuChoice::CHOICE_A,
        ]);
        MenuChoice::create([
            'institution_id' => $institution->id,
            'child_id' => $childB->id,
            'menu_date' => now()->toDateString(),
            'choice' => MenuChoice::CHOICE_B,
        ]);

        $first = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFMENUA1');
        $second = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFMENUB1');

        $this->assertSame(MenuChoice::CHOICE_A, $first['menu_choice']);
        $this->assertSame(MenuChoice::CHOICE_B, $second['menu_choice']);
        $this->assertDatabaseHas('meal_check_ins', [
            'child_id' => $childA->id,
            'menu_choice' => MenuChoice::CHOICE_A,
        ]);
        $this->assertDatabaseHas('meal_check_ins', [
            'child_id' => $childB->id,
            'menu_choice' => MenuChoice::CHOICE_B,
        ]);
    }

    public function test_ab_menu_defaults_to_a_when_no_explicit_choice_record_exists(): void
    {
        [$institution, $admin, $kioskUser, $mealType] = $this->seedKioskContext('KIOSK009A', true);
        $child = $this->createParticipantChild($institution->id, 'Alapertelmezett A');

        $this->assignActiveBarcode($child, 'DFMENUADEFAULT');
        $this->seedAbMenuForToday($institution->id, $admin->id);

        $result = app(MealKioskService::class)->scan($institution, $kioskUser, $mealType, 'DFMENUADEFAULT');

        $this->assertSame('success', $result['result']);
        $this->assertSame(MenuChoice::CHOICE_A, $result['menu_choice']);
        $this->assertDatabaseHas('meal_check_ins', [
            'child_id' => $child->id,
            'menu_choice' => MenuChoice::CHOICE_A,
        ]);
        $this->assertDatabaseMissing('menu_choices', [
            'child_id' => $child->id,
            'menu_date' => now()->toDateString(),
        ]);
    }

    private function seedInstitutionAdmin(string $code): array
    {
        $institution = $this->createInstitution($code);
        $admin = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $admin->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institution, $admin];
    }

    private function seedKioskContext(string $code, bool $moduleEnabled): array
    {
        [$institution, $admin] = $this->seedInstitutionAdmin($code);

        $mealType = MealType::create([
            'code' => 'lunch_'.$code,
            'name' => 'Ebéd',
            'default_order' => 1,
        ]);

        $institutionMealType = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $mealType->id,
            'is_active' => true,
            'is_parent_selectable' => true,
            'is_required' => false,
            'display_order' => 1,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institution->id,
            'name' => 'Alap csomag '.$code,
            'is_active' => true,
            'is_default' => true,
            'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
            'display_order' => 1,
        ]);

        DB::table('institution_meal_package_items')->insert([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionMealType->id,
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            [
                'barcode_entry_enabled' => $moduleEnabled,
                'barcode_kiosk_pin_hash' => Hash::make('1234'),
            ] + InstitutionSetting::defaults()
        );

        $kioskUser = User::factory()->create([
            'role' => User::ROLE_MEAL_KIOSK,
            'institution_id' => $institution->id,
            'email' => 'kiosk+'.$code.'@digifood.local',
            'password' => Hash::make('titkos123'),
            'is_active' => true,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $kioskUser->id,
            'scope_role' => User::ROLE_MEAL_KIOSK,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institution, $admin, $kioskUser, $institutionMealType];
    }

    private function createInstitution(string $code): Institution
    {
        return Institution::create([
            'name' => 'Kioszk intézmény '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);
    }

    private function createParticipantChild(int $institutionId, string $name): Child
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

        $child = Child::create([
            'institution_id' => $institutionId,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId), 0, 10),
            'group_name' => '2.B',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institutionId,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        return $child;
    }

    private function createEmployee(int $institutionId, string $name): InstitutionEmployee
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

        return InstitutionEmployee::create([
            'institution_id' => $institutionId,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function createParticipantEmployee(int $institutionId, string $name): InstitutionEmployee
    {
        $employee = $this->createEmployee($institutionId, $name);

        StudentMealSetting::create([
            'institution_id' => $institutionId,
            'eater_type' => 'institution_employee',
            'eater_id' => $employee->id,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        return $employee;
    }

    private function assignActiveBarcode(Child $child, string $token): void
    {
        $child->forceFill([
            'barcode_token' => $token,
            // A tényleges beolvasásos keresés (MealEligibilityService) a
            // barcode_token_hash oszlopon keresztül történik, ezért a
            // tesztadatoknál is ezt kell beállítani, ugyanúgy, ahogy az
            // EaterBarcodeService::persistBarcode() teszi éles esetben.
            'barcode_token_hash' => EaterBarcodeService::hashToken($token),
            'barcode_generated_at' => now(),
            'barcode_disabled_at' => null,
        ])->save();
    }

    private function assignActiveEmployeeBarcode(InstitutionEmployee $employee, string $token): void
    {
        $employee->forceFill([
            'barcode_token' => $token,
            'barcode_token_hash' => EaterBarcodeService::hashToken($token),
            'barcode_generated_at' => now(),
            'barcode_disabled_at' => null,
        ])->save();
    }

    private function kioskRequest(User $kioskUser, string $method, string $uri, array $data = [], $session = null): Request
    {
        $request = Request::create($uri, $method, $data);
        $session ??= app('session.store');
        $session->start();
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $kioskUser);
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    private function createInstitutionMealType(int $institutionId, string $name, string $code, int $displayOrder): InstitutionMealType
    {
        $mealType = MealType::create([
            'code' => $code,
            'name' => $name,
            'default_order' => $displayOrder,
        ]);

        $institutionMealType = InstitutionMealType::create([
            'institution_id' => $institutionId,
            'meal_type_id' => $mealType->id,
            'is_active' => true,
            'is_parent_selectable' => true,
            'is_required' => false,
            'display_order' => $displayOrder,
        ]);

        $packageId = InstitutionMealPackage::query()
            ->where('institution_id', $institutionId)
            ->where('is_default', true)
            ->value('id');

        if ($packageId !== null) {
            DB::table('institution_meal_package_items')->insert([
                'institution_meal_package_id' => $packageId,
                'institution_meal_type_id' => $institutionMealType->id,
                'display_order' => $displayOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $institutionMealType;
    }

    private function seedAbMenuForToday(int $institutionId, int $creatorId): void
    {
        $plan = AbMenuPlan::create([
            'institution_id' => $institutionId,
            'title' => 'Teszt A/B menü',
            'valid_from' => now()->toDateString(),
            'valid_to' => now()->toDateString(),
            'active' => true,
            'created_by' => $creatorId,
            'updated_by' => $creatorId,
        ]);

        $plan->items()->create([
            'menu_date' => now()->toDateString(),
            'menu_a' => 'A menü',
            'menu_b' => 'B menü',
            'menu_dietary' => 'Diétás menü',
        ]);
    }
}
