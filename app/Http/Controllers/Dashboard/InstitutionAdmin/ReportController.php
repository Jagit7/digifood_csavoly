<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\EmployeeMealCancellation;
use App\Models\InstitutionEmployee;
use App\Models\MealCancellation;
use App\Models\MealCheckIn;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $institution = $this->currentAdminInstitution();

        $selectedMonth = $this->resolveMonth($request->query('month'));
        $previousMonth = $selectedMonth->subMonth();

        $employeeEaterType = (new InstitutionEmployee)->getMorphClass();

        // A "dolgozó" adagot mindig az eater_type='institution_employee' rekordokból szűrjük, a "gyermek"
        // adagot pedig összesen mínusz dolgozóként számoljuk. Ez szándékos: régebbi/hiányos rekordoknál
        // (pl. az eater_type oszlop bevezetése előtti adatoknál) nem biztos, hogy eater_type='child' van
        // kitöltve, de child_id/összesített darabszám mindig helyes — egy explicit 'child' szűrés ezeket
        // tévesen kihagyná az összesítésből.
        $monthlyMealsCount = $this->mealCheckInBaseQuery($institution->id, $selectedMonth)->count();
        $monthlyEmployeeMealsCount = $this->mealCheckInBaseQuery($institution->id, $selectedMonth, $employeeEaterType)->count();
        $monthlyChildMealsCount = $monthlyMealsCount - $monthlyEmployeeMealsCount;

        $previousMonthlyMealsCount = $this->mealCheckInBaseQuery($institution->id, $previousMonth)->count();

        $monthlyChildCancellationsCount = $this->mealCancellationBaseQuery($institution->id, $selectedMonth)->count();
        $monthlyEmployeeCancellationsCount = $this->employeeMealCancellationBaseQuery($institution->id, $selectedMonth)->count();
        $monthlyCancellationsCount = $monthlyChildCancellationsCount + $monthlyEmployeeCancellationsCount;

        $previousMonthlyChildCancellationsCount = $this->mealCancellationBaseQuery($institution->id, $previousMonth)->count();
        $previousMonthlyEmployeeCancellationsCount = $this->employeeMealCancellationBaseQuery($institution->id, $previousMonth)->count();
        $previousMonthlyCancellationsCount = $previousMonthlyChildCancellationsCount + $previousMonthlyEmployeeCancellationsCount;

        $dailyTotalMeals = $this->dailyCounts($this->mealCheckInBaseQuery($institution->id, $selectedMonth), 'meals_count');
        $dailyEmployeeMeals = $this->dailyCounts($this->mealCheckInBaseQuery($institution->id, $selectedMonth, $employeeEaterType), 'meals_count');
        $dailyChildCancellations = $this->dailyCounts($this->mealCancellationBaseQuery($institution->id, $selectedMonth), 'cancellations_count');
        $dailyEmployeeCancellations = $this->dailyCounts($this->employeeMealCancellationBaseQuery($institution->id, $selectedMonth), 'cancellations_count');

        $dailyRows = $dailyTotalMeals->keys()
            ->merge($dailyChildCancellations->keys())
            ->merge($dailyEmployeeCancellations->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $date) use ($dailyTotalMeals, $dailyEmployeeMeals, $dailyChildCancellations, $dailyEmployeeCancellations) {
                $day = CarbonImmutable::parse($date, config('digifood.business_timezone', config('app.timezone')));
                $totalMeals = (int) ($dailyTotalMeals->get($date) ?? 0);
                $employeeMeals = (int) ($dailyEmployeeMeals->get($date) ?? 0);
                $childCancellations = (int) ($dailyChildCancellations->get($date) ?? 0);
                $employeeCancellations = (int) ($dailyEmployeeCancellations->get($date) ?? 0);

                return [
                    'date' => $day,
                    'child_meals' => $totalMeals - $employeeMeals,
                    'employee_meals' => $employeeMeals,
                    'meals' => $totalMeals,
                    'child_cancellations' => $childCancellations,
                    'employee_cancellations' => $employeeCancellations,
                    'cancellations' => $childCancellations + $employeeCancellations,
                ];
            });

        $activeChildrenByGroup = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->selectRaw('group_name, COUNT(*) as active_children_count')
            ->groupBy('group_name')
            ->orderBy('group_name')
            ->pluck('active_children_count', 'group_name');

        $mealCountsByGroup = MealCheckIn::query()
            ->join('children', 'children.id', '=', 'meal_check_ins.child_id')
            ->where('meal_check_ins.institution_id', $institution->id)
            ->where('meal_check_ins.status', MealCheckIn::STATUS_SUCCESS)
            ->whereBetween('meal_check_ins.service_date', [$selectedMonth->toDateString(), $selectedMonth->endOfMonth()->toDateString()])
            ->whereNotNull('children.group_name')
            ->where('children.group_name', '!=', '')
            ->selectRaw('children.group_name, COUNT(*) as meals_count')
            ->groupBy('children.group_name')
            ->pluck('meals_count', 'children.group_name');

        $cancellationCountsByGroup = MealCancellation::query()
            ->join('children', 'children.id', '=', 'meal_cancellations.child_id')
            ->where('meal_cancellations.institution_id', $institution->id)
            ->where('meal_cancellations.status', MealCancellation::STATUS_ACTIVE)
            ->whereBetween('meal_cancellations.service_date', [$selectedMonth->toDateString(), $selectedMonth->endOfMonth()->toDateString()])
            ->whereNotNull('children.group_name')
            ->where('children.group_name', '!=', '')
            ->selectRaw('children.group_name, COUNT(*) as cancellations_count')
            ->groupBy('children.group_name')
            ->pluck('cancellations_count', 'children.group_name');

        $groupRows = $activeChildrenByGroup->keys()
            ->merge($mealCountsByGroup->keys())
            ->merge($cancellationCountsByGroup->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $groupName) use ($activeChildrenByGroup, $mealCountsByGroup, $cancellationCountsByGroup) {
                return [
                    'group_name' => $groupName,
                    'active_children' => (int) ($activeChildrenByGroup[$groupName] ?? 0),
                    'meals' => (int) ($mealCountsByGroup[$groupName] ?? 0),
                    'cancellations' => (int) ($cancellationCountsByGroup[$groupName] ?? 0),
                ];
            });

        $mealTypeBreakdown = $this->mealTypeBreakdown($institution->id, $selectedMonth, $employeeEaterType);

        $activeChildrenCount = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->count();

        $activeEmployeesCount = InstitutionEmployee::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->count();

        $topCancellationDay = $dailyRows
            ->where('cancellations', '>', 0)
            ->sortByDesc('cancellations')
            ->first();

        return view('dashboard.institution_admin.reports.index', [
            'institution' => $institution,
            'selectedMonth' => $selectedMonth,
            'selectedMonthQuery' => $selectedMonth->format('Y-m'),
            'selectedMonthLabel' => $this->formatMonthLabel($selectedMonth),
            'previousMonthQuery' => $previousMonth->format('Y-m'),
            'nextMonthQuery' => $selectedMonth->addMonth()->format('Y-m'),
            'currentMonthQuery' => $this->currentMonth()->format('Y-m'),
            'statCards' => [
                'active_children' => $activeChildrenCount,
                'active_employees' => $activeEmployeesCount,
                'active_groups' => $activeChildrenByGroup->count(),
                'monthly_meals' => $monthlyMealsCount,
                'monthly_child_meals' => $monthlyChildMealsCount,
                'monthly_employee_meals' => $monthlyEmployeeMealsCount,
                'monthly_cancellations' => $monthlyCancellationsCount,
                'monthly_child_cancellations' => $monthlyChildCancellationsCount,
                'monthly_employee_cancellations' => $monthlyEmployeeCancellationsCount,
            ],
            'mealSummary' => [
                'total' => $monthlyMealsCount,
                'child_total' => $monthlyChildMealsCount,
                'employee_total' => $monthlyEmployeeMealsCount,
                'daily_average' => $this->averagePerActiveDay($monthlyMealsCount, $dailyRows->where('meals', '>', 0)->count()),
                'change_percent' => $this->percentageChange($monthlyMealsCount, $previousMonthlyMealsCount),
                'type_breakdown' => $mealTypeBreakdown,
            ],
            'cancellationSummary' => [
                'total' => $monthlyCancellationsCount,
                'child_total' => $monthlyChildCancellationsCount,
                'employee_total' => $monthlyEmployeeCancellationsCount,
                'daily_average' => $this->averagePerActiveDay($monthlyCancellationsCount, $dailyRows->where('cancellations', '>', 0)->count()),
                'change_percent' => $this->percentageChange($monthlyCancellationsCount, $previousMonthlyCancellationsCount),
                'top_day' => $topCancellationDay,
                'ratio' => null,
            ],
            'dailyRows' => $dailyRows,
            'groupRows' => $groupRows,
        ]);
    }

    /**
     * Napi bontású darabszám (dátum => darab) egy adott lekérdezésből.
     */
    private function dailyCounts(Builder $query, string $countAlias): Collection
    {
        return $query
            ->selectRaw("service_date, COUNT(*) as {$countAlias}")
            ->groupBy('service_date')
            ->orderBy('service_date')
            ->get()
            ->keyBy(fn ($item) => $item->service_date->toDateString())
            ->map(fn ($item) => (int) $item->{$countAlias});
    }

    /**
     * Étkezéstípusonkénti bontás, gyermek/dolgozó szerint külön jelölve.
     */
    private function mealTypeBreakdown(int $institutionId, CarbonImmutable $month, string $employeeEaterType): Collection
    {
        $rows = MealCheckIn::query()
            ->leftJoin('institution_meal_types', 'institution_meal_types.id', '=', 'meal_check_ins.institution_meal_type_id')
            ->leftJoin('meal_types', 'meal_types.id', '=', 'institution_meal_types.meal_type_id')
            ->where('meal_check_ins.institution_id', $institutionId)
            ->where('meal_check_ins.status', MealCheckIn::STATUS_SUCCESS)
            ->whereBetween('meal_check_ins.service_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->selectRaw("COALESCE(meal_types.name, 'Ismeretlen') as meal_type_name, meal_check_ins.eater_type as eater_type, COUNT(*) as meals_count")
            ->groupBy('meal_type_name', 'meal_check_ins.eater_type')
            ->get();

        $breakdown = collect();

        foreach ($rows as $row) {
            $entry = $breakdown->get($row->meal_type_name, [
                'meal_type_name' => $row->meal_type_name,
                'child_count' => 0,
                'employee_count' => 0,
                'meals_count' => 0,
            ]);

            $count = (int) $row->meals_count;
            $entry['meals_count'] += $count;

            if ($row->eater_type === $employeeEaterType) {
                $entry['employee_count'] += $count;
            } else {
                $entry['child_count'] += $count;
            }

            $breakdown->put($row->meal_type_name, $entry);
        }

        return $breakdown
            ->values()
            ->sortBy([
                ['meals_count', 'desc'],
                ['meal_type_name', 'asc'],
            ])
            ->values();
    }

    private function mealCheckInBaseQuery(int $institutionId, CarbonImmutable $month, ?string $eaterType = null)
    {
        return MealCheckIn::query()
            ->where('institution_id', $institutionId)
            ->where('status', MealCheckIn::STATUS_SUCCESS)
            ->whereBetween('service_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->when($eaterType !== null, fn ($query) => $query->where('eater_type', $eaterType));
    }

    private function mealCancellationBaseQuery(int $institutionId, CarbonImmutable $month)
    {
        return MealCancellation::query()
            ->where('institution_id', $institutionId)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$month->toDateString(), $month->endOfMonth()->toDateString()]);
    }

    private function employeeMealCancellationBaseQuery(int $institutionId, CarbonImmutable $month)
    {
        return EmployeeMealCancellation::query()
            ->where('institution_id', $institutionId)
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$month->toDateString(), $month->endOfMonth()->toDateString()]);
    }

    private function resolveMonth(?string $value): CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $this->currentMonth();
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value.'-01', config('app.timezone'))->startOfMonth();
        } catch (\Throwable) {
            return $this->currentMonth();
        }
    }

    private function currentMonth(): CarbonImmutable
    {
        return CarbonImmutable::now(config('digifood.business_timezone', config('app.timezone')))->startOfMonth();
    }

    private function formatMonthLabel(CarbonImmutable $month): string
    {
        $months = [
            1 => 'január',
            2 => 'február',
            3 => 'március',
            4 => 'április',
            5 => 'május',
            6 => 'június',
            7 => 'július',
            8 => 'augusztus',
            9 => 'szeptember',
            10 => 'október',
            11 => 'november',
            12 => 'december',
        ];

        return sprintf('%d. %s', $month->year, $months[$month->month]);
    }

    private function percentageChange(int $current, int $previous): int
    {
        if ($previous === 0) {
            return $current === 0 ? 0 : 100;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function averagePerActiveDay(int $total, int $dayCount): float
    {
        if ($total === 0 || $dayCount === 0) {
            return 0;
        }

        return round($total / $dayCount, 1);
    }
}
