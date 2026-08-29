<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\DietaryRestriction;
use App\Models\InstitutionMealPackage;
use App\Models\StudentMealSetting;
use App\Services\DailyMealHeadcountService;
use App\Services\InstitutionCalendarService;
use App\Services\Kitchen\EmployeeDailyMealHeadcountService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DailyOperationController extends Controller
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly DailyMealHeadcountService $headcount,
        private readonly EmployeeDailyMealHeadcountService $employeeHeadcount
    ) {}

    public function todayCounts(Request $request): View
    {
        $institution = $this->institution();
        $validated = $this->validatedFilters($request);
        $selectedDate = $validated['date'] ?? $this->calendar->now()->toDateString();
        $dailyData = $this->headcount->forDate($institution->id, $selectedDate);
        $employeeData = $this->employeeHeadcount->forDate($institution->id, $selectedDate);
        $date = $dailyData['date'];
        $today = $this->calendar->now()->startOfDay();
        $rows = $this->filteredRows($dailyData['rows'], $validated);
        $paginatedRows = $this->paginateRows($rows, $request);

        $headerButtons = [[
            'url' => route('dashboard.institution.daily.dietary-children', $this->listQuery($validated, $date)),
            'class' => 'btn btn-outline-success',
            'icon' => 'fa-solid fa-notes-medical',
            'text' => 'Diétás lista',
        ]];

        if ($institution->type === 'ovoda') {
            $headerButtons[] = [
                'url' => route('dashboard.institution.daily.today-counts.attendance-sheet', $this->printRouteParameters($validated, $date)),
                'class' => 'btn btn-outline-primary',
                'icon' => 'fa-solid fa-print',
                'text' => 'Jelenléti ív nyomtatása',
                'target' => '_blank',
                'rel' => 'noopener',
            ];
        }

        return view('dashboard.institution_admin.daily.today-counts', [
            'institution' => $institution,
            'isKindergarten' => $institution->type === 'ovoda',
            'date' => $date,
            'isTodaySelected' => $date->isSameDay($today),
            'previousDate' => $date->subDay()->toDateString(),
            'todayDate' => $today->toDateString(),
            'nextDate' => $date->addDay()->toDateString(),
            'stats' => $this->combinedTodayCountsStats($dailyData['stats'], $employeeData['stats']),
            'dayMeta' => $dailyData['meta'],
            'groups' => $this->groupOptions($dailyData['rows']),
            'rows' => $paginatedRows,
            'defaultPackage' => $this->defaultPackage($institution->id),
            'statusOptions' => $this->statusOptions(),
            'dietaryFilterOptions' => $this->dietaryFilterOptions(),
            'modeLabels' => StudentMealSetting::MODE_LABELS,
            'allergenType' => DietaryRestriction::TYPE_ALLERGEN,
            'printRouteParameters' => $this->printRouteParameters($validated, $date),
            'navigationQuery' => $this->navigationQuery($validated),
            'headerButtons' => $headerButtons,
        ]);
    }

    public function printAttendanceSheet(Request $request): View
    {
        $institution = $this->institution();
        abort_unless($institution->type === 'ovoda', 404);

        $validated = $this->validatedFilters($request);
        $selectedDate = $validated['date'] ?? $this->calendar->now()->toDateString();
        $dailyData = $this->headcount->forDate($institution->id, $selectedDate);
        $rows = $this->filteredRows($dailyData['rows'], $validated);
        $groupedRows = $rows
            ->groupBy(fn (array $row) => $row['child']->group_name ?: 'Csoport nélkül')
            ->sortKeys();

        return view('dashboard.institution_admin.daily.attendance-sheet-print', [
            'institution' => $institution,
            'date' => CarbonImmutable::instance($dailyData['date'])->locale('hu'),
            'groupedRows' => $groupedRows,
        ]);
    }

    public function printTodayCounts(Request $request): View
    {
        $institution = $this->institution();
        $validated = $this->validatedFilters($request);
        $selectedDate = $validated['date'] ?? $this->calendar->now()->toDateString();
        $dailyData = $this->headcount->forDate($institution->id, $selectedDate);
        $rows = $this->filteredRows($dailyData['rows'], $validated);
        $selectedGroupName = filled($validated['group_name'] ?? null) ? (string) $validated['group_name'] : null;

        return view('dashboard.institution_admin.daily.today-counts-print', [
            'institution' => $institution,
            'date' => CarbonImmutable::instance($dailyData['date'])->locale('hu'),
            'rows' => $rows,
            'groupedRows' => $this->groupedRowsForPrint($rows, $selectedGroupName),
            'selectedGroupName' => $selectedGroupName,
            'isKindergarten' => $institution->type === 'ovoda',
            'statusLabels' => $this->statusLabels(),
            'modeLabels' => StudentMealSetting::MODE_LABELS,
            'allergenType' => DietaryRestriction::TYPE_ALLERGEN,
            'defaultPackage' => $this->defaultPackage($institution->id),
        ]);
    }

    public function dietaryChildren(Request $request): View
    {
        $institution = $this->institution();
        $validated = $this->validatedFilters($request);
        $selectedDate = $validated['date'] ?? $this->calendar->now()->toDateString();
        $dailyData = $this->headcount->forDate($institution->id, $selectedDate);
        $employeeData = $this->employeeHeadcount->forDate($institution->id, $selectedDate);
        $date = $dailyData['date'];
        $today = $this->calendar->now()->startOfDay();
        $dietaryRows = $this->buildDietaryRows(
            $institution->id,
            $date,
            $this->filteredRows($dailyData['rows'], $validated)
        )->concat($this->buildDietaryEmployeeRows(
            $this->filteredEmployeeRows($employeeData['rows'], $validated)
        ))->values();
        $dietaryStats = $this->dietaryStats($institution->id, $dailyData['rows'], $employeeData['rows']);

        return view('dashboard.institution_admin.daily.dietary-children', [
            'institution' => $institution,
            'date' => $date,
            'isTodaySelected' => $date->isSameDay($today),
            'previousDate' => $date->subDay()->toDateString(),
            'todayDate' => $today->toDateString(),
            'nextDate' => $date->addDay()->toDateString(),
            'groups' => $this->groupOptions($dailyData['rows']),
            'rows' => $this->paginateRows($dietaryRows, $request, 20),
            'totalDietaryEaters' => $dietaryRows->count(),
            'filterQuery' => $this->listQuery($validated, $date),
            'navigationQuery' => $this->listQuery($validated, $date, false),
            'dietaryStats' => $dietaryStats,
        ]);
    }

    public function printDietaryChildren(Request $request): View
    {
        $institution = $this->institution();
        $validated = $this->validatedFilters($request);
        $selectedDate = $validated['date'] ?? $this->calendar->now()->toDateString();
        $dailyData = $this->headcount->forDate($institution->id, $selectedDate);
        $employeeData = $this->employeeHeadcount->forDate($institution->id, $selectedDate);
        $dietaryChildRows = $this->buildDietaryRows(
            $institution->id,
            $dailyData['date'],
            $this->filteredRows($dailyData['rows'], $validated)
        );
        $dietaryEmployeeRows = $this->buildDietaryEmployeeRows(
            $this->filteredEmployeeRows($employeeData['rows'], $validated)
        );
        $groupedRows = $dietaryChildRows
            ->groupBy(fn (array $row) => $row['display_group'] ?: 'Csoport nélkül')
            ->sortKeys();

        if ($dietaryEmployeeRows->isNotEmpty()) {
            $groupedRows->put('Dolgozók', $dietaryEmployeeRows->values());
        }

        return view('dashboard.institution_admin.daily.dietary-children-print', [
            'institution' => $institution,
            'date' => CarbonImmutable::instance($dailyData['date'])->locale('hu'),
            'groupedRows' => $groupedRows,
        ]);
    }

    public function exportDietaryChildren(Request $request)
    {
        $institution = $this->institution();
        $validated = $this->validatedFilters($request);
        $selectedDate = $validated['date'] ?? $this->calendar->now()->toDateString();
        $dailyData = $this->headcount->forDate($institution->id, $selectedDate);
        $employeeData = $this->employeeHeadcount->forDate($institution->id, $selectedDate);
        $dietaryRows = $this->buildDietaryRows(
            $institution->id,
            $dailyData['date'],
            $this->filteredRows($dailyData['rows'], $validated)
        )->concat($this->buildDietaryEmployeeRows(
            $this->filteredEmployeeRows($employeeData['rows'], $validated)
        ))->values();

        $content = "\xEF\xBB\xBF";
        $content .= $this->csvLine(['Dátum', 'Név', 'Típus', 'Osztály/csoport', 'Menücsomag', 'Diéta', 'Napi menü', 'Megjegyzés']);

        foreach ($dietaryRows as $row) {
            $content .= $this->csvLine([
                $dailyData['date']->toDateString(),
                $row['display_name'],
                $row['type'] === 'employee' ? 'Dolgozó' : 'Gyermek',
                $row['display_group'] ?: '',
                $row['menu_package'],
                $row['diet_names']->implode(', '),
                $row['daily_menu'] ?? '',
                $row['notes']->implode(' | '),
            ]);
        }

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="dietas-etkezok-'.$dailyData['date']->toDateString().'.csv"',
        ]);
    }

    public function cancellations()
    {
        abort(501, 'A napi működés modul ezen része még nincs implementálva ebben a checkoutban.');
    }

    private function institution()
    {
        $institution = $this->currentAdminInstitution();
        abort_if(! $institution, 403);

        return $institution;
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
            'group_name' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'dietary_filter' => ['nullable', 'string'],
        ]);
    }

    private function filteredRows(Collection $rows, array $validated): Collection
    {
        $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));
        $groupName = trim((string) ($validated['group_name'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $dietaryFilter = (string) ($validated['dietary_filter'] ?? '');

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search) {
                $child = $row['child'];

                return str_contains(mb_strtolower($child->name), $search)
                    || str_contains(mb_strtolower((string) $child->educational_identifier), $search);
            });
        }

        if ($groupName !== '') {
            $rows = $rows->filter(fn (array $row) => (string) $row['child']->group_name === $groupName);
        }

        if (in_array($status, $this->statusOptions(), true)) {
            $rows = $rows->where('status', $status);
        }

        if ($dietaryFilter === 'dietary') {
            $rows = $rows->filter(fn (array $row) => (bool) ($row['is_dietary'] ?? false));
        }

        if ($dietaryFilter === 'non_dietary') {
            $rows = $rows->filter(fn (array $row) => ! (bool) ($row['is_dietary'] ?? false));
        }

        return $rows->values();
    }

    /**
     * A filteredRows() dolgozói megfelelője. A dolgozóknak nincs
     * osztálya/csoportja, ezért ha a szűrésben osztály/csoport van
     * kiválasztva, a dolgozói sorok nem jelennek meg (az a szűrő
     * kizárólag a gyermekekre értelmezett).
     */
    private function filteredEmployeeRows(Collection $rows, array $validated): Collection
    {
        $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));
        $groupName = trim((string) ($validated['group_name'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $dietaryFilter = (string) ($validated['dietary_filter'] ?? '');

        if ($groupName !== '') {
            return collect();
        }

        if ($search !== '') {
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['employee']->name), $search));
        }

        if (in_array($status, $this->statusOptions(), true)) {
            $rows = $rows->where('status', $status);
        }

        if ($dietaryFilter === 'dietary') {
            $rows = $rows->filter(fn (array $row) => (bool) ($row['is_dietary'] ?? false));
        }

        if ($dietaryFilter === 'non_dietary') {
            $rows = $rows->filter(fn (array $row) => ! (bool) ($row['is_dietary'] ?? false));
        }

        return $rows->values();
    }

    private function buildDietaryRows(int $institutionId, CarbonImmutable $date, Collection $rows): Collection
    {
        $dailyMenuItem = $this->headcount->dietaryMenuItem($institutionId, $date);

        return $this->headcount->dietaryRows($rows)
            ->map(function (array $row) use ($dailyMenuItem) {
                $child = $row['child'];
                $mealSetting = $row['meal_setting'];
                $notes = collect([$mealSetting?->note, $dailyMenuItem?->note])
                    ->filter(fn ($value) => filled($value))
                    ->values();

                return $row + [
                    'type' => 'child',
                    'display_name' => $child->name,
                    'display_identifier' => $child->educational_identifier,
                    'display_group' => $child->group_name,
                    'diet_names' => $child->dietaryRestrictions->pluck('name')->values(),
                    'menu_package' => $this->mealPackageLabel($mealSetting),
                    'daily_menu' => filled($dailyMenuItem?->menu_dietary) ? $dailyMenuItem->menu_dietary : null,
                    'notes' => $notes,
                ];
            })
            ->values();
    }

    /**
     * A buildDietaryRows() dolgozói megfelelője. A dolgozóknak nincs napi
     * AB-menü választásuk, ezért a 'daily_menu' náluk mindig null.
     */
    private function buildDietaryEmployeeRows(Collection $rows): Collection
    {
        return $this->employeeHeadcount->dietaryRows($rows)
            ->map(function (array $row) {
                $employee = $row['employee'];
                $mealSetting = $row['meal_setting'];
                $notes = collect([$mealSetting?->note])
                    ->filter(fn ($value) => filled($value))
                    ->values();

                return $row + [
                    'type' => 'employee',
                    'display_name' => $employee->name,
                    'display_identifier' => null,
                    'display_group' => null,
                    'diet_names' => $employee->dietaryRestrictions->pluck('name')->values(),
                    'menu_package' => $this->mealPackageLabel($mealSetting),
                    'daily_menu' => null,
                    'notes' => $notes,
                ];
            })
            ->values();
    }

    private function mealPackageLabel(?StudentMealSetting $mealSetting): string
    {
        if (! $mealSetting) {
            return '—';
        }

        if ($mealSetting->mode === StudentMealSetting::MODE_INSTITUTION_DEFAULT) {
            return StudentMealSetting::MODE_LABELS[$mealSetting->mode] ?? $mealSetting->mode;
        }

        if ($mealSetting->mode === StudentMealSetting::MODE_PACKAGE) {
            return $mealSetting->mealPackage?->name ?? '—';
        }

        return $mealSetting->mealTypes->map(fn ($mealType) => $mealType->mealType->name)->implode(', ') ?: '—';
    }

    private function paginateRows(Collection $rows, Request $request, int $perPage = 15): LengthAwarePaginator
    {
        $totalRows = $rows->count();
        $lastPage = max((int) ceil($totalRows / $perPage), 1);
        $currentPage = min(max($request->integer('page', 1), 1), $lastPage);

        return new LengthAwarePaginator(
            $rows->forPage($currentPage, $perPage)->values(),
            $totalRows,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function groupOptions(Collection $rows): Collection
    {
        return $rows->pluck('child.group_name')
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->values();
    }

    private function defaultPackage(int $institutionId): ?InstitutionMealPackage
    {
        return InstitutionMealPackage::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->first();
    }

    private function statusOptions(): array
    {
        return [
            DailyMealHeadcountService::STATUS_EATING,
            DailyMealHeadcountService::STATUS_CANCELLED,
            DailyMealHeadcountService::STATUS_NO_ACTIVE_MEAL,
            DailyMealHeadcountService::STATUS_NO_SERVICE,
        ];
    }

    private function dietaryFilterOptions(): array
    {
        return [
            '' => 'Minden gyermek',
            'dietary' => 'Csak diétás étkezők',
            'non_dietary' => 'Nem diétás étkezők',
        ];
    }

    private function statusLabels(): array
    {
        return [
            DailyMealHeadcountService::STATUS_EATING => 'Étkezik',
            DailyMealHeadcountService::STATUS_CANCELLED => 'Lemondva',
            DailyMealHeadcountService::STATUS_NO_ACTIVE_MEAL => 'Kimarad',
            DailyMealHeadcountService::STATUS_NO_SERVICE => 'Nincs étkeztetés',
        ];
    }

    private function printRouteParameters(array $validated, CarbonImmutable $date): array
    {
        return array_filter([
            'date' => $date->toDateString(),
            'search' => $validated['search'] ?? null,
            'group_name' => $validated['group_name'] ?? null,
            'status' => $validated['status'] ?? null,
            'dietary_filter' => $validated['dietary_filter'] ?? null,
        ], fn ($value) => filled($value));
    }

    private function navigationQuery(array $validated): array
    {
        return array_filter([
            'search' => $validated['search'] ?? null,
            'group_name' => $validated['group_name'] ?? null,
            'status' => $validated['status'] ?? null,
            'dietary_filter' => $validated['dietary_filter'] ?? null,
        ], fn ($value) => filled($value));
    }

    private function listQuery(array $validated, CarbonImmutable $date, bool $includeDate = true): array
    {
        return array_filter([
            'date' => $includeDate ? $date->toDateString() : null,
            'search' => $validated['search'] ?? null,
            'group_name' => $validated['group_name'] ?? null,
        ], fn ($value) => filled($value));
    }

    private function groupedRowsForPrint(Collection $rows, ?string $selectedGroupName): Collection
    {
        if ($selectedGroupName !== null) {
            return collect([
                $selectedGroupName => $rows->values(),
            ]);
        }

        return $rows
            ->groupBy(fn (array $row) => $row['child']->group_name ?: 'Csoport nélkül')
            ->sortKeys();
    }

    private function dietaryStats(int $institutionId, Collection $childRows, Collection $employeeRows): array
    {
        $institutionDietaryChildren = $this->headcount->activeChildrenQuery($institutionId)
            ->whereHas('dietaryRestrictions', fn ($query) => $query->where('active', true))
            ->count();
        $institutionDietaryEmployees = $this->employeeHeadcount->activeEmployeesQuery($institutionId)
            ->whereHas('dietaryRestrictions', fn ($query) => $query->where('active', true))
            ->count();

        $dailyDietaryChildRows = $this->headcount->dietaryRows($childRows);
        $dailyDietaryEmployeeRows = $this->employeeHeadcount->dietaryRows($employeeRows);

        $cancelledDietaryChildMeals = $childRows
            ->filter(fn (array $row) => ($row['status'] ?? null) === DailyMealHeadcountService::STATUS_CANCELLED)
            ->filter(fn (array $row) => (bool) ($row['is_dietary'] ?? false))
            ->count();
        $cancelledDietaryEmployeeMeals = $employeeRows
            ->filter(fn (array $row) => ($row['status'] ?? null) === EmployeeDailyMealHeadcountService::STATUS_CANCELLED)
            ->filter(fn (array $row) => (bool) ($row['is_dietary'] ?? false))
            ->count();

        $dietTypeCount = $dailyDietaryChildRows
            ->flatMap(fn (array $row) => $row['child']->dietaryRestrictions->pluck('name'))
            ->concat($dailyDietaryEmployeeRows->flatMap(fn (array $row) => $row['employee']->dietaryRestrictions->pluck('name')))
            ->unique()
            ->count();

        return [
            'daily_dietary_eaters' => $dailyDietaryChildRows->count() + $dailyDietaryEmployeeRows->count(),
            'cancelled_dietary_meals' => $cancelledDietaryChildMeals + $cancelledDietaryEmployeeMeals,
            'total_dietary_children' => $institutionDietaryChildren,
            'total_dietary_employees' => $institutionDietaryEmployees,
            'diet_type_count' => $dietTypeCount,
        ];
    }

    private function csvLine(array $values): string
    {
        $escaped = array_map(function ($value) {
            $value = str_replace('"', '""', (string) $value);

            return '"'.$value.'"';
        }, $values);

        return implode(';', $escaped)."\r\n";
    }

    private function combinedTodayCountsStats(array $childStats, array $employeeStats): array
    {
        return array_merge($childStats, [
            'child_daily_eaters' => (int) ($childStats['daily_eaters'] ?? 0),
            'employee_daily_eaters' => (int) ($employeeStats['daily_eaters'] ?? 0),
            'daily_eaters' => (int) ($childStats['daily_eaters'] ?? 0) + (int) ($employeeStats['daily_eaters'] ?? 0),
            'child_cancelled_meals' => (int) ($childStats['cancelled_meals'] ?? 0),
            'employee_cancelled_meals' => (int) ($employeeStats['cancelled_meals'] ?? 0),
            'cancelled_meals' => (int) ($childStats['cancelled_meals'] ?? 0) + (int) ($employeeStats['cancelled_meals'] ?? 0),
            'child_active_eaters' => (int) ($childStats['active_eaters'] ?? 0),
            'employee_active_eaters' => (int) ($employeeStats['active_eaters'] ?? 0),
            'active_eaters' => (int) ($childStats['active_eaters'] ?? 0) + (int) ($employeeStats['active_eaters'] ?? 0),
            'employee_dietary_eaters' => (int) ($employeeStats['dietary_eaters'] ?? 0),
            'dietary_eaters' => (int) ($childStats['dietary_eaters'] ?? 0) + (int) ($employeeStats['dietary_eaters'] ?? 0),
        ]);
    }
}
