<?php

namespace Tests\Feature\Kitchen;

use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionSettingController;
use App\Jobs\SendKitchenDailySummaryJob;
use App\Mail\KitchenDailySummaryMail;
use App\Models\AbMenuItem;
use App\Models\AbMenuPlan;
use App\Models\Child;
use App\Models\ClassCancellation;
use App\Models\ClassGroup;
use App\Models\DietaryRestriction;
use App\Models\EmployeeMealCancellation;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionMealType;
use App\Models\InstitutionSetting;
use App\Models\KitchenNotificationLog;
use App\Models\MealCancellation;
use App\Models\MealType;
use App\Models\MenuChoice;
use App\Models\RecurringCancellationRule;
use App\Models\SchoolBreak;
use App\Models\SchoolYear;
use App\Models\StudentMealSetting;
use App\Models\StudentMealSettingItem;
use App\Models\User;
use App\Models\WorkingDay;
use App\Services\Kitchen\KitchenDailySummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KitchenNotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_settings_page_saves_kitchen_notification_recipients(): void
    {
        [$institution, $user] = $this->createInstitutionAdmin('KSET01');

        $this->actingAs($user);

        $response = app()->call(
            [app(InstitutionSettingController::class), 'update'],
            [
                'request' => $this->makeRequest($user, [
                    'send_kitchen_email' => '1',
                    'kitchen_notification_emails' => "konyha@example.test\nETKEZES@example.test",
                    'payment_notification_enabled' => '0',
                    'payment_notification_day' => 5,
                    'payment_due_day' => 5,
                    'ab_menu_choice_deadline_day' => 20,
                ]),
            ]
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.settings.edit'), $response->getTargetUrl());

        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertTrue($setting->send_kitchen_email);
        $this->assertSame(
            ['konyha@example.test', 'etkezes@example.test'],
            $setting->kitchenNotificationEmails()
        );
    }

    public function test_command_dispatches_only_at_cutoff_plus_one_minute(): void
    {
        $institution = $this->createConfiguredInstitution('KCMD01', ['konyha@example.test'], true, 9, 30);

        Queue::fake();

        CarbonImmutable::setTestNow('2026-09-03 09:30:00 Europe/Budapest');
        Artisan::call('digifood:kitchen-notifications:dispatch');
        Queue::assertNothingPushed();

        CarbonImmutable::setTestNow('2026-09-03 09:31:00 Europe/Budapest');
        Artisan::call('digifood:kitchen-notifications:dispatch');

        Queue::assertPushed(SendKitchenDailySummaryJob::class, 1);
        $this->assertDatabaseHas('kitchen_notification_logs', [
            'institution_id' => $institution->id,
            'target_service_date' => '2026-09-04 00:00:00',
            'status' => KitchenNotificationLog::STATUS_QUEUED,
        ]);
    }

    public function test_command_skips_disabled_or_recipientless_institutions(): void
    {
        $disabled = $this->createConfiguredInstitution('KCMD02', ['konyha@example.test'], false, 9, 30);
        $recipientless = $this->createConfiguredInstitution('KCMD03', [], true, 9, 30);

        Queue::fake();
        CarbonImmutable::setTestNow('2026-09-03 09:31:00 Europe/Budapest');

        Artisan::call('digifood:kitchen-notifications:dispatch');

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('kitchen_notification_logs', ['institution_id' => $disabled->id]);
        $this->assertDatabaseMissing('kitchen_notification_logs', ['institution_id' => $recipientless->id]);
    }

    /**
     * A konyhán hétvégén senki sem dolgozik, tehát a hétfői létszámról már
     * PÉNTEKEN (az utolsó munkanapon) tájékoztatni kell a konyhát, nem
     * vasárnap - vasárnap úgyis zárva vannak, nem tudnák időben feldolgozni.
     * A szabály: ha MA szolgáltatási nap, de HOLNAP már nem (hétvége vagy
     * "ledolgozatlan" nap kezdődik), akkor a kimaradás UTÁNI első tanítási
     * napról kell tájékoztatni, még ha az nem is holnapra esik.
     */
    public function test_command_sends_on_last_working_day_before_a_weekend_or_working_saturday(): void
    {
        $fridayInstitution = $this->createConfiguredInstitution('KCMD04', ['konyha@example.test'], true, 9, 30);
        $saturdayInstitution = $this->createConfiguredInstitution('KCMD06', ['konyha@example.test'], true, 9, 30);

        WorkingDay::create([
            'institution_id' => $saturdayInstitution->id,
            'date' => '2026-10-17',
            'name' => 'Ledolgozós szombat',
            'type' => 'school_saturday',
        ]);

        Queue::fake();

        // Rendes péntek: a holnapja (szombat) nem szolgáltatási nap, tehát ma
        // (az utolsó munkanapon) kell tájékoztatni a hétvége utáni első
        // tanítási napról (hétfő) - nem vasárnap.
        CarbonImmutable::setTestNow('2026-09-04 09:31:00 Europe/Budapest');
        Artisan::call('digifood:kitchen-notifications:dispatch');

        // A Ledolgozós szombat előtti péntek - itt a holnapja (a WorkingDay
        // miatt) maga is szolgáltatási nap, tehát a "normál" (2.) eset
        // érvényesül: erről a szombatról kell tájékoztatni.
        CarbonImmutable::setTestNow('2026-10-16 09:31:00 Europe/Budapest');
        Artisan::call('digifood:kitchen-notifications:dispatch');

        $this->assertDatabaseHas('kitchen_notification_logs', [
            'institution_id' => $fridayInstitution->id,
            'target_service_date' => '2026-09-07 00:00:00',
        ]);
        $this->assertDatabaseHas('kitchen_notification_logs', [
            'institution_id' => $saturdayInstitution->id,
            'target_service_date' => '2026-10-17 00:00:00',
        ]);
    }

    /**
     * Szünet előtt ugyanez a logika érvényes: az utolsó MUNKANAPON (nem a
     * szünet valamelyik napján, hiszen akkor senki sincs a konyhán) kell
     * tájékoztatni a szünet (és egy közvetlenül utána eső hétvége) UTÁNI
     * első tanítási napról.
     */
    public function test_command_sends_on_last_working_day_before_a_break_for_the_first_day_after_it(): void
    {
        $breakInstitution = $this->createConfiguredInstitution('KCMD05', ['konyha@example.test'], true, 9, 30);

        // A szünet hétfőtől péntekig tart, utána hétvége következik.
        SchoolBreak::create([
            'institution_id' => $breakInstitution->id,
            'title' => 'Őszi szünet',
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-02',
            'type' => 'school_break',
        ]);

        Queue::fake();

        // A szünet ELŐTTI utolsó munkanap (péntek): a holnapja (a szünet
        // első napja, hétfő) már nem szolgáltatási nap, tehát ma kell
        // tájékoztatni a szünet + az utána eső hétvége UTÁNI első tanítási
        // napról (a rákövetkező hétfő).
        CarbonImmutable::setTestNow('2026-09-25 09:31:00 Europe/Budapest');
        Artisan::call('digifood:kitchen-notifications:dispatch');

        $this->assertDatabaseHas('kitchen_notification_logs', [
            'institution_id' => $breakInstitution->id,
            'target_service_date' => '2026-10-05 00:00:00',
        ]);
    }

    public function test_command_does_not_send_in_the_middle_of_a_break(): void
    {
        $midBreakInstitution = $this->createConfiguredInstitution('KCMD08', ['konyha@example.test'], true, 9, 30);

        SchoolBreak::create([
            'institution_id' => $midBreakInstitution->id,
            'title' => 'Őszi szünet',
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-02',
            'type' => 'school_break',
        ]);

        Queue::fake();

        // A szünet KÖZEPÉN (kedd) MA sem szolgáltatási nap - a konyha
        // amúgy sincs nyitva, nem szabad levelet küldeni (a tájékoztatás már
        // a szünet előtti utolsó munkanapon kiment).
        CarbonImmutable::setTestNow('2026-09-29 09:31:00 Europe/Budapest');
        Artisan::call('digifood:kitchen-notifications:dispatch');

        $this->assertDatabaseMissing('kitchen_notification_logs', ['institution_id' => $midBreakInstitution->id]);
    }

    public function test_command_does_not_send_on_a_weekend_day(): void
    {
        $institution = $this->createConfiguredInstitution('KCMD09', ['konyha@example.test'], true, 9, 30);

        Queue::fake();

        // Szombaton és vasárnap MA sem szolgáltatási nap - senki sem dolgozik
        // a konyhán, nem szabad (és nem is kell, hiszen a pénteki levél már
        // tartalmazta a hétfői létszámot) levelet küldeni ezeken a napokon.
        CarbonImmutable::setTestNow('2026-09-05 09:31:00 Europe/Budapest');
        Artisan::call('digifood:kitchen-notifications:dispatch');

        CarbonImmutable::setTestNow('2026-09-06 09:31:00 Europe/Budapest');
        Artisan::call('digifood:kitchen-notifications:dispatch');

        $this->assertDatabaseMissing('kitchen_notification_logs', ['institution_id' => $institution->id]);
    }

    public function test_command_does_not_duplicate_same_institution_and_service_date(): void
    {
        $institution = $this->createConfiguredInstitution('KCMD07', ['konyha@example.test'], true, 9, 30);

        Queue::fake();
        CarbonImmutable::setTestNow('2026-09-03 09:31:00 Europe/Budapest');

        Artisan::call('digifood:kitchen-notifications:dispatch');
        Artisan::call('digifood:kitchen-notifications:dispatch');

        Queue::assertPushed(SendKitchenDailySummaryJob::class, 1);
        $this->assertSame(
            1,
            KitchenNotificationLog::query()->where('institution_id', $institution->id)->count()
        );
    }

    public function test_job_sends_summary_with_ab_dietary_and_restriction_breakdown(): void
    {
        $institution = $this->createConfiguredInstitution('KJOB01', ['konyha@example.test', 'chef@example.test'], true, 9, 30);
        $admin = $this->createAdminUser($institution);
        [$lunchType, $snackType] = $this->createMealTypes($institution);

        $lactose = DietaryRestriction::create([
            'institution_id' => $institution->id,
            'name' => 'Laktóz',
            'type' => DietaryRestriction::TYPE_INTOLERANCE,
            'active' => true,
            'sort_order' => 1,
        ]);
        $gluten = DietaryRestriction::create([
            'institution_id' => $institution->id,
            'name' => 'Glutén',
            'type' => DietaryRestriction::TYPE_ALLERGEN,
            'active' => true,
            'sort_order' => 2,
        ]);

        $childA = $this->createChild($institution->id, 'Alap Anna');
        $childB = $this->createChild($institution->id, 'Béla Bence');
        $dietaryChild = $this->createChild($institution->id, 'Diétás Dóra');
        $cancelledChild = $this->createChild($institution->id, 'Lemondott Lili');

        $dietaryChild->dietaryRestrictions()->attach([$lactose->id, $gluten->id]);

        foreach ([$childA, $childB, $dietaryChild, $cancelledChild] as $child) {
            $setting = StudentMealSetting::create([
                'student_id' => $child->id,
                'institution_id' => $institution->id,
                'institution_meal_package_id' => null,
                'mode' => StudentMealSetting::MODE_CUSTOM,
                'valid_from' => '2026-09-01',
                'valid_to' => null,
            ]);

            StudentMealSettingItem::create([
                'student_meal_setting_id' => $setting->id,
                'institution_meal_type_id' => $lunchType->id,
                'display_order' => 1,
            ]);
            StudentMealSettingItem::create([
                'student_meal_setting_id' => $setting->id,
                'institution_meal_type_id' => $snackType->id,
                'display_order' => 2,
            ]);
        }

        $plan = AbMenuPlan::create([
            'institution_id' => $institution->id,
            'title' => 'Szeptemberi A/B',
            'valid_from' => '2026-09-10',
            'valid_to' => '2026-09-10',
            'active' => true,
            'published_at' => '2026-08-20 10:00:00',
            'created_by' => $admin->id,
        ]);
        $item = AbMenuItem::create([
            'ab_menu_plan_id' => $plan->id,
            'menu_date' => '2026-09-10',
            'menu_a' => 'A menü',
            'menu_b' => 'B menü',
            'menu_dietary' => 'Diétás menü',
            'allergens' => null,
            'note' => null,
        ]);
        MenuChoice::create([
            'institution_id' => $institution->id,
            'child_id' => $childB->id,
            'ab_menu_item_id' => $item->id,
            'menu_date' => '2026-09-10',
            'choice' => MenuChoice::CHOICE_B,
            'selected_by' => $admin->id,
        ]);
        MealCancellation::create([
            'institution_id' => $institution->id,
            'child_id' => $cancelledChild->id,
            'service_date' => '2026-09-10',
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);

        $log = KitchenNotificationLog::create([
            'institution_id' => $institution->id,
            'target_service_date' => '2026-09-10',
            'status' => KitchenNotificationLog::STATUS_QUEUED,
            'recipient_emails' => ['konyha@example.test', 'chef@example.test'],
            'scheduled_at' => now(),
        ]);

        Mail::fake();

        app(SendKitchenDailySummaryJob::class, ['logId' => $log->id])->handle(app(KitchenDailySummaryService::class));

        Mail::assertSent(KitchenDailySummaryMail::class, function (KitchenDailySummaryMail $mail) {
            $summary = $mail->summary;

            return $mail->hasTo('konyha@example.test')
                && $mail->hasTo('chef@example.test')
                && $summary['stats']['daily_eaters'] === 3
                && $summary['stats']['menu_a_count'] === 1
                && $summary['stats']['menu_b_count'] === 1
                && $summary['stats']['dietary_count'] === 1
                && $summary['meal_type_counts']->pluck('count', 'name')->all() === [
                    'Ebéd' => 3,
                    'Uzsonna' => 3,
                ]
                && $summary['dietary_breakdown']->pluck('count', 'name')->all() === [
                    'Laktóz- és gluténmentes' => 1,
                ]
                && str_contains($summary['subject'], '2026.09.10.')
                && str_contains($summary['subject'], '3 fő');
        });

        $this->assertDatabaseHas('kitchen_notification_logs', [
            'id' => $log->id,
            'status' => KitchenNotificationLog::STATUS_SENT,
        ]);
    }

    public function test_summary_hides_ab_breakdown_when_no_ab_menu_exists(): void
    {
        $institution = $this->createConfiguredInstitution('KJOB02', ['konyha@example.test'], true, 9, 30);
        [$lunchType] = $this->createMealTypes($institution, includeSnack: false);
        $child = $this->createChild($institution->id, 'Napi Nóra');

        $setting = StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_CUSTOM,
            'valid_from' => '2026-09-01',
            'valid_to' => null,
        ]);
        StudentMealSettingItem::create([
            'student_meal_setting_id' => $setting->id,
            'institution_meal_type_id' => $lunchType->id,
            'display_order' => 1,
        ]);

        $summary = app(KitchenDailySummaryService::class)->buildSummary($institution, '2026-09-11');

        $this->assertFalse($summary['has_ab_menu']);
        $this->assertSame(1, $summary['stats']['daily_eaters']);
        $this->assertSame(0, $summary['stats']['menu_a_count']);
        $this->assertSame(0, $summary['stats']['menu_b_count']);
    }

    public function test_summary_combines_child_and_employee_headcounts_and_email_shows_the_breakdown(): void
    {
        $institution = $this->createConfiguredInstitution('KJOB04', ['konyha@example.test'], true, 9, 30);
        [$lunchType, $snackType] = $this->createMealTypes($institution);

        $child = $this->createChild($institution->id, 'Gyermek Gizi');
        $this->assignChildCustomMealSetting($institution->id, $child->id, [$lunchType->id, $snackType->id]);

        $dietaryRestriction = DietaryRestriction::create([
            'institution_id' => $institution->id,
            'name' => 'Tejfehérje',
            'type' => DietaryRestriction::TYPE_INTOLERANCE,
            'active' => true,
            'sort_order' => 1,
        ]);

        $employee = $this->createEmployee($institution->id, 'Dolgozó Dénes');
        $employee->dietaryRestrictions()->attach($dietaryRestriction->id);
        $this->assignEmployeeCustomMealSetting($institution->id, $employee->id, [$lunchType->id]);

        $cancelledEmployee = $this->createEmployee($institution->id, 'Lemondott Elemér');
        $this->assignEmployeeCustomMealSetting($institution->id, $cancelledEmployee->id, [$lunchType->id]);

        EmployeeMealCancellation::create([
            'institution_id' => $institution->id,
            'institution_employee_id' => $cancelledEmployee->id,
            'service_date' => '2026-09-11',
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'status' => EmployeeMealCancellation::STATUS_ACTIVE,
        ]);

        $summary = app(KitchenDailySummaryService::class)->buildSummary($institution, '2026-09-11');
        $childDaily = app(\App\Services\DailyMealHeadcountService::class)->forDate($institution->id, '2026-09-11');

        $this->assertSame(1, $childDaily['stats']['daily_eaters']);
        $this->assertSame(1, $summary['stats']['child_daily_eaters']);
        $this->assertSame(1, $summary['stats']['employee_daily_eaters']);
        $this->assertSame(2, $summary['stats']['daily_eaters']);
        $this->assertSame(1, $summary['stats']['employee_cancelled_meals']);
        $this->assertSame(1, $summary['stats']['dietary_eaters']);
        $this->assertSame([
            'Ebéd' => ['child' => 1, 'employee' => 1, 'total' => 2],
            'Uzsonna' => ['child' => 1, 'employee' => 0, 'total' => 1],
        ], $summary['meal_type_counts']->mapWithKeys(fn (array $item) => [
            $item['name'] => [
                'child' => $item['child_count'],
                'employee' => $item['employee_count'],
                'total' => $item['count'],
            ],
        ])->all());

        $mail = new KitchenDailySummaryMail($summary);
        $html = $mail->render();

        $this->assertStringContainsString('Gyermekek: <strong>1</strong>', $html);
        $this->assertStringContainsString('Dolgozók: <strong>1</strong>', $html);
        $this->assertStringContainsString('Összesen', $html);
        $this->assertStringContainsString('Dolgozó', $html);
    }

    public function test_job_remains_idempotent_on_repeated_execution(): void
    {
        $institution = $this->createConfiguredInstitution('KJOB03', ['konyha@example.test'], true, 9, 30);
        $log = KitchenNotificationLog::create([
            'institution_id' => $institution->id,
            'target_service_date' => '2026-09-11',
            'status' => KitchenNotificationLog::STATUS_SENT,
            'recipient_emails' => ['konyha@example.test'],
            'scheduled_at' => now(),
            'sent_at' => now(),
        ]);

        Mail::fake();

        app(SendKitchenDailySummaryJob::class, ['logId' => $log->id])->handle(app(KitchenDailySummaryService::class));

        Mail::assertNothingSent();
        $this->assertDatabaseHas('kitchen_notification_logs', [
            'id' => $log->id,
            'status' => KitchenNotificationLog::STATUS_SENT,
        ]);
    }

    public function test_school_sends_one_email_with_all_classes_and_unique_cancelled_children(): void
    {
        $institution = $this->createConfiguredInstitution('KSCHOOL', ['konyha@example.test'], true, 9, 30);
        [$lunch, $snack] = $this->createMealTypes($institution);
        $year = SchoolYear::create(['institution_id' => $institution->id, 'name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31', 'is_current' => true]);
        $classes = collect(['10.A', '2.A', '1.B', '1.A'])->mapWithKeys(function ($name) use ($institution, $year) {
            return [$name => ClassGroup::create(['institution_id' => $institution->id, 'school_year_id' => $year->id, 'name' => $name, 'active' => true])];
        });
        ClassGroup::create(['institution_id' => $institution->id, 'school_year_id' => $year->id, 'name' => 'Inaktív osztály', 'active' => false]);
        $children = collect([
            ['Nagy Péter', '1.A'], ['Kiss Anna', '1.A'], ['Étkező Elek', '1.A'],
            ['Jelen Júlia', '1.B'], ['Csoportos Cili', '2.A'],
        ])->mapWithKeys(function ($data) use ($institution, $lunch, $snack) {
            [$name, $group] = $data;
            $child = $this->createChild($institution->id, $name);
            $child->update(['group_name' => $group]);
            $this->assignChildCustomMealSetting($institution->id, $child->id, $group === '1.B' ? [$lunch->id] : [$lunch->id, $snack->id]);

            return [$name => $child];
        });
        $missing = $this->createChild($institution->id, 'Beállítás nélkül');
        $missing->update(['group_name' => '1.A']);
        foreach (['Nagy Péter', 'Kiss Anna', 'Beállítás nélkül'] as $name) {
            MealCancellation::create(['institution_id' => $institution->id, 'child_id' => $name === 'Beállítás nélkül' ? $missing->id : $children[$name]->id, 'service_date' => '2026-09-10', 'source' => MealCancellation::SOURCE_ADMIN, 'status' => MealCancellation::STATUS_ACTIVE]);
        }
        // Both individual and recurring cancellation still represent one child.
        RecurringCancellationRule::create(['institution_id' => $institution->id, 'child_id' => $children['Kiss Anna']->id, 'weekday' => 4, 'starts_on' => '2026-09-01', 'source' => 'admin', 'status' => RecurringCancellationRule::STATUS_ACTIVE]);
        $classes['2.A']->children()->attach($children['Csoportos Cili']->id, ['status' => 'active', 'joined_on' => '2026-09-01']);
        ClassCancellation::create(['institution_id' => $institution->id, 'class_group_id' => $classes['2.A']->id, 'date_from' => '2026-09-10', 'date_to' => '2026-09-10']);
        // Multiple settings do not duplicate an eater, either.
        $this->assignChildCustomMealSetting($institution->id, $children['Étkező Elek']->id, [$lunch->id, $snack->id]);
        $future = $this->createChild($institution->id, 'Később kezd');
        $future->update(['group_name' => '1.B']);
        StudentMealSetting::create(['student_id' => $future->id, 'institution_id' => $institution->id, 'mode' => 'custom', 'valid_from' => '2026-09-11']);
        $expired = $this->createChild($institution->id, 'Már megszűnt');
        $expired->update(['group_name' => '1.B']);
        StudentMealSetting::create(['student_id' => $expired->id, 'institution_id' => $institution->id, 'mode' => 'custom', 'valid_from' => '2026-09-01', 'valid_to' => '2026-09-09']);

        Queue::fake();
        Mail::fake();
        CarbonImmutable::setTestNow('2026-09-09 09:31:00 Europe/Budapest');
        Artisan::call('digifood:kitchen-notifications:dispatch');
        Artisan::call('digifood:kitchen-notifications:dispatch');
        Queue::assertPushed(SendKitchenDailySummaryJob::class, 1);
        $log = KitchenNotificationLog::where('institution_id', $institution->id)->firstOrFail();
        $job = new SendKitchenDailySummaryJob($log->id);
        $job->handle(app(KitchenDailySummaryService::class));
        $job->handle(app(KitchenDailySummaryService::class));
        Mail::assertSentCount(1);
        Mail::assertSent(KitchenDailySummaryMail::class, function ($mail) {
            $school = $mail->summary['school_summary'];
            $this->assertSame(5, $school['total']);
            $this->assertSame(3, $school['absent']);
            $this->assertSame(2, $school['eating']);
            $this->assertSame(['1.A', '1.B', '2.A', '10.A'], $school['classes']->pluck('name')->all());
            $classes = $school['classes']->keyBy('name');
            $this->assertSame([3, 2, 1], [$classes['1.A']['total'], $classes['1.A']['absent'], $classes['1.A']['eating']]);
            $this->assertSame([1, 0, 1], [$classes['1.B']['total'], $classes['1.B']['absent'], $classes['1.B']['eating']]);
            $this->assertSame([1, 1, 0], [$classes['2.A']['total'], $classes['2.A']['absent'], $classes['2.A']['eating']]);
            $this->assertSame([0, 0, 0], [$classes['10.A']['total'], $classes['10.A']['absent'], $classes['10.A']['eating']]);
            $this->assertSame(['Kiss Anna', 'Nagy Péter'], $classes['1.A']['absent_children']->pluck('name')->all());
            $this->assertSame(['Csoportos Cili'], $classes['2.A']['absent_children']->pluck('name')->all());
            $this->assertSame(['Ebéd' => 2, 'Uzsonna' => 1], $mail->summary['meal_type_counts']->pluck('child_count', 'name')->all());
            $html = $mail->render();
            foreach (['1.A', '1.B', '2.A', '10.A', '2026.09.10.', 'Nincs hiányzó / lemondott gyermek.'] as $text) {
                $this->assertStringContainsString($text, $html);
            }
            $this->assertStringNotContainsString('Inaktív osztály', $html);
            foreach (['Kiss Anna', 'Nagy Péter', 'Csoportos Cili'] as $name) {
                $this->assertSame(1, substr_count($html, $name));
            }
            $this->assertTrue(strpos($html, 'Kiss Anna') < strpos($html, '>1.B</h3>'));
            $this->assertTrue(strpos($html, 'Csoportos Cili') > strpos($html, '>2.A</h3>'));

            return true;
        });
    }

    public function test_kindergarten_and_nursery_keep_existing_summary_and_template_content(): void
    {
        foreach (['ovoda', 'bolcsode'] as $type) {
            $institution = $this->createConfiguredInstitution('K'.$type, ['konyha@example.test'], true, 9, 30);
            $institution->update(['type' => $type]);
            [$lunch] = $this->createMealTypes($institution, includeSnack: false);
            $child = $this->createChild($institution->id, 'Étkező Emma');
            $cancelled = $this->createChild($institution->id, 'Hiányzó Hanna');
            foreach ([$child, $cancelled] as $eater) {
                $this->assignChildCustomMealSetting($institution->id, $eater->id, [$lunch->id]);
            }
            MealCancellation::create(['institution_id' => $institution->id, 'child_id' => $cancelled->id, 'service_date' => '2026-09-10', 'source' => MealCancellation::SOURCE_ADMIN, 'status' => MealCancellation::STATUS_ACTIVE]);
            $employee = $this->createEmployee($institution->id, 'Dolgozó Dénes');
            $this->assignEmployeeCustomMealSetting($institution->id, $employee->id, [$lunch->id]);
            $summary = app(KitchenDailySummaryService::class)->buildSummary($institution, '2026-09-10');
            $this->assertArrayNotHasKey('school_summary', $summary);
            $this->assertSame(2, $summary['stats']['daily_eaters']);
            $this->assertSame(1, $summary['stats']['child_cancelled_meals']);
            $this->assertSame(['Ebéd' => 2], $summary['meal_type_counts']->pluck('count', 'name')->all());
            $mail = new KitchenDailySummaryMail($summary);
            $this->assertSame('emails.kitchen-daily-summary', $mail->content()->view);
            $html = $mail->render();
            $this->assertStringContainsString('Gyermekek: <strong>1</strong>', $html);
            $this->assertStringContainsString('Dolgozók: <strong>1</strong>', $html);
            $this->assertStringNotContainsString('Hiányzó Hanna', $html);
            $this->assertStringNotContainsString('ÖSSZESÍTÉS – GYERMEKEK', $html);
            $this->assertStringNotContainsString('Napi konyhai létszám –', $html);
        }
    }

    private function createInstitutionAdmin(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Konyhai Intézmény '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);
        $user = $this->createAdminUser($institution);

        return [$institution, $user];
    }

    private function createConfiguredInstitution(
        string $code,
        array $emails,
        bool $enabled,
        int $hour,
        int $minute
    ): Institution {
        $institution = Institution::create([
            'name' => 'Konyhai Intézmény '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        InstitutionSetting::create([
            'institution_id' => $institution->id,
            'send_kitchen_email' => $enabled,
            'kitchen_notification_emails' => $emails,
            'payment_notification_enabled' => false,
            'payment_notification_day' => 5,
            'payment_due_day' => 5,
            'ab_menu_choice_deadline_day' => 20,
        ]);
        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => $hour,
            'cancellation_minute' => $minute,
        ]);

        return $institution;
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

    private function createChild(int $institutionId, string $name): Child
    {
        return Child::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId), 0, 10),
            'group_name' => '3.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function createEmployee(int $institutionId, string $name, bool $active = true): InstitutionEmployee
    {
        return InstitutionEmployee::create([
            'institution_id' => $institutionId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'source_type' => 'manual',
            'active' => $active,
        ]);
    }

    private function createMealTypes(Institution $institution, bool $includeSnack = true): array
    {
        $lunch = MealType::firstOrCreate(
            ['code' => 'lunch'],
            ['name' => 'Ebéd', 'default_order' => 1]
        );
        $snack = MealType::firstOrCreate(
            ['code' => 'snack'],
            ['name' => 'Uzsonna', 'default_order' => 2]
        );

        $lunchType = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $lunch->id,
            'is_active' => true,
            'is_parent_selectable' => true,
            'is_required' => false,
            'display_order' => 1,
        ]);

        if (! $includeSnack) {
            return [$lunchType];
        }

        $snackType = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $snack->id,
            'is_active' => true,
            'is_parent_selectable' => true,
            'is_required' => false,
            'display_order' => 2,
        ]);

        return [$lunchType, $snackType];
    }

    private function assignChildCustomMealSetting(int $institutionId, int $childId, array $mealTypeIds): void
    {
        $setting = StudentMealSetting::create([
            'student_id' => $childId,
            'institution_id' => $institutionId,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_CUSTOM,
            'valid_from' => '2026-09-01',
            'valid_to' => null,
        ]);

        foreach (array_values($mealTypeIds) as $index => $mealTypeId) {
            StudentMealSettingItem::create([
                'student_meal_setting_id' => $setting->id,
                'institution_meal_type_id' => $mealTypeId,
                'display_order' => $index + 1,
            ]);
        }
    }

    private function assignEmployeeCustomMealSetting(int $institutionId, int $employeeId, array $mealTypeIds): void
    {
        $setting = StudentMealSetting::create([
            'student_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employeeId,
            'institution_id' => $institutionId,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_CUSTOM,
            'valid_from' => '2026-09-01',
            'valid_to' => null,
        ]);

        foreach (array_values($mealTypeIds) as $index => $mealTypeId) {
            StudentMealSettingItem::create([
                'student_meal_setting_id' => $setting->id,
                'institution_meal_type_id' => $mealTypeId,
                'display_order' => $index + 1,
            ]);
        }
    }

    private function makeRequest(User $user, array $data): Request
    {
        $request = Request::create(route('dashboard.institution.settings.update'), 'PUT', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
