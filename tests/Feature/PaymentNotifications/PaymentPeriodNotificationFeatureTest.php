<?php

namespace Tests\Feature\PaymentNotifications;

use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionSettingController;
use App\Jobs\SendPaymentPeriodNotificationJob;
use App\Mail\PaymentPeriodNotificationMail;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\PaymentPeriodNotificationLog;
use App\Models\SchoolBreak;
use App\Models\User;
use App\Services\PaymentNotifications\PaymentPeriodNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentPeriodNotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_settings_page_saves_plain_text_payment_notification_template(): void
    {
        [$institution, $user] = $this->createInstitutionAdmin('PAYSET01');

        $this->actingAs($user);

        $response = app(InstitutionSettingController::class)->update(
            $this->makeRequest($user, [
                'send_kitchen_email' => '0',
                'kitchen_notification_emails' => '',
                'payment_notification_enabled' => '1',
                'payment_notification_day' => 10,
                'payment_notification_subject' => '<b>Fizetés</b>',
                'payment_notification_body' => "Első sor\r\n<script>alert(1)</script>\r\nMásodik sor",
                'payment_due_day' => 15,
                'ab_menu_choice_deadline_day' => 20,
            ]),
            app(\App\Services\PaymentNotifications\PaymentNotificationTemplateService::class)
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $setting = InstitutionSetting::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->assertTrue($setting->payment_notification_enabled);
        $this->assertSame('Fizetés', $setting->payment_notification_subject);
        $this->assertSame("Első sor\n<script>alert(1)</script>\nMásodik sor", $setting->payment_notification_body);
    }

    public function test_command_dispatches_only_on_configured_payment_day(): void
    {
        $institution = $this->createConfiguredInstitution('PAYCMD01', 5);
        [$parent, , $guardian] = $this->createParentContext($institution, 'szulo1@example.com');
        $child = $this->createChild($institution->id, 'Napi Nora');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createClosedStatement($institution, $child, 2026, 10, 4200);

        CarbonImmutable::setTestNow('2026-10-04 09:31:00 Europe/Budapest');
        Artisan::call('digifood:payment-period-notifications:dispatch');
        $this->assertSame(0, PaymentPeriodNotificationLog::query()->count());

        CarbonImmutable::setTestNow('2026-10-05 08:00:00 Europe/Budapest');
        Artisan::call('digifood:payment-period-notifications:dispatch');
        $this->assertSame(0, PaymentPeriodNotificationLog::query()->count());

        CarbonImmutable::setTestNow('2026-10-05 09:30:00 Europe/Budapest');
        Artisan::call('digifood:payment-period-notifications:dispatch');
        $this->assertSame(0, PaymentPeriodNotificationLog::query()->count());

        CarbonImmutable::setTestNow('2026-10-05 09:31:00 Europe/Budapest');
        Artisan::call('digifood:payment-period-notifications:dispatch');

        $this->assertDatabaseHas('payment_period_notification_logs', [
            'institution_id' => $institution->id,
            'user_id' => $parent->id,
            'year' => 2026,
            'month' => 10,
        ]);
    }

    public function test_command_filters_out_non_final_or_zero_balance_settlements(): void
    {
        CarbonImmutable::setTestNow('2026-10-05 09:31:00 Europe/Budapest');

        $draftInstitution = $this->createConfiguredInstitution('PAYCMD02', 5);
        [, , $draftGuardian] = $this->createParentContext($draftInstitution, 'draft@example.com');
        $draftChild = $this->createChild($draftInstitution->id, 'Draft Dani');
        $draftChild->guardians()->attach($draftGuardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createStatement($draftInstitution, $draftChild, 2026, 10, [
            'total_payable' => 3500,
            'invoiceable_amount' => 3500,
            'status' => MonthlyPaymentStatement::STATUS_DRAFT,
        ]);

        $issuesInstitution = $this->createConfiguredInstitution('PAYCMD03', 5);
        [, , $issuesGuardian] = $this->createParentContext($issuesInstitution, 'issues@example.com');
        $issuesChild = $this->createChild($issuesInstitution->id, 'Problemas Petra');
        $issuesChild->guardians()->attach($issuesGuardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $this->createStatement($issuesInstitution, $issuesChild, 2026, 10, [
            'total_payable' => 3600,
            'invoiceable_amount' => 3600,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
            'issues' => ['missing_price'],
        ]);

        $zeroInstitution = $this->createConfiguredInstitution('PAYCMD04', 5);
        [, , $zeroGuardian] = $this->createParentContext($zeroInstitution, 'zero@example.com');
        $zeroChild = $this->createChild($zeroInstitution->id, 'Nulla Noemi');
        $zeroChild->guardians()->attach($zeroGuardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $zeroStatement = $this->createClosedStatement($zeroInstitution, $zeroChild, 2026, 10, 2800);
        $recordedBy = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $zeroInstitution->id,
            'is_active' => true,
        ]);
        InstitutionPayment::create([
            'institution_id' => $zeroInstitution->id,
            'child_id' => $zeroChild->id,
            'guardian_id' => $zeroGuardian->id,
            'monthly_payment_statement_id' => $zeroStatement->id,
            'amount' => 2800,
            'paid_at' => now(),
            'payment_method' => InstitutionPayment::METHOD_CARD,
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'recorded_by' => $recordedBy->id,
        ]);

        Artisan::call('digifood:payment-period-notifications:dispatch');

        $this->assertSame(0, PaymentPeriodNotificationLog::query()->count());
    }

    public function test_command_creates_single_log_for_multi_child_parent_and_is_idempotent(): void
    {
        $institution = $this->createConfiguredInstitution('PAYCMD05', 5);
        [$parent, , $guardian] = $this->createParentContext($institution, 'family@example.com');

        $childA = $this->createChild($institution->id, 'Elso Emma');
        $childB = $this->createChild($institution->id, 'Masodik Mark');
        $childA->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $childB->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $this->createClosedStatement($institution, $childA, 2026, 10, 3000);
        $this->createClosedStatement($institution, $childB, 2026, 10, 2000);

        CarbonImmutable::setTestNow('2026-10-05 09:31:00 Europe/Budapest');

        Artisan::call('digifood:payment-period-notifications:dispatch');
        Artisan::call('digifood:payment-period-notifications:dispatch');

        $log = PaymentPeriodNotificationLog::query()->firstOrFail();
        $this->assertSame($institution->id, $log->institution_id);
        $this->assertSame($parent->id, $log->user_id);
        $this->assertSame(1, PaymentPeriodNotificationLog::query()->count());
    }

    public function test_scheduled_context_uses_central_cutoff_for_friday_and_school_break_cases(): void
    {
        $fridayInstitution = $this->createConfiguredInstitution('PAYCTX01', 4);
        $breakInstitution = $this->createConfiguredInstitution('PAYCTX02', 25);

        SchoolBreak::create([
            'institution_id' => $breakInstitution->id,
            'title' => 'Oszi szunet',
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-02',
            'type' => 'school_break',
        ]);

        $service = app(PaymentPeriodNotificationService::class);

        $fridayContext = $service->scheduledNotificationContext(
            $fridayInstitution,
            CarbonImmutable::parse('2026-09-04 09:31:00', 'Europe/Budapest')
        );
        $breakContext = $service->scheduledNotificationContext(
            $breakInstitution,
            CarbonImmutable::parse('2026-09-25 09:31:00', 'Europe/Budapest')
        );

        $this->assertTrue($fridayContext['is_due']);
        $this->assertSame('2026-09-07', $fridayContext['next_service_day']?->toDateString());
        $this->assertSame('2026-09-08', $fridayContext['earliest_cancellable_day']?->toDateString());

        $this->assertTrue($breakContext['is_due']);
        $this->assertSame('2026-10-05', $breakContext['next_service_day']?->toDateString());
        $this->assertSame('2026-10-06', $breakContext['earliest_cancellable_day']?->toDateString());
    }

    public function test_job_sends_notification_with_plain_text_body_and_automatic_financial_data(): void
    {
        $institution = $this->createConfiguredInstitution('PAYJOB01', 5, [
            'payment_notification_subject' => 'Egyedi fizetési értesítő',
            'payment_notification_body' => "Tisztelt Szülő!\n\n<script>alert(1)</script>\nKérjük, ellenőrizze az elszámolást.",
        ]);
        [$parent, , $guardian] = $this->createParentContext($institution, 'ertesites@example.com');

        $childA = $this->createChild($institution->id, 'Csenge Cinti');
        $childB = $this->createChild($institution->id, 'Bence Boti');
        $childA->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);
        $childB->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        $this->createClosedStatement($institution, $childA, 2026, 10, 4100);
        $this->createClosedStatement($institution, $childB, 2026, 10, 2900);

        $paymentPeriod = CarbonImmutable::create(2026, 10, 1, 0, 0, 0, config('app.timezone'));
        $recipient = app(PaymentPeriodNotificationService::class)
            ->notificationRecipientsForInstitution($institution, $paymentPeriod)
            ->firstOrFail();

        $log = PaymentPeriodNotificationLog::create([
            'institution_id' => $institution->id,
            'user_id' => $parent->id,
            'guardian_id' => $guardian->id,
            'year' => 2026,
            'month' => 10,
            'recipient_email' => $recipient['recipient_email'],
            'status' => PaymentPeriodNotificationLog::STATUS_QUEUED,
            'subject' => $recipient['subject'],
            'queued_at' => now(),
        ]);

        Mail::fake();

        app(SendPaymentPeriodNotificationJob::class, ['logId' => $log->id])
            ->handle(app(PaymentPeriodNotificationService::class));

        Mail::assertSent(PaymentPeriodNotificationMail::class, function (PaymentPeriodNotificationMail $mail) use ($log) {
            $rendered = $mail->render();

            return $mail->hasTo($log->recipient_email)
                && $mail->envelope()->subject === 'Egyedi fizetési értesítő'
                && str_contains($rendered, '2026. október')
                && str_contains($rendered, '2026. november')
                && str_contains($rendered, '2026. szeptember')
                && str_contains($rendered, '2026. október 15.')
                && str_contains($rendered, '7 000 Ft')
                && str_contains($rendered, 'Fizetendő összeg')
                && str_contains($rendered, 'Havi elszámolás megtekintése')
                && str_contains($rendered, 'Kérjük, ellenőrizze az elszámolást.')
                && str_contains($rendered, '&lt;script&gt;alert(1)&lt;/script&gt;')
                && ! str_contains($rendered, '<script>alert(1)</script>')
                && str_contains($rendered, route('parent.monthly-settlements.index', ['month' => '2026-10']));
        });

        $this->assertDatabaseHas('payment_period_notification_logs', [
            'id' => $log->id,
            'status' => PaymentPeriodNotificationLog::STATUS_SENT,
        ]);
    }

    public function test_job_is_idempotent_for_already_sent_log(): void
    {
        $institution = $this->createConfiguredInstitution('PAYJOB02', 5);
        [$parent, , $guardian] = $this->createParentContext($institution, 'sent@example.com');

        $log = PaymentPeriodNotificationLog::create([
            'institution_id' => $institution->id,
            'user_id' => $parent->id,
            'guardian_id' => $guardian->id,
            'year' => 2026,
            'month' => 10,
            'recipient_email' => 'sent@example.com',
            'status' => PaymentPeriodNotificationLog::STATUS_SENT,
            'subject' => 'Korábban elküldve',
            'queued_at' => now(),
            'sent_at' => now(),
        ]);

        Mail::fake();

        app(SendPaymentPeriodNotificationJob::class, ['logId' => $log->id])
            ->handle(app(PaymentPeriodNotificationService::class));

        Mail::assertNothingSent();
        $this->assertDatabaseHas('payment_period_notification_logs', [
            'id' => $log->id,
            'status' => PaymentPeriodNotificationLog::STATUS_SENT,
        ]);
    }

    private function createInstitutionAdmin(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Fizetési Intézmény '.$code,
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

        InstitutionSetting::create(array_merge(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        ));

        return [$institution, $user];
    }

    private function createConfiguredInstitution(string $code, int $notificationDay, array $overrides = []): Institution
    {
        $institution = Institution::create([
            'name' => 'Fizetési Intézmény '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        InstitutionSetting::create(array_merge([
            'institution_id' => $institution->id,
            'payment_notification_enabled' => true,
            'payment_notification_day' => $notificationDay,
            'payment_due_day' => 15,
            'ab_menu_choice_deadline_day' => 20,
            'card_payment_enabled' => true,
        ], $overrides));

        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 9,
            'cancellation_minute' => 30,
        ]);

        return $institution;
    }

    private function createParentContext(Institution $institution, string $email): array
    {
        $user = User::factory()->create([
            'name' => 'Teszt Szülő',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szülő',
            'email' => $email,
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
                'name' => 'Kedvezmény nélkül',
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
            'group_name' => '2.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function createClosedStatement(
        Institution $institution,
        Child $child,
        int $year,
        int $month,
        int $totalPayable
    ): MonthlyPaymentStatement {
        return $this->createStatement($institution, $child, $year, $month, [
            'total_payable' => $totalPayable,
            'invoiceable_amount' => $totalPayable,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    private function createStatement(
        Institution $institution,
        Child $child,
        int $year,
        int $month,
        array $overrides = []
    ): MonthlyPaymentStatement {
        return MonthlyPaymentStatement::create(array_merge([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => $year,
            'month' => $month,
            'meal_amount' => $overrides['invoiceable_amount'] ?? 0,
            'invoiceable_amount' => 0,
            'previous_balance' => 0,
            'previous_cancellation_credit' => 0,
            'billing_adjustment_amount' => 0,
            'total_payable' => 0,
            'status' => MonthlyPaymentStatement::STATUS_DRAFT,
            'issues' => [],
        ], $overrides));
    }

    private function makeRequest(User $user, array $data): Request
    {
        $request = Request::create(route('dashboard.institution.settings.update'), 'PUT', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
