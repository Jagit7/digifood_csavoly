<?php

namespace App\Services;

use App\Models\Child;
use App\Models\DietaryRestriction;
use App\Models\InstitutionEmployee;
use App\Models\MealCancellation;
use App\Models\RecurringCancellationRule;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\WorkingDay;
use App\Services\Kitchen\EmployeeDailyMealHeadcountService;
use App\Services\Meals\AbMenuSelectionService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InstitutionMealCalendarService
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly HungarianHolidayService $holidays,
        private readonly DailyMealHeadcountService $headcount,
        private readonly AbMenuSelectionService $abMenuSelection,
        private readonly EmployeeDailyMealHeadcountService $employeeHeadcount
    ) {
    }

    public function period(int $institutionId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $start = CarbonImmutable::instance($from)->startOfDay();
        $end = CarbonImmutable::instance($to)->startOfDay();
        $children = $this->children($institutionId)->get();
        $childIds = $children->pluck('id');
        $activeIds = $childIds->flip();
        $mealSettings = $this->headcount->mealSettingsForChildrenInPeriod(
            $institutionId,
            $childIds,
            $start->toDateString(),
            $end->toDateString()
        );
        $cancellations = $this->cancellationsByDate($institutionId, $start, $end, $activeIds);

        // Dolgozók (institution_employee) additív bevonása a naptár létszámaiba -
        // ugyanazokkal a szabályokkal, mint a gyerekeknél: csak aktív dolgozó,
        // csak akinek van érvényes étkezési beállítása az adott napra, és a
        // lemondások figyelembevételével. A fizetési/számlázási logikát ez
        // egyáltalán nem érinti, csak a létszám-kimutatást.
        $employees = $this->employeeHeadcount->activeEmployeesQuery($institutionId)->get();
        $employeeIds = $employees->pluck('id');
        $employeeActiveIds = $employeeIds->flip();
        $employeeMealSettings = $this->employeeHeadcount->mealSettingsForEmployeesInPeriod(
            $institutionId,
            $employeeIds,
            $start->toDateString(),
            $end->toDateString()
        );
        $employeeCancellations = $this->employeeHeadcount->cancellationsByDate(
            $institutionId,
            $start,
            $end,
            $employeeActiveIds
        );

        $menuItems = $this->abMenuSelection->menuItemsForRange($institutionId, $start, $end);
        $choiceMap = $this->abMenuSelection->choiceMapForChildrenAndItems(
            $institutionId,
            $childIds,
            $menuItems->values()
        );
        $breaks = $this->breaksByDate($institutionId, $start, $end);
        $workingDays = WorkingDay::query()
            ->where('institution_id', $institutionId)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (WorkingDay $day) => $day->date->toDateString());
        $publicHolidays = $this->holidays->between($start, $end);
        $serviceDays = $this->calendar->serviceDaysBetween($institutionId, $start, $end)
            ->map->toDateString()
            ->flip();
        $days = collect();

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dateString = $date->toDateString();
            $isServiceDay = $serviceDays->has($dateString);
            $cancelledIds = $cancellations->get($dateString, collect());
            $menuItem = $menuItems->get($dateString);
            $eaters = $isServiceDay
                ? $children
                    ->reject(fn (Child $child) => $cancelledIds->has($child->id))
                    ->filter(fn (Child $child) => $this->mealSettingForDate($mealSettings->get($child->id, collect()), $dateString) !== null)
                    ->values()
                : collect();
            $categories = $this->categoryCounts($eaters);
            $menuCounts = $this->abMenuSelection->countsForChildrenAndItem($eaters, $menuItem, $choiceMap);

            $employeeCancelledIds = $employeeCancellations->get($dateString, collect());
            $employeeEaters = $isServiceDay
                ? $employees
                    ->reject(fn (InstitutionEmployee $employee) => $employeeCancelledIds->has($employee->id))
                    ->filter(fn (InstitutionEmployee $employee) => $this->employeeHeadcount->mealSettingForDate($employeeMealSettings->get($employee->id, collect()), $dateString) !== null)
                    ->values()
                : collect();
            $employeeCategories = $this->categoryCounts($employeeEaters);

            $days->put($dateString, [
                'date' => $date,
                'is_service_day' => $isServiceDay,
                'is_current_month' => true,
                'total' => $eaters->count() + $employeeEaters->count(),
                'standard' => $categories['standard'] + $employeeCategories['standard'],
                'allergen' => $categories['allergen'] + $employeeCategories['allergen'],
                'other_diet' => $categories['other_diet'] + $employeeCategories['other_diet'],
                'has_ab_menu' => $menuItem !== null,
                'menu_a_count' => $menuCounts['menu_a_count'],
                'menu_b_count' => $menuCounts['menu_b_count'],
                'dietary_count' => $menuCounts['dietary_count'],
                'cancelled' => $isServiceDay ? ($cancelledIds->count() + $employeeCancelledIds->count()) : 0,
                // Átláthatósági (gyerek/dolgozó bontású) mezők - additívak, a fenti
                // összesített kulcsokat nem váltják ki, csak kiegészítik.
                'child_total' => $eaters->count(),
                'employee_total' => $employeeEaters->count(),
                'child_cancelled' => $isServiceDay ? $cancelledIds->count() : 0,
                'employee_cancelled' => $isServiceDay ? $employeeCancelledIds->count() : 0,
                'holiday' => $publicHolidays->get($dateString),
                'break' => $breaks->get($dateString),
                'working_day' => $workingDays->get($dateString),
            ]);
        }

        return $days;
    }

    public function day(int $institutionId, CarbonInterface $date): array
    {
        $day = CarbonImmutable::instance($date)->startOfDay();
        $summary = $this->period($institutionId, $day, $day)->first();
        $children = $this->children($institutionId)
            ->orderByRaw('CASE WHEN group_name IS NULL OR group_name = ? THEN 1 ELSE 0 END', [''])
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();
        $mealSettings = $this->headcount->mealSettingsForChildrenInPeriod(
            $institutionId,
            $children->pluck('id'),
            $day->toDateString(),
            $day->toDateString()
        );
        $menuItem = $this->abMenuSelection->menuItemsForRange($institutionId, $day, $day)->get($day->toDateString());
        $choiceMap = $menuItem
            ? $this->abMenuSelection->choiceMapForChildrenAndItems($institutionId, $children->pluck('id'), collect([$menuItem]))
            : collect();
        $cancelledIds = $this->cancellationsByDate(
            $institutionId,
            $day,
            $day,
            $children->pluck('id')->flip()
        )->get($day->toDateString(), collect());

        $roster = $children->map(function (Child $child) use ($summary, $cancelledIds, $mealSettings, $day, $menuItem, $choiceMap) {
            $allergens = $child->dietaryRestrictions
                ->where('type', DietaryRestriction::TYPE_ALLERGEN)
                ->pluck('name')
                ->values();
            $otherRestrictions = $child->dietaryRestrictions
                ->where('type', '!=', DietaryRestriction::TYPE_ALLERGEN)
                ->pluck('name')
                ->values();
            $mealSetting = $this->mealSettingForDate($mealSettings->get($child->id, collect()), $day->toDateString());
            $isEating = $summary['is_service_day']
                && $mealSetting !== null
                && ! $cancelledIds->has($child->id);
            $explicitChoice = $menuItem
                ? $this->abMenuSelection->explicitChoiceRecord($choiceMap, $child->id, $menuItem)
                : null;

            return [
                'child' => $child,
                'is_eating' => $isEating,
                'effective_menu_category' => $isEating
                    ? $this->abMenuSelection->effectiveCategoryForChildAndItem($child, $menuItem, $explicitChoice)
                    : null,
                'allergens' => $allergens,
                'other_restrictions' => $otherRestrictions,
            ];
        });

        // Additív dolgozói napi lista - a fizetési/számlázási logikát nem
        // érinti, csak a naptár napi részletező nézetéhez ad plusz adatot
        // (jelenleg AB-menü választás nélkül, mert a dolgozóknak ez a naptár
        // ezt eddig nem is jelenítette meg).
        $employees = $this->employeeHeadcount->activeEmployeesQuery($institutionId)->get();
        $employeeMealSettings = $this->employeeHeadcount->mealSettingsForEmployeesInPeriod(
            $institutionId,
            $employees->pluck('id'),
            $day->toDateString(),
            $day->toDateString()
        );
        $employeeCancelledIds = $this->employeeHeadcount->cancellationsByDate(
            $institutionId,
            $day,
            $day,
            $employees->pluck('id')->flip()
        )->get($day->toDateString(), collect());

        $employeeRoster = $employees->map(function (InstitutionEmployee $employee) use ($summary, $employeeCancelledIds, $employeeMealSettings, $day) {
            $allergens = $employee->dietaryRestrictions
                ->where('type', DietaryRestriction::TYPE_ALLERGEN)
                ->pluck('name')
                ->values();
            $otherRestrictions = $employee->dietaryRestrictions
                ->where('type', '!=', DietaryRestriction::TYPE_ALLERGEN)
                ->pluck('name')
                ->values();
            $mealSetting = $this->employeeHeadcount->mealSettingForDate($employeeMealSettings->get($employee->id, collect()), $day->toDateString());
            $isEating = $summary['is_service_day']
                && $mealSetting !== null
                && ! $employeeCancelledIds->has($employee->id);

            return [
                'employee' => $employee,
                'is_eating' => $isEating,
                'allergens' => $allergens,
                'other_restrictions' => $otherRestrictions,
            ];
        });

        return ['summary' => $summary, 'roster' => $roster, 'employee_roster' => $employeeRoster];
    }

    private function children(int $institutionId): Builder
    {
        return Child::query()
            ->where('institution_id', $institutionId)
            ->where('active', true)
            ->with(['dietaryRestrictions' => fn ($query) => $query
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')]);
    }

    private function categoryCounts(Collection $children): array
    {
        $allergen = 0;
        $otherDiet = 0;
        $standard = 0;

        foreach ($children as $child) {
            if ($child->dietaryRestrictions->contains('type', DietaryRestriction::TYPE_ALLERGEN)) {
                $allergen++;
            } elseif ($child->dietaryRestrictions->isNotEmpty()) {
                $otherDiet++;
            } else {
                $standard++;
            }
        }

        return [
            'standard' => $standard,
            'allergen' => $allergen,
            'other_diet' => $otherDiet,
        ];
    }

    private function mealSettingForDate(Collection $settings, string $date): ?StudentMealSetting
    {
        return $this->headcount->mealSettingForDate($settings, $date);
    }

    private function cancellationsByDate(
        int $institutionId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        Collection $activeIds
    ): Collection {
        $result = collect();
        $add = function (string $date, int $childId) use ($result, $activeIds): void {
            if (!$activeIds->has($childId)) {
                return;
            }

            if (!$result->has($date)) {
                $result->put($date, collect());
            }

            $result->get($date)->put($childId, true);
        };

        MealCancellation::query()
            ->where('institution_id', $institutionId)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$from->toDateString(), $to->toDateString()])
            ->get(['child_id', 'service_date'])
            ->each(fn (MealCancellation $item) => $add($item->service_date->toDateString(), $item->child_id));

        $rules = RecurringCancellationRule::query()
            ->where('institution_id', $institutionId)
            ->whereIn('status', [RecurringCancellationRule::STATUS_ACTIVE, RecurringCancellationRule::STATUS_ENDED])
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString()))
            ->get();

        $classRows = DB::table('class_cancellations')
            ->join('class_group_memberships', 'class_group_memberships.class_group_id', '=', 'class_cancellations.class_group_id')
            ->join('class_groups', 'class_groups.id', '=', 'class_cancellations.class_group_id')
            ->where('class_cancellations.institution_id', $institutionId)
            ->where('class_groups.institution_id', $institutionId)
            ->where('class_group_memberships.status', 'active')
            ->whereDate('class_cancellations.date_from', '<=', $to->toDateString())
            ->whereDate('class_cancellations.date_to', '>=', $from->toDateString())
            ->get(['class_group_memberships.child_id', 'class_cancellations.date_from', 'class_cancellations.date_to']);

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $dateString = $date->toDateString();

            foreach ($rules as $rule) {
                if ($rule->weekday === $date->dayOfWeekIso
                    && $rule->starts_on->toDateString() <= $dateString
                    && (!$rule->ends_on || $rule->ends_on->toDateString() >= $dateString)) {
                    $add($dateString, $rule->child_id);
                }
            }

            foreach ($classRows as $row) {
                if ($row->date_from <= $dateString && $row->date_to >= $dateString) {
                    $add($dateString, $row->child_id);
                }
            }
        }

        return $result;
    }

    private function breaksByDate(int $institutionId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $result = collect();
        $breaks = SchoolBreak::query()
            ->where('institution_id', $institutionId)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get();

        foreach ($breaks as $break) {
            $start = CarbonImmutable::parse(max($from->toDateString(), $break->start_date->toDateString()));
            $end = CarbonImmutable::parse(min($to->toDateString(), $break->end_date->toDateString()));

            for ($date = $start; $date->lte($end); $date = $date->addDay()) {
                $result->put($date->toDateString(), $break);
            }
        }

        return $result;
    }
}
