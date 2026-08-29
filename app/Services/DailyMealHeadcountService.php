<?php

namespace App\Services;

use App\Models\AbMenuItem;
use App\Models\AbMenuPlan;
use App\Models\Child;
use App\Models\ClassCancellation;
use App\Models\MealCancellation;
use App\Models\RecurringCancellationRule;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\WorkingDay;
use App\Services\Meals\AbMenuSelectionService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyMealHeadcountService
{
    public const STATUS_EATING = 'eating';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_ACTIVE_MEAL = 'no_active_meal';
    public const STATUS_NO_SERVICE = 'no_service';

    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly HungarianHolidayService $holidays,
        private readonly AbMenuSelectionService $abMenuSelection
    ) {
    }

    public function forDate(int $institutionId, CarbonInterface|string $date): array
    {
        $day = $this->date($date);
        $dateString = $day->toDateString();
        $children = $this->activeChildrenQuery($institutionId)->get();
        $childIds = $children->pluck('id');
        $mealSettings = $this->mealSettingsForChildren($institutionId, $childIds, $dateString);
        $activeEaterIds = $mealSettings->keys()->map(fn ($id) => (int) $id)->flip();
        $cancelledIds = $this->effectiveCancellationIds($institutionId, $dateString, $activeEaterIds);
        $dayMeta = $this->dayMeta($institutionId, $day);
        $menuItem = $this->abMenuSelection->resolveMenuItemForDate($institutionId, $dateString);
        $choiceMap = $menuItem
            ? $this->abMenuSelection->choiceMapForChildrenAndItems($institutionId, $activeEaterIds->keys()->values(), collect([$menuItem]))
            : collect();

        $rows = $children->map(function (Child $child) use ($mealSettings, $cancelledIds, $dayMeta, $menuItem, $choiceMap) {
            $mealSetting = $mealSettings->get($child->id);
            $status = $this->statusForRow($mealSetting !== null, $cancelledIds->has($child->id), $dayMeta['is_service_day']);
            $explicitChoice = $menuItem
                ? $this->abMenuSelection->explicitChoiceRecord($choiceMap, $child->id, $menuItem)
                : null;
            $effectiveMenuCategory = $status === self::STATUS_EATING
                ? $this->abMenuSelection->effectiveCategoryForChildAndItem($child, $menuItem, $explicitChoice)
                : null;

            return [
                'child' => $child,
                'meal_setting' => $mealSetting,
                'status' => $status,
                'is_dietary' => $child->dietaryRestrictions->isNotEmpty(),
                'menu_item' => $menuItem,
                'menu_choice_record' => $explicitChoice,
                'effective_menu_category' => $effectiveMenuCategory,
            ];
        });
        $eatingChildren = $rows
            ->where('status', self::STATUS_EATING)
            ->pluck('child')
            ->filter(fn ($child) => $child instanceof Child)
            ->values();
        $menuBreakdown = $this->abMenuSelection->countsForChildrenAndItem($eatingChildren, $menuItem, $choiceMap);

        return [
            'date' => $day,
            'meta' => $dayMeta,
            'stats' => [
                'daily_eaters' => $rows->where('status', self::STATUS_EATING)->count(),
                'cancelled_meals' => $rows->where('status', self::STATUS_CANCELLED)->count(),
                'active_eaters' => $mealSettings->count(),
                'missing_children' => $rows->where('status', self::STATUS_NO_ACTIVE_MEAL)->count(),
                'dietary_eaters' => $this->dietaryEaterCount($rows),
                'has_ab_menu' => $menuItem !== null,
                'menu_a_count' => $menuBreakdown['menu_a_count'],
                'menu_b_count' => $menuBreakdown['menu_b_count'],
                'dietary_count' => $menuBreakdown['dietary_count'],
            ],
            'rows' => $rows,
        ];
    }

    public function activeChildrenQuery(int $institutionId): Builder
    {
        return Child::query()
            ->where('institution_id', $institutionId)
            ->where('active', true)
            ->orderByRaw('CASE WHEN group_name IS NULL OR group_name = ? THEN 1 ELSE 0 END', [''])
            ->orderBy('group_name')
            ->orderBy('name')
            ->with([
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ]);
    }

    public function mealSettingsForChildren(int $institutionId, Collection $childIds, string $date): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage',
                'mealTypes.mealType',
            ])
            ->where('institution_id', $institutionId)
            ->whereIn('student_id', $childIds)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }

    public function mealSettingsForChildrenInPeriod(
        int $institutionId,
        Collection $childIds,
        string $from,
        string $to
    ): Collection {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage',
                'mealTypes.mealType',
            ])
            ->where('institution_id', $institutionId)
            ->whereIn('student_id', $childIds)
            ->whereDate('valid_from', '<=', $to)
            ->where(function ($query) use ($from) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $from);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id');
    }

    public function mealSettingForDate(Collection $settings, string $date): ?StudentMealSetting
    {
        return $settings->first(function (StudentMealSetting $setting) use ($date) {
            return $setting->valid_from->toDateString() <= $date
                && ($setting->valid_to === null || $setting->valid_to->toDateString() >= $date);
        });
    }

    public function dietaryRows(Collection $rows): Collection
    {
        return $rows
            ->where('status', self::STATUS_EATING)
            ->filter(fn (array $row) => (bool) ($row['is_dietary'] ?? false))
            ->values();
    }

    public function dietaryEaterCount(Collection $rows): int
    {
        return $this->dietaryRows($rows)->count();
    }

    public function dietaryMenuItem(int $institutionId, CarbonInterface|string $date): ?AbMenuItem
    {
        $day = $this->date($date)->toDateString();

        return AbMenuItem::query()
            ->select('ab_menu_items.*')
            ->join('ab_menu_plans', 'ab_menu_plans.id', '=', 'ab_menu_items.ab_menu_plan_id')
            ->where('ab_menu_plans.institution_id', $institutionId)
            ->where('ab_menu_plans.active', true)
            ->whereDate('ab_menu_plans.valid_from', '<=', $day)
            ->whereDate('ab_menu_plans.valid_to', '>=', $day)
            ->whereDate('ab_menu_items.menu_date', $day)
            ->orderByDesc('ab_menu_plans.published_at')
            ->orderByDesc('ab_menu_plans.id')
            ->first();
    }

    public function dayMeta(int $institutionId, CarbonImmutable $date): array
    {
        $dateString = $date->toDateString();
        $publicHoliday = $this->holidays->between($date, $date)->get($dateString);
        $schoolBreak = SchoolBreak::query()
            ->where('institution_id', $institutionId)
            ->whereDate('start_date', '<=', $dateString)
            ->whereDate('end_date', '>=', $dateString)
            ->first();
        $workingDay = WorkingDay::query()
            ->where('institution_id', $institutionId)
            ->whereDate('date', $dateString)
            ->first();
        $isServiceDay = $this->calendar->isServiceDay($institutionId, $date);

        return [
            'is_service_day' => $isServiceDay,
            'holiday' => $publicHoliday,
            'school_break' => $schoolBreak,
            'working_day' => $workingDay,
        ];
    }

    private function effectiveCancellationIds(int $institutionId, string $date, Collection $activeEaterIds): Collection
    {
        if ($activeEaterIds->isEmpty() || !$this->calendar->isServiceDay($institutionId, $date)) {
            return collect();
        }

        $weekday = CarbonImmutable::parse($date, $this->calendar->timezone())->dayOfWeekIso;
        $ids = DB::query()
            ->fromSub($this->effectiveCancellationUnion($institutionId, $date, $weekday), 'effective_cancellations')
            ->distinct()
            ->pluck('child_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $activeEaterIds->has($id))
            ->values();

        return $ids->flip();
    }

    private function effectiveCancellationUnion(int $institutionId, string $date, int $weekday)
    {
        $individual = MealCancellation::query()
            ->select('child_id')
            ->where('institution_id', $institutionId)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereDate('service_date', $date);

        $recurring = RecurringCancellationRule::query()
            ->select('child_id')
            ->where('institution_id', $institutionId)
            ->whereIn('status', [
                RecurringCancellationRule::STATUS_ACTIVE,
                RecurringCancellationRule::STATUS_ENDED,
            ])
            ->where('weekday', $weekday)
            ->whereDate('starts_on', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $date);
            });

        $classLevel = ClassCancellation::query()
            ->join('class_group_memberships', 'class_group_memberships.class_group_id', '=', 'class_cancellations.class_group_id')
            ->join('class_groups', 'class_groups.id', '=', 'class_cancellations.class_group_id')
            ->select('class_group_memberships.child_id')
            ->where('class_cancellations.institution_id', $institutionId)
            ->where('class_groups.institution_id', $institutionId)
            ->where('class_group_memberships.status', 'active')
            ->whereDate('class_cancellations.date_from', '<=', $date)
            ->whereDate('class_cancellations.date_to', '>=', $date);

        return $individual->union($recurring)->union($classLevel);
    }

    private function statusForRow(bool $hasMealSetting, bool $isCancelled, bool $isServiceDay): string
    {
        if (!$hasMealSetting) {
            return self::STATUS_NO_ACTIVE_MEAL;
        }

        if (!$isServiceDay) {
            return self::STATUS_NO_SERVICE;
        }

        if ($isCancelled) {
            return self::STATUS_CANCELLED;
        }

        return self::STATUS_EATING;
    }

    private function date(CarbonInterface|string $date): CarbonImmutable
    {
        if ($date instanceof CarbonInterface) {
            return CarbonImmutable::instance($date)
                ->setTimezone($this->calendar->timezone())
                ->startOfDay();
        }

        return CarbonImmutable::parse($date, $this->calendar->timezone())->startOfDay();
    }
}
