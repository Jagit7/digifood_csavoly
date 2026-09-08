<?php

namespace Tests\Feature\Parent;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionMealSetting;
use App\Models\User;
use App\Services\ParentPortal\ParentAccountActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Reproduces the REAL production case reported for doszil7@gmail.com
 * (Doszkocs Attila):
 *
 *   1. Institution 1 (iskola) admin creates a guardian for the parent's
 *      email and attaches Child1. The parent activates their account
 *      through the normal activation-token flow -> a User row is created
 *      and Guardian1.user_id is set to that User's id.
 *   2. LATER, Institution 2 (ovoda) admin independently creates ANOTHER
 *      guardian record for the SAME email (this is exactly what
 *      ChildController::attachGuardian() / attachNewGuardianForEdit() do
 *      in "new guardian" mode: a fresh Guardian row, institution_id = 2,
 *      user_id left NULL) and attaches Child2 to it.
 *
 * Expected (per the task): the parent should be able to see BOTH Child1
 * and Child2 from their single, already-activated account, without having
 * to accept a second invitation per institution.
 */
class ParentGuardianAccountLinkingRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_created_in_second_institution_after_parent_is_already_active_gets_linked_and_visible(): void
    {
        $email = 'doszil7@gmail.com';

        $institution1 = $this->createInstitution('DOSZ1', 'Bajai Szentistvani Altalanos Iskola Csavolyi Tagintezmenye');
        $institution2 = $this->createInstitution('DOSZ2', 'Csavolyi Napkozi Otthonos Ovoda');

        // --- Step 1: institution 1 creates the guardian + child, parent activates ---
        $guardian1 = Guardian::query()->create([
            'institution_id' => $institution1->id,
            'last_name' => 'Doszkocs',
            'first_name' => 'Attila',
            'email' => $email,
            'source_type' => 'manual',
            'active' => true,
        ]);
        $child1 = $this->createChild($institution1->id, 'Teszt Gyerek1');
        $child1->guardians()->attach($guardian1->id, ['created_at' => now(), 'updated_at' => now()]);

        $activationService = app(ParentAccountActivationService::class);
        $activation = $activationService->requestActivation($email);
        $this->assertSame('activation_created', $activation['status']);

        // Fetch the plain token the same way the controller test-helper flow
        // would (the service only emails it in real usage; in tests we can
        // read it back from the just-created row via a fresh request for
        // consistency with how the real token is generated).
        $token = \App\Models\ParentAccountActivationToken::query()->where('email', $email)->firstOrFail();
        // The plain token is not stored (only its hash) -- so, to activate
        // in this test, we call requestActivation() again is not viable
        // (it would invalidate the token). Instead we directly exercise the
        // service's activate() contract via a manually generated token that
        // mirrors requestActivation()'s own mechanism.
        $this->assertNotNull($token);

        // Use reflection-free approach: call activate() with a token we
        // control end-to-end, by generating it exactly like the service does.
        [$plainToken, ] = $this->issueKnownActivationToken($email);

        $user = $activationService->activate($plainToken, 'jelszo1234');

        $this->assertSame($email, $user->email);
        $this->assertTrue((bool) $user->is_active);
        $this->assertSame($guardian1->id, $guardian1->fresh()->id);
        $this->assertSame($user->id, $guardian1->fresh()->user_id);

        // --- Step 2: institution 2 independently creates ANOTHER guardian
        //     record with the same email, exactly like
        //     ChildController::attachGuardian() does in "new guardian" mode
        //     (no user_id is set at creation time). ---
        $guardian2 = Guardian::query()->create([
            'institution_id' => $institution2->id,
            'last_name' => 'Doszkocs',
            'first_name' => 'Attila',
            'email' => $email,
            'source_type' => 'manual',
            'active' => true,
        ]);
        $child2 = $this->createChild($institution2->id, 'Teszt gyerek2');
        $child2->guardians()->attach($guardian2->id, ['created_at' => now(), 'updated_at' => now()]);

        // --- Expected outcome: the already-active parent account should
        //     see BOTH children, from ONE login, without accepting a
        //     second invitation. ---
        $response = $this->actingAs($user)->get('/szulo/gyermekeim');

        $response->assertOk();
        $response->assertViewHas('children', fn ($children) => $children->total() === 2);
        $response->assertViewHas('childCards', function (Collection $childCards) use ($child1, $child2) {
            $ids = $childCards->pluck('child.id')->all();
            sort($ids);

            return $ids === collect([$child1->id, $child2->id])->sort()->values()->all();
        });
    }

    public function test_requesting_activation_again_for_an_already_active_parent_email_still_links_orphaned_guardians(): void
    {
        $email = 'doszil7-b@gmail.com';

        $institution1 = $this->createInstitution('DOSZB1', 'Elso Intezmeny');
        $institution2 = $this->createInstitution('DOSZB2', 'Masodik Intezmeny');

        $user = User::factory()->create([
            'name' => 'Doszkocs Attila',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution1->id,
            'is_active' => true,
        ]);
        $guardian1 = Guardian::query()->create([
            'institution_id' => $institution1->id,
            'user_id' => $user->id,
            'last_name' => 'Doszkocs',
            'first_name' => 'Attila',
            'email' => $email,
            'source_type' => 'manual',
            'active' => true,
        ]);

        // Orphaned guardian from institution 2 (created after the parent
        // was already active elsewhere) -- user_id intentionally left null,
        // exactly as ChildController's "new guardian" path leaves it.
        $guardian2 = Guardian::query()->create([
            'institution_id' => $institution2->id,
            'last_name' => 'Doszkocs',
            'first_name' => 'Attila',
            'email' => $email,
            'source_type' => 'manual',
            'active' => true,
        ]);

        $activationService = app(ParentAccountActivationService::class);
        $result = $activationService->requestActivation($email);

        // The account is already active -> no new password-setup token is
        // (or should be) issued...
        $this->assertSame('already_active_parent', $result['status']);

        // ...but the side effect must be that any of this email's
        // currently-orphaned (user_id === null) active guardians get linked
        // to the existing active account, so the parent does not have to do
        // anything else to see their other child.
        $this->assertSame($user->id, $guardian2->fresh()->user_id);
        $this->assertSame($user->id, $guardian1->fresh()->user_id);
    }

    /**
     * Biztonsági regresszió: ha egy azonos e-mail című guardian-rekord MÁR
     * egy MÁSIK, konkrét felhasználóhoz van kötve (tehát nem árva, hanem
     * ténylegesen "konfliktusban" van az aktív szülővel - pl. mert két
     * különböző valós ember guardian-rekordja véletlenül vagy megosztott
     * levelezés miatt azonos e-mail címet kapott), az automatikus
     * összekapcsolás SOHA nem írhatja felül ezt a meglévő kapcsolatot és
     * nem kapcsolhatja át a másik felhasználóhoz.
     *
     * Ez mindhárom önjavító hívási pontra vonatkozik: guardian-létrehozás
     * (Guardian::booted()), bejelentkezés, és ismételt aktiváció-kérés.
     * Itt közvetlenül a linkOrphanedGuardiansForEmail() szolgáltatás-
     * metódust teszteljük, mert mindhárom hívási pont ugyanezt hívja.
     */
    public function test_guardian_already_linked_to_another_user_is_never_relinked_even_with_matching_email(): void
    {
        $sharedEmail = 'megosztott.email@example.com';

        $institution1 = $this->createInstitution('CONFLICT1', 'Elso Intezmeny');
        $institution2 = $this->createInstitution('CONFLICT2', 'Masodik Intezmeny');

        // Az elsődleges, aktív szülői fiók - ő próbálná meg "learatni" a
        // másik guardian-rekordot, ha a védelem hibás lenne.
        $activeParent = User::factory()->create([
            'name' => 'Aktiv Szulo',
            'email' => $sharedEmail,
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution1->id,
            'is_active' => true,
        ]);
        $ownGuardian = Guardian::query()->create([
            'institution_id' => $institution1->id,
            'user_id' => $activeParent->id,
            'last_name' => 'Aktiv',
            'first_name' => 'Szulo',
            'email' => $sharedEmail,
            'source_type' => 'manual',
            'active' => true,
        ]);
        $ownChild = $this->createChild($institution1->id, 'Sajat Gyerek');
        $ownChild->guardians()->attach($ownGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        // Egy MÁSIK, konkrét felhasználóhoz MÁR hozzákötött guardian-
        // rekord ugyanazzal az e-mail címmel egy másik intézményben -
        // tehát NEM árva, hanem egy valódi (bár szokatlan, azonos e-mailt
        // használó) másik személy/fiók.
        $otherParent = User::factory()->create([
            'name' => 'Masik Szulo',
            'email' => 'masik.szulo@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution2->id,
            'is_active' => true,
        ]);
        $conflictingGuardian = Guardian::query()->create([
            'institution_id' => $institution2->id,
            'user_id' => $otherParent->id,
            'last_name' => 'Masik',
            'first_name' => 'Szulo',
            'email' => $sharedEmail,
            'source_type' => 'manual',
            'active' => true,
        ]);
        $foreignChild = $this->createChild($institution2->id, 'Idegen Gyerek');
        $foreignChild->guardians()->attach($conflictingGuardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $activationService = app(ParentAccountActivationService::class);

        // Közvetlen hívás (ugyanaz fut le bejelentkezéskor, guardian-
        // létrehozáskor és ismételt aktiváció-kérésnél is).
        $linked = $activationService->linkOrphanedGuardiansForEmail($sharedEmail);

        // A hívás visszaadja az e-mailhez tartozó aktív szülőt (van ilyen:
        // $activeParent), DE ettől még nem szabad, hogy bármit átírjon a
        // már máshoz kötött guardian-rekordon.
        $this->assertNotNull($linked);

        $this->assertSame($otherParent->id, $conflictingGuardian->fresh()->user_id, 'A masik felhasznalohoz mar kotott guardian user_id-ja nem valtozhat meg.');
        $this->assertSame($institution2->id, $conflictingGuardian->fresh()->institution_id, 'Az institution_id nem valtozhat meg.');
        $this->assertSame($activeParent->id, $ownGuardian->fresh()->user_id, 'A sajat guardian kapcsolata nem serulhet.');

        // A gyermek-guardian kapcsolat (child_guardian) sem serulhet vagy
        // torlodhet.
        $this->assertTrue($foreignChild->guardians()->where('guardians.id', $conflictingGuardian->id)->exists());
        $this->assertTrue($ownChild->guardians()->where('guardians.id', $ownGuardian->id)->exists());

        // Az aktiv szulo bejelentkezve NEM lathatja az idegen gyermeket.
        $response = $this->actingAs($activeParent)->get('/szulo/gyermekeim');
        $response->assertOk();
        $response->assertViewHas('children', fn ($children) => $children->total() === 1);
        $response->assertViewHas('childCards', function (Collection $childCards) use ($ownChild, $foreignChild) {
            $ids = $childCards->pluck('child.id')->all();

            return $ids === [$ownChild->id] && ! in_array($foreignChild->id, $ids, true);
        });

        // A masik szulo is csak a sajat (idegen) gyermeket lathatja.
        $otherResponse = $this->actingAs($otherParent)->get('/szulo/gyermekeim');
        $otherResponse->assertOk();
        $otherResponse->assertViewHas('children', fn ($children) => $children->total() === 1);
        $otherResponse->assertViewHas('childCards', function (Collection $childCards) use ($foreignChild) {
            return $childCards->pluck('child.id')->all() === [$foreignChild->id];
        });
    }

    /**
     * Generates an activation token the same way
     * ParentAccountActivationService::requestActivation() does internally,
     * but returns the PLAIN token to the test so activate() can be called
     * directly -- without relying on requestActivation() itself (which
     * refuses to issue a token for an already-active parent, and which we
     * cannot re-read the plain value of afterwards since only the hash is
     * persisted).
     */
    private function issueKnownActivationToken(string $email): array
    {
        \App\Models\ParentAccountActivationToken::query()->where('email', $email)->delete();

        $plainToken = bin2hex(random_bytes(32));
        $token = \App\Models\ParentAccountActivationToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(60),
        ]);

        return [$plainToken, $token];
    }

    private function createInstitution(string $code, string $name): Institution
    {
        $institution = Institution::query()->create([
            'name' => $name,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        InstitutionMealSetting::query()->firstOrCreate([
            'institution_id' => $institution->id,
        ], [
            'cancellation_hour' => 9,
            'cancellation_minute' => 0,
        ]);

        return $institution;
    }

    private function createChild(int $institutionId, string $name): Child
    {
        $discount = DiscountType::query()->firstOrCreate(
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

        return Child::query()->create([
            'institution_id' => $institutionId,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId.microtime(true)), 0, 10),
            'group_name' => '1.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }
}
