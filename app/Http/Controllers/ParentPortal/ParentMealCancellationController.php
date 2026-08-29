<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParentPortal\RestoreParentMealCancellationRequest;
use App\Http\Requests\ParentPortal\StoreParentMealCancellationRequest;
use App\Models\Child;
use App\Models\Institution;
use App\Models\MealCancellation;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Models\WorkingDay;
use App\Services\HungarianHolidayService;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParentMealCancellationController extends Controller
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly HungarianHolidayService $holidays
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $today = $this->calendar->now()->startOfDay();
        $children = $this->linkedChildren($user);
        $selectedChildId = $this->resolveSelectedChildId($request, $children);
        $visibleChildren = $selectedChildId === 'all'
            ? $children
            : $children->where('id', (int) $selectedChildId)->values();
        $selectedDate = $request->filled('date')
            ? CarbonImmutable::parse((string) $request->input('date'), $this->calendar->timezone())->startOfDay()
            : $today;
        $viewMode = $request->input('view') === 'month' ? 'month' : 'week';
        [$rangeStart, $rangeEnd, $previousDate, $nextDate, $periodLabel] = $this->calendarRange($selectedDate, $viewMode);
        return view('parent.meal-cancellations.index', [
            'children' => $children,
            'visibleChildren' => $visibleChildren,
            'selectedChildId' => $selectedChildId,
            'selectedDate' => $selectedDate,
            'viewMode' => $viewMode,
            'previousDate' => $previousDate,
            'nextDate' => $nextDate,
            'periodLabel' => $periodLabel,
            'days' => $visibleChildren->isNotEmpty()
                ? $this->buildCalendarDays($visibleChildren, $rangeStart, $rangeEnd, $selectedDate)
                : collect(),
            'weekdayLabels' => ['Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Péntek', 'Szombat', 'Vasárnap'],
            'legend' => $this->legendItems(),
            'today' => $today,
        ]);
    }

    public function day(Request $request, string $date): JsonResponse
    {
        $children = $this->linkedChildren(auth()->user());
        $selectedChildId = $this->resolveSelectedChildId($request, $children);
        $visibleChildren = $selectedChildId === 'all'
            ? $children
            : $children->where('id', (int) $selectedChildId)->values();

        if ($visibleChildren->isEmpty()) {
            abort(404);
        }

        $serviceDate = CarbonImmutable::parse($date, $this->calendar->timezone())->startOfDay();
        $activeCancellations = $this->cancellationMap($visibleChildren->pluck('id'), $serviceDate, $serviceDate);
        $classCancelled = $this->classCancellationMapForChildren($visibleChildren, $serviceDate, $serviceDate);
        $contexts = collect();

        $rows = $visibleChildren->map(function (Child $child) use ($serviceDate, $activeCancellations, $classCancelled, $contexts) {
            $context = $this->institutionDayContext($child->institution, $serviceDate);
            $contexts->push($context);

            return $this->buildChildDayData(
                $child,
                $serviceDate,
                $context,
                $activeCancellations->has($this->childDateKey($child->id, $serviceDate)),
                $classCancelled->has($this->childDateKey($child->id, $serviceDate))
            );
        })->values();
        $summary = $this->summarizeVisibleDay($contexts, $rows);

        return response()->json([
            'date_label' => $serviceDate->locale('hu')->isoFormat('YYYY. MMMM D., dddd'),
            'day_type' => $summary['type_label'],
            'deadline_label' => $summary['deadline_label'],
            'can_submit_cancellation' => $rows->contains(fn (array $row) => $row['can_cancel']),
            'can_submit_restore' => $rows->contains(fn (array $row) => $row['can_restore']),
            'rows' => $rows,
        ]);
    }

    public function store(StoreParentMealCancellationRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $children = $this->authorizedChildren($user, $request->validated('child_ids'));
        $serviceDate = CarbonImmutable::parse($request->validated('service_date'), $this->calendar->timezone())->startOfDay();
        $submittedAt = $this->calendar->now();
        $timestamp = now();

        DB::transaction(function () use ($children, $serviceDate, $submittedAt, $user) {
            foreach ($children as $child) {
                $payload = $this->ensureChildCanCancel($child, $serviceDate, $submittedAt);

                $cancellation = MealCancellation::query()->firstOrNew([
                    'child_id' => $child->id,
                    'service_date' => $serviceDate->toDateString(),
                ]);

                $cancellation->institution_id = $child->institution_id;
                $cancellation->source = MealCancellation::SOURCE_PARENT;
                $cancellation->status = MealCancellation::STATUS_ACTIVE;
                $cancellation->reason = null;
                $cancellation->created_by = $user->id;
                $cancellation->revoked_by = null;
                $cancellation->revoked_at = null;
                $cancellation->save();
            }
        });

        return back()->with('success', 'A kiválasztott étkezések lemondása sikeresen mentve.');
    }

    public function restore(RestoreParentMealCancellationRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $children = $this->authorizedChildren($user, $request->validated('child_ids'));
        $serviceDate = CarbonImmutable::parse($request->validated('service_date'), $this->calendar->timezone())->startOfDay();
        $submittedAt = $this->calendar->now();
        $cancellations = MealCancellation::query()
            ->whereIn('child_id', $children->pluck('id'))
            ->whereDate('service_date', $serviceDate->toDateString())
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->where('source', MealCancellation::SOURCE_PARENT)
            ->where('created_by', $user->id)
            ->get()
            ->keyBy('child_id');

        if ($cancellations->count() !== $children->count()) {
            throw ValidationException::withMessages([
                'child_ids' => 'A kiválasztott gyermekek közül legalább egyhez nincs aktív lemondás ezen a napon.',
            ]);
        }

        DB::transaction(function () use ($children, $serviceDate, $submittedAt, $cancellations, $user) {
            foreach ($children as $child) {
                $this->ensureChildCanRestore($child, $serviceDate, $cancellations->get($child->id), $submittedAt);
            }

            MealCancellation::query()
                ->whereIn('id', $cancellations->pluck('id'))
                ->update([
                    'status' => MealCancellation::STATUS_REVOKED,
                    'revoked_by' => $user->id,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return back()->with('success', 'A kiválasztott étkezések visszaállítása sikeresen mentve.');
    }

    private function linkedChildren(User $user): Collection
    {
        $guardianIds = $user->guardians()->where('active', true)->pluck('guardians.id');

        return Child::query()
            ->where('active', true)
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', $guardianIds))
            ->with([
                'institution.mealSetting',
                'institution.mealPackages' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['mealTypes.mealType'])
                    ->orderByDesc('is_default')
                    ->orderBy('display_order')
                    ->orderBy('name'),
                'mealSettings' => fn ($query) => $query
                    ->with(['mealPackage.mealTypes.mealType', 'mealTypes.mealType'])
                    ->orderByDesc('valid_from')
                    ->orderByDesc('id'),
            ])
            ->orderBy('name')
            ->get()
            ->values();
    }

    private function resolveSelectedChildId(Request $request, Collection $children): string
    {
        if ($children->count() === 1) {
            return (string) $children->first()->id;
        }

        $selected = (string) $request->input('child_id', 'all');

        if ($selected === 'all') {
            return 'all';
        }

        if (! $children->pluck('id')->contains((int) $selected)) {
            abort(404);
        }

        return $selected;
    }

    private function calendarRange(
        CarbonImmutable $selectedDate,
        string $viewMode
    ): array {
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
        Collection $children,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        CarbonImmutable $selectedDate
    ): Collection {
        $childIds = $children->pluck('id');
        $cancellations = $this->cancellationMap($childIds, $rangeStart, $rangeEnd);
        $classCancelled = $this->classCancellationMapForChildren($children, $rangeStart, $rangeEnd);

        $days = collect();

        for ($date = $rangeStart; $date->lte($rangeEnd); $date = $date->addDay()) {
            $contexts = collect();
            $rows = $children->map(function (Child $child) use ($date, $cancellations, $classCancelled, $contexts) {
                $context = $this->institutionDayContext($child->institution, $date);
                $contexts->push($context);

                return $this->buildChildDayData(
                    $child,
                    $date,
                    $context,
                    $cancellations->has($this->childDateKey($child->id, $date)),
                    $classCancelled->has($this->childDateKey($child->id, $date))
                );
            })->values();
            $summary = $this->summarizeVisibleDay($contexts, $rows);

            $days->push([
                'date' => $date,
                'is_current_month' => $date->month === $selectedDate->month,
                'type_class' => $summary['type_class'],
                'type_label' => $summary['type_label'],
                'type_description' => $summary['type_description'],
                'deadline_label' => $summary['deadline_label'],
                'rows' => $rows,
                'cancelled_count' => $rows->where('is_cancelled', true)->count(),
                'actionable_count' => $rows->filter(fn (array $row) => $row['can_cancel'] || $row['can_restore'])->count(),
                'is_actionable' => $rows->contains(fn (array $row) => $row['can_cancel'] || $row['can_restore']),
            ] + $this->dayAppearance($date, $summary, $rows));
        }

        return $days;
    }

    private function classCancellationMapForChildren(
        Collection $children,
        CarbonImmutable $from,
        CarbonImmutable $to
    ): Collection {
        return $children
            ->groupBy('institution_id')
            ->reduce(function (Collection $carry, Collection $institutionChildren, int|string $institutionId) use ($from, $to) {
                $map = $this->classCancellationMap((int) $institutionId, $institutionChildren->pluck('id'), $from, $to);

                return $carry->merge($map);
            }, collect());
    }

    private function buildChildDayData(
        Child $child,
        CarbonImmutable $date,
        array $context,
        bool $isCancelled,
        bool $isClassCancelled
    ): array {
        $meal = $this->mealSelectionForDate($child, $date);
        $availability = $context['availability'];
        $reason = null;
        $canCancel = false;
        $canRestore = false;
        $status = 'Nem módosítható';

        if (! $context['is_service_day']) {
            $reason = $context['type_description'];
        } elseif ($isClassCancelled) {
            $status = 'Csoportszintű lemondás';
            $reason = 'Az étkezés ezen a napon intézményi vagy csoportszintű lemondás miatt marad el.';
        } elseif (! $meal['has_meal']) {
            $status = 'Nincs aktív étkezés';
            $reason = 'Erre a napra nincs aktív étkezési csomag vagy étkezéstípus beállítva.';
        } elseif ($isCancelled) {
            $status = 'Lemondva';
            if ($availability['cancellable']) {
                $canRestore = true;
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
            'child_id' => $child->id,
            'child_name' => $child->name,
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
                'type_label' => 'Szombati tanítási nap',
                'type_description' => $workingDay->name ?: 'Szombati tanítási nap',
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

    private function mealSelectionForDate(Child $child, CarbonImmutable $date): array
    {
        $setting = $this->currentMealSetting($child, $date);

        if (! $setting) {
            return [
                'has_meal' => false,
                'label' => 'Nincs aktív étkezés',
                'types' => [],
            ];
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

        $defaultPackage = $child->institution?->mealPackages?->firstWhere('is_default', true);
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

    private function currentMealSetting(Child $child, CarbonImmutable $date): ?StudentMealSetting
    {
        return $child->mealSettings->first(function (StudentMealSetting $setting) use ($date) {
            $validFrom = CarbonImmutable::parse($setting->valid_from, $this->calendar->timezone())->startOfDay();
            $validTo = $setting->valid_to
                ? CarbonImmutable::parse($setting->valid_to, $this->calendar->timezone())->startOfDay()
                : null;

            return $validFrom->lte($date) && ($validTo === null || $validTo->gte($date));
        });
    }

    private function cancellationMap(Collection $childIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return MealCancellation::query()
            ->whereIn('child_id', $childIds)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$from->toDateString(), $to->toDateString()])
            ->get(['child_id', 'service_date'])
            ->mapWithKeys(fn (MealCancellation $row) => [
                $this->childDateKey($row->child_id, CarbonImmutable::parse($row->service_date, $this->calendar->timezone())) => true,
            ]);
    }

    private function classCancellationMap(
        int $institutionId,
        Collection $childIds,
        CarbonImmutable $from,
        CarbonImmutable $to
    ): Collection {
        $rows = DB::table('class_cancellations')
            ->join('class_group_memberships', 'class_group_memberships.class_group_id', '=', 'class_cancellations.class_group_id')
            ->join('class_groups', 'class_groups.id', '=', 'class_cancellations.class_group_id')
            ->where('class_cancellations.institution_id', $institutionId)
            ->where('class_groups.institution_id', $institutionId)
            ->whereIn('class_group_memberships.child_id', $childIds)
            ->where('class_group_memberships.status', 'active')
            ->whereDate('class_cancellations.date_from', '<=', $to->toDateString())
            ->whereDate('class_cancellations.date_to', '>=', $from->toDateString())
            ->get(['class_group_memberships.child_id', 'class_cancellations.date_from', 'class_cancellations.date_to']);

        $map = collect();

        foreach ($rows as $row) {
            $start = CarbonImmutable::parse($row->date_from, $this->calendar->timezone())->startOfDay();
            $end = CarbonImmutable::parse($row->date_to, $this->calendar->timezone())->startOfDay();

            for ($date = $start; $date->lte($end); $date = $date->addDay()) {
                $map->put($this->childDateKey((int) $row->child_id, $date), true);
            }
        }

        return $map;
    }

    private function childDateKey(int $childId, CarbonImmutable $date): string
    {
        return $childId.'@'.$date->toDateString();
    }

    private function deadlineMessage(array $availability): string
    {
        if (! ($availability['deadline'] ?? null)) {
            return 'Ehhez a naphoz nincs érvényes lemondási határidő.';
        }

        $deadline = $this->formatDeadlineLabel($availability['deadline']);

        return $availability['cancellable']
            ? 'Lemondható eddig: '.$deadline
            : 'A lemondási határidő időközben lejárt. A lemondás késői lemondásként nem rögzíthető. Határidő: '.$deadline;
    }

    private function deadlineDisplayLabel(array $availability): ?string
    {
        if (! ($availability['deadline'] ?? null)) {
            return null;
        }

        return 'Határidő: '.$this->formatDeadlineLabel($availability['deadline']);
    }

    private function summarizeVisibleDay(Collection $contexts, Collection $rows): array
    {
        $typeLabels = $contexts->pluck('type_label')->filter()->unique()->values();
        $typeDescriptions = $contexts->pluck('type_description')->filter()->unique()->values();
        $typeClasses = $contexts->pluck('type_class')->filter()->unique()->values();
        $deadlineLabels = $contexts
            ->map(fn (array $context) => $this->deadlineDisplayLabel($context['availability']))
            ->filter()
            ->unique()
            ->values();
        $hasActionableRow = $rows->contains(fn (array $row) => $row['can_cancel'] || $row['can_restore']);

        if ($typeLabels->count() === 1) {
            $typeLabel = (string) $typeLabels->first();
            $typeDescription = $typeDescriptions->count() === 1
                ? (string) $typeDescriptions->first()
                : 'A kiválasztott gyermekeknél aznap azonos intézményi nap érvényes.';
            $typeClass = (string) ($typeClasses->first() ?? 'type-service');
        } else {
            $typeLabel = 'Eltérő intézményi nap';
            $typeDescription = $hasActionableRow
                ? 'A kiválasztott gyermekek intézményeiben eltérő napi szabályok lehetnek érvényben.'
                : 'A kiválasztott gyermekeknél aznap eltérő intézményi szabályok érvényesek.';
            $typeClass = 'type-mixed';
        }

        return [
            'is_service_day' => $hasActionableRow,
            'type_class' => $typeClass,
            'type_label' => $typeLabel,
            'type_description' => $typeDescription,
            'deadline_label' => match ($deadlineLabels->count()) {
                0 => null,
                1 => (string) $deadlineLabels->first(),
                default => 'Határidő: intézményenként eltérő',
            },
        ];
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

    private function authorizedChildren(User $user, array $childIds): Collection
    {
        $children = $this->linkedChildren($user)->whereIn('id', collect($childIds)->map(fn ($id) => (int) $id))->values();

        if ($children->count() !== count(array_unique(array_map('intval', $childIds)))) {
            throw ValidationException::withMessages([
                'child_ids' => 'Csak a saját gyermekei étkezése módosítható.',
            ]);
        }

        return $children;
    }

    private function ensureChildCanCancel(Child $child, CarbonImmutable $serviceDate, CarbonImmutable $submittedAt): array
    {
        $context = $this->institutionDayContext($child->institution, $serviceDate, $submittedAt);
        $meal = $this->mealSelectionForDate($child, $serviceDate);

        if (! $context['is_service_day']) {
            throw ValidationException::withMessages([
                'service_date' => 'A kiválasztott nap nem étkezési nap.',
            ]);
        }

        if (! $meal['has_meal']) {
            throw ValidationException::withMessages([
                'service_date' => 'A kiválasztott napon nincs megrendelt étkezés.',
            ]);
        }

        if ($this->classCancellationMap($child->institution_id, collect([$child->id]), $serviceDate, $serviceDate)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'service_date' => 'Erre a napra már csoportszintű lemondás van rögzítve.',
            ]);
        }

        if (! ($context['availability']['cancellable'] ?? false)) {
            throw ValidationException::withMessages([
                'service_date' => $this->deadlineMessage($context['availability']),
            ]);
        }

        if (MealCancellation::query()
            ->where('child_id', $child->id)
            ->whereDate('service_date', $serviceDate->toDateString())
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->exists()) {
            throw ValidationException::withMessages([
                'service_date' => 'Erre a napra már létezik aktív lemondás.',
            ]);
        }

        return $meal;
    }

    private function ensureChildCanRestore(
        Child $child,
        CarbonImmutable $serviceDate,
        MealCancellation $cancellation,
        CarbonImmutable $submittedAt
    ): void {
        $context = $this->institutionDayContext($child->institution, $serviceDate, $submittedAt);

        if (! ($context['availability']['cancellable'] ?? false)) {
            throw ValidationException::withMessages([
                'service_date' => $this->deadlineMessage($context['availability']),
            ]);
        }

        if ($cancellation->child_id !== $child->id || $cancellation->institution_id !== $child->institution_id) {
            throw ValidationException::withMessages([
                'child_ids' => 'Érvénytelen lemondás-visszavonási kérés.',
            ]);
        }

        if ($cancellation->source !== MealCancellation::SOURCE_PARENT) {
            throw ValidationException::withMessages([
                'child_ids' => 'Csak a szülő által rögzített lemondás vonható vissza.',
            ]);
        }
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
