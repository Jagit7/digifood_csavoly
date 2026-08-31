<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\EmployeeMealCancellation;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionMealPackage;
use App\Models\MealCancellation;
use App\Models\StudentMealSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstitutionCalendarCancelledListFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_day_shows_both_calendar_action_buttons(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CAL001');
        $this->createEatingChild($institution->id, 'Anna', '1.A');

        $response = $this->actingAs($user)->withSession($this->institutionSession($user, $institution))->get(route('dashboard.institution.school-breaks.calendar', [
            'view' => 'month',
            'date' => '2026-09-02',
        ]));

        $response->assertOk();
        $response->assertSee('Teljes napi lista', false);
        $response->assertSee('Lemondottak', false);
        $response->assertSee(route('dashboard.institution.school-breaks.calendar.day', '2026-09-02'), false);
    }

    public function test_non_service_day_shows_no_calendar_action_buttons(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CAL002');

        $response = $this->actingAs($user)->withSession($this->institutionSession($user, $institution))->get(route('dashboard.institution.school-breaks.calendar', [
            'view' => 'month',
            'date' => '2026-09-06',
        ]));

        $response->assertOk();
        $response->assertDontSee(route('dashboard.institution.school-breaks.calendar.cancelled', ['date' => '2026-09-06']), false);
        $response->assertDontSee(route('dashboard.institution.school-breaks.calendar.day', ['date' => '2026-09-06']), false);
    }

    public function test_cancelled_modal_markup_is_present_on_calendar_page(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CAL003');
        $this->createEatingChild($institution->id, 'Bela', '2.A');

        $response = $this->actingAs($user)->withSession($this->institutionSession($user, $institution))->get(route('dashboard.institution.school-breaks.calendar', [
            'view' => 'month',
            'date' => '2026-09-02',
        ]));

        $response->assertOk();
        $response->assertSee('cancelledMealsModal', false);
        $response->assertSee('show.bs.modal', false);
    }

    public function test_cancelled_endpoint_returns_only_current_institution_people_sorted_by_group_and_name(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CAL004');
        [$otherInstitution] = $this->seedInstitutionAdmin('CAL005');

        $childB = $this->createEatingChild($institution->id, 'Kovacs Bela', '1.B');
        $childA2 = $this->createEatingChild($institution->id, 'Kiss Anna', '1.A');
        $childA1 = $this->createEatingChild($institution->id, 'Adam Aron', '1.A');
        $employee = $this->createEatingEmployee($institution->id, 'Dolgozo Denes');
        $foreignChild = $this->createEatingChild($otherInstitution->id, 'Kulso Karoly', '1.A');

        $this->cancelChild($institution->id, $childB->id, '2026-09-02');
        $this->cancelChild($institution->id, $childA2->id, '2026-09-02');
        $this->cancelChild($institution->id, $childA1->id, '2026-09-02');
        $this->cancelEmployee($institution->id, $employee->id, '2026-09-02');
        $this->cancelChild($otherInstitution->id, $foreignChild->id, '2026-09-02');

        $payload = $this->cancelledPayload($user, $institution, '2026-09-02');

        $this->assertCount(4, $payload['items']);
        $this->assertSame(4, $payload['cancelled_count']);
        $this->assertSame('1.A', $payload['items'][0]['group_label']);
        $this->assertSame('Adam Aron', $payload['items'][0]['name']);
        $this->assertSame('1.A', $payload['items'][1]['group_label']);
        $this->assertSame('Kiss Anna', $payload['items'][1]['name']);
        $this->assertSame('1.B', $payload['items'][2]['group_label']);
        $this->assertSame('Kovacs Bela', $payload['items'][2]['name']);
        $this->assertSame('Dolgozó', $payload['items'][3]['group_label']);
        $this->assertNotContains('Kulso Karoly', array_column($payload['items'], 'name'));
    }

    public function test_cancelled_endpoint_returns_empty_state_payload_when_no_cancellations_exist(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CAL006');
        $this->createEatingChild($institution->id, 'Anna', '1.A');

        $payload = $this->cancelledPayload($user, $institution, '2026-09-02');

        $this->assertSame(0, $payload['cancelled_count']);
        $this->assertCount(0, $payload['items']);
    }

    public function test_calendar_cancelled_count_matches_modal_payload_count(): void
    {
        [$institution, $user] = $this->seedInstitutionAdmin('CAL007');
        $child = $this->createEatingChild($institution->id, 'Minta Anna', '3.A');
        $employee = $this->createEatingEmployee($institution->id, 'Minta Emil');

        $this->cancelChild($institution->id, $child->id, '2026-09-02');
        $this->cancelEmployee($institution->id, $employee->id, '2026-09-02');

        $calendarResponse = $this->actingAs($user)->withSession($this->institutionSession($user, $institution))->get(route('dashboard.institution.school-breaks.calendar', [
            'view' => 'month',
            'date' => '2026-09-02',
        ]));
        $payload = $this->cancelledPayload($user, $institution, '2026-09-02');

        $calendarResponse->assertOk();
        $calendarResponse->assertSee('data-cancelled-count="2"', false);
        $this->assertSame(2, $payload['cancelled_count']);
        $this->assertCount(2, $payload['items']);
    }

    private function seedInstitutionAdmin(string $code): array
    {
        $institution = Institution::query()->create([
            'name' => 'Naptar Intezmeny '.$code,
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

        InstitutionMealPackage::query()->create([
            'institution_id' => $institution->id,
            'name' => 'Alap csomag',
            'is_default' => true,
            'is_active' => true,
            'display_order' => 1,
            'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
        ]);

        return [$institution, $user];
    }

    private function createEatingChild(int $institutionId, string $name, string $groupName): Child
    {
        $child = Child::query()->create([
            'institution_id' => $institutionId,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId), 0, 10),
            'group_name' => $groupName,
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        StudentMealSetting::query()->create([
            'student_id' => $child->id,
            'institution_id' => $institutionId,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-08-01',
        ]);

        return $child;
    }

    private function createEatingEmployee(int $institutionId, string $name): InstitutionEmployee
    {
        $employee = InstitutionEmployee::query()->create([
            'institution_id' => $institutionId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'source_type' => 'manual',
            'active' => true,
        ]);

        StudentMealSetting::query()->create([
            'student_id' => null,
            'eater_type' => 'institution_employee',
            'eater_id' => $employee->id,
            'institution_id' => $institutionId,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-08-01',
        ]);

        return $employee;
    }

    private function cancelChild(int $institutionId, int $childId, string $date): void
    {
        MealCancellation::query()->create([
            'institution_id' => $institutionId,
            'child_id' => $childId,
            'service_date' => $date,
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
        ]);
    }

    private function cancelEmployee(int $institutionId, int $employeeId, string $date): void
    {
        EmployeeMealCancellation::query()->create([
            'institution_id' => $institutionId,
            'institution_employee_id' => $employeeId,
            'service_date' => $date,
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'status' => EmployeeMealCancellation::STATUS_ACTIVE,
        ]);
    }

    private function institutionSession(User $user, Institution $institution): array
    {
        return [
            'dashboard.selected_institution_id' => $institution->id,
            'dashboard.selected_institution_user_id' => $user->id,
        ];
    }

    private function cancelledPayload(User $user, Institution $institution, string $date): array
    {
        $response = $this->actingAs($user)
            ->withSession($this->institutionSession($user, $institution))
            ->getJson(route('dashboard.institution.school-breaks.calendar.cancelled', $date));

        $response->assertOk();

        return $response->json();
    }
}
