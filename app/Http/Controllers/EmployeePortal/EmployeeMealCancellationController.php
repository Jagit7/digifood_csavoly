<?php

namespace App\Http\Controllers\EmployeePortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeePortal\RestoreEmployeeMealCancellationRequest;
use App\Http\Requests\EmployeePortal\StoreEmployeeMealCancellationRequest;
use App\Models\EmployeeMealCancellation;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Models\WorkingDay;
use App\Services\EmployeeMealCancellationService;
use App\Services\HungarianHolidayService;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A dolgozói portál saját étkezés-lemondás oldala. A tényleges jogosultsági/
 * naptár-ellenőrzést és írást az App\Services\EmployeeMealCancellationService
 * (már meglévő, az admin oldali rögzítéshez épített szolgáltatás)
 * recordSingleAsEmployee()/revokeAsEmployee() metódusai végzik - ez a
 * controller csak a megjelenítendő napi állapotokat építi fel, az
 * App\Http\Controllers\ParentPortal\ParentMealCancellationController
 * mintáját és megjelenését követve (naptár-rács napkártyákkal + napi
 * részletező modal), de egyszerűsítve: a dolgozónak nincs
 * csoport/osztály-szintű lemondása, és jellemzően egyetlen aktív dolgozói
 * rekordja van (bár a modell technikailag többet is megenged, ld.
 * linkedEmployees()) - ezért minden naphoz pontosan egy "sor" tartozik,
 * szemben a szülői oldal több-gyermekes napi soraival.
 *
 * FONTOS: store()/restore()/linkedEmployees()/resolveSelectedEmployeeId()/
 * authorizedEmployee() metódusok VÁLTOZATLANOK maradtak a korábbi
 * verzióhoz képest - a mutáló (lemondás/visszaállítás) útvonal és a hozzá
 * tartozó jogosultság-ellenőrzés (StoreEmployeeMealCancellationRequest,
 * RestoreEmployeeMealCancellationRequest, EmployeeMealCancellationService)
 * nem módosult, kizárólag a MEGJELENÍTÉS lett a szülői felülethez igazítva.
 */
class EmployeeMealCancellationController extends Controller
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly HungarianHolidayService $holidays,
        private readonly EmployeeMealCancellationService $cancellationService
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $today = $this->calendar->now()->startOfDay();
        $employees = $this->linkedEmployees($user);
        $selectedEmployeeId = $this->resolveSelectedEmployeeId($request, $employees);
        $employee = $employees->firstWhere('id', (int) $selectedEmployeeId);

        $selectedDate = $request->filled('date')
            ? CarbonImmutable::parse((string) $request->input('date'), $this->calendar->timezone())->startOfDay()
            : $today;
        $viewMode = $request->input('view') === 'month' ? 'month' : 'week';
        [$rangeStart, $rangeEnd, $previousDate, $nextDate, $periodLabel] = $this->calendarRange($selectedDate, $viewMode);

        return view('employee.meal-cancellations.index', [
            'employees' => $employees,
            'selectedEmployeeId' => $selectedEmployeeId,
            'selectedDate' => $selectedDate,
            'viewMode' => $viewMode,
            'previousDate' => $previousDate,
            'nextDate' => $nextDate,
            'periodLabel' => $periodLabel,
            'days' => $employee
                ? $this->buildCalendarDays($employee, $rangeStart, $rangeEnd, $selectedDate)
                : collect(),
            'weekdayLabels' => ['Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Péntek', 'Szombat', 'Vasárnap'],
            'legend' => $this->legendItems(),
            'today' => $today,
        ]);
    }

    public function day(Request $request, string $date): JsonResponse
    {
        $user = auth()->user();
        $employees = $this->linkedEmployees($user);
        $selectedEmployeeId = $this->resolveSelectedEmployeeId($request, $employees);
        $employee = $employees->firstWhere('id', (int) $selectedEmployeeId);

        if ($employee === null) {
            abort(404);
        }

        $serviceDate = CarbonImmutable::parse($date, $this->calendar->timezone())->startOfDay();
        $institution = $employee->institution;

        if (! $institution) {
            abort(404);
        }

        $context = $this->institutionDayContext($institution, $serviceDate);
        $cancellation = $this->cancellationService->activeForEmployeeOnDate($institution->id, $employee->id, $serviceDate);
        $row = $this->buildEmployeeDayData($employee, $serviceDate, $context, $cancellation !== null, $cancellation);

        return response()->json([
            'date_label' => $serviceDate->locale('hu')->isoFormat('YYYY. MMMM D., dddd'),
            'day_type' => $context['type_label'],
            'deadline_label' => $this->deadlineDisplayLabel($context['availability']),
            'can_submit_cancellation' => $row['can_cancel'],
            'can_submit_restore' => $row['can_restore'],
            'rows' => [$row],
        ]);
    }

    public function store(StoreEmployeeMealCancellationRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $employee = $this->authorizedEmployee($user, (int) $request->validated('employee_id'));
        $institution = $employee->institution;

        try {
            $this->cancellationService->recordSingleAsEmployee(
                $institution,
                $employee,
                (string) $request->validated('service_date'),
                $user
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'A kiválasztott nap lemondása sikeresen mentve.');
    }

    public function restore(RestoreEmployeeMealCancellationRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $employee = $this->authorizedEmployee($user, (int) $request->validated('employee_id'));
        $serviceDate = CarbonImmutable::parse($request->validated('service_date'), $this->calendar->timezone())->startOfDay();

        $cancellation = EmployeeMealCancellation::query()
            ->where('institution_employee_id', $employee->id)
            ->whereDate('service_date', $serviceDate->toDateString())
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->first();

        if ($cancellation === null) {
            throw ValidationException::withMessages([
                'service_date' => 'Erre a napra nincs aktív, visszaállítható lemondás.',
            ]);
        }

        try {
            $this->cancellationService->revokeAsEmployee($cancellation, $user);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'A lemondás visszaállítása sikeresen mentve.');
    }

    private function linkedEmployees(User $user): Collection
    {
        return InstitutionEmployee::query()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->with('institution')
            ->orderBy('name')
            ->get();
    }

    private function resolveSelectedEmployeeId(Request $request, Collection $employees): string
    {
        if ($employees->isEmpty()) {
            return '';
        }

        if ($employees->count() === 1) {
            return (string) $employees->first()->id;
        }

        $selected = (string) $request->input('employee_id', $employees->first()->id);

        if (! $employees->pluck('id')->contains((int) $selected)) {
            abort(404);
        }

        return $selected;
    }

    private function authorizedEmployee(User $user, int $employeeId): InstitutionEmployee
    {
        $employee = $this->linkedEmployees($user)->firstWhere('id', $employeeId);

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Csak a saját dolgozói jogviszonyához tartozó étkezés módosítható.',
            ]);
        }

        return $employee;
    }

    private function calendarRange(CarbonImmutable $selectedDate, string $viewMode): array
    {
        if ($viewMode === 'week') {
            $start = $selectedDate->startOfWeek(CarbonInterface::MONDAY);
            $end = $selectedDate->endOfWeek(CarbonInterface::SUNDAY);

            return [
                $start,
                $end,
                $selectedDate->subWeek(),
                $selectedDate->addWeek(),
                $start->locale('hu')->isoFormat('YYYY. MMMM D.').' - '.$end->locale('hu')->isoFormat('MMMM D.'),
            ];
        }

        $monthStart = $selectedDate->startOfMonth();
        $monthEnd = $selectedDate->endOfMonth();

        return [
            $monthStart->startOfWeek(CarbonInterface::MONDAY),
            $monthEnd->endOfWeek(CarbonInterface::SUNDAY),
            $selectedDate->subMonthNoOverflow(),
            $selectedDate->addMonthNoOverflow(),
            $selectedDate->locale('hu')->isoFormat('YYYY. MMMM'),
        ];
    }

    private function buildCalendarDays(
        InstitutionEmployee $employee,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        CarbonImmutable $selectedDate
    ): Collection {
        $institution = $employee->institution;
        $cancellations = EmployeeMealCancellation::query()
            ->where('institution_employee_id', $employee->id)
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get()
            ->keyBy(fn (EmployeeMealCancellation $row) => CarbonImmutable::parse($row->service_date)->toDateString());

        $days = collect();

        for ($date = $rangeStart; $date->lte($rangeEnd); $date = $date->addDay()) {
            $context = $this->institutionDayContext($institution, $date);
            $dateKey = $date->toDateString();
            $rows = collect([
                $this->buildEmployeeDayData(
                    $employee,
                    $date,
                    $context,
                    $cancellations->has($dateKey),
                    $cancellations->get($dateKey)
                ),
            ]);

            $days->push([
                'date' => $date,
                'is_current_month' => $date->month === $selectedDate->month,
                'type_class' => $context['type_class'],
                'type_label' => $context['type_label'],
                'type_description' => $context['type_description'],
                'deadline_label' => $this->deadlineDisplayLabel($context['availability']),
                'rows' => $rows,
                'cancelled_count' => $rows->where('is_cancelled', true)->count(),
                'actionable_count' => $rows->filter(fn (array $row) => $row['can_cancel'] || $row['can_restore'])->count(),
                'is_actionable' => $rows->contains(fn (array $row) => $row['can_cancel'] || $row['can_restore']),
            ] + $this->dayAppearance($date, $context, $rows));
        }

        return $days;
    }

    private function buildEmployeeDayData(
        InstitutionEmployee $employee,
        CarbonImmutable $date,
        array $context,
        bool $isCancelled,
        ?EmployeeMealCancellation $cancellation
    ): array {
        $meal = $this->mealSelectionForEmployee($employee, $date);
        $availability = $context['availability'];
        $reason = null;
        $canCancel = false;
        $canRestore = false;
        $status = 'Nem módosítható';

        if (! $context['is_service_day']) {
            $reason = $context['type_description'];
        } elseif (! $meal['has_meal']) {
            $status = 'Nincs aktív étkezés';
            $reason = 'Erre a napra nincs aktív dolgozói étkezési beállítás.';
        } elseif ($isCancelled) {
            $status = 'Lemondva';

            if ($availability['cancellable']) {
                $canRestore = $cancellation?->source === EmployeeMealCancellation::SOURCE_EMPLOYEE
                    && (int) $cancellation->created_by === (int) auth()->id();

                if (! $canRestore) {
                    $reason = $cancellation?->source === EmployeeMealCancellation::SOURCE_EMPLOYEE
                        ? null
                        : 'Ezt a lemondást az intézmény rögzítette, csak ő vonhatja vissza.';
                }
            } else {
                $reason = $this->deadlineDisplayLabel($availability);
            }
        } elseif ($availability['cancellable']) {
            $status = 'Aktív';
            $canCancel = true;
        } else {
            $status = 'Határidő lejárt';
            $reason = $this->deadlineDisplayLabel($availability);
        }

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'meal_label' => $meal['label'],
            'meal_types' => $meal['types'],
            'has_meal' => $meal['has_meal'],
            'is_cancelled' => $isCancelled,
            'can_cancel' => $canCancel,
            'can_restore' => $canRestore,
            'status' => $status,
            'reason' => $reason,
        ];
    }

    private function institutionDayContext(
        Institution $institution,
        CarbonImmutable $date,
        ?CarbonImmutable $now = null
    ): array {
        $break = SchoolBreak::query()
            ->where('institution_id', $institution->id)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();
        $workingDay = WorkingDay::query()
            ->where('institution_id', $institution->id)
            ->whereDate('date', $date->toDateString())
            ->first();
        $holiday = $this->holidays->between($date, $date)->get($date->toDateString());
        $isServiceDay = $this->calendar->isServiceDay($institution->id, $date);
        $availability = $this->calendar->cancellationAvailability($institution->id, $date, $now);

        if ($break) {
            return [
                'is_service_day' => false,
                'type_class' => 'type-break',
                'type_label' => 'Intézményi szünet',
                'type_description' => $break->title,
                'availability' => $availability,
            ];
        }

        if ($holiday) {
            return [
                'is_service_day' => false,
                'type_class' => 'type-holiday',
                'type_label' => 'Munkaszüneti nap',
                'type_description' => $holiday,
                'availability' => $availability,
            ];
        }

        if ($workingDay) {
            return [
                'is_service_day' => true,
                'type_class' => 'type-working-saturday',
                'type_label' => 'Szombati munkanap',
                'type_description' => $workingDay->name ?: 'Szombati munkanap',
                'availability' => $availability,
            ];
        }

        if ($date->isWeekend()) {
            return [
                'is_service_day' => false,
                'type_class' => 'type-weekend',
                'type_label' => 'Hétvége',
                'type_description' => 'Hétvégén nincs étkeztetés.',
                'availability' => $availability,
            ];
        }

        return [
            'is_service_day' => $isServiceDay,
            'type_class' => $isServiceDay ? 'type-service' : 'type-inactive',
            'type_label' => $isServiceDay ? $this->serviceDayLabel($institution) : 'Nincs étkeztetés',
            'type_description' => $isServiceDay ? 'Aktív étkezési nap.' : 'Erre a napra nincs intézményi étkeztetés.',
            'availability' => $availability,
        ];
    }

    private function mealSelectionForEmployee(InstitutionEmployee $employee, CarbonImmutable $date): array
    {
        $setting = $employee->mealSettings
            ->first(function (StudentMealSetting $setting) use ($date) {
                $validFrom = CarbonImmutable::parse($setting->valid_from, $this->calendar->timezone())->startOfDay();
                $validTo = $setting->valid_to
                    ? CarbonImmutable::parse($setting->valid_to, $this->calendar->timezone())->startOfDay()
                    : null;

                return $validFrom->lte($date) && ($validTo === null || $validTo->gte($date));
            });

        if (! $setting) {
            return ['has_meal' => false, 'label' => 'Nincs aktív étkezés', 'types' => []];
        }

        if ($setting->mode === StudentMealSetting::MODE_PACKAGE && $setting->mealPackage) {
            return [
                'has_meal' => true,
                'label' => $setting->mealPackage->name,
                'types' => $setting->mealPackage->mealTypes
                    ->map(fn ($type) => $type->mealType?->name ?? 'Étkezés')
                    ->filter()
                    ->values()
                    ->all(),
            ];
        }

        if ($setting->mode === StudentMealSetting::MODE_CUSTOM) {
            $types = $setting->mealTypes
                ->map(fn ($type) => $type->mealType?->name ?? 'Étkezés')
                ->filter()
                ->values()
                ->all();

            return [
                'has_meal' => count($types) > 0,
                'label' => count($types) > 0 ? 'Egyedi étkezések' : 'Nincs aktív étkezés',
                'types' => $types,
            ];
        }

        $defaultPackage = $employee->institution?->mealPackages()->where('is_active', true)->where('is_default', true)->first();
        $types = $defaultPackage?->mealTypes
            ? $defaultPackage->mealTypes
                ->map(fn ($type) => $type->mealType?->name ?? 'Étkezés')
                ->filter()
                ->values()
                ->all()
            : [];

        return [
            'has_meal' => $defaultPackage !== null,
            'label' => $defaultPackage?->name ?? 'Nincs intézményi alapértelmezett csomag',
            'types' => $types,
        ];
    }

    private function deadlineDisplayLabel(array $availability): ?string
    {
        if (! ($availability['deadline'] ?? null)) {
            return null;
        }

        return 'Határidő: '.$this->formatDeadlineLabel($availability['deadline']);
    }

    private function formatDeadlineLabel(CarbonImmutable $deadline): string
    {
        $today = $this->calendar->now()->startOfDay();
        $deadlineDay = $deadline->startOfDay();

        if ($deadlineDay->equalTo($today)) {
            return 'ma '.$deadline->format('H:i');
        }

        if ($deadlineDay->equalTo($today->addDay())) {
            return 'holnap '.$deadline->format('H:i');
        }

        $monthLabels = [
            1 => 'jan.',
            2 => 'febr.',
            3 => 'márc.',
            4 => 'ápr.',
            5 => 'máj.',
            6 => 'jún.',
            7 => 'júl.',
            8 => 'aug.',
            9 => 'szept.',
            10 => 'okt.',
            11 => 'nov.',
            12 => 'dec.',
        ];

        return sprintf(
            '%s %d. %s',
            $monthLabels[$deadline->month] ?? $deadline->locale('hu')->isoFormat('MMM'),
            $deadline->day,
            $deadline->format('H:i')
        );
    }

    private function serviceDayLabel(Institution $institution): string
    {
        return match ($institution->type) {
            'iskola' => 'Tanítási nap',
            'ovoda' => 'Nevelési nap',
            default => 'Intézményi nap',
        };
    }

    private function legendItems(): array
    {
        return [
            ['class' => 'day-tone-today', 'label' => 'Ma'],
            ['class' => 'day-tone-past', 'label' => 'Múltbeli nap'],
            ['class' => 'day-tone-weekend', 'label' => 'Hétvége'],
            ['class' => 'day-tone-cancellable', 'label' => 'Lemondható nap'],
            ['class' => 'day-tone-neutral', 'label' => 'Egyéb nap'],
        ];
    }

    private function dayAppearance(CarbonImmutable $date, array $context, Collection $rows): array
    {
        $today = $this->calendar->now()->startOfDay();
        $hasCancellableRow = $rows->contains(fn (array $row) => $row['can_cancel']);

        if ($date->equalTo($today)) {
            return [
                'tone_class' => 'day-tone-today',
                'state_badge' => 'Ma',
                'state_icon' => 'fa-solid fa-star',
            ];
        }

        if ($date->lt($today)) {
            return [
                'tone_class' => 'day-tone-past',
                'state_badge' => 'Múltbeli nap',
                'state_icon' => 'fa-regular fa-clock',
            ];
        }

        if ($date->isWeekend() && $context['type_class'] !== 'type-working-saturday') {
            return [
                'tone_class' => 'day-tone-weekend',
                'state_badge' => 'Hétvége',
                'state_icon' => 'fa-solid fa-couch',
            ];
        }

        if ($hasCancellableRow) {
            return [
                'tone_class' => 'day-tone-cancellable',
                'state_badge' => 'Lemondható',
                'state_icon' => 'fa-solid fa-circle-check',
            ];
        }

        return [
            'tone_class' => 'day-tone-neutral',
            'state_badge' => $context['is_service_day'] ? 'Nem lemondható' : $context['type_label'],
            'state_icon' => $context['is_service_day'] ? 'fa-solid fa-circle-info' : 'fa-solid fa-calendar-day',
        ];
    }
}
