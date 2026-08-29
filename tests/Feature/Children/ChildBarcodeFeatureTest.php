<?php

namespace Tests\Feature\Children;

use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildBarcodeController;
use App\Http\Requests\Dashboard\InstitutionAdmin\Children\ChildBarcodeSelectionRequest;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\StudentMealSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ChildBarcodeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_barcode_generation_creates_random_token(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('BC001');
        $child = $this->createChild($institution->id, 'Anna');

        $this->actingAs($user);
        $response = $this->controller()->store($child);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $child->refresh();

        $this->assertNotNull($child->barcode_token);
        $this->assertNull($child->barcode_disabled_at);
        $this->assertNotNull($child->barcode_generated_at);
        $this->assertNotSame((string) $child->id, $child->barcode_token);
    }

    public function test_generated_tokens_are_unique(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('BC002');
        $firstChild = $this->createChild($institution->id, 'Bela');
        $secondChild = $this->createChild($institution->id, 'Csilla');

        $this->actingAs($user);
        $this->controller()->store($firstChild);
        $this->controller()->store($secondChild);

        $firstChild->refresh();
        $secondChild->refresh();

        $this->assertNotSame($firstChild->barcode_token, $secondChild->barcode_token);
    }

    public function test_bulk_generation_does_not_overwrite_existing_code(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('BC003');
        $firstChild = $this->createParticipantChild($institution->id, 'Dora');
        $secondChild = $this->createParticipantChild($institution->id, 'Emese');

        $this->actingAs($user);
        $this->controller()->store($firstChild);
        $firstToken = $firstChild->fresh()->barcode_token;

        $response = $this->controller()->bulkGenerate(
            $this->makeSelectionRequest($user, [$firstChild->id, $secondChild->id])
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/children/barcodes/print-batches/', $response->getTargetUrl());
        $this->assertSame($firstToken, $firstChild->fresh()->barcode_token);
        $this->assertNotNull($secondChild->fresh()->barcode_token);
    }

    public function test_other_institution_child_cannot_be_modified(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('BC004');
        $otherInstitution = Institution::create([
            'name' => 'Masik intezmeny',
            'institution_code' => 'BCO04',
            'type' => 'iskola',
            'active' => true,
        ]);
        $foreignChild = $this->createChild($otherInstitution->id, 'Ferenc');

        $this->actingAs($user);

        try {
            $this->controller()->store($foreignChild);
            $this->fail('403-as kivetelre szamitottunk.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_barcode_can_be_disabled_and_regenerated(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('BC005');
        $child = $this->createChild($institution->id, 'Gergo');

        $this->actingAs($user);
        $this->controller()->store($child);
        $originalToken = $child->fresh()->barcode_token;

        $disableResponse = $this->controller()->destroy($child);

        $this->assertInstanceOf(RedirectResponse::class, $disableResponse);
        $this->assertNotNull($child->fresh()->barcode_disabled_at);

        $regenerateResponse = $this->controller()->regenerate($child);

        $this->assertInstanceOf(RedirectResponse::class, $regenerateResponse);

        $child->refresh();

        $this->assertNull($child->barcode_disabled_at);
        $this->assertNotSame($originalToken, $child->barcode_token);
    }

    public function test_only_child_with_active_barcode_can_be_printed(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('BC006');
        $child = $this->createParticipantChild($institution->id, 'Hanna');

        $this->actingAs($user);
        $firstResponse = $this->controller()->printChild($child);

        $this->assertInstanceOf(RedirectResponse::class, $firstResponse);
        $this->controller()->store($child);

        $printResponse = $this->controller()->printChild($child);

        $this->assertInstanceOf(View::class, $printResponse);
        $this->assertStringContainsString('Hanna', $printResponse->render());
    }

    public function test_bulk_selection_validation_requires_at_least_one_child(): void
    {
        [, $user] = $this->seedUserWithInstitution('BC007');
        $request = ChildBarcodeSelectionRequest::create('/dashboard/institution-admin/children/barcodes/generate-selected', 'POST', [
            'children' => [],
            'child_ids' => [],
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = $this->app['validator']->make($request->all(), (new ChildBarcodeSelectionRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('child_ids', $validator->errors()->toArray());
    }

    public function test_print_page_contains_only_selected_institution_children(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('BC008');
        $child = $this->createParticipantChild($institution->id, 'Ilona');
        $foreignInstitution = Institution::create([
            'name' => 'Kulso iskola',
            'institution_code' => 'BCX08',
            'type' => 'iskola',
            'active' => true,
        ]);
        $this->createParticipantChild($foreignInstitution->id, 'Janos');

        $this->actingAs($user);
        $this->controller()->store($child);

        $response = $this->controller()->printSelected(
            $this->makeSelectionRequest($user, [$child->id])
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $token = basename($response->getTargetUrl());
        $previewResponse = $this->controller()->printBatch($token);

        $this->assertInstanceOf(View::class, $previewResponse);
        $html = $previewResponse->render();

        $this->assertStringContainsString('Ilona', $html);
        $this->assertStringNotContainsString('Janos', $html);
    }

    public function test_print_view_splits_ten_and_eleven_cards_into_separate_a4_pages(): void
    {
        [$institution, $user] = $this->seedUserWithInstitution('BC009');
        $children = collect();

        for ($index = 1; $index <= 11; $index++) {
            $child = $this->createParticipantChild($institution->id, 'Gyermek '.$index);
            $child->forceFill([
                'barcode_token' => 'DFTEST'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'barcode_generated_at' => now(),
                'barcode_disabled_at' => null,
            ])->save();
            $children->push($child);
        }

        $this->actingAs($user);
        $response = $this->controller()->printSelected(
            $this->makeSelectionRequest($user, $children->pluck('id')->all())
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $token = basename($response->getTargetUrl());
        $previewResponse = $this->controller()->printBatch($token);

        $this->assertInstanceOf(View::class, $previewResponse);
        $html = $previewResponse->render();

        $this->assertSame(2, substr_count($html, 'class="print-page"'));
        $this->assertSame(11, substr_count($html, 'class="barcode-card"'));
    }

    private function seedUserWithInstitution(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Vonalkod intezmeny '.$code,
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

    private function createParticipantChild(int $institutionId, string $name): Child
    {
        $child = $this->createChild($institutionId, $name);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institutionId,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-07-01',
            'valid_to' => null,
        ]);

        return $child;
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

    private function controller(): ChildBarcodeController
    {
        return app(ChildBarcodeController::class);
    }

    private function makeSelectionRequest(User $user, array $childIds): ChildBarcodeSelectionRequest
    {
        $request = ChildBarcodeSelectionRequest::create(
            '/dashboard/institution-admin/children/barcodes/generate-selected',
            'POST',
            [
                'children' => $childIds,
                'child_ids' => $childIds,
            ]
        );
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $validator = $this->app['validator']->make($request->all(), $request->rules());
        $request->setValidator($validator);

        return $request;
    }
}
