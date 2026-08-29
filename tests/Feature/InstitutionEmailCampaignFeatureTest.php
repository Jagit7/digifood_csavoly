<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignEmailJob;
use App\Http\Controllers\Dashboard\InstitutionAdmin\EmailCampaignController;
use App\Mail\InstitutionCampaignMail;
use App\Models\Child;
use App\Models\ClassGroup;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InstitutionEmailCampaignFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_admin_can_open_email_create_page(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('MAIL001');
        $this->createSchoolYear($institution->id);

        $this->actingAs($user);

        $response = $this->controller()->create($this->makeRequest(
            $user,
            '/dashboard/institution-admin/communication/emails/create',
            'GET'
        ));

        $this->assertInstanceOf(View::class, $response);
        view()->share('errors', new ViewErrorBag());
        $html = $response->render();
        $this->assertStringContainsString('Új e-mail küldése', $html);
    }

    public function test_other_institution_child_cannot_be_used_as_recipient(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('MAIL002');
        [$otherInstitution] = $this->seedInstitutionAdmin('MAIL002X');

        $this->createSchoolYear($institution->id);
        $this->createSchoolYear($otherInstitution->id);
        $foreignChild = $this->createChild($otherInstitution->id, 'Másik Gyermek');

        $this->actingAs($user);

        try {
            $this->controller()->store($this->makeRequest(
                $user,
                '/dashboard/institution-admin/communication/emails',
                'POST',
                [
                    'subject' => 'Teszt tárgy',
                    'body' => '<p>Teszt</p>',
                    'child_ids' => [$foreignChild->id],
                ]
            ));

            $this->fail('Validációs hibát vártunk idegen intézményi gyermeknél.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('child_ids', $exception->errors());
        }

        $this->assertDatabaseCount('email_campaigns', 0);
    }

    public function test_class_group_selection_collects_guardians(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('MAIL003');
        $schoolYear = $this->createSchoolYear($institution->id);
        $classGroup = $this->createClassGroup($institution->id, $schoolYear->id, '4.A');
        $child = $this->createChild($institution->id, 'Kiss Anna');
        $guardian = $this->createGuardian($institution->id, 'Szülő', 'Anna', 'anna.parent@example.test');

        $classGroup->children()->attach($child->id, [
            'status' => 'active',
            'joined_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $child->guardians()->attach($guardian->id, [
            'relationship_type' => 'parent',
            'is_legal_representative' => true,
            'has_no_custody' => false,
            'is_emergency_contact' => true,
            'receives_family_allowance' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Queue::fake();

        $this->actingAs($user);

        $response = $this->controller()->store($this->makeRequest(
            $user,
            '/dashboard/institution-admin/communication/emails',
            'POST',
            [
                'subject' => 'Osztály értesítő',
                'body' => '<p>Üzenet</p>',
                'class_group_ids' => [$classGroup->id],
            ]
        ));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertDatabaseHas('email_campaign_recipients', [
            'email' => 'anna.parent@example.test',
            'recipient_name' => 'Szülő Anna',
        ]);
    }

    public function test_same_email_is_created_only_once_per_campaign(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('MAIL004');
        $this->createSchoolYear($institution->id);
        $guardian = $this->createGuardian($institution->id, 'Közös', 'Szülő', 'same@example.test');
        $firstChild = $this->createChild($institution->id, 'Első Gyermek');
        $secondChild = $this->createChild($institution->id, 'Második Gyermek');

        foreach ([$firstChild, $secondChild] as $child) {
            $child->guardians()->attach($guardian->id, [
                'relationship_type' => 'parent',
                'is_legal_representative' => true,
                'has_no_custody' => false,
                'is_emergency_contact' => true,
                'receives_family_allowance' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Queue::fake();

        $this->actingAs($user);

        $this->controller()->store($this->makeRequest(
            $user,
            '/dashboard/institution-admin/communication/emails',
            'POST',
            [
                'subject' => 'Közös cím',
                'body' => '<p>Üzenet</p>',
                'child_ids' => [$firstChild->id, $secondChild->id],
            ]
        ));

        $campaign = EmailCampaign::query()->firstOrFail();
        $this->assertSame(1, $campaign->recipient_count);
        $recipient = EmailCampaignRecipient::query()->firstOrFail();
        $this->assertSame(['Első Gyermek', 'Második Gyermek'], $recipient->child_names);
    }

    public function test_campaign_is_created_and_jobs_are_queued(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('MAIL005');
        $this->createSchoolYear($institution->id);
        $child = $this->createChild($institution->id, 'Teszt Gyermek');
        $guardian = $this->createGuardian($institution->id, 'Teszt', 'Gondviselő', 'queue@example.test');
        $child->guardians()->attach($guardian->id, [
            'relationship_type' => 'parent',
            'is_legal_representative' => true,
            'has_no_custody' => false,
            'is_emergency_contact' => true,
            'receives_family_allowance' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Queue::fake();

        $this->actingAs($user);

        $response = $this->controller()->store($this->makeRequest(
            $user,
            '/dashboard/institution-admin/communication/emails',
            'POST',
            [
                'subject' => 'Queue teszt',
                'body' => '<p>Üzenet</p>',
                'child_ids' => [$child->id],
            ]
        ));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertDatabaseHas('email_campaigns', [
            'institution_id' => $institution->id,
            'subject' => 'Queue teszt',
            'status' => EmailCampaign::STATUS_QUEUED,
        ]);
        Queue::assertPushed(SendCampaignEmailJob::class, 1);
    }

    public function test_other_institution_campaign_cannot_be_opened(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('MAIL006');
        [, $otherUser] = $this->seedInstitutionAdmin('MAIL006X');

        $campaign = EmailCampaign::query()->create([
            'institution_id' => $institution->id,
            'created_by' => $user->id,
            'subject' => 'Zárt kampány',
            'body' => '<p>Test</p>',
            'status' => EmailCampaign::STATUS_QUEUED,
            'recipient_count' => 1,
            'queued_at' => now(),
        ]);

        $this->actingAs($otherUser);

        try {
            $this->controller()->show($campaign);
            $this->fail('403-as hibát vártunk másik intézmény kampányánál.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_empty_usable_recipient_list_returns_validation_error(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('MAIL007');
        $this->createSchoolYear($institution->id);
        $child = $this->createChild($institution->id, 'Nincs Email Gyermek');
        $guardian = $this->createGuardian($institution->id, 'Email', 'Nélküli', null);

        $child->guardians()->attach($guardian->id, [
            'relationship_type' => 'parent',
            'is_legal_representative' => true,
            'has_no_custody' => false,
            'is_emergency_contact' => true,
            'receives_family_allowance' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        try {
            $this->controller()->store($this->makeRequest(
                $user,
                '/dashboard/institution-admin/communication/emails',
                'POST',
                [
                    'subject' => 'Nincs címzett',
                    'body' => '<p>Üzenet</p>',
                    'child_ids' => [$child->id],
                ]
            ));

            $this->fail('Validációs hibát vártunk üres használható címzettlistánál.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recipients', $exception->errors());
        }

        $this->assertDatabaseCount('email_campaigns', 0);
    }

    public function test_guardian_without_email_is_not_created_as_recipient(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('MAIL008');
        $this->createSchoolYear($institution->id);
        $child = $this->createChild($institution->id, 'Vegyes Gyermek');
        $withoutEmail = $this->createGuardian($institution->id, 'Nincs', 'Email', null);
        $withEmail = $this->createGuardian($institution->id, 'Van', 'Email', 'valid@example.test');

        foreach ([$withoutEmail, $withEmail] as $guardian) {
            $child->guardians()->attach($guardian->id, [
                'relationship_type' => 'parent',
                'is_legal_representative' => true,
                'has_no_custody' => false,
                'is_emergency_contact' => true,
                'receives_family_allowance' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Queue::fake();

        $this->actingAs($user);

        $this->controller()->store($this->makeRequest(
            $user,
            '/dashboard/institution-admin/communication/emails',
            'POST',
            [
                'subject' => 'Email szűrés',
                'body' => '<p>Üzenet</p>',
                'child_ids' => [$child->id],
            ]
        ));

        $this->assertDatabaseHas('email_campaign_recipients', ['email' => 'valid@example.test']);
        $this->assertDatabaseMissing('email_campaign_recipients', ['recipient_name' => 'Nincs Email']);
    }

    public function test_queue_job_marks_recipient_sent_on_success(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('MAIL009');
        $campaign = EmailCampaign::query()->create([
            'institution_id' => $institution->id,
            'created_by' => $user->id,
            'subject' => 'Teszt #GYERMEK_NEVE#',
            'body' => '<p>Helló #SZULO_NEVE#</p>',
            'status' => EmailCampaign::STATUS_QUEUED,
            'recipient_count' => 1,
            'queued_at' => now(),
        ]);

        $recipient = $campaign->recipients()->create([
            'email' => 'delivery@example.test',
            'recipient_name' => 'Minta Szülő',
            'child_names' => ['Minta Gyermek'],
            'class_group_names' => ['3.B'],
            'status' => EmailCampaignRecipient::STATUS_QUEUED,
        ]);

        Mail::fake();

        $job = new SendCampaignEmailJob($recipient->id);
        $job->handle();

        Mail::assertSent(InstitutionCampaignMail::class);
        $this->assertDatabaseHas('email_campaign_recipients', [
            'id' => $recipient->id,
            'status' => EmailCampaignRecipient::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('email_campaigns', [
            'id' => $campaign->id,
            'status' => EmailCampaign::STATUS_COMPLETED,
            'sent_count' => 1,
            'failed_count' => 0,
        ]);
    }

    public function test_parent_activation_invite_mail_uses_current_route_for_placeholder(): void
    {
        config(['app.url' => 'https://etkezes.csillagvilagovoda.hu']);
        URL::forceRootUrl('https://etkezes.csillagvilagovoda.hu');
        URL::forceScheme('https');

        [$institution, $user] = $this->seedInstitutionAdmin('MAIL010');
        $campaign = EmailCampaign::query()->create([
            'institution_id' => $institution->id,
            'created_by' => $user->id,
            'type' => EmailCampaign::TYPE_PARENT_ACTIVATION_INVITE,
            'subject' => 'Aktiváció',
            'body' => '<p><a href="#SZULOI_FIOK_AKTIVALASA_URL#">Link</a> #SZULOI_FIOK_AKTIVALASA_URL#</p>',
            'status' => EmailCampaign::STATUS_QUEUED,
            'recipient_count' => 1,
            'queued_at' => now(),
        ]);

        $recipient = $campaign->recipients()->create([
            'email' => 'parent@example.test',
            'recipient_name' => 'Minta Szülő',
            'child_names' => ['Minta Gyermek'],
            'class_group_names' => ['3.B'],
            'status' => EmailCampaignRecipient::STATUS_QUEUED,
        ]);

        $html = (new InstitutionCampaignMail($recipient->fresh('campaign.institution')))->render();

        $this->assertStringContainsString('https://etkezes.csillagvilagovoda.hu/szulo/aktivalas', $html);
        $this->assertStringNotContainsString('#SZULOI_FIOK_AKTIVALASA_URL#', $html);
    }

    public function test_parent_activation_invite_mail_rewrites_legacy_absolute_activation_url_to_current_route(): void
    {
        config(['app.url' => 'https://etkezes.csillagvilagovoda.hu']);
        URL::forceRootUrl('https://etkezes.csillagvilagovoda.hu');
        URL::forceScheme('https');

        [$institution, $user] = $this->seedInstitutionAdmin('MAIL011');
        $campaign = EmailCampaign::query()->create([
            'institution_id' => $institution->id,
            'created_by' => $user->id,
            'type' => EmailCampaign::TYPE_PARENT_ACTIVATION_INVITE,
            'subject' => 'Aktiváció',
            'body' => '<p><a href="http://localhost/digifood_csillag/public/szulo/aktivalas">Régi link</a></p>',
            'status' => EmailCampaign::STATUS_QUEUED,
            'recipient_count' => 1,
            'queued_at' => now(),
        ]);

        $recipient = $campaign->recipients()->create([
            'email' => 'parent@example.test',
            'recipient_name' => 'Minta Szülő',
            'child_names' => ['Minta Gyermek'],
            'class_group_names' => ['3.B'],
            'status' => EmailCampaignRecipient::STATUS_QUEUED,
        ]);

        $html = (new InstitutionCampaignMail($recipient->fresh('campaign.institution')))->render();

        $this->assertStringContainsString('https://etkezes.csillagvilagovoda.hu/szulo/aktivalas', $html);
        $this->assertStringNotContainsString('http://localhost/digifood_csillag/public/szulo/aktivalas', $html);
    }

    public function test_employee_activation_invite_mail_uses_current_route_for_placeholder(): void
    {
        config(['app.url' => 'https://etkezes.csillagvilagovoda.hu']);
        URL::forceRootUrl('https://etkezes.csillagvilagovoda.hu');
        URL::forceScheme('https');

        [$institution, $user] = $this->seedInstitutionAdmin('MAIL012');
        $campaign = EmailCampaign::query()->create([
            'institution_id' => $institution->id,
            'created_by' => $user->id,
            'type' => EmailCampaign::TYPE_EMPLOYEE_ACTIVATION_INVITE,
            'subject' => 'Dolgozói aktiváció',
            'body' => '<p><a href="#DOLGOZOI_FIOK_AKTIVALASA_URL#">Link</a></p>',
            'status' => EmailCampaign::STATUS_QUEUED,
            'recipient_count' => 1,
            'queued_at' => now(),
        ]);

        $recipient = $campaign->recipients()->create([
            'email' => 'employee@example.test',
            'recipient_name' => 'Minta Dolgozó',
            'child_names' => [],
            'class_group_names' => [],
            'status' => EmailCampaignRecipient::STATUS_QUEUED,
        ]);

        $html = (new InstitutionCampaignMail($recipient->fresh('campaign.institution')))->render();

        $this->assertStringContainsString('https://etkezes.csillagvilagovoda.hu/dolgozo/aktivalas', $html);
        $this->assertStringNotContainsString('#DOLGOZOI_FIOK_AKTIVALASA_URL#', $html);
    }

    private function seedInstitutionAdmin(string $code): array
    {
        $institution = Institution::query()->create([
            'name' => 'Email Intézmény '.$code,
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

    private function createSchoolYear(int $institutionId): SchoolYear
    {
        return SchoolYear::query()->create([
            'institution_id' => $institutionId,
            'name' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-15',
            'is_active' => true,
        ]);
    }

    private function createClassGroup(int $institutionId, int $schoolYearId, string $name): ClassGroup
    {
        return ClassGroup::query()->create([
            'institution_id' => $institutionId,
            'school_year_id' => $schoolYearId,
            'name' => $name,
            'group_type' => 'school_class',
            'active' => true,
        ]);
    }

    private function createChild(int $institutionId, string $name): Child
    {
        return Child::query()->create([
            'institution_id' => $institutionId,
            'name' => $name,
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function createGuardian(int $institutionId, string $lastName, string $firstName, ?string $email): Guardian
    {
        return Guardian::query()->create([
            'institution_id' => $institutionId,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'email' => $email,
            'source_type' => 'manual',
            'active' => true,
        ]);
    }

    private function controller(): EmailCampaignController
    {
        return app(EmailCampaignController::class);
    }

    private function makeRequest(User $user, string $uri, string $method, array $data = []): Request
    {
        $request = Request::create($uri, $method, $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
