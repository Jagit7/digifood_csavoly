<?php

namespace App\Services\Kitchen;

use App\Models\EmployeeMealCancellation;
use App\Models\EmployeeRecurringCancellationRule;
use App\Models\InstitutionEmployee;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\WorkingDay;
use App\Services\HungarianHolidayService;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeDailyMealHeadcountService
{
    public const STATUS_EATING = 'eating';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_ACTIVE_MEAL = 'no_active_meal';

    public const STATUS_NO_SERVICE = 'no_service';

    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly HungarianHolidayService $holidays
    ) {}

    public function forDate(int $institutionId, CarbonInterface|string $date): array
    {
        $day = $this->date($date);
        $dateString = $day->toDateString();
        $employees = $this->activeEmployeesQuery($institutionId)->get();
        $employeeIds = $employees->pluck('id');
        $mealSettings = $this->mealSettingsForEmployees($institutionId, $employeeIds, $dateString);
        $activeEaterIds = $mealSettings->keys()->map(fn ($id) => (int) $id)->flip();
        $cancelledIds = $this->effectiveCancellationIds($institutionId, $dateString, $activeEaterIds);
        $dayMeta = $this->dayMeta($institutionId, $day);

        $rows = $employees->map(function (InstitutionEmployee $employee) use ($mealSettings, $cancelledIds, $dayMeta) {
            $mealSetting = $mealSettings->get($employee->id);
            $status = $this->statusForRow($mealSetting !== null, $cancelledIds->has($employee->id), $dayMeta['is_service_day']);

            return [
                'employee' => $employee,
                'meal_setting' => $mealSetting,
                'status' => $status,
                'cancelled' => $status === self::STATUS_CANCELLED,
                'is_dietary' => $employee->dietaryRestrictions->isNotEmpty(),
                'menu_item' => null,
                'menu_choice_record' => null,
                'effective_menu_category' => null,
            ];
        });

        return [
            'date' => $day,
            'meta' => $dayMeta,
            'stats' => [
                'daily_eaters' => $rows->where('status', self::STATUS_EATING)->count(),
                'cancelled_meals' => $rows->where('status', self::STATUS_CANCELLED)->count(),
                'active_eaters' => $mealSettings->count(),
                'missing_employees' => $rows->where('status', self::STATUS_NO_ACTIVE_MEAL)->count(),
                'dietary_eaters' => $this->dietaryEaterCount($rows),
            ],
            'rows' => $rows,
        ];
    }

    public function activeEmployeesQuery(int $institutionId): Builder
    {
        return InstitutionEmployee::query()
            ->where('institution_id', $institutionId)
            ->where('active', true)
            ->orderBy('name')
            ->with([
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ]);
    }

    public function mealSettingsForEmployees(int $institutionId, Collection $employeeIds, string $date): Collection
    {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage',
                'mealTypes.mealType',
            ])
            ->where('institution_id', $institutionId)
            ->where('eater_type', (new InstitutionEmployee)->getMorphClass())
            ->whereIn('eater_id', $employeeIds)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('eater_id')
            ->map(fn (Collection $settings) => $settings->first());
    }

    /**
     * A DailyMealHeadcountService::mealSettingsForChildrenInPeriod() dolgozói
     * megfelelője - egy teljes időszakra (nem csak egyetlen napra) tölti be a
     * dolgozók étkezési beállításait, hogy a naptár-nézet (hét/hónap) ne
     * futtasson napi bontásban külön lekérdezést. Additív metódus, a
     * meglévő forDate()/mealSettingsForEmployees() logikát nem érinti.
     */
    public function mealSettingsForEmployeesInPeriod(
        int $institutionId,
        Collection $employeeIds,
        string $from,
        string $to
    ): Collection {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage',
                'mealTypes.mealType',
            ])
            ->where('institution_id', $institutionId)
            ->where('eater_type', (new InstitutionEmployee)->getMorphClass())
            ->whereIn('eater_id', $employeeIds)
            ->whereDate('valid_from', '<=', $to)
            ->where(function ($query) use ($from) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $from);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('eater_id');
    }

    /**
     * A DailyMealHeadcountService::mealSettingForDate() dolgozói megfelelője.
     */
    public function mealSettingForDate(Collection $settings, string $date): ?StudentMealSetting
    {
        return $settings->first(function (StudentMealSetting $setting) use ($date) {
            return $setting->valid_from->toDateString() <= $date
                && ($setting->valid_to === null || $setting->valid_to->toDateString() >= $date);
        });
    }

    /**
     * A DailyMealHeadcountService (gyermek) cancellationsByDate() dolgozói
     * megfelelője - egyéni és ismétlődő lemondásokat gyűjt egy időszakra.
     * A dolgozóknak nincs osztály/csoport szintű lemondása (nincs
     * "class group" fogalom náluk), ezért csak ez a két forrás létezik.
     */
    public function cancellationsByDate(
        int $institutionId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        Collection $activeIds
    ): Collection {
        $result = collect();
        $add = function (string $date, int $employeeId) use ($result, $activeIds): void {
            if (! $activeIds->has($employeeId)) {
                return;
            }

            if (! $result->has($date)) {
                $result->put($date, collect());
            }

            $result->get($date)->put($employeeId, true);
        };

        EmployeeMealCancellation::query()
            ->where('institution_id', $institutionId)
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$from->toDateString(), $to->toDateString()])
            ->get(['institution_employee_id', 'service_date'])
            ->each(fn (EmployeeMealCancellation $item) => $add($item->service_date->toDateString(), $item->institution_employee_id));

        $rules = EmployeeRecurringCancellationRule::query()
            ->where('institution_id', $institutionId)
            ->whereIn('status', [
                EmployeeRecurringCancellationRule::STATUS_ACTIVE,
                EmployeeRecurringCancellationRule::STATUS_ENDED,
            ])
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString()))
            ->get();

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $dateString = $date->toDateString();

            foreach ($rules as $rule) {
                if ($rule->weekday === $date->dayOfWeekIso
                    && $rule->starts_on->toDateString() <= $dateString
                    && (! $rule->ends_on || $rule->ends_on->toDateString() >= $dateString)) {
                    $add($dateString, $rule->institution_employee_id);
                }
            }
        }

        return $result;
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

        return [
            'is_service_day' => $this->calendar->isServiceDay($institutionId, $date),
            'holiday' => $publicHoliday,
            'school_break' => $schoolBreak,
            'working_day' => $workingDay,
        ];
    }

    private function effectiveCancellationIds(int $institutionId, string $date, Collection $activeEaterIds): Collection
    {
        if ($activeEaterIds->isEmpty() || ! $this->calendar->isServiceDay($institutionId, $date)) {
            return collect();
        }

        $weekday = CarbonImmutable::parse($date)->dayOfWeekIso;

        $individualIds = EmployeeMealCancellation::query()
            ->where('institution_id', $institutionId)
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereDate('service_date', $date)
            ->pluck('institution_employee_id');

        $recurringIds = EmployeeRecurringCancellationRule::query()
            ->where('institution_id', $institutionId)
            ->whereIn('status', [
                EmployeeRecurringCancellationRule::STATUS_ACTIVE,
                EmployeeRecurringCancellationRule::STATUS_ENDED,
            ])
            ->where('weekday', $weekday)
            ->whereDate('starts_on', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date);
            })
            ->pluck('institution_employee_id');

        $ids = $individualIds->merge($recurringIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn ($id) => $activeEaterIds->has($id))
            ->values();

        return $ids->flip();
    }

    private function statusForRow(bool $hasMealSetting, bool $isCancelled, bool $isServiceDay): string
    {
        if (! $hasMealSetting) {
            return self::STATUS_NO_ACTIVE_MEAL;
        }

        if (! $isServiceDay) {
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
