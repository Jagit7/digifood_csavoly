<?php

namespace Tests\Unit;

use App\Models\Institution;
use App\Models\InstitutionMealSetting;
use App\Models\SchoolBreak;
use App\Models\WorkingDay;
use App\Services\HungarianHolidayService;
use App\Services\InstitutionCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionCalendarCancellationDeadlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_monday_meal_uses_previous_friday_deadline(): void
    {
        $institution = $this->createInstitution();
        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 8,
            'cancellation_minute' => 30,
        ]);

        $service = app(InstitutionCalendarService::class);
        $deadline = $service->cancellationDeadline($institution->id, '2026-09-07');

        $this->assertSame('2026-09-04 08:30', $deadline?->format('Y-m-d H:i'));
    }

    public function test_first_day_after_break_uses_last_service_day_before_break(): void
    {
        $institution = $this->createInstitution();
        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 8,
            'cancellation_minute' => 30,
        ]);

        SchoolBreak::create([
            'institution_id' => $institution->id,
            'title' => 'Őszi szünet',
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-02',
            'type' => 'school_break',
        ]);

        $service = app(InstitutionCalendarService::class);
        $deadline = $service->cancellationDeadline($institution->id, '2026-10-05');

        $this->assertSame('2026-09-25 08:30', $deadline?->format('Y-m-d H:i'));
    }

    public function test_working_saturday_is_treated_as_service_day(): void
    {
        $institution = $this->createInstitution();
        InstitutionMealSetting::create([
            'institution_id' => $institution->id,
            'cancellation_hour' => 8,
            'cancellation_minute' => 30,
        ]);

        WorkingDay::create([
            'institution_id' => $institution->id,
            'date' => '2026-10-17',
            'name' => 'Ledolgozós szombat',
            'type' => 'school_saturday',
        ]);

        $service = app(InstitutionCalendarService::class);

        $this->assertTrue($service->isServiceDay($institution->id, '2026-10-17'));
        $this->assertSame('2026-10-16 08:30', $service->cancellationDeadline($institution->id, '2026-10-17')?->format('Y-m-d H:i'));
    }

    private function createInstitution(): Institution
    {
        return Institution::create([
            'name' => 'Teszt intézmény',
            'institution_code' => substr(md5((string) microtime(true)), 0, 6),
            'type' => 'iskola',
            'active' => true,
        ]);
    }
}
