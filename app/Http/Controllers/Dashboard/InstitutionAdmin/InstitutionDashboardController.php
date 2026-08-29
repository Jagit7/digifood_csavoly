<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\DataImport;
use App\Models\Menu;
use App\Services\InstitutionCalendarService;
use App\Services\InstitutionMealCalendarService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class InstitutionDashboardController extends Controller
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly InstitutionMealCalendarService $mealCalendar
    ) {}

    public function __invoke(): View
    {
        $institution = $this->currentAdminInstitution();
        $today = $this->calendar->now()->startOfDay();
        $todayData = $this->mealCalendar->day($institution->id, $today);
        $nextServiceDay = $this->calendar
            ->serviceDaysBetween($institution->id, $today->addDay(), $today->addDays(400))
            ->first();
        $nextDaySummary = $nextServiceDay
            ? $this->mealCalendar->period($institution->id, $nextServiceDay, $nextServiceDay)->first()
            : null;
        $nextDayData = $nextDaySummary ? ['summary' => $nextDaySummary] : null;

        $weekDays = $this->mealCalendar->period(
            $institution->id,
            $today->startOfWeek(),
            $today->endOfWeek()
        );
        $monthDays = $this->mealCalendar->period(
            $institution->id,
            $today->startOfMonth(),
            $today->endOfMonth()
        );
        $upcomingDays = $this->mealCalendar->period(
            $institution->id,
            $today,
            $today->addDays(30)
        );

        $window = $this->calendar->cancellationWindow($institution->id);
        $nextWeekStart = $today->addWeek()->startOfWeek();
        $nextWeekMenu = Menu::query()
            ->where('institution_id', $institution->id)
            ->where('type', 'weekly')
            ->where('active', true)
            ->whereDate('week_start', '<=', $nextWeekStart->toDateString())
            ->whereDate('week_end', '>=', $nextWeekStart->toDateString())
            ->latest('published_at')
            ->first();
        $recentImportErrors = DataImport::query()
            ->where('institution_id', $institution->id)
            ->where('error_count', '>', 0)
            ->where('created_at', '>=', $today->subDays(7)->utc())
            ->sum('error_count');

        $attentionItems = collect();

        if (! $window['configured']) {
            $attentionItems->push([
                'level' => 'danger',
                'icon' => 'fas fa-exclamation-triangle',
                'text' => 'Nincs beállítva a lemondási határidő.',
                'route' => 'dashboard.institution.reference-data.index',
                'action' => 'Beállítás',
                'roles' => ['institution_admin'],
            ]);
        }

        if (! $nextWeekMenu) {
            $attentionItems->push([
                'level' => 'warning',
                'icon' => 'fas fa-utensils',
                'text' => 'A jövő heti étlap még nincs publikálva.',
                'route' => 'dashboard.institution.menus.create',
                'action' => 'Feltöltés',
                'roles' => ['institution_admin', 'kitchen'],
            ]);
        }

        if ($recentImportErrors > 0) {
            $attentionItems->push([
                'level' => 'warning',
                'icon' => 'fas fa-exclamation-circle',
                'text' => "{$recentImportErrors} importálási hiba keletkezett az elmúlt 7 napban.",
                'route' => 'dashboard.institution.imports.index',
                'action' => 'Ellenőrzés',
                'roles' => ['institution_admin'],
            ]);
        }

        $role = auth()->user()->contextRole() ?? auth()->user()->role;
        $attentionItems = $attentionItems
            ->filter(fn (array $item) => ! isset($item['roles']) || in_array($role, $item['roles'], true))
            ->values();
        $groupCounts = $todayData['roster']
            ->where('is_eating', true)
            ->groupBy(fn (array $row) => $row['child']->group_name ?: 'Nincs csoport')
            ->map->count()
            ->sortDesc()
            ->take(8);
        $specialDays = $upcomingDays
            ->filter(fn (array $day) => $day['holiday'] || $day['break'] || $day['working_day'])
            ->take(5)
            ->values();

        $weekChart = $this->dailyChart($weekDays);
        $monthChart = $this->weeklyChart($monthDays);
        $todayComposition = [
            $todayData['summary']['standard'],
            $todayData['summary']['allergen'],
            $todayData['summary']['other_diet'],
        ];
        $nextComposition = $nextDayData ? [
            $nextDayData['summary']['standard'],
            $nextDayData['summary']['allergen'],
            $nextDayData['summary']['other_diet'],
        ] : [0, 0, 0];

        return view('dashboard.institution_admin.overview', [
            'institution' => $institution,
            'today' => $today,
            'todayData' => $todayData,
            'nextServiceDay' => $nextServiceDay,
            'nextDayData' => $nextDayData,
            'window' => $window,
            'nextWeekMenu' => $nextWeekMenu,
            'attentionItems' => $attentionItems,
            'specialDays' => $specialDays,
            'groupCounts' => $groupCounts,
            'chartPayload' => ['week' => $weekChart, 'month' => $monthChart],
            'todayComposition' => $todayComposition,
            'nextComposition' => $nextComposition,
            'groupLabels' => $groupCounts->keys()->values(),
            'groupValues' => $groupCounts->values(),
        ]);
    }

    private function dailyChart(Collection $days): array
    {
        $weekdayNames = [1 => 'H', 2 => 'K', 3 => 'Sze', 4 => 'Cs', 5 => 'P', 6 => 'Szo', 7 => 'V'];

        return [
            'categories' => $days->map(fn (array $day) => $weekdayNames[$day['date']->dayOfWeekIso])->values(),
            'standard' => $days->pluck('standard')->values(),
            'allergen' => $days->pluck('allergen')->values(),
            'other_diet' => $days->pluck('other_diet')->values(),
            'cancelled' => $days->pluck('cancelled')->values(),
        ];
    }

    private function weeklyChart(Collection $days): array
    {
        $weeks = $days->groupBy(fn (array $day) => $day['date']->weekOfYear);

        return [
            'categories' => $weeks->map(function (Collection $week) {
                $first = $week->first()['date'];
                $last = $week->last()['date'];

                return $first->format('m.d.').'–'.$last->format('m.d.');
            })->values(),
            'standard' => $weeks->map->sum('standard')->values(),
            'allergen' => $weeks->map->sum('allergen')->values(),
            'other_diet' => $weeks->map->sum('other_diet')->values(),
            'cancelled' => $weeks->map->sum('cancelled')->values(),
        ];
    }
}
