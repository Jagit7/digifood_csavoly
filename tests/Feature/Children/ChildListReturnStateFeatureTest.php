<?php

namespace Tests\Feature\Children;

use App\Http\Controllers\Dashboard\InstitutionAdmin\BillingAddressController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildMealSettingController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionMealParticipantController;
use App\Models\Child;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionMealType;
use App\Models\MealType;
use App\Models\StudentMealSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
use Tests\TestCase;

class ChildListReturnStateFeatureTest extends TestCase
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

    public function test_child_update_preserves_children_list_state_and_sanitizes_query(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CHRET001');
        $discount = $this->createDiscountType($institution->id, 'Teljes ár', 0);
        $child = $this->createChild($institution, [
            'name' => 'Kiss Pali',
            'educational_identifier' => 'CHRET001-CHILD',
            'group_name' => '7.A',
            'discount_type_id' => $discount->id,
        ]);
        $this->createChild($institution, [
            'name' => 'Masik Hetedik',
            'educational_identifier' => 'CHRET001-KEEP',
            'group_name' => '7.A',
            'discount_type_id' => $discount->id,
        ]);

        $this->actingAs($user);

        $response = $this->childController()->update(
            $this->makeRequest(
                $user,
                route('dashboard.institution.children.update', $child),
                'PUT',
                [
                    'return_list' => 'children',
                    'return_query' => "page=4&search=Kiss&group_name=7.A&status=active&data_quality=no_email&verified_status=unverified&meal_status=participant&return_to=https://evil.test\r\nLocation:https://evil.test",
                    'name' => 'Kiss Palina',
                    'educational_identifier' => 'CHRET001-CHILD',
                    'discount_type_id' => $discount->id,
                    'discount_valid_from' => '2026-08-24',
                    'is_eater' => '0',
                    'active' => '1',
                ]
            ),
            $child
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(
            route('dashboard.institution.children.index', [
                'page' => 4,
                'search' => 'Kiss',
                'group_name' => '7.A',
                'status' => 'active',
                'data_quality' => 'no_email',
                'verified_status' => 'unverified',
            ]),
            $response->getTargetUrl()
        );
        $this->assertDatabaseHas('children', [
            'id' => $child->id,
            'name' => 'Kiss Palina',
        ]);
    }

    public function test_child_update_can_return_to_basics_list(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CHRET002');
        $discount = $this->createDiscountType($institution->id, 'Teljes ár', 0);
        $restriction = $this->createDietaryRestriction($institution->id, 'Tejmentes');
        $child = $this->createChild($institution, [
            'name' => 'Alapadat Anna',
            'educational_identifier' => 'CHRET002-CHILD',
            'group_name' => '5.B',
            'discount_type_id' => $discount->id,
        ]);

        $this->actingAs($user);

        $response = $this->childController()->update(
            $this->makeRequest(
                $user,
                route('dashboard.institution.children.update', $child),
                'PUT',
                [
                    'return_list' => 'basics',
                    'return_query' => 'page=2&search=Anna&sort=name&quality=no_email&discount_type_id=any&dietary_restriction_id='.$restriction->id.'&opened_child='.$child->id,
                    'name' => 'Alapadat Anna Frissítve',
                    'educational_identifier' => 'CHRET002-CHILD',
                    'discount_type_id' => $discount->id,
                    'discount_valid_from' => '2026-08-24',
                    'is_eater' => '0',
                    'active' => '1',
                ]
            ),
            $child
        );

        $this->assertSame(
            route('dashboard.institution.billing-addresses.index', [
                'page' => 2,
                'search' => 'Anna',
                'sort' => 'name',
                'quality' => 'no_email',
                'discount_type_id' => 'any',
                'dietary_restriction_id' => $restriction->id,
                'opened_child' => $child->id,
            ]),
            $response->getTargetUrl()
        );
    }

    public function test_meal_setting_update_returns_to_eaters_list_with_whitelisted_filters(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CHRET003');
        $discount = $this->createDiscountType($institution->id, '50%', 50);
        $restriction = $this->createDietaryRestriction($institution->id, 'Gluténmentes');
        $this->createDefaultMealPackageContext($institution->id, $user->id);
        $child = $this->createChild($institution, [
            'name' => 'Etkezo Ede',
            'educational_identifier' => 'CHRET003-CHILD',
            'group_name' => '6.C',
            'discount_type_id' => $discount->id,
        ]);
        $mealSetting = $this->createMealSetting($child, $institution, '2026-08-24');

        $this->actingAs($user);

        $response = $this->mealSettingController()->update(
            $this->makeRequest(
                $user,
                route('dashboard.institution.children.meal-settings.update', [$child, $mealSetting]),
                'PUT',
                [
                    'return_list' => 'eaters',
                    'return_query' => 'page=5&search=Ede&group_name=6.C&meal_status=participant&status=active&diet_filter=restriction_'.$restriction->id.'&discount_filter='.$discount->id.'&unknown=1',
                    'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
                    'valid_from' => '2026-08-24',
                ]
            ),
            $child,
            $mealSetting
        );

        $this->assertSame(
            route('dashboard.institution.children.meal-participants.index', [
                'page' => 5,
                'search' => 'Ede',
                'group_name' => '6.C',
                'meal_status' => 'participant',
                'status' => 'active',
                'diet_filter' => 'restriction_'.$restriction->id,
                'discount_filter' => $discount->id,
            ]),
            $response->getTargetUrl()
        );
    }

    public function test_invalid_return_list_falls_back_to_children_index_and_edit_view_keeps_return_state(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CHRET004');
        $discount = $this->createDiscountType($institution->id, 'Teljes ár', 0);
        $child = $this->createChild($institution, [
            'name' => 'Hibas Hilda',
            'educational_identifier' => 'CHRET004-CHILD',
            'group_name' => '8.A',
            'discount_type_id' => $discount->id,
        ]);
        $this->createChild($institution, [
            'name' => 'Masik Nyolcadikos',
            'educational_identifier' => 'CHRET004-KEEP',
            'group_name' => '8.A',
            'discount_type_id' => $discount->id,
        ]);

        $this->actingAs($user);

        $fallbackResponse = $this->childController()->update(
            $this->makeRequest(
                $user,
                route('dashboard.institution.children.update', $child),
                'PUT',
                [
                    'return_list' => 'https://evil.test',
                    'return_query' => 'page=3&search=Hilda&group_name=8.A&status=active&meal_status=participant',
                    'name' => 'Hibas Hilda Frissítve',
                    'educational_identifier' => 'CHRET004-CHILD',
                    'discount_type_id' => $discount->id,
                    'discount_valid_from' => '2026-08-24',
                    'is_eater' => '0',
                    'active' => '1',
                ]
            ),
            $child
        );

        $this->assertSame(
            route('dashboard.institution.children.index', [
                'page' => 3,
                'search' => 'Hilda',
                'group_name' => '8.A',
                'status' => 'active',
            ]),
            $fallbackResponse->getTargetUrl()
        );

        $returnQuery = 'page=2&search=Hilda&sort=name&quality=no_email';
        $editUrl = route('dashboard.institution.children.edit', $child).'?'.http_build_query([
            'return_list' => 'basics',
            'return_query' => $returnQuery,
        ]);

        $editResponse = $this->childController()->edit(
            $this->makeRequest(
                $user,
                $editUrl,
                'GET',
                [
                    'return_list' => 'basics',
                    'return_query' => $returnQuery,
                ]
            ),
            $child
        );

        $this->assertInstanceOf(View::class, $editResponse);
        view()->share('errors', new ViewErrorBag);
        $html = $editResponse->render();

        $this->assertStringContainsString('name="return_list" value="basics"', $html);
        $this->assertStringContainsString('name="return_query" value="page=2&amp;search=Hilda&amp;sort=name&amp;quality=no_email"', $html);
        $this->assertStringContainsString('Vissza az Alapadatokhoz', $html);
    }

    public function test_out_of_range_pages_redirect_to_last_available_page_for_each_child_list(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CHRET005');
        $discount = $this->createDiscountType($institution->id, 'Teljes ár', 0);
        $restriction = $this->createDietaryRestriction($institution->id, 'Tojasmentes');
        $this->createDefaultMealPackageContext($institution->id, $user->id);

        for ($i = 1; $i <= 51; $i++) {
            $child = $this->createChild($institution, [
                'name' => sprintf('Lista Gyerek %02d', $i),
                'educational_identifier' => sprintf('CHRET005-%02d', $i),
                'group_name' => '9.A',
                'discount_type_id' => $discount->id,
            ]);

            if ($i <= 50) {
                $this->createMealSetting($child, $institution, '2026-08-24');
            }
        }

        $this->actingAs($user);
        Paginator::currentPageResolver(fn () => 3);

        $childrenResponse = $this->childController()->index($this->makeRequest(
            $user,
            route('dashboard.institution.children.index', [
                'group_name' => '9.A',
                'status' => 'active',
                'page' => 3,
            ]),
            'GET'
        ));
        $this->assertInstanceOf(RedirectResponse::class, $childrenResponse);
        $this->assertSame(
            route('dashboard.institution.children.index', [
                'page' => 2,
                'group_name' => '9.A',
                'status' => 'active',
            ]),
            $childrenResponse->getTargetUrl()
        );

        $basicsResponse = $this->billingAddressController()->index($this->makeRequest(
            $user,
            route('dashboard.institution.billing-addresses.index', [
                'search' => 'Lista Gyerek',
                'sort' => 'name',
                'page' => 3,
            ]),
            'GET'
        ));
        $this->assertInstanceOf(RedirectResponse::class, $basicsResponse);
        $this->assertSame(
            route('dashboard.institution.billing-addresses.index', [
                'page' => 2,
                'search' => 'Lista Gyerek',
                'sort' => 'name',
            ]),
            $basicsResponse->getTargetUrl()
        );

        $eatersResponse = $this->mealParticipantController()->index($this->makeRequest(
            $user,
            route('dashboard.institution.children.meal-participants.index', [
                'search' => 'Lista Gyerek',
                'group_name' => '9.A',
                'meal_status' => 'participant',
                'status' => 'active',
                'page' => 3,
            ]),
            'GET'
        ));
        $this->assertInstanceOf(RedirectResponse::class, $eatersResponse);
        $this->assertSame(
            route('dashboard.institution.children.meal-participants.index', [
                'page' => 1,
                'search' => 'Lista Gyerek',
                'group_name' => '9.A',
                'meal_status' => 'participant',
                'status' => 'active',
            ]),
            $eatersResponse->getTargetUrl()
        );
    }

    private function childController(): ChildController
    {
        return app(ChildController::class);
    }

    private function billingAddressController(): BillingAddressController
    {
        return app(BillingAddressController::class);
    }

    private function mealParticipantController(): InstitutionMealParticipantController
    {
        return app(InstitutionMealParticipantController::class);
    }

    private function mealSettingController(): ChildMealSettingController
    {
        return app(ChildMealSettingController::class);
    }

    private function makeRequest(User $user, string $uri, string $method, array $data = []): Request
    {
        $request = Request::create($uri, $method, $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function seedInstitutionAdmin(string $code): array
    {
        $institution = Institution::query()->create([
            'name' => 'Gyermek Lista '.$code,
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

    private function createDiscountType(int $institutionId, string $name, int $percentage): DiscountType
    {
        return DiscountType::query()->create([
            'institution_id' => $institutionId,
            'name' => $name,
            'percentage' => $percentage,
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createDietaryRestriction(int $institutionId, string $name): DietaryRestriction
    {
        return DietaryRestriction::query()->create([
            'institution_id' => $institutionId,
            'name' => $name,
            'type' => DietaryRestriction::TYPE_ALLERGEN,
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createChild(Institution $institution, array $overrides = []): Child
    {
        $defaultDiscount = DiscountType::query()->firstOrCreate(
            [
                'institution_id' => $institution->id,
                'name' => 'Alap '.$institution->id,
            ],
            [
                'percentage' => 0,
                'active' => true,
                'sort_order' => 1,
            ]
        );

        return Child::query()->create(array_merge([
            'institution_id' => $institution->id,
            'discount_type_id' => $defaultDiscount->id,
            'name' => 'Teszt Gyermek',
            'educational_identifier' => 'RET-'.uniqid(),
            'group_name' => null,
            'source_type' => 'manual',
            'active' => true,
        ], $overrides));
    }

    private function createDefaultMealPackageContext(int $institutionId, int $createdBy): array
    {
        $mealType = MealType::query()->create([
            'name' => 'Ebéd '.$institutionId,
            'code' => 'ebed-'.$institutionId,
            'default_order' => 1,
            'is_active' => true,
        ]);

        $institutionMealType = InstitutionMealType::query()->create([
            'institution_id' => $institutionId,
            'meal_type_id' => $mealType->id,
            'is_active' => true,
            'display_order' => 1,
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
            'institution_meal_type_id' => $institutionMealType->id,
            'display_order' => 1,
        ]);

        return [
            'package' => $package,
            'institution_meal_type' => $institutionMealType,
        ];
    }

    private function createMealSetting(
        Child $child,
        Institution $institution,
        string $validFrom,
        ?string $validTo = null
    ): StudentMealSetting {
        return StudentMealSetting::query()->create([
            'student_id' => $child->id,
            'eater_type' => $child->getMorphClass(),
            'eater_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'created_by' => 1,
            'note' => null,
            'closed_by' => null,
            'closed_at' => null,
            'closure_reason' => null,
            'closure_note' => null,
        ]);
    }
}
