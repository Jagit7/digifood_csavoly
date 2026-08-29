<?php

namespace Tests\Feature\Parent;

use App\Models\AbMenuItem;
use App\Models\AbMenuPlan;
use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionMealType;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Models\MealType;
use App\Models\MenuChoice;
use App\Models\ParentMonthlySettlementPayment;
use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\Finance\Payments\CardPaymentInitiationResult;
use App\Services\Finance\Payments\Providers\CibCardPaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ParentPortalFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_parent_login_page_is_available_on_parent_prefix(): void
    {
        $response = $this->followingRedirects()->get($this->parentUrl('/login'));

        $response->assertOk();
        $response->assertSee('Belépek');
        $response->assertSee('DigiFood szülői felületére.');
    }

    public function test_parent_can_log_in_and_reach_dashboard(): void
    {
        [$user] = $this->createParentContext();

        $response = $this->post($this->parentUrl('/login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('parent.dashboard'));

        $dashboard = $this->actingAs($user)->get($this->parentUrl('/vezerlopult'));

        $dashboard->assertOk();
        $dashboard->assertSee('DigiFood');
        $dashboard->assertSee('Gyermekeim');
    }

    public function test_parent_dashboard_handles_empty_state_without_linked_child(): void
    {
        [$user] = $this->createParentContext();

        $response = $this->actingAs($user)->get($this->parentUrl('/vezerlopult'));

        $response->assertOk();
        $response->assertSee('Nincs kapcsolt gyermek');
        $response->assertSee('DigiFood');
    }

    public function test_parent_dashboard_shows_only_linked_child_financial_and_meal_data(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 9,
            'cancellation_minute' => 0,
        ]);

        $child = $this->createChild($institution->id, 'Sajat Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-07-01',
            'valid_to' => null,
            'created_by' => $user->id,
        ]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 4000,
            'invoiceable_amount' => 4000,
            'total_payable' => 4000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        MonthlyPaymentDay::create([
            'monthly_payment_statement_id' => $statement->id,
            'date' => '2026-07-21',
            'status' => MonthlyPaymentDay::STATUS_PAY,
            'original_daily_price' => 1000,
            'discount_percent' => 0,
            'payable_amount' => 1000,
        ]);

        InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => 1500,
            'paid_at' => now(),
            'payment_method' => InstitutionPayment::METHOD_CARD,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'recorded_by' => $user->id,
        ]);

        $otherGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Masik',
            'first_name' => 'Szulo',
            'email' => 'masik@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $otherChild = $this->createChild($institution->id, 'Masik Gyermek');
        $otherChild->guardians()->attach($otherGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->get($this->parentUrl('/vezerlopult'));

        $response->assertOk();
        $response->assertSee('Sajat Gyermek');
        $response->assertDontSee('Masik Gyermek');
        $response->assertSee('2 500 Ft');
        $response->assertSee('Gyermekeim');
    }

    /**
     * Regresszió teszt: a vezérlőpult "Közelgő étkezések és lemondások"
     * widgetje (ParentDashboardController::buildUpcomingTimeline())
     * korábban hibás Collection::sortBy() hívást használt (egyparaméteres
     * érték-kinyerő closure-öket adott át ott, ahol a Laravel a
     * sortByMany() belső logikájában két paraméteres $a/$b összehasonlító
     * függvényt vár) - emiatt a lista NEM a legközelebbi étkezési nap
     * szerint volt rendezve. Ez a teszt legalább 4, egymástól távol eső
     * hónapból származó napot ad hozzá (a MonthlyPaymentStatement lekérdezés
     * csökkenő év/hónap szerint rendez, tehát a nyers, rendezetlen sorrend
     * pont a legtávolabbi hónap napjával kezdődne), és ellenőrzi, hogy a
     * végeredmény a valós, dátum szerinti (legközelebbi elöl) sorrendet
     * mutatja.
     */
    public function test_dashboard_upcoming_timeline_is_sorted_by_nearest_meal_date_first(): void
    {
        CarbonImmutable::setTestNow('2026-07-25 08:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 9,
            'cancellation_minute' => 0,
        ]);

        $child = $this->createChild($institution->id, 'Sorrend Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        // Szándékosan a legtávolabbi hónaptól a legközelebbiig hozzuk létre
        // a bejegyzéseket - a lekérdezés (desc year, desc month) így is a
        // legtávolabbi hónapot adná vissza elsőként, ha a globális,
        // dátum szerinti újrarendezés nem működne helyesen.
        $months = [10, 9, 8, 7];
        $dates = [
            10 => '2026-10-01',
            9 => '2026-09-01',
            8 => '2026-08-03',
            7 => '2026-07-28',
        ];

        foreach ($months as $month) {
            $statement = MonthlyPaymentStatement::create([
                'institution_id' => $institution->id,
                'child_id' => $child->id,
                'year' => 2026,
                'month' => $month,
                'meal_amount' => 1000,
                'invoiceable_amount' => 1000,
                'total_payable' => 1000,
                'status' => MonthlyPaymentStatement::STATUS_DRAFT,
            ]);

            MonthlyPaymentDay::create([
                'monthly_payment_statement_id' => $statement->id,
                'date' => $dates[$month],
                'status' => MonthlyPaymentDay::STATUS_PAY,
                'original_daily_price' => 1000,
                'discount_percent' => 0,
                'payable_amount' => 1000,
            ]);
        }

        $response = $this->actingAs($user)->get($this->parentUrl('/vezerlopult'));

        $response->assertOk();

        $timeline = $response->viewData('timeline');
        $orderedDateLabels = $timeline->pluck('date_label')->values()->all();

        $this->assertSame([
            CarbonImmutable::parse('2026-07-28')->locale('hu')->isoFormat('MMMM D. dddd'),
            CarbonImmutable::parse('2026-08-03')->locale('hu')->isoFormat('MMMM D. dddd'),
            CarbonImmutable::parse('2026-09-01')->locale('hu')->isoFormat('MMMM D. dddd'),
            CarbonImmutable::parse('2026-10-01')->locale('hu')->isoFormat('MMMM D. dddd'),
        ], $orderedDateLabels);
    }

    public function test_non_parent_user_is_logged_out_from_parent_area(): void
    {
        $institution = Institution::create([
            'name' => 'Admin intezmeny',
            'institution_code' => 'PAR001',
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/vezerlopult'));

        $response->assertRedirect(route('parent.login'));
        $response->assertSessionHasErrors([
            'email' => 'A szülői felületre csak szülői szerepkörű felhasználó léphet be.',
        ]);
        $this->assertGuest();
    }

    public function test_parent_children_page_lists_only_linked_children(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        $ownChild = $this->createChild($institution->id, 'Sajat Gyermek');
        $ownChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $otherGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Masik',
            'first_name' => 'Szulo',
            'email' => 'masik@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $otherChild = $this->createChild($institution->id, 'Masik Gyermek');
        $otherChild->guardians()->attach($otherGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->get($this->parentUrl('/gyermekeim'));

        $response->assertOk();
        $response->assertSee('Sajat Gyermek');
        $response->assertDontSee('Masik Gyermek');
    }

    public function test_parent_children_page_renders_rich_cards_for_multiple_children_with_dynamic_meal_and_discount_data(): void
    {
        CarbonImmutable::setTestNow('2026-07-29 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        $secondInstitution = Institution::create([
            'name' => 'Masodik Intezmeny',
            'institution_code' => 'PAR200',
            'type' => 'ovoda',
            'active' => true,
        ]);

        $secondGuardian = Guardian::create([
            'institution_id' => $secondInstitution->id,
            'user_id' => $user->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szulo 2',
            'email' => 'szulo.masodik@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 9,
            'cancellation_minute' => 0,
        ]);

        InstitutionMealSetting::create([
            'institution_id' => $secondInstitution->id,
            'cancellation_hour' => 8,
            'cancellation_minute' => 30,
        ]);

        $discount = DiscountType::create([
            'institution_id' => $institution->id,
            'name' => '50% kedvezmeny',
            'percentage' => 50,
            'active' => true,
            'sort_order' => 2,
        ]);

        $childWithMeal = Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => 'Elso Gyermek',
            'educational_identifier' => 'elso123456',
            'group_name' => '2.B',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
        $childWithMeal->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $childWithoutMeal = $this->createChild($secondInstitution->id, 'Masodik Gyermek');
        $childWithoutMeal->update([
            'group_name' => 'Napraforgo',
            'school_year' => '2026/2027',
        ]);
        $childWithoutMeal->guardians()->attach($secondGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $breakfast = MealType::create([
            'code' => 'reggeli',
            'name' => 'Reggeli',
            'default_order' => 1,
        ]);
        $lunch = MealType::create([
            'code' => 'ebed',
            'name' => 'Ebed',
            'default_order' => 2,
        ]);
        $snack = MealType::create([
            'code' => 'uzsonna',
            'name' => 'Uzsonna',
            'default_order' => 3,
        ]);

        $institutionBreakfast = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $breakfast->id,
            'is_active' => true,
            'is_parent_selectable' => true,
            'is_required' => false,
            'display_order' => 1,
        ]);
        $institutionLunch = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $lunch->id,
            'is_active' => true,
            'is_parent_selectable' => true,
            'is_required' => true,
            'display_order' => 2,
        ]);
        $institutionSnack = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $snack->id,
            'is_active' => false,
            'is_parent_selectable' => false,
            'is_required' => false,
            'display_order' => 3,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institution->id,
            'name' => 'Teljes csomag',
            'description' => null,
            'is_active' => true,
            'is_default' => false,
            'display_order' => 1,
            'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
            'created_by' => $user->id,
        ]);

        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionBreakfast->id,
            'display_order' => 1,
        ]);
        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionLunch->id,
            'display_order' => 2,
        ]);
        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionSnack->id,
            'display_order' => 3,
        ]);

        StudentMealSetting::create([
            'student_id' => $childWithMeal->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => $package->id,
            'mode' => StudentMealSetting::MODE_PACKAGE,
            'valid_from' => '2026-07-01',
            'valid_to' => null,
            'created_by' => $user->id,
            'note' => 'Dietas adag ellenorzott',
        ]);

        $restriction = DietaryRestriction::create([
            'institution_id' => $institution->id,
            'name' => 'Laktozmentes',
            'type' => DietaryRestriction::TYPE_INTOLERANCE,
            'active' => true,
            'sort_order' => 1,
        ]);
        $childWithMeal->dietaryRestrictions()->attach($restriction->id);

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'ab_menu_choice_deadline_day' => 20,
        ]);

        $plan = AbMenuPlan::create([
            'institution_id' => $institution->id,
            'title' => 'A/B menu terv',
            'valid_from' => '2026-07-01',
            'valid_to' => '2026-08-31',
            'active' => true,
            'published_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        AbMenuItem::create([
            'ab_menu_plan_id' => $plan->id,
            'menu_date' => '2026-07-30',
            'menu_a' => 'Paradicsomleves',
            'menu_b' => 'Rantott sajt',
            'menu_dietary' => 'Dietas foetel',
        ]);

        MenuChoice::create([
            'institution_id' => $institution->id,
            'child_id' => $childWithMeal->id,
            'menu_date' => '2026-07-30',
            'choice' => MenuChoice::CHOICE_B,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/gyermekeim'));

        $response->assertOk();
        $response->assertSee('Itt láthatod gyermekeid intézményi, étkezési és kedvezményadatait.', false);
        $response->assertSee('Elso Gyermek');
        $response->assertSee('Masodik Gyermek');
        $response->assertSee('Szuloi intezmeny');
        $response->assertSee('Masodik Intezmeny');
        $response->assertSee('2.B');
        $response->assertSee('Napraforgo');
        $response->assertSee('Teljes csomag');
        $response->assertSee('Reggeli');
        $response->assertSee('Ebed');
        $response->assertDontSee('Uzsonna');
        $response->assertSee('Laktozmentes');
        $response->assertSee('50% kedvezmeny');
        $response->assertSee('50%');
        $response->assertSee('Ehhez a gyermekhez jelenleg nincs aktív étkezési csomag beállítva.');
        $response->assertSee('Nincs aktív kedvezmény');
        $response->assertSee(htmlspecialchars(route('parent.children.show', $childWithMeal), ENT_QUOTES, 'UTF-8'), false);
        $response->assertSee(htmlspecialchars(route('parent.meal-cancellations', [
            'child_id' => $childWithMeal->id,
            'date' => '2026-07-29',
            'view' => 'week',
        ]), ENT_QUOTES, 'UTF-8'), false);
        $response->assertSee(htmlspecialchars(route('parent.meal-cancellations', [
            'child_id' => $childWithMeal->id,
            'date' => '2026-07-29',
            'view' => 'month',
        ]), ENT_QUOTES, 'UTF-8'), false);
    }

    public function test_parent_children_page_shows_empty_state_when_no_child_is_linked(): void
    {
        [$user] = $this->createParentContext();

        $response = $this->actingAs($user)->get($this->parentUrl('/gyermekeim'));

        $response->assertOk();
        $response->assertSee('Nincs kapcsolt gyermek');
        $response->assertSee('A kapcsolást az intézményi adminisztrátor tudja elvégezni.');
    }

    public function test_parent_cannot_open_other_guardians_child_by_url(): void
    {
        [$user, $institution] = $this->createParentContext();

        $foreignGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Tiltott',
            'first_name' => 'Kapcsolat',
            'email' => 'tiltott@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $foreignChild = $this->createChild($institution->id, 'Tiltott Gyermek');
        $foreignChild->guardians()->attach($foreignGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->get($this->parentUrl('/gyermekeim/'.$foreignChild->id));

        $response->assertNotFound();
    }

    public function test_parent_child_details_show_dynamic_meal_data_and_ab_menu_only_from_existing_relations(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Sajat Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $breakfast = MealType::create([
            'code' => 'reggeli',
            'name' => 'Reggeli',
            'default_order' => 1,
        ]);

        $lunch = MealType::create([
            'code' => 'ebed',
            'name' => 'Ebéd',
            'default_order' => 2,
        ]);

        $inactiveSnack = MealType::create([
            'code' => 'uzsonna',
            'name' => 'Uzsonna',
            'default_order' => 3,
        ]);

        $institutionBreakfast = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $breakfast->id,
            'is_active' => true,
            'is_parent_selectable' => true,
            'is_required' => false,
            'display_order' => 1,
        ]);

        $institutionLunch = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $lunch->id,
            'is_active' => true,
            'is_parent_selectable' => true,
            'is_required' => true,
            'display_order' => 2,
        ]);

        $institutionSnack = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $inactiveSnack->id,
            'is_active' => false,
            'is_parent_selectable' => false,
            'is_required' => false,
            'display_order' => 3,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institution->id,
            'name' => 'Teljes csomag',
            'description' => null,
            'is_active' => true,
            'is_default' => false,
            'display_order' => 1,
            'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
            'created_by' => $user->id,
        ]);

        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionBreakfast->id,
            'display_order' => 1,
        ]);

        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionLunch->id,
            'display_order' => 2,
        ]);

        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionSnack->id,
            'display_order' => 3,
        ]);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => $package->id,
            'mode' => StudentMealSetting::MODE_PACKAGE,
            'valid_from' => '2026-07-01',
            'valid_to' => null,
            'created_by' => $user->id,
            'note' => 'Allergia miatt ellenőrzött adag',
        ]);

        $restriction = DietaryRestriction::create([
            'institution_id' => $institution->id,
            'name' => 'Laktózmentes',
            'type' => DietaryRestriction::TYPE_INTOLERANCE,
            'active' => true,
            'sort_order' => 1,
        ]);

        $child->dietaryRestrictions()->attach($restriction->id);

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'ab_menu_choice_deadline_day' => 20,
        ]);

        $plan = AbMenuPlan::create([
            'institution_id' => $institution->id,
            'title' => 'A/B menü terv',
            'valid_from' => '2026-07-01',
            'valid_to' => '2026-08-31',
            'active' => true,
            'published_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        AbMenuItem::create([
            'ab_menu_plan_id' => $plan->id,
            'menu_date' => '2026-07-30',
            'menu_a' => 'Paradicsomleves',
            'menu_b' => 'Rántott sajt',
            'menu_dietary' => 'Diétás főétel',
        ]);

        MenuChoice::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'menu_date' => '2026-07-30',
            'choice' => MenuChoice::CHOICE_B,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/gyermekeim/'.$child->id));

        $response->assertOk();
        $response->assertSee('Étkezési adatok');
        $response->assertSee('Teljes csomag');
        $response->assertSee('Reggeli');
        $response->assertSee('Ebéd');
        $response->assertDontSee('Uzsonna');
        $response->assertSee('Laktózmentes');
        $response->assertSee('Allergia miatt ellenőrzött adag');
    }

    public function test_parent_child_details_show_missing_package_message_when_no_active_meal_setting_exists(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Sajat Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->get($this->parentUrl('/gyermekeim/'.$child->id));

        $response->assertOk();
        $response->assertSee('Étkezési adatok');
        $response->assertSee('Ehhez a gyermekhez jelenleg nincs aktív étkezési csomag beállítva.');
        $response->assertDontSee('A/B menü');
    }

    public function test_parent_child_details_show_closed_meal_relationship_instead_of_missing_package_error(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Lezart Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-07-01',
            'valid_to' => '2026-08-13',
            'created_by' => $user->id,
            'closed_by' => $user->id,
            'closed_at' => '2026-08-14 18:03:00',
            'closure_reason' => StudentMealSetting::CLOSURE_REASON_TRANSFER,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/gyermekeim/'.$child->id));

        $response->assertOk();
        $response->assertSee('Étkezési jogviszony lezárva');
        $response->assertSee('Utolsó étkezési nap');
        $response->assertSee('2026.08.13.');
        $response->assertSee('Intézményváltás');
        $response->assertDontSee('Ehhez a gyermekhez jelenleg nincs aktív étkezési csomag beállítva.');
    }

    public function test_parent_account_page_is_available(): void
    {
        [$user, , $guardian] = $this->createParentContext();

        $response = $this->actingAs($user)->get($this->parentUrl('/fiokom'));

        $response->assertOk();
        $response->assertSee('Saját adataim');
        $response->assertSee($user->email);
        $response->assertSee($guardian->full_name);
    }

    public function test_parent_can_update_own_contact_and_billing_data(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        $response = $this->actingAs($user)->put($this->parentUrl('/fiokom'), [
            'full_name' => 'Kovács Anna',
            'email' => 'anna.kovacs@example.com',
            'phone' => '+36 30 123 4567',
            'postal_code' => '2092',
            'city' => 'Budakeszi',
            'street_name' => 'Fő',
            'street_type' => 'utca',
            'house_number' => '12',
            'floor' => '2',
            'door' => '5',
            'billing_same_as_contact' => '1',
            'billing_name' => 'Kovács Anna',
            'billing_postal_code' => '',
            'billing_city' => '',
            'billing_address' => '',
            'tax_number' => '12345678-1-42',
        ]);

        $response->assertRedirect(route('parent.account'));
        $response->assertSessionHas('success_modal');

        $user->refresh();
        $guardian->refresh();
        $billingProfile = BillingProfile::where('guardian_id', $guardian->id)->latest()->first();

        $this->assertSame('Kovács Anna', $user->name);
        $this->assertSame('anna.kovacs@example.com', $user->email);
        $this->assertSame('+36 30 123 4567', $user->phone);
        $this->assertSame('Kovács', $guardian->last_name);
        $this->assertSame('Anna', $guardian->first_name);
        $this->assertSame('2092', $guardian->postal_code);
        $this->assertSame('Budakeszi', $guardian->city);
        $this->assertSame('Fő', $guardian->street_name);
        $this->assertSame('utca', $guardian->street_type);
        $this->assertSame('12', $guardian->house_number);
        $this->assertSame('2', $guardian->floor);
        $this->assertSame('5', $guardian->door);
        $this->assertNotNull($billingProfile);
        $this->assertTrue((bool) $billingProfile->active);
        $this->assertSame('guardian', $billingProfile->payer_type);
        $this->assertSame('Kovács Anna', $billingProfile->billing_name);
        $this->assertSame('12345678-1-42', $billingProfile->tax_number);
        $this->assertSame('2092', $billingProfile->postal_code);
        $this->assertSame('Budakeszi', $billingProfile->city);
        $this->assertSame('Fő utca 12 2 5', $billingProfile->address);
        $this->assertSame('anna.kovacs@example.com', $billingProfile->email);
        $this->assertSame($institution->id, $billingProfile->institution_id);
    }

    public function test_parent_account_keeps_old_input_when_billing_data_is_incomplete(): void
    {
        [$user] = $this->createParentContext();

        $response = $this->from($this->parentUrl('/fiokom'))
            ->actingAs($user)
            ->put($this->parentUrl('/fiokom'), [
                'full_name' => 'Teszt Szülő',
                'email' => 'szulo@example.com',
                'phone' => '+36 20 555 1234',
                'billing_name' => 'Teszt Szülő',
                'billing_postal_code' => '1111',
                'billing_city' => '',
                'billing_address' => '',
                'billing_same_as_contact' => '0',
            ]);

        $response->assertRedirect($this->parentUrl('/fiokom'));
        $response->assertSessionHasErrors(['billing_city', 'billing_address']);
        $response->assertSessionHasInput('billing_name', 'Teszt Szülő');
        $response->assertSessionHasInput('billing_postal_code', '1111');
    }

    public function test_parent_can_keep_existing_email_without_unique_validation_error(): void
    {
        [$user] = $this->createParentContext();

        $response = $this->actingAs($user)->put($this->parentUrl('/fiokom'), [
            'full_name' => 'Teszt Szulo',
            'email' => $user->email,
            'phone' => '',
            'postal_code' => '',
            'city' => '',
            'street_name' => '',
            'street_type' => '',
            'house_number' => '',
            'floor' => '',
            'door' => '',
            'billing_same_as_contact' => '0',
            'billing_name' => '',
            'billing_postal_code' => '',
            'billing_city' => '',
            'billing_address' => '',
            'tax_number' => '',
        ]);

        $response->assertRedirect(route('parent.account'));
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_parent_account_rejects_email_used_by_another_user(): void
    {
        [$user, $institution] = $this->createParentContext();

        User::factory()->create([
            'name' => 'Masik Szulo',
            'email' => 'masik.szulo@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $response = $this->from($this->parentUrl('/fiokom'))
            ->actingAs($user)
            ->put($this->parentUrl('/fiokom'), [
                'full_name' => 'Teszt Szulo',
                'email' => 'masik.szulo@example.com',
                'phone' => '',
                'postal_code' => '',
                'city' => '',
                'street_name' => '',
                'street_type' => '',
                'house_number' => '',
                'floor' => '',
                'door' => '',
                'billing_same_as_contact' => '0',
                'billing_name' => '',
                'billing_postal_code' => '',
                'billing_city' => '',
                'billing_address' => '',
                'tax_number' => '',
            ]);

        $response->assertRedirect($this->parentUrl('/fiokom'));
        $response->assertSessionHasErrors(['email']);
    }

    public function test_parent_account_updates_only_authenticated_parents_guardian_record(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        $otherGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Masik',
            'first_name' => 'Szulo',
            'email' => 'masik@example.com',
            'phone' => '+36 70 000 0000',
            'postal_code' => '9999',
            'city' => 'Pecs',
            'street_name' => 'Masik',
            'street_type' => 'utca',
            'house_number' => '9',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $this->actingAs($user)->put($this->parentUrl('/fiokom'), [
            'full_name' => 'Teszt Frissitett',
            'email' => 'frissitett@example.com',
            'phone' => '+36 30 999 0000',
            'postal_code' => '1024',
            'city' => 'Budapest',
            'street_name' => 'Fo',
            'street_type' => 'ut',
            'house_number' => '4',
            'floor' => '',
            'door' => '',
            'billing_same_as_contact' => '0',
            'billing_name' => '',
            'billing_postal_code' => '',
            'billing_city' => '',
            'billing_address' => '',
            'tax_number' => '',
        ]);

        $guardian->refresh();
        $otherGuardian->refresh();

        $this->assertSame('frissitett@example.com', $guardian->email);
        $this->assertSame('masik@example.com', $otherGuardian->email);
        $this->assertSame('9999', $otherGuardian->postal_code);
        $this->assertSame('Pecs', $otherGuardian->city);
    }

    public function test_parent_monthly_settlements_show_single_child_statement(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 15,
            'card_payment_enabled' => true,
        ]);

        $child = $this->createChild($institution->id, 'Sajat Gyermek');
        $child->update(['group_name' => '3.A']);
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 17850,
            'previous_cancellation_credit' => 1700,
            'billing_adjustment_amount' => 0,
            'invoiceable_amount' => 16150,
            'previous_balance' => 0,
            'total_payable' => 16150,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        MonthlyPaymentDay::create([
            'monthly_payment_statement_id' => $statement->id,
            'date' => '2026-07-01',
            'status' => MonthlyPaymentDay::STATUS_PAY,
            'original_daily_price' => 850,
            'discount_percent' => 0,
            'payable_amount' => 850,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));

        $response->assertOk();
        $response->assertSee('Havi elszámolások');
        $response->assertSee('2026. július');
        $response->assertSee('Sajat Gyermek');
        $response->assertSee('3.A');
        $response->assertSee('16 150 Ft');
        $response->assertSee('Teljes összeg befizetése');
    }

    public function test_parent_monthly_settlements_show_multiple_children_combined_total(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 12,
            'card_payment_enabled' => true,
        ]);

        $firstChild = $this->createChild($institution->id, 'Elso Gyermek');
        $secondChild = $this->createChild($institution->id, 'Masodik Gyermek');
        $firstChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $secondChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $firstChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 10000,
            'invoiceable_amount' => 10000,
            'previous_balance' => 0,
            'total_payable' => 10000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $secondChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 8000,
            'invoiceable_amount' => 8000,
            'previous_balance' => 0,
            'total_payable' => 8000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));

        $response->assertOk();
        $response->assertSee('Elso Gyermek');
        $response->assertSee('Masodik Gyermek');
        $response->assertSee('18 000 Ft');
    }

    public function test_parent_can_prepare_combined_payment_for_multiple_children(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => true,
            'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
            'card_payment_test_mode' => true,
            'cib_terminal_id' => 'SNL0001',
        ]);

        $gateway = Mockery::mock(CibCardPaymentGateway::class);
        $gateway->shouldReceive('initiate')
            ->once()
            ->andReturn(new CardPaymentInitiationResult(success: true));
        $this->app->instance(CibCardPaymentGateway::class, $gateway);

        $firstChild = $this->createChild($institution->id, 'Elso Gyermek');
        $secondChild = $this->createChild($institution->id, 'Masodik Gyermek');
        $firstChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $secondChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $firstStatement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $firstChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 12000,
            'invoiceable_amount' => 12000,
            'previous_balance' => 500,
            'total_payable' => 12500,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $secondStatement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $secondChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 7000,
            'invoiceable_amount' => 7000,
            'previous_balance' => 0,
            'total_payable' => 7000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $page = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));
        preg_match('/name="payment_intent_key" value="([^"]+)"/', $page->getContent(), $matches);

        $response = $this->actingAs($user)->post($this->parentUrl('/havi-elszamolasok/fizetes'), [
            'month' => '2026-07',
            'payment_intent_key' => $matches[1] ?? '',
        ]);

        $response->assertRedirect(route('parent.monthly-settlements.index', ['month' => '2026-07']));
        $response->assertSessionHas('success');

        $payment = ParentMonthlySettlementPayment::query()->first();
        $this->assertNotNull($payment);
        $this->assertSame(19500, $payment->total_amount);
        $this->assertSame(2, $payment->items()->count());
        $this->assertDatabaseHas('parent_monthly_settlement_payment_items', [
            'parent_monthly_settlement_payment_id' => $payment->id,
            'monthly_payment_statement_id' => $firstStatement->id,
            'amount' => 12500,
        ]);
        $this->assertDatabaseHas('parent_monthly_settlement_payment_items', [
            'parent_monthly_settlement_payment_id' => $payment->id,
            'monthly_payment_statement_id' => $secondStatement->id,
            'amount' => 7000,
        ]);
    }

    public function test_already_paid_child_is_not_recounted_in_combined_payment(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => true,
        ]);

        $firstChild = $this->createChild($institution->id, 'Elso Gyermek');
        $secondChild = $this->createChild($institution->id, 'Masodik Gyermek');
        $firstChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $secondChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $paidStatement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $firstChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 6000,
            'invoiceable_amount' => 6000,
            'previous_balance' => 0,
            'total_payable' => 6000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $openStatement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $secondChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 9000,
            'invoiceable_amount' => 9000,
            'previous_balance' => 0,
            'total_payable' => 9000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $firstChild->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $paidStatement->id,
            'amount' => 6000,
            'paid_at' => now(),
            'payment_method' => InstitutionPayment::METHOD_CARD,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'recorded_by' => $user->id,
        ]);

        $page = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));
        preg_match('/name="payment_intent_key" value="([^"]+)"/', $page->getContent(), $matches);

        $this->actingAs($user)->post($this->parentUrl('/havi-elszamolasok/fizetes'), [
            'month' => '2026-07',
            'payment_intent_key' => $matches[1] ?? '',
        ]);

        $payment = ParentMonthlySettlementPayment::query()->first();
        $this->assertNotNull($payment);
        $this->assertSame(9000, $payment->total_amount);
        $this->assertDatabaseMissing('parent_monthly_settlement_payment_items', [
            'parent_monthly_settlement_payment_id' => $payment->id,
            'monthly_payment_statement_id' => $paidStatement->id,
        ]);
        $this->assertDatabaseHas('parent_monthly_settlement_payment_items', [
            'parent_monthly_settlement_payment_id' => $payment->id,
            'monthly_payment_statement_id' => $openStatement->id,
            'amount' => 9000,
        ]);
    }

    public function test_partially_paid_statement_only_counts_remaining_amount(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => true,
        ]);

        $child = $this->createChild($institution->id, 'Reszben Fizetett Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 10000,
            'invoiceable_amount' => 10000,
            'previous_balance' => 0,
            'total_payable' => 10000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => 4000,
            'paid_at' => now(),
            'payment_method' => InstitutionPayment::METHOD_BANK_TRANSFER,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'recorded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));

        $response->assertOk();
        $response->assertSee('Részben fizetve');
        $response->assertSee('6 000 Ft');
    }

    public function test_settlement_page_shows_discount_and_cancellation_together(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => true,
        ]);

        $discount = DiscountType::create([
            'institution_id' => $institution->id,
            'name' => '50% kedvezmeny',
            'percentage' => 50,
            'active' => true,
            'sort_order' => 2,
        ]);

        $child = Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => 'Kedvezmenyes Gyermek',
            'educational_identifier' => 'kedv123456',
            'group_name' => '4.B',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'discount_id' => $discount->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 8500,
            'previous_cancellation_credit' => 1700,
            'invoiceable_amount' => 6800,
            'previous_balance' => 0,
            'total_payable' => 6800,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        MonthlyPaymentDay::create([
            'monthly_payment_statement_id' => $statement->id,
            'date' => '2026-07-01',
            'status' => MonthlyPaymentDay::STATUS_PAY,
            'original_daily_price' => 1700,
            'discount_percent' => 50,
            'payable_amount' => 850,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));

        $response->assertOk();
        $response->assertSee('50%-os kedvezmény');
        $response->assertSee('2026. júniusi lemondások jóváírása');
    }

    public function test_parent_cannot_access_other_parents_monthly_settlement_data(): void
    {
        [$user, $institution] = $this->createParentContext();

        $foreignGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Masik',
            'first_name' => 'Szulo',
            'email' => 'masik@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $foreignChild = $this->createChild($institution->id, 'Tiltott Gyermek');
        $foreignChild->guardians()->attach($foreignGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $foreignChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 9000,
            'invoiceable_amount' => 9000,
            'previous_balance' => 0,
            'total_payable' => 9000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));

        $response->assertOk();
        $response->assertDontSee('Tiltott Gyermek');
        $response->assertSee('Még nincs kiszámítva');
    }

    public function test_double_payment_request_reuses_same_pending_payment(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => true,
        ]);

        $child = $this->createChild($institution->id, 'Dupla Kattintas');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 5000,
            'invoiceable_amount' => 5000,
            'previous_balance' => 0,
            'total_payable' => 5000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $page = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));
        preg_match('/name="payment_intent_key" value="([^"]+)"/', $page->getContent(), $matches);
        $payload = [
            'month' => '2026-07',
            'payment_intent_key' => $matches[1] ?? '',
        ];

        $this->actingAs($user)->post($this->parentUrl('/havi-elszamolasok/fizetes'), $payload);
        $this->actingAs($user)->post($this->parentUrl('/havi-elszamolasok/fizetes'), $payload);

        $this->assertSame(1, ParentMonthlySettlementPayment::query()->count());
    }

    public function test_monthly_settlements_show_warning_when_month_is_not_closed(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Nyitott Honap');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 9000,
            'invoiceable_amount' => 9000,
            'previous_balance' => 0,
            'total_payable' => 9000,
            'status' => MonthlyPaymentStatement::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));

        $response->assertOk();
        $response->assertSee('Elszámolás előkészítés alatt');
        $response->assertSee('Az intézmény még nem zárta le ezt a hónapot. A végleges összeg később jelenik meg.');
    }

    public function test_monthly_settlements_show_missing_price_warning(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Hianyos Ar');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 0,
            'invoiceable_amount' => 0,
            'previous_balance' => 0,
            'total_payable' => 0,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'issues' => ['Hianyos Ar: nincs ervenyes ar 2026.07.01 napra.'],
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));

        $response->assertOk();
        $response->assertSee('Van olyan gyermek, akinél hiányzó ár');
        $response->assertSee('Hianyos Ar: nincs ervenyes ar 2026.07.01 napra.');
    }

    public function test_monthly_settlements_show_paid_state_card_when_fully_paid(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => true,
        ]);

        $child = $this->createChild($institution->id, 'Rendezett Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 5000,
            'invoiceable_amount' => 5000,
            'previous_balance' => 0,
            'total_payable' => 5000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => 5000,
            'paid_at' => now(),
            'payment_method' => InstitutionPayment::METHOD_CARD,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'recorded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));

        $response->assertOk();
        $response->assertSee('Rendezve');
        $response->assertSee('Köszönjük! A havi díj befizetésre került.');
        $response->assertDontSee('Teljes összeg befizetése');
    }

    public function test_monthly_settlements_show_no_amount_due_state_card(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => true,
        ]);

        $child = $this->createChild($institution->id, 'Nullas Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 0,
            'invoiceable_amount' => 0,
            'previous_balance' => 0,
            'total_payable' => 0,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));

        $response->assertOk();
        $response->assertSee('Nincs fizetendő összeg');
        $response->assertSee('Erre a hónapra nem keletkezett fizetendő összeg.');
        $response->assertDontSee('Teljes összeg befizetése');
    }

    public function test_monthly_settlements_without_parameter_open_current_month(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Aktualis Honap Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 1000,
            'invoiceable_amount' => 1000,
            'previous_balance' => 0,
            'total_payable' => 1000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok'));

        $response->assertOk();
        $response->assertSee('2026. július');
        $response->assertDontSee('Aktuális hónap');

        CarbonImmutable::setTestNow();
    }

    public function test_monthly_settlements_links_use_selected_month_for_navigation(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Navigacios Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 8,
            'meal_amount' => 1000,
            'invoiceable_amount' => 1000,
            'previous_balance' => 0,
            'total_payable' => 1000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-08'));

        $response->assertOk();
        $response->assertSee('2026. augusztus');
        $response->assertSee(htmlspecialchars(route('parent.monthly-settlements.index', ['month' => '2026-07']), ENT_QUOTES, 'UTF-8'), false);
        $response->assertSee(htmlspecialchars(route('parent.monthly-settlements.index', ['month' => '2026-09']), ENT_QUOTES, 'UTF-8'), false);
        $response->assertSee('Aktuális hónap');
        $response->assertSee(htmlspecialchars(route('parent.monthly-settlements.index', ['month' => '2026-07']), ENT_QUOTES, 'UTF-8'), false);

        CarbonImmutable::setTestNow();
    }

    public function test_monthly_settlements_can_step_forward_and_back_multiple_times(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Lepkedo Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        foreach ([7, 8, 9] as $monthNumber) {
            MonthlyPaymentStatement::create([
                'institution_id' => $institution->id,
                'child_id' => $child->id,
                'year' => 2026,
                'month' => $monthNumber,
                'meal_amount' => 1000,
                'invoiceable_amount' => 1000,
                'previous_balance' => 0,
                'total_payable' => 1000,
                'status' => MonthlyPaymentStatement::STATUS_CLOSED,
                'closed_at' => now(),
            ]);
        }

        $august = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-08'));
        $august->assertOk();
        $august->assertSee('2026. augusztus');
        $august->assertSee(htmlspecialchars(route('parent.monthly-settlements.index', ['month' => '2026-07']), ENT_QUOTES, 'UTF-8'), false);
        $august->assertSee(htmlspecialchars(route('parent.monthly-settlements.index', ['month' => '2026-09']), ENT_QUOTES, 'UTF-8'), false);

        $september = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-09'));
        $september->assertOk();
        $september->assertSee('2026. szeptember');
        $september->assertSee(htmlspecialchars(route('parent.monthly-settlements.index', ['month' => '2026-08']), ENT_QUOTES, 'UTF-8'), false);

        $backToAugust = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-08'));
        $backToAugust->assertOk();
        $backToAugust->assertSee('2026. augusztus');

        $backToJuly = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));
        $backToJuly->assertOk();
        $backToJuly->assertSee('2026. július');

        CarbonImmutable::setTestNow();
    }

    public function test_monthly_settlements_current_month_link_returns_to_current_month(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Visszaugras Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 1000,
            'invoiceable_amount' => 1000,
            'previous_balance' => 0,
            'total_payable' => 1000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-09'));

        $response->assertOk();
        $response->assertSee('Aktuális hónap');
        $response->assertSee(htmlspecialchars(route('parent.monthly-settlements.index', ['month' => '2026-07']), ENT_QUOTES, 'UTF-8'), false);

        CarbonImmutable::setTestNow();
    }

    public function test_monthly_settlements_invalid_month_parameter_falls_back_to_current_month(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Hibas Datum Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 1000,
            'invoiceable_amount' => 1000,
            'previous_balance' => 0,
            'total_payable' => 1000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-13'));

        $response->assertOk();
        $response->assertSee('2026. július');

        CarbonImmutable::setTestNow();
    }

    public function test_parent_payments_page_renders_live_financial_overview_and_history(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => true,
        ]);

        $child = $this->createChild($institution->id, 'Fizeto Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $statement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 8000,
            'invoiceable_amount' => 8000,
            'previous_balance' => 0,
            'total_payable' => 8000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        MonthlyPaymentDay::create([
            'monthly_payment_statement_id' => $statement->id,
            'date' => '2026-07-21',
            'status' => MonthlyPaymentDay::STATUS_PAY,
            'original_daily_price' => 2000,
            'discount_percent' => 0,
            'payable_amount' => 2000,
        ]);

        InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => 3000,
            'paid_at' => '2026-07-28 09:15:00',
            'payment_method' => InstitutionPayment::METHOD_CARD,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => 'TX-2026-07-001',
            'recorded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/befizetesek'));

        $response->assertOk();
        $response->assertSee('5 000 Ft');
        $response->assertSee('2026. július');
        $response->assertSee('Fizeto Gyermek');
        $response->assertSee('TX-2026-07-001');
        $response->assertSee('Befizetés bankkártyával');
        $response->assertSee('Lejárt');

        CarbonImmutable::setTestNow();
    }

    public function test_parent_payments_page_hides_foreign_data_and_shows_guidance_when_card_payment_is_disabled(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => false,
        ]);

        $ownChild = $this->createChild($institution->id, 'Sajat Fizeto');
        $ownChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $ownChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 6400,
            'invoiceable_amount' => 6400,
            'previous_balance' => 0,
            'total_payable' => 6400,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $foreignGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Masik',
            'first_name' => 'Szulo',
            'email' => 'masik-fizeto@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $foreignChild = $this->createChild($institution->id, 'Tiltott Fizeto');
        $foreignChild->guardians()->attach($foreignGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $foreignStatement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $foreignChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 9100,
            'invoiceable_amount' => 9100,
            'previous_balance' => 0,
            'total_payable' => 9100,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        InstitutionPayment::create([
            'institution_id' => $institution->id,
            'child_id' => $foreignChild->id,
            'guardian_id' => $foreignGuardian->id,
            'monthly_payment_statement_id' => $foreignStatement->id,
            'amount' => 9100,
            'paid_at' => '2026-07-20 08:00:00',
            'payment_method' => InstitutionPayment::METHOD_BANK_TRANSFER,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => 'FOREIGN-TX',
            'recorded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/befizetesek'));

        $response->assertOk();
        $response->assertSee('Sajat Fizeto');
        $response->assertDontSee('Tiltott Fizeto');
        $response->assertDontSee('FOREIGN-TX');
        $response->assertSee('A befizetés módjáról az intézmény tájékoztatása az irányadó.');
        $response->assertDontSee('Befizetés bankkártyával');

        CarbonImmutable::setTestNow();
    }

    public function test_parent_payments_page_shows_empty_states_without_children_or_history(): void
    {
        [$user] = $this->createParentContext();

        $response = $this->actingAs($user)->get($this->parentUrl('/befizetesek'));

        $response->assertOk();
        $response->assertSee('Nincs kapcsolt gyermek');
        $response->assertSee('Még nincs megjeleníthető befizetési előzmény.');
        $response->assertSee('0 Ft');
    }

    public function test_parent_invoices_page_shows_empty_state_without_any_invoice(): void
    {
        [$user] = $this->createParentContext();

        $response = $this->actingAs($user)->get($this->parentUrl('/szamlak'));

        $response->assertOk();
        $response->assertSee('Még nincs kiállított számlája.');
        $response->assertSee('0 db');
    }

    public function test_parent_invoices_page_lists_only_visible_invoices_and_calculates_stats(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        $ownChild = $this->createChild($institution->id, 'Szamlas Gyermek');
        $ownChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $ownStatement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $ownChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 12000,
            'invoiceable_amount' => 12000,
            'previous_balance' => 0,
            'total_payable' => 12000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->createInstitutionInvoice([
            'institution_id' => $institution->id,
            'child_id' => $ownChild->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $ownStatement->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'provider_invoice_id' => 'manual-1',
            'invoice_number' => 'DF-2026-0001',
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'issue_date' => '2026-07-08',
            'due_date' => '2026-07-15',
            'fulfillment_date' => '2026-07-08',
            'net_amount' => 10000,
            'vat_amount' => 2000,
            'gross_amount' => 12000,
            'currency' => 'HUF',
            'payment_method' => InstitutionPayment::METHOD_BANK_TRANSFER,
            'customer_name' => 'Teszt Szulo',
            'customer_email' => 'szulo@example.com',
            'billing_postcode' => '7621',
            'billing_city' => 'Pecs',
            'billing_address' => 'Fo utca 1.',
            'invoice_url' => 'https://example.com/invoices/df-2026-0001.pdf',
            'created_by' => $user->id,
        ]);

        $foreignGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Masik',
            'first_name' => 'Szulo',
            'email' => 'masik-szamla@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $foreignChild = $this->createChild($institution->id, 'Tiltott Szamla');
        $foreignChild->guardians()->attach($foreignGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $foreignStatement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $foreignChild->id,
            'year' => 2026,
            'month' => 6,
            'meal_amount' => 9000,
            'invoiceable_amount' => 9000,
            'previous_balance' => 0,
            'total_payable' => 9000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->createInstitutionInvoice([
            'institution_id' => $institution->id,
            'child_id' => $foreignChild->id,
            'guardian_id' => $foreignGuardian->id,
            'monthly_payment_statement_id' => $foreignStatement->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'provider_invoice_id' => 'manual-2',
            'invoice_number' => 'FOREIGN-2026-1',
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'fulfillment_date' => '2026-06-01',
            'net_amount' => 7500,
            'vat_amount' => 1500,
            'gross_amount' => 9000,
            'currency' => 'HUF',
            'payment_method' => InstitutionPayment::METHOD_CARD,
            'customer_name' => 'Masik Szulo',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Masik utca 2.',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/szamlak'));

        $response->assertOk();
        $response->assertSee('DF-2026-0001');
        $response->assertDontSee('FOREIGN-2026-1');
        $response->assertSee('1 db');
        $response->assertSee('12 000 Ft');
        $response->assertSee('2026. július 8.');
        $response->assertSee('PDF letöltése');
        $response->assertSee('Megtekintés');

        CarbonImmutable::setTestNow();
    }

    public function test_parent_invoices_page_supports_year_filter_and_keeps_it_in_pagination(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        $child = $this->createChild($institution->id, 'Szuro Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        foreach (range(1, 11) as $index) {
            $statement = MonthlyPaymentStatement::create([
                'institution_id' => $institution->id,
                'child_id' => $child->id,
                'year' => 2025,
                'month' => $index,
                'meal_amount' => 1000 + $index,
                'invoiceable_amount' => 1000 + $index,
                'previous_balance' => 0,
                'total_payable' => 1000 + $index,
                'status' => MonthlyPaymentStatement::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            $this->createInstitutionInvoice([
                'institution_id' => $institution->id,
                'child_id' => $child->id,
                'guardian_id' => $guardian->id,
                'monthly_payment_statement_id' => $statement->id,
                'provider' => InstitutionInvoice::PROVIDER_MANUAL,
                'provider_invoice_id' => 'old-'.$index,
                'invoice_number' => 'OLD-2025-'.$index,
                'status' => InstitutionInvoice::STATUS_ISSUED,
                'issue_date' => '2025-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'-10',
                'due_date' => '2025-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'-28',
                'fulfillment_date' => '2025-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'-15',
                'net_amount' => 1000,
                'vat_amount' => 0,
                'gross_amount' => 1000,
                'currency' => 'HUF',
                'payment_method' => InstitutionPayment::METHOD_BANK_TRANSFER,
                'customer_name' => 'Teszt Szulo',
                'billing_postcode' => '7621',
                'billing_city' => 'Pecs',
                'billing_address' => 'Fo utca 1.',
                'created_by' => $user->id,
            ]);
        }

        $response = $this->actingAs($user)->get($this->parentUrl('/szamlak?year=earlier'));

        $response->assertOk();
        $response->assertSee('OLD-2025-11');
        $response->assertSee('year=earlier', false);

        CarbonImmutable::setTestNow();
    }

    public function test_parent_can_download_own_invoice_document_but_not_foreign_one(): void
    {
        Storage::fake('public');

        [$user, $institution, $guardian] = $this->createParentContext();

        $ownChild = $this->createChild($institution->id, 'Letoltheto Gyermek');
        $ownChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $ownStatement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $ownChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 5000,
            'invoiceable_amount' => 5000,
            'previous_balance' => 0,
            'total_payable' => 5000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        Storage::disk('public')->put('invoices/own-invoice.pdf', 'pdf-content');

        $ownInvoice = $this->createInstitutionInvoice([
            'institution_id' => $institution->id,
            'child_id' => $ownChild->id,
            'guardian_id' => $guardian->id,
            'monthly_payment_statement_id' => $ownStatement->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'invoice_number' => 'OWN-PDF-1',
            'issue_date' => '2026-07-05',
            'due_date' => '2026-07-15',
            'fulfillment_date' => '2026-07-05',
            'net_amount' => 5000,
            'vat_amount' => 0,
            'gross_amount' => 5000,
            'currency' => 'HUF',
            'payment_method' => InstitutionPayment::METHOD_CARD,
            'customer_name' => 'Teszt Szulo',
            'billing_postcode' => '7621',
            'billing_city' => 'Pecs',
            'billing_address' => 'Fo utca 1.',
            'invoice_pdf_path' => 'invoices/own-invoice.pdf',
            'created_by' => $user->id,
        ]);

        $download = $this->actingAs($user)->get($this->parentUrl('/szamlak/'.$ownInvoice->id.'/letoltes'));
        $download->assertOk();

        $foreignGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Idegen',
            'first_name' => 'Szulo',
            'email' => 'idegen-pdf@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $foreignChild = $this->createChild($institution->id, 'Idegen Gyermek');
        $foreignChild->guardians()->attach($foreignGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $foreignStatement = MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $foreignChild->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 5000,
            'invoiceable_amount' => 5000,
            'previous_balance' => 0,
            'total_payable' => 5000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $foreignInvoice = $this->createInstitutionInvoice([
            'institution_id' => $institution->id,
            'child_id' => $foreignChild->id,
            'guardian_id' => $foreignGuardian->id,
            'monthly_payment_statement_id' => $foreignStatement->id,
            'provider' => InstitutionInvoice::PROVIDER_MANUAL,
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'invoice_number' => 'FOREIGN-PDF-1',
            'issue_date' => '2026-07-05',
            'due_date' => '2026-07-15',
            'fulfillment_date' => '2026-07-05',
            'net_amount' => 5000,
            'vat_amount' => 0,
            'gross_amount' => 5000,
            'currency' => 'HUF',
            'payment_method' => InstitutionPayment::METHOD_CARD,
            'customer_name' => 'Idegen Szulo',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Idegen utca 2.',
            'invoice_pdf_path' => 'invoices/foreign-invoice.pdf',
            'created_by' => $user->id,
        ]);

        $forbidden = $this->actingAs($user)->get($this->parentUrl('/szamlak/'.$foreignInvoice->id.'/letoltes'));
        $forbidden->assertNotFound();
    }

    public function test_parent_monthly_settlements_page_shows_dynamic_merchant_summary_and_cib_links(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => true,
            'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
        ]);

        $child = $this->createChild($institution->id, 'Fizeto Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => 6000,
            'invoiceable_amount' => 6000,
            'previous_balance' => 0,
            'total_payable' => 6000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month=2026-07'));

        $response->assertOk();
        $response->assertSee('CIB fizetési tájékoztató');
        $response->assertSee('CIB GYFK');
        $response->assertSee('Reklamáció és visszatérítés');
        $response->assertSee('Szuloi Intezmeny Fenntarto');
        $response->assertSee('HU');
    }

    public function test_parent_legal_pages_render_cib_content_and_footer_links(): void
    {
        [$user] = $this->createParentContext();

        $cardPayment = $this->actingAs($user)->get($this->parentUrl('/bankkartyas-fizetesi-tajekoztato'));
        $cardPayment->assertOk();
        $cardPayment->assertSee('CIB Bank Zrt.');
        $cardPayment->assertSee('HU');
        $cardPayment->assertSee('CIB GYFK');

        $faq = $this->actingAs($user)->get($this->parentUrl('/bankkartyas-fizetesi-gyik'));
        $faq->assertOk();
        $faq->assertSee('Mastercard');

        $flow = $this->actingAs($user)->get($this->parentUrl('/fizetesi-folyamat'));
        $flow->assertOk();
        $flow->assertSee('TRID');
        $flow->assertSee('AMO');

        $complaints = $this->actingAs($user)->get($this->parentUrl('/reklamacio-es-visszaterites'));
        $complaints->assertOk();
        $complaints->assertSee('ANUM');

        $customerService = $this->actingAs($user)->get($this->parentUrl('/ugyfelszolgalat'));
        $customerService->assertOk();
        $customerService->assertSee('intezmeny@example.com');

        $imprint = $this->actingAs($user)->get($this->parentUrl('/impresszum'));
        $imprint->assertOk();
        $imprint->assertSee('12345678-2-41');
    }

    public function test_parent_menu_choice_page_shows_published_plan_and_default_a_choice_without_record(): void
    {
        CarbonImmutable::setTestNow('2026-08-12 10:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'ab_menu_choice_deadline_day' => 20,
        ]);

        $child = $this->createChild($institution->id, 'Valaszto Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $plan = AbMenuPlan::create([
            'institution_id' => $institution->id,
            'title' => 'Szeptemberi A/B menü',
            'valid_from' => '2026-09-01',
            'valid_to' => '2026-09-30',
            'active' => true,
            'published_at' => '2026-08-10 09:00:00',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $item = AbMenuItem::create([
            'ab_menu_plan_id' => $plan->id,
            'menu_date' => '2026-09-01',
            'menu_a' => 'A menü teszt',
            'menu_b' => 'B menü teszt',
        ]);

        $response = $this->actingAs($user)->get($this->parentUrl('/menuvalasztas'));

        $response->assertOk();
        $response->assertSee('Valaszto Gyermek');
        $response->assertSee('Szeptemberi A/B menü');
        $response->assertSee('Választási határidő');
        $response->assertSee('A választás a határidő napján 23:59-ig módosítható.');
        $response->assertSee('choice-a-'.$child->id.'-'.$item->id, false);
        $response->assertSee('checked', false);

        CarbonImmutable::setTestNow();
    }

    public function test_parent_menu_choice_update_saves_explicit_b_and_deletes_record_when_switched_back_to_a(): void
    {
        CarbonImmutable::setTestNow('2026-08-12 10:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'ab_menu_choice_deadline_day' => 20,
        ]);

        $child = $this->createChild($institution->id, 'Mento Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $plan = AbMenuPlan::create([
            'institution_id' => $institution->id,
            'title' => 'Szeptemberi választás',
            'valid_from' => '2026-09-01',
            'valid_to' => '2026-09-30',
            'active' => true,
            'published_at' => '2026-08-11 08:00:00',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $item = AbMenuItem::create([
            'ab_menu_plan_id' => $plan->id,
            'menu_date' => '2026-09-02',
            'menu_a' => 'A menü',
            'menu_b' => 'B menü',
        ]);

        $saveB = $this->actingAs($user)->put($this->parentUrl('/menuvalasztas/'.$child->id), [
            'choices' => [
                $item->id => MenuChoice::CHOICE_B,
            ],
        ]);

        $saveB->assertRedirect();
        $saveB->assertSessionHas('success');
        $this->assertDatabaseHas('menu_choices', [
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'ab_menu_item_id' => $item->id,
            'choice' => MenuChoice::CHOICE_B,
            'selected_by' => $user->id,
        ]);

        $saveA = $this->actingAs($user)->put($this->parentUrl('/menuvalasztas/'.$child->id), [
            'choices' => [
                $item->id => MenuChoice::CHOICE_A,
            ],
        ]);

        $saveA->assertRedirect();
        $saveA->assertSessionHas('success');
        $this->assertDatabaseMissing('menu_choices', [
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'ab_menu_item_id' => $item->id,
        ]);

        CarbonImmutable::setTestNow();
    }

    public function test_parent_menu_choice_update_rejects_foreign_child_and_dietary_child_submission(): void
    {
        CarbonImmutable::setTestNow('2026-08-12 10:00:00');

        [$user, $institution, $guardian] = $this->createParentContext();

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'ab_menu_choice_deadline_day' => 20,
        ]);

        $plan = AbMenuPlan::create([
            'institution_id' => $institution->id,
            'title' => 'Szeptemberi tiltások',
            'valid_from' => '2026-09-01',
            'valid_to' => '2026-09-30',
            'active' => true,
            'published_at' => '2026-08-11 08:00:00',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $item = AbMenuItem::create([
            'ab_menu_plan_id' => $plan->id,
            'menu_date' => '2026-09-03',
            'menu_a' => 'A menü',
            'menu_b' => 'B menü',
            'menu_dietary' => 'Diétás menü',
        ]);

        $foreignGuardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Masik',
            'first_name' => 'Szulo',
            'email' => 'masik-menu@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $foreignChild = $this->createChild($institution->id, 'Tiltott Gyermek');
        $foreignChild->guardians()->attach($foreignGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $foreignResponse = $this->actingAs($user)->put($this->parentUrl('/menuvalasztas/'.$foreignChild->id), [
            'choices' => [
                $item->id => MenuChoice::CHOICE_B,
            ],
        ]);

        $foreignResponse->assertNotFound();

        $dietaryChild = $this->createChild($institution->id, 'Diétás Gyermek');
        $dietaryChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $restriction = DietaryRestriction::create([
            'institution_id' => $institution->id,
            'name' => 'Gluténmentes',
            'type' => DietaryRestriction::TYPE_INTOLERANCE,
            'active' => true,
            'sort_order' => 1,
        ]);
        $dietaryChild->dietaryRestrictions()->attach($restriction->id);

        $dietaryResponse = $this->from($this->parentUrl('/menuvalasztas'))
            ->actingAs($user)
            ->put($this->parentUrl('/menuvalasztas/'.$dietaryChild->id), [
                'choices' => [
                    $item->id => MenuChoice::CHOICE_B,
                ],
            ]);

        $dietaryResponse->assertRedirect($this->parentUrl('/menuvalasztas'));
        $dietaryResponse->assertSessionHasErrors('choices');
        $this->assertDatabaseMissing('menu_choices', [
            'child_id' => $dietaryChild->id,
            'ab_menu_item_id' => $item->id,
        ]);

        CarbonImmutable::setTestNow();
    }

    private function createParentContext(): array
    {
        $institution = Institution::create([
            'name' => 'Szuloi intezmeny',
            'institution_code' => 'PAR100',
            'type' => 'iskola',
            'address_zip' => '1024',
            'address_city' => 'Budapest',
            'address_line' => 'Fo utca 1.',
            'email' => 'intezmeny@example.com',
            'phone' => '+36 1 999 0000',
            'billing_name' => 'Szuloi Intezmeny Fenntarto',
            'billing_tax_number' => '12345678-2-41',
            'billing_zip' => '1024',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'om_identifier' => '100565',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Teszt Szulo',
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
            'first_name' => 'Szulo',
            'email' => $user->email,
            'source_type' => 'manual',
            'active' => true,
        ]);

        return [$user, $institution, $guardian];
    }

    private function parentUrl(string $path): string
    {
        return (parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'http').'://'.(parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost').((parse_url(config('app.url'), PHP_URL_PORT) ? ':'.parse_url(config('app.url'), PHP_URL_PORT) : '')).'/szulo'.$path;
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

    private function createInstitutionInvoice(array $attributes): InstitutionInvoice
    {
        $invoice = new InstitutionInvoice;
        $invoice->forceFill($attributes);
        $invoice->save();

        return $invoice->fresh();
    }
}
