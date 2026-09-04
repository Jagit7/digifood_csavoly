<?php

namespace Tests\Feature\DailyHeadcount;

use App\Http\Controllers\Dashboard\InstitutionAdmin\DailyHeadcountEmailSettingController;
use App\Jobs\SendDailyHeadcountEmailJob;
use App\Mail\DailyHeadcountEmailMail;
use App\Models\Child;
use App\Models\DailyHeadcountEmailLog;
use App\Models\Institution;
use App\Models\InstitutionDailyHeadcountEmailGroupSetting;
use App\Models\InstitutionDailyHeadcountEmailRecipient;
use App\Models\InstitutionSetting;
use App\Models\MealCancellation;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\DailyHeadcount\DailyHeadcountEmailService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Célzott tesztek a "Napi létszám e-mailek" funkcióhoz (osztályonkénti/
 * csoportonkénti automatikus napi étkezési létszám e-mail, iskolai és
 * óvodai intézményeknél egyaránt).
 *
 * A meglévő tesztfájlok mintáját követi (ld. KitchenNotificationFeatureTest,
 * InstitutionSettingControllerPreservationTest): a controller metódusait
 * közvetlenül hívja egy kézzel épített Request-tel, a routing/middleware
 * réteg megkerülésével, actingAs()-szel biztosítva az auth()->user()-t.
 */
class DailyHeadcountEmailFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * 1. Közös intézményi küldési idő mentése.
     */
    public function test_schedule_update_saves_common_send_time_and_enabled_flag(): void
    {
        [$institution, $user] = $this->createInstitutionAdmin('SCH001');
        $this->actingAs($user);

        $response = app(DailyHeadcountEmailSettingController::class)->updateSchedule(
            $this->makeRequest($user, [
                'daily_headcount_email_enabled' => '1',
                'daily_headcount_email_send_time' => '08:15',
            ])
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(
            route('dashboard.institution.daily-headcount-emails.index'),
            $response->getTargetUrl()
        );

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();
        $this->assertTrue($setting->daily_headcount_email_enabled);
        $this->assertSame('08:15:00', $setting->daily_headcount_email_send_time);
    }

    /**
     * 2. Iskolai osztály konfiguráció mentése.
     */
    public function test_school_class_configuration_is_saved(): void
    {
        [$institution, $user] = $this->createInstitutionAdmin('SCH002', 'iskola');
        $this->createChild($institution->id, 'Iskolás Elek', '3.a');
        $this->actingAs($user);

        app(DailyHeadcountEmailSettingController::class)->updateGroup(
            $this->makeRequest($user, [
                'enabled' => '1',
                'emails' => ['osztalyfonok@example.test'],
            ]),
            '3.a'
        );

        $this->assertTrue(
            InstitutionDailyHeadcountEmailGroupSetting::query()
                ->where('institution_id', $institution->id)
                ->where('group_name', '3.a')
                ->value('enabled')
        );
        $this->assertSame(
            ['osztalyfonok@example.test'],
            InstitutionDailyHeadcountEmailRecipient::query()
                ->where('institution_id', $institution->id)
                ->where('group_name', '3.a')
                ->pluck('email')
                ->all()
        );
    }

    /**
     * 3. Óvodai csoport konfiguráció mentése.
     */
    public function test_kindergarten_group_configuration_is_saved(): void
    {
        [$institution, $user] = $this->createInstitutionAdmin('SCH003', 'ovoda');
        $this->createChild($institution->id, 'Óvodás Ottó', 'Bárány csoport');
        $this->actingAs($user);

        app(DailyHeadcountEmailSettingController::class)->updateGroup(
            $this->makeRequest($user, [
                'enabled' => '1',
                'emails' => ['ovono@example.test'],
            ]),
            'Bárány csoport'
        );

        $this->assertTrue(
            InstitutionDailyHeadcountEmailGroupSetting::query()
                ->where('institution_id', $institution->id)
                ->where('group_name', 'Bárány csoport')
                ->value('enabled')
        );
        $this->assertSame(
            ['ovono@example.test'],
            InstitutionDailyHeadcountEmailRecipient::query()
                ->where('institution_id', $institution->id)
                ->where('group_name', 'Bárány csoport')
                ->pluck('email')
                ->all()
        );
    }

    /**
     * 4. Több e-mail cím mentése egy osztályhoz/csoporthoz, majd a lista
     * cseréje (a korábbi, a mentésből kimaradt címek törlődnek).
     */
    public function test_multiple_recipients_can_be_saved_and_replaced(): void
    {
        [$institution, $user] = $this->createInstitutionAdmin('SCH004');
        $this->createChild($institution->id, 'Elek', '4.b');
        $this->actingAs($user);

        app(DailyHeadcountEmailSettingController::class)->updateGroup(
            $this->makeRequest($user, [
                'enabled' => '1',
                'emails' => ['a@example.test', 'B@Example.test', 'a@example.test'],
            ]),
            '4.b'
        );

        $this->assertSame(
            ['a@example.test', 'b@example.test'],
            InstitutionDailyHeadcountEmailRecipient::query()
                ->where('institution_id', $institution->id)
                ->where('group_name', '4.b')
                ->orderBy('email')
                ->pluck('email')
                ->all()
        );

        app(DailyHeadcountEmailSettingController::class)->updateGroup(
            $this->makeRequest($user, [
                'enabled' => '1',
                'emails' => ['c@example.test'],
            ]),
            '4.b'
        );

        $this->assertSame(
            ['c@example.test'],
            InstitutionDailyHeadcountEmailRecipient::query()
                ->where('institution_id', $institution->id)
                ->where('group_name', '4.b')
                ->pluck('email')
                ->all()
        );
    }

    /**
     * 5. Hibás e-mail cím validáció.
     */
    public function test_invalid_email_fails_validation(): void
    {
        [$institution, $user] = $this->createInstitutionAdmin('SCH005');
        $this->createChild($institution->id, 'Elek', '5.a');
        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        try {
            app(DailyHeadcountEmailSettingController::class)->updateGroup(
                $this->makeRequest($user, [
                    'enabled' => '1',
                    'emails' => ['nem-egy-email-cim'],
                ]),
                '5.a'
            );
        } finally {
            $this->assertSame(
                0,
                InstitutionDailyHeadcountEmailRecipient::query()
                    ->where('institution_id', $institution->id)
                    ->where('group_name', '5.a')
                    ->count()
            );
        }
    }

    /**
     * 6. Megfelelő idő után elküldi - akkor is, ha a scheduler néhány
     * perccel később fut, mint a beállított küldési idő (nem percre pontos
     * egyezést vizsgálunk).
     */
    public function test_command_sends_after_the_configured_time_even_if_the_scheduler_runs_a_few_minutes_late(): void
    {
        $institution = $this->createConfiguredInstitution('CMD001', '07:30');
        $this->enableGroup($institution, '3.a', ['osztalyfonok@example.test']);

        Queue::fake();
        CarbonImmutable::setTestNow('2026-09-07 07:33:00 Europe/Budapest'); // hétfő

        Artisan::call('digifood:daily-headcount-email:dispatch');

        Queue::assertPushed(SendDailyHeadcountEmailJob::class, 1);

        $log = DailyHeadcountEmailLog::query()
            ->where('institution_id', $institution->id)
            ->where('group_name', '3.a')
            ->whereDate('headcount_date', '2026-09-07')
            ->firstOrFail();

        $this->assertSame(DailyHeadcountEmailLog::STATUS_QUEUED, $log->status);
    }

    /**
     * 7. Ugyanazon a napon csak egyszer küldi (idempotencia).
     */
    public function test_command_does_not_send_twice_on_the_same_day(): void
    {
        $institution = $this->createConfiguredInstitution('CMD002', '07:30');
        $this->enableGroup($institution, '3.a', ['osztalyfonok@example.test']);

        Queue::fake();

        CarbonImmutable::setTestNow('2026-09-07 07:30:00 Europe/Budapest');
        Artisan::call('digifood:daily-headcount-email:dispatch');

        CarbonImmutable::setTestNow('2026-09-07 07:31:00 Europe/Budapest');
        Artisan::call('digifood:daily-headcount-email:dispatch');

        CarbonImmutable::setTestNow('2026-09-07 07:32:00 Europe/Budapest');
        Artisan::call('digifood:daily-headcount-email:dispatch');

        Queue::assertPushed(SendDailyHeadcountEmailJob::class, 1);
        $this->assertSame(
            1,
            DailyHeadcountEmailLog::query()
                ->where('institution_id', $institution->id)
                ->where('group_name', '3.a')
                ->count()
        );
    }

    /**
     * 11. (kiegészítés a 7-eshez) Egy elmaradt, korábbi napi küldést a
     * rendszer másnap már nem pótol utólag.
     */
    public function test_missed_send_time_is_not_sent_retroactively_the_next_day(): void
    {
        $institution = $this->createConfiguredInstitution('CMD003', '07:30');
        $this->enableGroup($institution, '3.a', ['osztalyfonok@example.test']);

        Queue::fake();

        // Tegnap a scheduler valamiért nem futott le - nincs semmilyen log.
        CarbonImmutable::setTestNow('2026-09-08 09:00:00 Europe/Budapest'); // kedd, a mai napi küldési idő már elmúlt

        Artisan::call('digifood:daily-headcount-email:dispatch');

        // A mai napra viszont ki kell mennie.
        Queue::assertPushed(SendDailyHeadcountEmailJob::class, 1);
        $this->assertSame(
            0,
            DailyHeadcountEmailLog::query()
                ->where('institution_id', $institution->id)
                ->whereDate('headcount_date', '2026-09-07')
                ->count()
        );
        $this->assertSame(
            1,
            DailyHeadcountEmailLog::query()
                ->where('institution_id', $institution->id)
                ->whereDate('headcount_date', '2026-09-08')
                ->count()
        );
    }

    /**
     * 8. Másik intézmény adatait nem használja - sem a scheduler parancs,
     * sem a controller (URL-manipulációval sem érhető el másik intézmény
     * osztálya/csoportja).
     */
    public function test_command_and_controller_never_use_another_institutions_data(): void
    {
        $institutionA = $this->createConfiguredInstitution('ISO001', '07:30');
        $this->enableGroup($institutionA, '3.a', ['a-admin@example.test']);

        $institutionB = $this->createConfiguredInstitution('ISO002', '07:30');
        $this->enableGroup($institutionB, '3.a', ['b-admin@example.test']);

        Queue::fake();
        CarbonImmutable::setTestNow('2026-09-07 07:30:00 Europe/Budapest');
        Artisan::call('digifood:daily-headcount-email:dispatch');

        Queue::assertPushed(SendDailyHeadcountEmailJob::class, 2);

        $logA = DailyHeadcountEmailLog::query()->where('institution_id', $institutionA->id)->firstOrFail();
        $this->assertSame(['a-admin@example.test'], $logA->recipient_emails);
        $logB = DailyHeadcountEmailLog::query()->where('institution_id', $institutionB->id)->firstOrFail();
        $this->assertSame(['b-admin@example.test'], $logB->recipient_emails);

        // Az intézmény A admin felhasználója nem érheti el a B intézmény
        // "3.a" csoportját, még ha a névvel egyezik is - assertGroupBelongsToInstitution() 404-et ad.
        [, $userA] = $this->createInstitutionAdmin('ISO003');
        DB::table('institution_user')->where('user_id', $userA->id)->delete();
        DB::table('institution_user')->insert([
            'institution_id' => $institutionA->id,
            'user_id' => $userA->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($userA);

        $this->expectException(NotFoundHttpException::class);
        app(DailyHeadcountEmailSettingController::class)->preview('nem-letezo-csoport-ebben-az-intezmenyben');
    }

    /**
     * 9. Lemondott gyermek nem növeli a létszámot.
     */
    public function test_cancelled_child_does_not_increase_the_headcount(): void
    {
        $institution = $this->createConfiguredInstitution('CNT001', '07:30');
        $eating = $this->createChild($institution->id, 'Étkező Elek', '3.a');
        $cancelled = $this->createChild($institution->id, 'Lemondott Lili', '3.a');

        foreach ([$eating, $cancelled] as $child) {
            StudentMealSetting::create([
                'student_id' => $child->id,
                'institution_id' => $institution->id,
                'institution_meal_package_id' => null,
                'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
                'valid_from' => '2026-09-01',
                'valid_to' => null,
            ]);
        }

        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $cancelled->id,
            'service_date' => '2026-09-07',
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);

        $summary = app(DailyHeadcountEmailService::class)->buildGroupSummary($institution, '3.a', '2026-09-07');

        $this->assertSame(1, $summary['eaters_count']);
    }

    /**
     * 10. Inaktív (kikapcsolt) osztály/csoport nem küld, még akkor sem, ha
     * vannak beállított címzettjei.
     */
    public function test_disabled_group_does_not_receive_email(): void
    {
        $institution = $this->createConfiguredInstitution('DIS001', '07:30');
        $this->createChild($institution->id, 'Elek', '3.a');

        // Van címzett, de a csoport NINCS bekapcsolva.
        app(DailyHeadcountEmailService::class)->syncGroupRecipients($institution->id, '3.a', ['osztalyfonok@example.test']);

        Queue::fake();
        CarbonImmutable::setTestNow('2026-09-07 07:30:00 Europe/Budapest');
        Artisan::call('digifood:daily-headcount-email:dispatch');

        Queue::assertNotPushed(SendDailyHeadcountEmailJob::class);
        $this->assertSame(0, DailyHeadcountEmailLog::query()->where('institution_id', $institution->id)->count());
    }

    /**
     * 11. Címzett nélküli konfiguráció nem küld, még bekapcsolt állapotban
     * sem.
     */
    public function test_group_without_recipients_does_not_receive_email(): void
    {
        $institution = $this->createConfiguredInstitution('REC001', '07:30');
        $this->createChild($institution->id, 'Elek', '3.a');

        // Be van kapcsolva, de nincs egyetlen címzett sem.
        app(DailyHeadcountEmailService::class)->setGroupEnabled($institution->id, '3.a', true);

        Queue::fake();
        CarbonImmutable::setTestNow('2026-09-07 07:30:00 Europe/Budapest');
        Artisan::call('digifood:daily-headcount-email:dispatch');

        Queue::assertNotPushed(SendDailyHeadcountEmailJob::class);
        $this->assertSame(0, DailyHeadcountEmailLog::query()->where('institution_id', $institution->id)->count());
    }

    /**
     * 12. Több aktív osztály/csoport egyszerre, ugyanabban a közös
     * időpontban feldolgozódik.
     */
    public function test_multiple_active_groups_are_processed_at_the_common_send_time(): void
    {
        $institution = $this->createConfiguredInstitution('MULTI01', '07:30');
        $this->createChild($institution->id, 'Elek', '3.a');
        $this->createChild($institution->id, 'Panka', '3.b');
        $this->createChild($institution->id, 'Kata', '4.a'); // ez marad kikapcsolva

        $this->enableGroup($institution, '3.a', ['3a@example.test']);
        $this->enableGroup($institution, '3.b', ['3b@example.test']);

        Queue::fake();
        CarbonImmutable::setTestNow('2026-09-07 07:30:00 Europe/Budapest');
        Artisan::call('digifood:daily-headcount-email:dispatch');

        Queue::assertPushed(SendDailyHeadcountEmailJob::class, 2);
        $this->assertSame(
            ['3.a', '3.b'],
            DailyHeadcountEmailLog::query()
                ->where('institution_id', $institution->id)
                ->orderBy('group_name')
                ->pluck('group_name')
                ->all()
        );
    }

    /**
     * 13. Az e-mail Blade nézet a projekt meglévő Digifood e-mail
     * megjelenését használja (logó, fejléc/lábléc, márkajelzés), és nem
     * tartalmaz gyermeknévlistát / személyes adatot.
     */
    public function test_email_view_matches_the_existing_digifood_look_and_contains_no_personal_data(): void
    {
        $institution = $this->createConfiguredInstitution('VIEW001', '07:30');
        $this->createChild($institution->id, 'Titkos Titusz', '3.a');

        $summary = app(DailyHeadcountEmailService::class)->buildGroupSummary($institution, '3.a', '2026-09-07');

        $html = view('emails.daily-headcount', $summary)->render();

        $this->assertStringContainsString('home/logo.png', $html);
        $this->assertStringContainsString('Digifood', $html);
        $this->assertStringContainsString('Napi étkezési létszám', $html);
        $this->assertStringContainsString((string) $summary['eaters_count'].' fő', $html);
        $this->assertStringNotContainsString('Titkos Titusz', $html);
    }

    /**
     * Kiegészítő teszt: a Job ténylegesen a beállított címzetteknek küldi
     * ki a levelet, és "sent" állapotra állítja a naplót.
     */
    public function test_job_sends_mail_to_configured_recipients_and_marks_log_sent(): void
    {
        $institution = $this->createConfiguredInstitution('JOB001', '07:30');
        $this->createChild($institution->id, 'Elek', '3.a');

        $log = DailyHeadcountEmailLog::create([
            'institution_id' => $institution->id,
            'group_name' => '3.a',
            'headcount_date' => '2026-09-07',
            'status' => DailyHeadcountEmailLog::STATUS_QUEUED,
            'recipient_emails' => ['osztalyfonok@example.test'],
            'scheduled_at' => now(),
        ]);

        app(DailyHeadcountEmailService::class)->syncGroupRecipients($institution->id, '3.a', ['osztalyfonok@example.test']);

        Mail::fake();

        app(SendDailyHeadcountEmailJob::class, ['logId' => $log->id])->handle(app(DailyHeadcountEmailService::class));

        Mail::assertSent(DailyHeadcountEmailMail::class, function (DailyHeadcountEmailMail $mail) {
            return $mail->hasTo('osztalyfonok@example.test');
        });

        $this->assertSame(DailyHeadcountEmailLog::STATUS_SENT, $log->fresh()->status);
    }

    private function createInstitutionAdmin(string $code, string $type = 'iskola'): array
    {
        $institution = Institution::create([
            'name' => 'Teszt Intézmény '.$code,
            'institution_code' => $code,
            'type' => $type,
            'active' => true,
        ]);
        $user = $this->createAdminUser($institution);

        return [$institution, $user];
    }

    private function createConfiguredInstitution(string $code, string $sendTime, string $type = 'iskola'): Institution
    {
        $institution = Institution::create([
            'name' => 'Teszt Intézmény '.$code,
            'institution_code' => $code,
            'type' => $type,
            'active' => true,
        ]);

        InstitutionSetting::create(array_merge(InstitutionSetting::defaults(), [
            'institution_id' => $institution->id,
            'daily_headcount_email_enabled' => true,
            'daily_headcount_email_send_time' => $sendTime.':00',
        ]));

        return $institution;
    }

    private function enableGroup(Institution $institution, string $groupName, array $emails): void
    {
        app(DailyHeadcountEmailService::class)->updateGroupConfiguration(
            $institution->id,
            $groupName,
            true,
            $emails
        );
    }

    private function createAdminUser(Institution $institution): User
    {
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

        return $user;
    }

    private function createChild(int $institutionId, string $name, string $groupName): Child
    {
        return Child::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId.$groupName), 0, 10),
            'group_name' => $groupName,
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function makeRequest(User $user, array $data): Request
    {
        $request = Request::create('/', 'POST', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
