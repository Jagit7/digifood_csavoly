<?php

namespace Tests\Feature\Children;

use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildController;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A gyermek szerkesztő oldalán ("+ Új gondviselő hozzáadása" blokk)
 * bevezetett funkció tesztjei: eddig a ChildController::update() csak a
 * MÁR kapcsolt gondviselők adatait tudta módosítani, új gondviselő
 * felvételére/hozzákapcsolására nem volt lehetőség innen. Ez a teszt
 * fedi le az új "new_guardian" bemenet kezelését, és azt is, hogy a
 * meglévő gondviselő-szerkesztés funkció nem sérült.
 */
class ChildEditGuardianFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_guardian_can_be_added_from_edit_page_when_child_has_no_guardian(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CEG001');
        $discount = $this->createDiscountType($institution->id);
        $child = $this->createChild($institution->id, $discount->id, 'CEG001-CHILD');

        $this->actingAs($user);

        $response = $this->controller()->update($this->makeUpdateRequest($user, $child, [
            'name' => $child->name,
            'discount_type_id' => $discount->id,
            'discount_valid_from' => '2026-08-24',
            'active' => '1',
            'new_guardian' => [
                'last_name' => 'Kovács',
                'first_name' => 'Anna',
                'email' => 'kovacs.anna@example.test',
                'phone' => '+36301234567',
                'relationship_type' => 'Édesanya',
                'is_legal_representative' => '1',
                'active' => '1',
            ],
        ]), $child);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $child->refresh();
        $this->assertCount(1, $child->guardians);

        $guardian = $child->guardians->first();
        $this->assertSame('Kovács', $guardian->last_name);
        $this->assertSame('Anna', $guardian->first_name);
        $this->assertSame('kovacs.anna@example.test', $guardian->email);
        $this->assertSame($institution->id, $guardian->institution_id);
        $this->assertTrue($guardian->active);
        $this->assertSame('Édesanya', $guardian->pivot->relationship_type);
        $this->assertTrue((bool) $guardian->pivot->is_legal_representative);
    }

    public function test_new_guardian_can_be_added_as_second_guardian(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CEG002');
        $discount = $this->createDiscountType($institution->id);
        $child = $this->createChild($institution->id, $discount->id, 'CEG002-CHILD');
        $this->attachGuardianDirectly($child, $institution->id, 'Első', 'Gondviselő');

        $this->actingAs($user);

        $response = $this->controller()->update($this->makeUpdateRequest($user, $child, [
            'name' => $child->name,
            'discount_type_id' => $discount->id,
            'discount_valid_from' => '2026-08-24',
            'active' => '1',
            'new_guardian' => [
                'last_name' => 'Második',
                'first_name' => 'Gondviselő',
            ],
        ]), $child);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $child->refresh();
        $this->assertCount(2, $child->guardians);
        $this->assertTrue($child->guardians->contains(fn (Guardian $g) => $g->last_name === 'Második'));
    }

    public function test_adding_a_third_guardian_is_rejected(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CEG003');
        $discount = $this->createDiscountType($institution->id);
        $child = $this->createChild($institution->id, $discount->id, 'CEG003-CHILD');
        $this->attachGuardianDirectly($child, $institution->id, 'Első', 'Gondviselő');
        $this->attachGuardianDirectly($child, $institution->id, 'Második', 'Gondviselő');

        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        $this->controller()->update($this->makeUpdateRequest($user, $child, [
            'name' => $child->name,
            'discount_type_id' => $discount->id,
            'discount_valid_from' => '2026-08-24',
            'active' => '1',
            'new_guardian' => [
                'last_name' => 'Harmadik',
                'first_name' => 'Gondviselő',
            ],
        ]), $child);

        $this->assertCount(2, $child->guardians()->get());
    }

    public function test_empty_new_guardian_block_is_ignored_and_existing_guardian_edit_still_works(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CEG004');
        $discount = $this->createDiscountType($institution->id);
        $child = $this->createChild($institution->id, $discount->id, 'CEG004-CHILD');
        $guardian = $this->attachGuardianDirectly($child, $institution->id, 'Régi', 'Vezetéknév');

        $this->actingAs($user);

        $response = $this->controller()->update($this->makeUpdateRequest($user, $child, [
            'name' => $child->name,
            'discount_type_id' => $discount->id,
            'discount_valid_from' => '2026-08-24',
            'active' => '1',
            'guardians' => [
                $guardian->id => [
                    'last_name' => 'Frissített',
                    'first_name' => $guardian->first_name,
                    'active' => '1',
                ],
            ],
            // Az űrlapon a "+ Új gondviselő hozzáadása" blokk nincs
            // megnyitva/kitöltve - ilyenkor a mentés NEM dobhat "kötelező
            // mező" hibát az üres new_guardian.last_name/first_name miatt.
            'new_guardian' => [
                'last_name' => '',
                'first_name' => '',
            ],
        ]), $child);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $child->refresh();
        $this->assertCount(1, $child->guardians);
        $this->assertSame('Frissített', $child->guardians->first()->last_name);
    }

    private function controller(): ChildController
    {
        return app(ChildController::class);
    }

    private function makeUpdateRequest(User $user, Child $child, array $data): Request
    {
        $request = Request::create(
            route('dashboard.institution.children.update', ['child' => $child->id]),
            'PUT',
            $data
        );
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function seedInstitutionAdmin(string $code): array
    {
        $institution = Institution::query()->create([
            'name' => 'Gondviselő Teszt Intézmény '.$code,
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

    private function createChild(int $institutionId, int $discountTypeId, string $educationalIdentifier): Child
    {
        return Child::create([
            'institution_id' => $institutionId,
            'discount_type_id' => $discountTypeId,
            'name' => 'Teszt Gyermek '.$educationalIdentifier,
            'educational_identifier' => $educationalIdentifier,
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function attachGuardianDirectly(Child $child, int $institutionId, string $lastName, string $firstName): Guardian
    {
        $guardian = Guardian::create([
            'institution_id' => $institutionId,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'source_type' => 'manual',
            'active' => true,
        ]);

        $child->guardians()->attach($guardian->id, [
            'relationship_type' => null,
            'is_legal_representative' => false,
            'has_no_custody' => false,
            'is_emergency_contact' => false,
            'receives_family_allowance' => false,
        ]);

        return $guardian;
    }
}
