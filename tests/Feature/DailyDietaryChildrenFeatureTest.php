<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\InstitutionAdmin\DailyOperationController;
use App\Models\AbMenuItem;
use App\Models\AbMenuPlan;
use App\Models\Child;
use App\Models\DietaryRestriction;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\MealCancellation;
use App\Models\StudentMealSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Tests\TestCase;

class DailyDietaryChildrenFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_dietary_filter_and_pagination_keep_query_parameters(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('DIETF1');
        $restriction = $this->createDietaryRestriction($institution->id, 'Laktózmentes');

        for ($index = 1; $index <= 16; $index++) {
            $child = $this->createChild($institution->id, sprintf('A%02d Gyermek', $index), '1.A');
            StudentMealSetting::create([
                'student_id' => $child->id,
                'institution_id' => $institution->id,
                'institution_meal_package_id' => null,
                'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
                'valid_from' => '2026-07-01',
                'valid_to' => null,
            ]);

            if ($index <= 15) {
                $child->dietaryRestrictions()->attach($restriction->id);
            }
        }

        $this->actingAs($user);
        $view = $this->controller()->todayCounts($this->request([
            'date' => '2026-07-16',
            'dietary_filter' => 'dietary',
            'page' => 2,
        ]));

        $rows = $view->getData()['rows'];
        $stats = $view->getData()['stats'];

        $this->assertSame(15, $rows->total());
        $this->assertSame(15, $stats['dietary_eaters']);
        $this->assertStringContainsString('date=2026-07-16', $rows->url(1));
        $this->assertStringContainsString('dietary_filter=dietary', $rows->url(1));
    }

    public function test_dietary_list_excludes_cancelled_and_other_institution_children_and_keeps_sorting(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('DIETF2');
        $otherInstitution = Institution::create([
            'name' => 'Másik Intézmény',
            'institution_code' => 'DIETX2',
            'type' => 'iskola',
            'active' => true,
        ]);

        $restriction = $this->createDietaryRestriction($institution->id, 'Gluténmentes');
        $otherRestriction = $this->createDietaryRestriction($otherInstitution->id, 'Tejmentes');
        $package = InstitutionMealPackage::create([
            'institution_id' => $institution->id,
            'name' => 'Normál csomag',
            'description' => null,
            'is_default' => false,
            'is_active' => true,
            'display_order' => 1,
            'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
        ]);

        $anna = $this->createChild($institution->id, 'Anna', '1.A');
        $bela = $this->createChild($institution->id, 'Béla', '1.A');
        $cecilia = $this->createChild($institution->id, 'Cecília', '2.A');
        $otherChild = $this->createChild($otherInstitution->id, 'Dénes', '1.A');

        $anna->dietaryRestrictions()->attach($restriction->id);
        $bela->dietaryRestrictions()->attach($restriction->id);
        $otherChild->dietaryRestrictions()->attach($otherRestriction->id);

        foreach ([$anna, $bela, $cecilia] as $child) {
            StudentMealSetting::create([
                'student_id' => $child->id,
                'institution_id' => $institution->id,
                'institution_meal_package_id' => $package->id,
                'mode' => StudentMealSetting::MODE_PACKAGE,
                'valid_from' => '2026-07-01',
                'valid_to' => null,
                'note' => 'Érzékenység miatt',
            ]);
        }

        StudentMealSetting::create([
            'student_id' => $otherChild->id,
            'institution_id' => $otherInstitution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-07-01',
            'valid_to' => null,
        ]);

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $bela->id,
            'service_date' => '2026-07-16',
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);

        AbMenuPlan::create([
            'institution_id' => $institution->id,
            'title' => 'Nyári menü',
            'valid_from' => '2026-07-01',
            'valid_to' => '2026-07-31',
            'active' => true,
            'published_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $plan = AbMenuPlan::firstOrFail();

        AbMenuItem::create([
            'ab_menu_plan_id' => $plan->id,
            'menu_date' => '2026-07-16',
            'menu_a' => 'A menü',
            'menu_b' => 'B menü',
            'menu_dietary' => 'Diétás leves és főétel',
            'allergens' => null,
            'note' => 'Konyhai megjegyzés',
        ]);

        $this->actingAs($user);
        $view = $this->controller()->dietaryChildren($this->request([
            'date' => '2026-07-16',
        ]));

        $rows = collect($view->getData()['rows']->items());

        $this->assertSame(['Anna'], $rows->pluck('child.name')->all());
        $this->assertSame('Normál csomag', $rows->first()['menu_package']);
        $this->assertSame(['Gluténmentes'], $rows->first()['diet_names']->all());
        $this->assertSame('Diétás leves és főétel', $rows->first()['daily_menu']);
        $this->assertSame(['Érzékenység miatt', 'Konyhai megjegyzés'], $rows->first()['notes']->all());
    }

    public function test_dietary_print_and_csv_use_selected_date_data(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('DIETF3');
        $restriction = $this->createDietaryRestriction($institution->id, 'Tojásmentes');
        $child = $this->createChild($institution->id, 'Edit', 'Napraforgó');
        $child->dietaryRestrictions()->attach($restriction->id);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-07-01',
            'valid_to' => null,
        ]);

        $this->actingAs($user);
        $printView = $this->controller()->printDietaryChildren($this->request([
            'date' => '2026-07-16',
        ]));
        $printHtml = $printView->render();

        $this->assertStringContainsString('Diétás étkezők listája', $printHtml);
        $this->assertStringContainsString($institution->name, $printHtml);
        $this->assertStringContainsString('2026. 07. 16.', $printHtml);
        $this->assertStringContainsString('Edit', $printHtml);
        $this->assertStringContainsString('Tojásmentes', $printHtml);

        $csvResponse = $this->controller()->exportDietaryChildren($this->request([
            'date' => '2026-07-16',
        ]));
        $content = $csvResponse->getContent();

        $this->assertSame('text/csv; charset=UTF-8', $csvResponse->headers->get('content-type'));
        $this->assertSame('attachment; filename="dietas-etkezok-2026-07-16.csv"', $csvResponse->headers->get('content-disposition'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('"2026-07-16";"Edit";"Napraforgó"', $content);
        $this->assertStringContainsString('"Tojásmentes"', $content);
    }

    private function controller(): DailyOperationController
    {
        return app(DailyOperationController::class);
    }

    private function request(array $query): Request
    {
        return Request::create('/dashboard/institution-admin/daily', 'GET', $query);
    }

    private function seedUserWithInstitution(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Teszt Intézmény '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => 'institution_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institution, $user];
    }

    private function createChild(int $institutionId, string $name, string $groupName): Child
    {
        return Child::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId), 0, 10),
            'group_name' => $groupName,
            'school_year' => '2025/2026',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function createDietaryRestriction(int $institutionId, string $name): DietaryRestriction
    {
        return DietaryRestriction::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'type' => DietaryRestriction::TYPE_INTOLERANCE,
            'active' => true,
            'sort_order' => 1,
        ]);
    }
}
