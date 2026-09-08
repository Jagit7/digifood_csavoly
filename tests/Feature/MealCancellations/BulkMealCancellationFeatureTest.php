<?php

namespace Tests\Feature\MealCancellations;

use App\Http\Controllers\Dashboard\InstitutionAdmin\BulkMealCancellationController;
use App\Http\Requests\Dashboard\InstitutionAdmin\MealCancellations\BulkMealCancellationPreviewRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\MealCancellations\BulkMealCancellationStoreRequest;
use App\Models\BulkMealCancellationBatch;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionMealType;
use App\Models\MealCancellation;
use App\Models\MealType;
use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\DailyMealHeadcountService;
use App\Services\InstitutionCalendarService;
use App\Services\MealCancellationService;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class BulkMealCancellationFeatureTest extends TestCase
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

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_bulk_store_creates_single_child_single_day_cancellation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $child = $this->createChild($institution->id, 'Egyes Elek', '3.A');
        $this->assignMealSetting($institution->id, $child->id, '2026-08-24');

        $response = $this->store($user, [
            'selected_child_ids' => [$child->id],
            'date_from' => '2026-08-25',
            'date_to' => '2026-08-25',
            'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
            'event_name' => 'Kóruspróba',
            'reason' => 'Teszt művelet',
        ]);

        $batch = BulkMealCancellationBatch::query()->firstOrFail();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.meal-cancellations.bulk.show', $batch), $response->getTargetUrl());
        $this->assertDatabaseHas('meal_cancellations', [
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-08-25',
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);
        $this->assertSame(1, $batch->created_cancellation_count);
        $this->assertSame(0, $batch->duplicate_count);
    }

    public function test_bulk_store_handles_multiple_classes_and_ignores_unselected_child(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $first = $this->createChild($institution->id, 'Anna', '3.A');
        $second = $this->createChild($institution->id, 'Bence', '4.B');
        $third = $this->createChild($institution->id, 'Cili', '5.C');

        foreach ([$first, $second, $third] as $child) {
            $this->assignMealSetting($institution->id, $child->id, '2026-08-24');
        }

        $response = $this->store($user, [
            'selected_child_ids' => [$first->id, $second->id],
            'date_from' => '2026-08-25',
            'date_to' => '2026-08-25',
            'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
            'event_name' => 'Vegyes csoport',
        ]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertDatabaseCount('meal_cancellations', 2);
        $this->assertDatabaseMissing('meal_cancellations', [
            'child_id' => $third->id,
            'service_date' => '2026-08-25',
        ]);
    }

    public function test_bulk_store_uses_closed_date_interval_and_skips_weekend_and_school_break(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $child = $this->createChild($institution->id, 'Dora', '2.A');
        $this->assignMealSetting($institution->id, $child->id, '2026-08-24');

        SchoolBreak::create([
            'institution_id' => $institution->id,
            'title' => 'Rendkívüli szünet',
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
            'type' => 'school_break',
        ]);

        $this->store($user, [
            'selected_child_ids' => [$child->id],
            'date_from' => '2026-09-04',
            'date_to' => '2026-09-08',
            'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
            'event_name' => 'Kirándulás',
        ]);

        $batch = BulkMealCancellationBatch::query()->firstOrFail();

        $this->assertDatabaseHas('meal_cancellations', ['child_id' => $child->id, 'service_date' => '2026-09-04']);
        $this->assertDatabaseHas('meal_cancellations', ['child_id' => $child->id, 'service_date' => '2026-09-08']);
        $this->assertDatabaseMissing('meal_cancellations', ['child_id' => $child->id, 'service_date' => '2026-09-05']);
        $this->assertDatabaseMissing('meal_cancellations', ['child_id' => $child->id, 'service_date' => '2026-09-06']);
        $this->assertDatabaseMissing('meal_cancellations', ['child_id' => $child->id, 'service_date' => '2026-09-07']);
        $this->assertSame(2, $batch->created_cancellation_count);
        $this->assertSame(3, $batch->non_service_day_count);
    }

    public function test_bulk_preview_rejects_other_institution_child_id(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $otherInstitution = Institution::create([
            'name' => 'Másik intézmény',
            'institution_code' => 'BULK002',
            'type' => 'iskola',
            'active' => true,
        ]);
        $foreignChild = $this->createChild($otherInstitution->id, 'Tiltott Tilda', '1.A');

        try {
            $this->makePreviewRequest($user, [
                'selected_child_ids' => [$foreignChild->id],
                'date_from' => '2026-08-25',
                'date_to' => '2026-08-25',
                'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
                'event_name' => 'Tiltott kérés',
            ])->validateResolved();

            $this->fail('Validációs hibára számítottunk.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('selected_child_ids.0', $exception->errors());
        }
    }

    public function test_bulk_create_shows_validation_summary_and_preserves_old_input_after_failed_preview(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $child = $this->createChild($institution->id, 'Vera', '3.A');

        $html = $this->renderBulkCreatePage(
            $user,
            ['selected_child_ids' => [$child->id]],
            [
                'selected_child_ids' => ['Válassz ki legalább egy gyermeket az előnézethez.'],
                'event_name' => ['Add meg az esemény vagy csoport nevét.'],
                'date_to' => ['A lemondás vége nem lehet korábbi, mint a kezdőnap.'],
            ],
            [
                'selected_child_ids' => [$child->id],
                'date_from' => '2026-08-25',
                'date_to' => '2026-08-24',
                'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
                'event_name' => '',
                'reason' => 'Teszt megjegyzés',
            ]
        );

        $this->assertStringContainsString('Az előnézet nem készíthető el, mert néhány adat hiányzik vagy hibás.', $html);
        $this->assertStringContainsString('Válassz ki legalább egy gyermeket az előnézethez.', $html);
        $this->assertStringContainsString('Add meg az esemény vagy csoport nevét.', $html);
        $this->assertStringContainsString('Teszt megjegyzés', $html);
        $this->assertStringContainsString('value="2026-08-25"', $html);
        $this->assertStringContainsString('Kijelölve: <span id="selected-count">1</span>', $html);
    }

    public function test_bulk_create_keeps_grade_filter_options_visible_even_when_default_status_has_no_matches(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $this->createChild($institution->id, 'Gréta', '3.A');

        $html = $this->renderBulkCreatePage($user);

        $this->assertStringContainsString('3. évfolyam', $html);
        $this->assertStringContainsString('data-none-selected-text="Nincs kiválasztva"', $html);
    }

    public function test_bulk_create_sidebar_contains_dedicated_bulk_menu_entry(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [, $user] = $this->createInstitutionAdminContext();

        $html = $this->renderBulkCreatePage($user);

        $this->assertStringContainsString('Csoportos lemondás', $html);
        $this->assertStringContainsString(route('dashboard.institution.meal-cancellations.bulk.create'), $html);
        $this->assertStringContainsString('class="mm-active"', $html);
    }

    public function test_bulk_store_does_not_duplicate_existing_cancellation_and_handles_partial_existing_range(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $child = $this->createChild($institution->id, 'Levi', '3.B');
        $this->assignMealSetting($institution->id, $child->id, '2026-08-24');

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => '2026-08-25',
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
            'created_by' => $user->id,
        ]);

        $this->store($user, [
            'selected_child_ids' => [$child->id],
            'date_from' => '2026-08-25',
            'date_to' => '2026-08-26',
            'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
            'event_name' => 'Részleges átfedés',
        ]);

        $batch = BulkMealCancellationBatch::query()->firstOrFail();

        $this->assertDatabaseCount('meal_cancellations', 2);
        $this->assertSame(1, $batch->duplicate_count);
        $this->assertSame(1, $batch->created_cancellation_count);
    }

    public function test_bulk_store_skips_child_without_active_meal_setting(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $child = $this->createChild($institution->id, 'Misi', '1.A');

        $this->store($user, [
            'selected_child_ids' => [$child->id],
            'date_from' => '2026-08-25',
            'date_to' => '2026-08-25',
            'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
            'event_name' => 'Nincs étkezés',
        ]);

        $batch = BulkMealCancellationBatch::query()->firstOrFail();

        $this->assertDatabaseCount('meal_cancellations', 0);
        $this->assertSame(1, $batch->missing_meal_setting_count);
        $this->assertSame(0, $batch->created_cancellation_count);
    }

    public function test_bulk_store_applies_existing_deadline_rule_for_admin(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 09:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $child = $this->createChild($institution->id, 'Nora', '2.B');
        $this->assignMealSetting($institution->id, $child->id, '2026-08-24');

        $this->store($user, [
            'selected_child_ids' => [$child->id],
            'date_from' => '2026-08-25',
            'date_to' => '2026-08-25',
            'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
            'event_name' => 'Késői kérés',
        ]);

        $batch = BulkMealCancellationBatch::query()->firstOrFail();

        $this->assertSame(1, $batch->deadline_blocked_count);
        $this->assertSame(0, $batch->created_cancellation_count);
        $this->assertDatabaseCount('meal_cancellations', 0);
    }

    public function test_repeated_identical_bulk_request_is_idempotent_for_meal_cancellations(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $child = $this->createChild($institution->id, 'Pali', '4.A');
        $this->assignMealSetting($institution->id, $child->id, '2026-08-24');

        $payload = [
            'selected_child_ids' => [$child->id],
            'date_from' => '2026-08-25',
            'date_to' => '2026-08-25',
            'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
            'event_name' => 'Ismételt kérés',
        ];

        $this->store($user, $payload);
        $this->store($user, $payload);

        $this->assertDatabaseCount('meal_cancellations', 1);
        $secondBatch = BulkMealCancellationBatch::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $secondBatch->duplicate_count);
        $this->assertSame(0, $secondBatch->created_cancellation_count);
    }

    public function test_bulk_cancellations_are_used_by_daily_headcount_and_payment_calculation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $first = $this->createChild($institution->id, 'Rita', '1.A');
        $second = $this->createChild($institution->id, 'Soma', '1.A');

        foreach ([$first, $second] as $child) {
            $this->assignMealSetting($institution->id, $child->id, '2026-08-24');
        }

        $this->createMealPriceContext($institution->id, $user->id);

        $this->store($user, [
            'selected_child_ids' => [$first->id],
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-01',
            'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
            'event_name' => 'Integrációs teszt',
        ]);

        $headcount = app(DailyMealHeadcountService::class)->forDate($institution->id, '2026-09-01');
        $this->assertSame(1, $headcount['stats']['cancelled_meals']);
        $this->assertSame(1, $headcount['stats']['daily_eaters']);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 9, 1));

        $day = $first->monthlyPaymentStatements()
            ->where('year', 2026)
            ->where('month', 9)
            ->firstOrFail()
            ->days()
            ->whereDate('date', '2026-09-01')
            ->firstOrFail();

        $this->assertSame(MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE, $day->status);
        $this->assertSame(0, $day->payable_amount);
    }

    public function test_bulk_store_rolls_back_transaction_when_cancellation_creation_fails(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'Europe/Budapest'));
        [$institution, $user] = $this->createInstitutionAdminContext();
        $first = $this->createChild($institution->id, 'Tomi', '5.A');
        $second = $this->createChild($institution->id, 'Ula', '5.B');

        foreach ([$first, $second] as $child) {
            $this->assignMealSetting($institution->id, $child->id, '2026-08-24');
        }

        $realService = new MealCancellationService(app(InstitutionCalendarService::class));
        $callCount = 0;

        $this->partialMock(MealCancellationService::class, function ($mock) use (&$callCount, $realService) {
            $mock->shouldReceive('cancellationWindowOrFail')->andReturnUsing(
                fn (int $institutionId) => $realService->cancellationWindowOrFail($institutionId)
            );
            $mock->shouldReceive('createActiveCancellations')->andReturnUsing(function (...$args) use (&$callCount, $realService) {
                $callCount++;

                if ($callCount === 2) {
                    throw new RuntimeException('Szándékos mentési hiba');
                }

                return $realService->createActiveCancellations(...$args);
            });
        });

        try {
            $this->store($user, [
                'selected_child_ids' => [$first->id, $second->id],
                'date_from' => '2026-08-25',
                'date_to' => '2026-08-25',
                'meal_scope' => BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED,
                'event_name' => 'Rollback teszt',
            ]);
            $this->fail('Kivételre számítottunk.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Szándékos mentési hiba', $exception->getMessage());
        }

        $this->assertDatabaseCount('bulk_meal_cancellation_batches', 0);
        $this->assertDatabaseCount('bulk_meal_cancellation_batch_items', 0);
        $this->assertDatabaseCount('meal_cancellations', 0);
    }

    private function store(User $user, array $data): RedirectResponse
    {
        $this->actingAs($user);

        return $this->controller()->store($this->makeStoreRequest($user, $data));
    }

    private function controller(): BulkMealCancellationController
    {
        return app(BulkMealCancellationController::class);
    }

    private function makeStoreRequest(User $user, array $data): BulkMealCancellationStoreRequest
    {
        $request = BulkMealCancellationStoreRequest::createFromBase(
            Request::create(route('dashboard.institution.meal-cancellations.bulk.store'), 'POST', $data)
        );
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        return $request;
    }

    private function makePreviewRequest(User $user, array $data): BulkMealCancellationPreviewRequest
    {
        $request = BulkMealCancellationPreviewRequest::createFromBase(
            Request::create(route('dashboard.institution.meal-cancellations.bulk.preview'), 'POST', $data)
        );
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function renderBulkCreatePage(
        User $user,
        array $query = [],
        array $errors = [],
        array $oldInput = []
    ): string {
        $this->actingAs($user);

        $request = Request::create(route('dashboard.institution.meal-cancellations.bulk.create'), 'GET', $query);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(
                'GET',
                '/dashboard/institution-admin/meal-cancellations/bulk/create',
                []
            );
            $route->name('dashboard.institution.meal-cancellations.bulk.create');

            return $route;
        });

        if ($oldInput !== []) {
            $request->session()->flashInput($oldInput);
        }

        $errorBag = new ViewErrorBag;

        if ($errors !== []) {
            $errorBag->put('default', new MessageBag($errors));
            $request->session()->flash('errors', $errorBag);
        }

        app()->instance('request', $request);

        return $this->controller()->create($request)->with('errors', $errorBag)->render();
    }

    private function createInstitutionAdminContext(): array
    {
        $institution = Institution::create([
            'name' => 'Batch intézmény',
            'institution_code' => 'BULK'.substr(md5((string) microtime(true)), 0, 4),
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create();
        $user->forceFill([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
        ])->save();

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => 'institution_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 8,
            'cancellation_minute' => 30,
        ]);

        return [$institution, $user];
    }

    private function createChild(int $institutionId, string $name, string $groupName): Child
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
            'educational_identifier' => substr(md5($name.$institutionId.microtime(true)), 0, 10),
            'group_name' => $groupName,
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function assignMealSetting(int $institutionId, int $childId, string $validFrom): void
    {
        StudentMealSetting::create([
            'student_id' => $childId,
            'institution_id' => $institutionId,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => $validFrom,
            'valid_to' => null,
        ]);
    }

    private function createMealPriceContext(int $institutionId, int $userId): void
    {
        $mealType = MealType::create([
            'code' => 'lunch-'.$institutionId,
            'name' => 'Ebéd',
            'default_order' => 1,
        ]);

        $institutionMealType = InstitutionMealType::create([
            'institution_id' => $institutionId,
            'meal_type_id' => $mealType->id,
            'is_active' => true,
            'is_parent_selectable' => false,
            'is_required' => true,
            'display_order' => 1,
        ]);

        InstitutionMealPrice::create([
            'institution_meal_type_id' => $institutionMealType->id,
            'price' => 1000,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'created_by' => $userId,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institutionId,
            'name' => 'Normál csomag',
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
    }
}
