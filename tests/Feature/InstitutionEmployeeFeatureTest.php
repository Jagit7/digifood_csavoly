<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionEmployeeController;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InstitutionEmployeeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_admin_can_create_employee_with_discount_and_dietary_restrictions(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMP001');
        $discount = $this->createDiscountType($institution->id, 'Dolgozoi 50%', 50);
        $restriction = $this->createDietaryRestriction($institution->id, 'Tej');

        $this->actingAs($user);

        $response = $this->controller()->store($this->makeRequest(
            $user,
            '/dashboard/institution-admin/employees',
            'POST',
            [
                'name' => 'Teszt Dolgozo',
                'email' => 'dolgozo@example.test',
                'phone' => '06301234567',
                'country' => 'Magyarorszag',
                'postal_code' => '1111',
                'city' => 'Budapest',
                'street_name' => 'Kossuth Lajos',
                'street_type' => 'utca',
                'house_number' => '12',
                'bank_account_holder' => 'Teszt Dolgozo',
                'bank_account_number' => '11700000-11111111',
                'discount_type_id' => $discount->id,
                'dietary_restriction_ids' => [$restriction->id],
                'active' => '1',
            ]
        ));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.employees.index'), $response->getTargetUrl());
        $this->assertDatabaseHas('institution_employees', [
            'institution_id' => $institution->id,
            'name' => 'Teszt Dolgozo',
            'email' => 'dolgozo@example.test',
            'discount_type_id' => $discount->id,
            'active' => true,
        ]);

        $employee = InstitutionEmployee::query()->where('institution_id', $institution->id)->firstOrFail();
        $this->assertSame('Teszt Dolgozo', $employee->bank_account_holder);
        $this->assertSame('11700000-11111111', $employee->bank_account_number);
        $this->assertDatabaseHas('institution_employee_dietary_restriction', [
            'institution_employee_id' => $employee->id,
            'dietary_restriction_id' => $restriction->id,
        ]);

        $rawBankAccount = DB::table('institution_employees')->where('id', $employee->id)->value('bank_account_number');
        $this->assertNotSame('11700000-11111111', $rawBankAccount);
    }

    public function test_employee_form_rejects_foreign_discount_and_dietary_restrictions(): void
    {
        [, $user] = $this->seedInstitutionAdmin('EMP002');
        $otherInstitution = $this->createInstitution('EMP002X');
        $foreignDiscount = $this->createDiscountType($otherInstitution->id, 'Masik kedvezmeny', 25);
        $foreignRestriction = $this->createDietaryRestriction($otherInstitution->id, 'Gluten');

        $this->actingAs($user);

        try {
            $this->controller()->store($this->makeRequest(
                $user,
                '/dashboard/institution-admin/employees',
                'POST',
                [
                    'name' => 'Hibas Dolgozo',
                    'discount_type_id' => $foreignDiscount->id,
                    'dietary_restriction_ids' => [$foreignRestriction->id],
                ]
            ));

            $this->fail('Validacios hibat vartunk idegen intezmenyi adatoknal.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey('discount_type_id', $errors);
            $this->assertArrayHasKey('dietary_restriction_ids.0', $errors);
        }

        $this->assertDatabaseCount('institution_employees', 0);
    }

    public function test_employee_index_filters_by_search_and_status(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMP003');

        InstitutionEmployee::create([
            'institution_id' => $institution->id,
            'name' => 'Aktiv Alkalmazott',
            'email' => 'aktiv@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        InstitutionEmployee::create([
            'institution_id' => $institution->id,
            'name' => 'Inaktiv Dolgozo',
            'email' => 'inaktiv@example.test',
            'source_type' => 'manual',
            'active' => false,
        ]);

        $this->actingAs($user);

        $response = $this->controller()->index($this->makeRequest(
            $user,
            '/dashboard/institution-admin/employees',
            'GET',
            [
                'email' => 'inaktiv@example.test',
                'status' => 'inactive',
            ]
        ));

        $this->assertInstanceOf(View::class, $response);
        view()->share('errors', new ViewErrorBag);
        $html = $response->render();

        $this->assertStringContainsString('Inaktiv Dolgozo', $html);
        $this->assertStringNotContainsString('Aktiv Alkalmazott', $html);
    }

    public function test_employee_index_filters_by_email_diet_discount_and_ignores_foreign_filter_values(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMP003B');
        $otherInstitution = $this->createInstitution('EMP003C');

        $localDiscount = $this->createDiscountType($institution->id, 'Dolgozoi 30%', 30);
        $foreignDiscount = $this->createDiscountType($otherInstitution->id, 'Masik 60%', 60);
        $localRestriction = $this->createDietaryRestriction($institution->id, 'Tejmentes');
        $foreignRestriction = $this->createDietaryRestriction($otherInstitution->id, 'Glutenmentes');

        $matchingEmployee = InstitutionEmployee::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $localDiscount->id,
            'name' => 'Minta Dolgozo',
            'email' => '  MiNtA@Example.test  ',
            'source_type' => 'manual',
            'active' => true,
        ]);
        $matchingEmployee->dietaryRestrictions()->sync([$localRestriction->id]);

        InstitutionEmployee::create([
            'institution_id' => $institution->id,
            'name' => 'Csak Nev',
            'email' => 'masik@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        InstitutionEmployee::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $localDiscount->id,
            'name' => 'Nem Dietas Kedvezmenyes',
            'email' => 'kedvezmenyes@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        InstitutionEmployee::create([
            'institution_id' => $otherInstitution->id,
            'discount_type_id' => $foreignDiscount->id,
            'name' => 'Masik Intezmeny',
            'email' => 'minta@example.test',
            'source_type' => 'manual',
            'active' => true,
        ])->dietaryRestrictions()->sync([$foreignRestriction->id]);

        $this->actingAs($user);

        $filteredResponse = $this->controller()->index($this->makeRequest(
            $user,
            '/dashboard/institution-admin/employees',
            'GET',
            [
                'search' => 'Minta',
                'email' => '  minta@example.test  ',
                'diet_filter' => 'restriction_'.$localRestriction->id,
                'discount_filter' => 'discount_'.$localDiscount->id,
                'status' => 'active',
            ]
        ));

        $this->assertInstanceOf(View::class, $filteredResponse);
        view()->share('errors', new ViewErrorBag);
        $filteredHtml = $filteredResponse->render();

        $this->assertStringContainsString('Minta Dolgozo', $filteredHtml);
        $this->assertStringNotContainsString('Csak Nev', $filteredHtml);
        $this->assertStringNotContainsString('Nem Dietas Kedvezmenyes', $filteredHtml);
        $this->assertStringNotContainsString('Masik Intezmeny', $filteredHtml);

        $foreignFilterResponse = $this->controller()->index($this->makeRequest(
            $user,
            '/dashboard/institution-admin/employees',
            'GET',
            [
                'diet_filter' => 'restriction_'.$foreignRestriction->id,
                'discount_filter' => 'discount_'.$foreignDiscount->id,
            ]
        ));

        $this->assertInstanceOf(View::class, $foreignFilterResponse);
        $foreignHtml = $foreignFilterResponse->render();

        $this->assertStringContainsString('Minta Dolgozo', $foreignHtml);
        $this->assertStringContainsString('Csak Nev', $foreignHtml);
        $this->assertStringContainsString('Nem Dietas Kedvezmenyes', $foreignHtml);
        $this->assertStringNotContainsString('Masik Intezmeny', $foreignHtml);
    }

    public function test_employee_update_and_toggle_active_are_institution_scoped(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMP004');
        [$otherInstitution, $otherUser] = $this->seedInstitutionAdmin('EMP004X');

        $employee = InstitutionEmployee::create([
            'institution_id' => $institution->id,
            'name' => 'Modositando Dolgozo',
            'email' => 'regi@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $this->actingAs($user);

        $updateResponse = $this->controller()->update(
            $this->makeRequest(
                $user,
                '/dashboard/institution-admin/employees/'.$employee->id,
                'PUT',
                [
                    'return_query' => 'page=3&search=Frissitett&email=filter%40example.test&status=active&diet_filter=with_diet&discount_filter=with_discount',
                    'name' => 'Frissitett Dolgozo',
                    'email' => 'uj@example.test',
                    'active' => '1',
                ]
            ),
            $employee
        );

        $this->assertInstanceOf(RedirectResponse::class, $updateResponse);
        $this->assertSame(
            route('dashboard.institution.employees.index', [
                'page' => 3,
                'search' => 'Frissitett',
                'email' => 'filter@example.test',
                'status' => 'active',
                'diet_filter' => 'with_diet',
                'discount_filter' => 'with_discount',
            ]),
            $updateResponse->getTargetUrl()
        );
        $this->assertDatabaseHas('institution_employees', [
            'id' => $employee->id,
            'name' => 'Frissitett Dolgozo',
            'email' => 'uj@example.test',
        ]);

        $toggleResponse = $this->controller()->toggleActive(
            $this->makeRequest(
                $user,
                '/dashboard/institution-admin/employees/'.$employee->id.'/toggle-active',
                'POST',
                [
                    'page' => 2,
                    'search' => 'Frissitett',
                    'email' => 'uj@example.test',
                    'status' => 'active',
                    'diet_filter' => 'with_diet',
                    'discount_filter' => 'with_discount',
                ]
            ),
            $employee
        );

        $this->assertInstanceOf(RedirectResponse::class, $toggleResponse);
        $this->assertSame(
            route('dashboard.institution.employees.index', [
                'page' => 2,
                'search' => 'Frissitett',
                'email' => 'uj@example.test',
                'email' => 'uj@example.test',
                'status' => 'active',
                'diet_filter' => 'with_diet',
                'discount_filter' => 'with_discount',
            ]),
            $toggleResponse->getTargetUrl()
        );
        $this->assertDatabaseHas('institution_employees', [
            'id' => $employee->id,
            'active' => false,
        ]);

        $this->actingAs($otherUser);

        try {
            $this->controller()->edit(
                $this->makeRequest(
                    $otherUser,
                    '/dashboard/institution-admin/employees/'.$employee->id.'/edit',
                    'GET'
                ),
                $employee
            );
            $this->fail('403-as kivetelt vartunk masik intezmeny dolgozojanak megnyitasakor.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertNotSame($institution->id, $otherInstitution->id);
    }

    public function test_employee_edit_and_update_preserve_whitelisted_list_query_only(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('EMP005');

        $employee = InstitutionEmployee::create([
            'institution_id' => $institution->id,
            'name' => 'Szurt Dolgozo',
            'email' => 'szurt@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $this->actingAs($user);

        $editResponse = $this->controller()->edit(
            $this->makeRequest(
                $user,
                '/dashboard/institution-admin/employees/'.$employee->id.'/edit',
                'GET',
                [
                    'return_query' => 'page=3&search=Kiss&email=gmail&diet_filter=with_diet&discount_filter=with_discount&status=active&return_to=https://evil.test',
                ]
            ),
            $employee
        );

        $this->assertInstanceOf(View::class, $editResponse);
        $this->assertSame(
            'page=3&search=Kiss&email=gmail&status=active&diet_filter=with_diet&discount_filter=with_discount',
            $editResponse->getData()['returnQuery']
        );

        $updateResponse = $this->controller()->update(
            $this->makeRequest(
                $user,
                '/dashboard/institution-admin/employees/'.$employee->id,
                'PUT',
                [
                    'return_query' => "page=3&search=Kiss&email=gmail&diet_filter=with_diet&discount_filter=with_discount&status=active&return_to=https://evil.test\r\nLocation:https://evil.test",
                    'name' => 'Szurt Dolgozo Frissitve',
                    'email' => 'szurt@example.test',
                    'active' => '1',
                ]
            ),
            $employee
        );

        $this->assertSame(
            route('dashboard.institution.employees.index', [
                'page' => 3,
                'search' => 'Kiss',
                'email' => 'gmail',
                'status' => 'active',
                'diet_filter' => 'with_diet',
                'discount_filter' => 'with_discount',
            ]),
            $updateResponse->getTargetUrl()
        );
    }

    private function controller(): InstitutionEmployeeController
    {
        return app(InstitutionEmployeeController::class);
    }

    private function makeRequest(User $user, string $uri, string $method, array $data = []): Request
    {
        $request = Request::create($uri, $method, $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function seedInstitutionAdmin(string $code): array
    {
        $institution = $this->createInstitution($code);
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

    private function createInstitution(string $code): Institution
    {
        return Institution::create([
            'name' => 'Dolgozoi Intezmeny '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);
    }

    private function createDiscountType(int $institutionId, string $name, int $percentage): DiscountType
    {
        return DiscountType::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'percentage' => $percentage,
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createDietaryRestriction(int $institutionId, string $name): DietaryRestriction
    {
        return DietaryRestriction::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'type' => DietaryRestriction::TYPE_ALLERGEN,
            'active' => true,
            'sort_order' => 1,
        ]);
    }
}
