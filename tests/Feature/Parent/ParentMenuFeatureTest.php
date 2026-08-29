<?php

namespace Tests\Feature\Parent;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParentMenuFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_menu_index_lists_only_accessible_active_institution_menus(): void
    {
        Storage::fake('public');

        [$user, $institution, $guardian] = $this->createParentContext('PAR200');

        $ownChild = $this->createChild($institution->id, 'Sajat Gyermek');
        $ownChild->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $secondInstitution = Institution::create([
            'name' => 'Masodik Intezmeny',
            'institution_code' => 'PAR201',
            'type' => 'ovoda',
            'active' => true,
        ]);

        $secondGuardian = Guardian::create([
            'institution_id' => $secondInstitution->id,
            'user_id' => $user->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szulo Ketto',
            'email' => 'masodik@example.com',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $secondChild = $this->createChild($secondInstitution->id, 'Masodik Gyermek');
        $secondChild->guardians()->attach($secondGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $ownMenu = $this->createMenu($institution->id, $user->id, [
            'title' => 'Sajat heti etlap',
            'file_name' => 'etlap-sajat.pdf',
        ]);

        $secondMenu = $this->createMenu($secondInstitution->id, $user->id, [
            'title' => 'Masodik intezmeny etlapja',
            'file_name' => 'masodik.png',
            'mime_type' => 'image/png',
            'file_path' => 'menus/masodik.png',
        ]);

        $foreignInstitution = Institution::create([
            'name' => 'Idegen Intezmeny',
            'institution_code' => 'PAR202',
            'type' => 'iskola',
            'active' => true,
        ]);

        $foreignMenu = $this->createMenu($foreignInstitution->id, $user->id, [
            'title' => 'Idegen etlap',
            'file_name' => 'idegen.pdf',
            'file_path' => 'menus/idegen.pdf',
        ]);

        $inactiveMenu = $this->createMenu($institution->id, $user->id, [
            'title' => 'Inaktiv etlap',
            'active' => false,
            'file_name' => 'inaktiv.pdf',
            'file_path' => 'menus/inaktiv.pdf',
        ]);

        $response = $this->actingAs($user)->get(route('parent.menus.index'));

        $response->assertOk();
        $response->assertSee('Feltöltött étlapok');
        $response->assertSee($ownMenu->title);
        $response->assertSee($secondMenu->title);
        $response->assertDontSee($foreignMenu->title);
        $response->assertDontSee($inactiveMenu->title);
    }

    public function test_parent_can_view_and_download_only_own_institution_menu_files(): void
    {
        Storage::fake('public');

        [$user, $institution, $guardian] = $this->createParentContext('PAR210');

        $child = $this->createChild($institution->id, 'Sajat Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        Storage::disk('public')->put('menus/sajat-menu.pdf', 'parent-menu-pdf');
        Storage::disk('public')->put('menus/idegen-menu.pdf', 'foreign-menu-pdf');

        $ownMenu = $this->createMenu($institution->id, $user->id, [
            'title' => 'Sajat PDF etlap',
            'file_name' => 'sajat-menu.pdf',
            'file_path' => 'menus/sajat-menu.pdf',
        ]);

        $foreignInstitution = Institution::create([
            'name' => 'Idegen Intezmeny',
            'institution_code' => 'PAR203',
            'type' => 'iskola',
            'active' => true,
        ]);

        $foreignMenu = $this->createMenu($foreignInstitution->id, $user->id, [
            'title' => 'Idegen PDF etlap',
            'file_name' => 'idegen-menu.pdf',
            'file_path' => 'menus/idegen-menu.pdf',
        ]);

        $viewResponse = $this->actingAs($user)->get(route('parent.menus.show', $ownMenu));
        $viewResponse->assertOk();
        $viewResponse->assertHeader('content-type', 'application/pdf');

        $downloadResponse = $this->actingAs($user)->get(route('parent.menus.download', $ownMenu));
        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('content-disposition');

        $forbiddenView = $this->actingAs($user)->get(route('parent.menus.show', $foreignMenu));
        $forbiddenView->assertNotFound();

        $forbiddenDownload = $this->actingAs($user)->get(route('parent.menus.download', $foreignMenu));
        $forbiddenDownload->assertNotFound();
    }

    private function createParentContext(string $institutionCode): array
    {
        $institution = Institution::create([
            'name' => 'Szuloi intezmeny',
            'institution_code' => $institutionCode,
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Teszt Szulo',
            'email' => strtolower($institutionCode).'@example.com',
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

    private function createMenu(int $institutionId, int $userId, array $attributes = []): Menu
    {
        $menu = new Menu;
        $menu->forceFill(array_merge([
            'institution_id' => $institutionId,
            'type' => 'weekly',
            'title' => 'Teszt etlap',
            'week_start' => '2026-08-24',
            'week_end' => '2026-08-28',
            'has_saturday' => false,
            'saturday_date' => null,
            'saturday_note' => null,
            'file_path' => 'menus/teszt-etlap.pdf',
            'file_name' => 'teszt-etlap.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'active' => true,
            'published_at' => now(),
            'created_by' => $userId,
            'updated_by' => $userId,
        ], $attributes));
        $menu->save();

        if (! Storage::disk('public')->exists($menu->file_path)) {
            Storage::disk('public')->put($menu->file_path, 'menu-preview-content');
        }

        return $menu->fresh();
    }
}
