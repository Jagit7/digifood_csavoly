<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\AbMenuPlan;
use App\Models\SchoolBreak;
use App\Services\InstitutionCalendarService;
use App\Services\InstitutionMealCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SchoolBreakController extends Controller
{
    public function __construct(
        private readonly InstitutionCalendarService $calendarService,
        private readonly InstitutionMealCalendarService $mealCalendar
    ) {}

    public function index(): View
    {
        $institution = $this->currentAdminInstitution();

        $today = now()->toDateString();

        $schoolBreaks = SchoolBreak::query()
            ->where('institution_id', $institution->id)
            ->orderBy('start_date')
            ->paginate(15);

        $stats = [
            'total' => SchoolBreak::where('institution_id', $institution->id)->count(),

            'active' => SchoolBreak::where('institution_id', $institution->id)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->count(),

            'upcoming' => SchoolBreak::where('institution_id', $institution->id)
                ->whereDate('start_date', '>', $today)
                ->count(),

            'past' => SchoolBreak::where('institution_id', $institution->id)
                ->whereDate('end_date', '<', $today)
                ->count(),
        ];

        return view('dashboard.institution_admin.school_breaks.index', compact(
            'institution',
            'schoolBreaks',
            'stats'
        ));
    }

    public function create(): View
    {
        $types = [
            'summer' => 'Nyári szünet',
            'autumn' => 'Őszi szünet',
            'winter' => 'Téli szünet',
            'spring' => 'Tavaszi szünet',
            'holiday' => 'Ünnepnap',
            'institutional' => 'Intézményi szünet',
            'maintenance' => 'Karbantartás / zárás',
            'other' => 'Egyéb',
        ];

        return view('dashboard.institution_admin.school_breaks.create', compact('types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        $data['institution_id'] = $institution->id;
        $data['type'] = $data['type'] ?? 'school_break';

        SchoolBreak::create($data);

        return redirect()
            ->route('dashboard.institution.school-breaks.index')
            ->with('success', 'Iskolai szünet sikeresen létrehozva.');
    }

    public function edit(SchoolBreak $schoolBreak): View
    {
        $institution = $this->currentAdminInstitution();

        abort_if($schoolBreak->institution_id !== $institution->id, 403);

        $types = [
            'summer' => 'Nyári szünet',
            'autumn' => 'Őszi szünet',
            'winter' => 'Téli szünet',
            'spring' => 'Tavaszi szünet',
            'holiday' => 'Ünnepnap',
            'institutional' => 'Intézményi szünet',
            'maintenance' => 'Karbantartás / zárás',
            'other' => 'Egyéb',
        ];

        return view('dashboard.institution_admin.school_breaks.edit', compact(
            'schoolBreak',
            'types'
        ));
    }

    public function update(Request $request, SchoolBreak $schoolBreak): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        abort_if($schoolBreak->institution_id !== $institution->id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        $data['type'] = $data['type'] ?? 'school_break';

        $schoolBreak->update($data);

        return redirect()
            ->route('dashboard.institution.school-breaks.index')
            ->with('success', 'Iskolai szünet sikeresen módosítva.');
    }

    public function destroy(SchoolBreak $schoolBreak): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        abort_if($schoolBreak->institution_id !== $institution->id, 403);

        $schoolBreak->delete();

        return redirect()
            ->route('dashboard.institution.school-breaks.index')
            ->with('success', 'Iskolai szünet törölve.');
    }

    public function calendar(Request $request): View
    {
        $institution = $this->currentAdminInstitution();

        $validated = $request->validate([
            'view' => ['nullable', 'in:week,month'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $viewMode = $validated['view'] ?? 'month';
        $selectedDate = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], $this->calendarService->timezone())->startOfDay()
            : $this->calendarService->now()->startOfDay();

        if ($viewMode === 'week') {
            $rangeStart = $selectedDate->startOfWeek();
            $rangeEnd = $selectedDate->endOfWeek();
            $previousDate = $selectedDate->subWeek();
            $nextDate = $selectedDate->addWeek();
            $periodLabel = $rangeStart->format('Y. m. d.').' – '.$rangeEnd->format('Y. m. d.');
        } else {
            $rangeStart = $selectedDate->startOfMonth()->startOfWeek();
            $rangeEnd = $selectedDate->endOfMonth()->endOfWeek();
            $previousDate = $selectedDate->subMonthNoOverflow();
            $nextDate = $selectedDate->addMonthNoOverflow();
            $periodLabel = $selectedDate->year.'. '.$this->monthName($selectedDate->month);
        }

        $days = $this->mealCalendar->period($institution->id, $rangeStart, $rangeEnd)
            ->map(function (array $day) use ($selectedDate, $viewMode) {
                $day['is_current_month'] = $viewMode === 'week' || $day['date']->month === $selectedDate->month;

                return $day;
            });
        $summaryDays = $viewMode === 'month'
    ? $days->filter(fn (array $day) => $day['is_current_month'])
    : $days;

        $stats = [
            'service_days' => $summaryDays->where('is_service_day', true)->count(),
            'total' => $summaryDays->sum('total'),
            'standard' => $summaryDays->sum('standard'),
            'allergen' => $summaryDays->sum('allergen'),
            'other_diet' => $summaryDays->sum('other_diet'),
            'menu_a_count' => $summaryDays->sum('menu_a_count'),
            'menu_b_count' => $summaryDays->sum('menu_b_count'),
            'dietary_count' => $summaryDays->sum('dietary_count'),
            'cancelled_count' => $summaryDays->sum('cancelled'),
        ];

        $stats['daily_average'] = $stats['service_days'] > 0
            ? round($stats['total'] / $stats['service_days'])
            : 0;

        $peakDay = $summaryDays
            ->where('is_service_day', true)
            ->sortByDesc('total')
            ->first();

        $stats['peak_count'] = $peakDay['total'] ?? 0;

        $stats['peak_date_label'] = isset($peakDay['date'])
            ? $peakDay['date']->locale('hu')->translatedFormat('Y. m. d. l')
            : null;

        // Az A/B menü kártyák (és a heti/havi rácsban a napi A/B sorok) csak
        // akkor jelenjenek meg, ha az intézménynél ténylegesen van aktív A-B
        // menüterv - egyébként a "0 A, 0 B" kártyák félrevezetőek lennének
        // azoknál az intézményeknél, akik nem is használják ezt a funkciót.
        $hasActiveAbMenuPlan = AbMenuPlan::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->exists();

        return view('dashboard.institution_admin.school_breaks.calendar', compact(
            'institution',
            'days',
            'stats',
            'viewMode',
            'selectedDate',
            'previousDate',
            'nextDate',
            'periodLabel',
            'hasActiveAbMenuPlan'
        ));
    }

    public function calendarDay(Request $request, string $date): View
    {
        Validator::make(['date' => $date], [
            'date' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        $institution = $this->currentAdminInstitution();
        $selectedDate = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $date,
            $this->calendarService->timezone()
        )->startOfDay();
        $data = $this->mealCalendar->day($institution->id, $selectedDate);

        // Ugyanaz a szabály, mint a heti/havi naptár nézetnél (calendar()):
        // az A/B menü kártyák csak akkor jelenjenek meg, ha az
        // intézménynél ténylegesen van aktív A-B menüterv.
        $hasActiveAbMenuPlan = AbMenuPlan::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->exists();

        $perPage = 25;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $rosterCollection = $data['roster']->values();
        $roster = new LengthAwarePaginator(
            $rosterCollection->forPage($currentPage, $perPage)->values(),
            $rosterCollection->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('dashboard.institution_admin.school_breaks.calendar-day', [
            'institution' => $institution,
            'selectedDate' => $selectedDate,
            'summary' => $data['summary'],
            'roster' => $roster,
            'hasActiveAbMenuPlan' => $hasActiveAbMenuPlan,
        ]);
    }

    public function calendarCancelled(string $date): JsonResponse
    {
        Validator::make(['date' => $date], [
            'date' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        $institution = $this->currentAdminInstitution();
        $selectedDate = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $date,
            $this->calendarService->timezone()
        )->startOfDay();
        $data = $this->mealCalendar->day($institution->id, $selectedDate);
        $cancelledRoster = $data['cancelled_roster'];

        return response()->json([
            'date' => $selectedDate->toDateString(),
            'date_label' => $selectedDate->format('Y.m.d.'),
            'cancelled_count' => $cancelledRoster->count(),
            'items' => $cancelledRoster->map(fn (array $row) => [
                'type' => $row['type'],
                'name' => $row['name'],
                'group_name' => $row['group_name'],
                'group_label' => $row['group_label'],
            ])->values(),
        ]);
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
            5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
            9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
        ][$month];
    }
}
