<?php

namespace Tests\Feature;

use Tests\TestCase;

class InstitutionCalendarRoutesTest extends TestCase
{
    public function test_calendar_overview_and_daily_routes_are_registered(): void
    {
        $this->assertStringEndsWith(
            '/dashboard/institution-admin',
            route('dashboard.institution.home')
        );
        $this->assertStringEndsWith(
            '/dashboard/institution-admin/daily/today-counts?date=2026-07-16',
            route('dashboard.institution.daily.today-counts', [
                'date' => '2026-07-16',
            ])
        );
        $this->assertStringEndsWith(
            '/dashboard/institution-admin/daily/today-counts/attendance-sheet?date=2026-07-16',
            route('dashboard.institution.daily.today-counts.attendance-sheet', [
                'date' => '2026-07-16',
            ])
        );
        $this->assertStringEndsWith(
            '/dashboard/institution-admin/daily/dietary-children?date=2026-07-16',
            route('dashboard.institution.daily.dietary-children', [
                'date' => '2026-07-16',
            ])
        );
        $this->assertStringEndsWith(
            '/dashboard/institution-admin/daily/dietary-children/print?date=2026-07-16',
            route('dashboard.institution.daily.dietary-children.print', [
                'date' => '2026-07-16',
            ])
        );
        $this->assertStringEndsWith(
            '/dashboard/institution-admin/daily/dietary-children/export?date=2026-07-16',
            route('dashboard.institution.daily.dietary-children.export', [
                'date' => '2026-07-16',
            ])
        );
        $this->assertStringEndsWith(
            '/dashboard/institution-admin/school-breaks/calendar?view=week&date=2026-07-13',
            route('dashboard.institution.school-breaks.calendar', [
                'view' => 'week',
                'date' => '2026-07-13',
            ])
        );
        $this->assertStringEndsWith(
            '/dashboard/institution-admin/school-breaks/calendar/2026-07-13',
            route('dashboard.institution.school-breaks.calendar.day', '2026-07-13')
        );
        $this->assertStringEndsWith(
            '/dashboard/institution-admin/school-breaks/calendar/2026-07-13/cancelled',
            route('dashboard.institution.school-breaks.calendar.cancelled', '2026-07-13')
        );
    }
}
