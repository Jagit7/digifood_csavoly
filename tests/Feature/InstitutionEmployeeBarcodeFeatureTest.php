<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionEmployeeBarcodeController;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InstitutionEmployeeBarcodeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_barcode_can_be_created(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('EMPBC001');
        $employee = $this->createEmployee($institution->id, 'Dolgozó Egy');

        $this->actingAs($user);
        $response = $this->controller()->store($employee);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $employee->refresh();
        $this->assertNotNull($employee->barcode_token);
        $this->assertNull($employee->barcode_disabled_at);
        $this->assertNotNull($employee->barcode_generated_at);
    }

    public function test_employee_barcode_can_be_disabled_activated_and_regenerated(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('EMPBC002');
        $employee = $this->createEmployee($institution->id, 'Dolgozó Kettő');

        $employee->forceFill([
            'barcode_token' => 'DFEMPBC002',
            'barcode_generated_at' => now(),
            'barcode_disabled_at' => null,
        ])->save();

        $this->actingAs($user);
        $disableResponse = $this->controller()->destroy($employee);
        $this->assertInstanceOf(RedirectResponse::class, $disableResponse);
        $this->assertNotNull($employee->fresh()->barcode_disabled_at);

        $activateResponse = $this->controller()->activate($employee->fresh());
        $this->assertInstanceOf(RedirectResponse::class, $activateResponse);
        $this->assertNull($employee->fresh()->barcode_disabled_at);

        $originalToken = $employee->fresh()->barcode_token;
        $regenerateResponse = $this->controller()->regenerate($employee->fresh());
        $this->assertInstanceOf(RedirectResponse::class, $regenerateResponse);
        $this->assertNotSame($originalToken, $employee->fresh()->barcode_token);
    }

    public function test_employee_barcode_routes_are_institution_scoped(): void
    {
        [, $user] = $this->seedUserWithInstitution('EMPBC003');
        $foreignInstitution = $this->createInstitution('EMPBC003X');
        $employee = $this->createEmployee($foreignInstitution->id, 'Idegen Dolgozó');

        $this->actingAs($user);

        try {
            $this->controller()->store($employee);
            $this->fail('403-as kivételre számítottunk.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_only_active_employee_barcode_can_be_printed(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('EMPBC004');
        $employee = $this->createEmployee($institution->id, 'Nyomtatható Dolgozó');

        $this->actingAs($user);
        $firstResponse = $this->controller()->print($employee);
        $this->assertInstanceOf(RedirectResponse::class, $firstResponse);

        $employee->forceFill([
            'barcode_token' => 'DFEMPBC004',
            'barcode_generated_at' => now(),
            'barcode_disabled_at' => null,
        ])->save();

        $printResponse = $this->controller()->print($employee->fresh());
        $this->assertInstanceOf(View::class, $printResponse);
        $this->assertStringContainsString('Nyomtatható Dolgozó', $printResponse->render());
    }

    private function controller(): InstitutionEmployeeBarcodeController
    {
        return app(InstitutionEmployeeBarcodeController::class);
    }

    private function seedUserWithInstitution(string $code): array
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
            'name' => 'Dolgozói vonalkód intézmény '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);
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
}
