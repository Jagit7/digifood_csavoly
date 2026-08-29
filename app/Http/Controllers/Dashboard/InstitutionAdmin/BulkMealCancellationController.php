<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InstitutionAdmin\MealCancellations\BulkMealCancellationPreviewRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\MealCancellations\BulkMealCancellationStoreRequest;
use App\Models\BulkMealCancellationBatch;
use App\Models\BulkMealCancellationBatchItem;
use App\Models\Child;
use App\Models\Institution;
use App\Models\MealCancellation;
use App\Models\SchoolYear;
use App\Services\BulkMealCancellationService;
use App\Services\Children\ChildGradeResolver;
use App\Services\Meals\StudentMealSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BulkMealCancellationController extends Controller
{
    public function __construct(
        private readonly BulkMealCancellationService $bulkMealCancellations,
        private readonly ChildGradeResolver $gradeResolver,
        private readonly StudentMealSettingService $mealSettingService
    ) {}

    public function create(Request $request): View
    {
        $institution = $this->institution();
        $filters = $this->filtersFromRequest($request);
        $baseChildren = $this->filteredChildren($institution, $filters);
        $gradeMap = $this->gradeResolver->resolveForChildren($institution, $baseChildren);
        $children = $baseChildren->map(function (Child $child) use ($gradeMap) {
            $child->setAttribute('resolved_grade_label', $gradeMap->get($child->id)['grade_label'] ?? null);

            return $child;
        });
        $availableGrades = $children
            ->pluck('resolved_grade_label')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($filters['grades'] !== []) {
            $children = $children->filter(fn (Child $child) => in_array(
                (string) ($child->resolved_grade_label ?? ''),
                $filters['grades'],
                true
            ))->values();
        }

        $statuses = $this->mealSettingService->mealParticipationStatusesForChildren(
            $institution,
            $children->pluck('id'),
            $filters['anchor_date']
        );

        if ($filters['meal_status'] !== 'all') {
            $children = $children->filter(function (Child $child) use ($statuses, $filters) {
                $status = $statuses->get($child->id)['status'] ?? StudentMealSettingService::PARTICIPATION_STATUS_MISSING;

                return $status === $filters['meal_status'];
            })->values();
        }

        $statuses = $statuses->only($children->pluck('id')->all());
        $overlapMap = $this->overlapMap($institution, $children, $filters['date_from'], $filters['date_to']);
        $selectedChildIds = collect(old('selected_child_ids', $request->input('selected_child_ids', [])))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $recentBatches = BulkMealCancellationBatch::query()
            ->where('institution_id', $institution->id)
            ->with('creator:id,name')
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard.institution_admin.meal-cancellations.bulk.create', [
            'institution' => $institution,
            'filters' => $filters,
            'children' => $children,
            'statuses' => $statuses,
            'overlapMap' => $overlapMap,
            'schoolYears' => SchoolYear::query()
                ->where('institution_id', $institution->id)
                ->orderByDesc('name')
                ->pluck('name')
                ->values(),
            'availableGroups' => Child::query()
                ->where('institution_id', $institution->id)
                ->when($filters['school_year'], fn ($query) => $query->where('school_year', $filters['school_year']))
                ->whereNotNull('group_name')
                ->orderBy('group_name')
                ->distinct()
                ->pluck('group_name')
                ->values(),
            'availableGrades' => $availableGrades,
            'selectedChildIds' => $selectedChildIds,
            'mealScopeOptions' => [
                BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED => 'Minden aznapra beállított étkezés',
            ],
            'recentBatches' => $recentBatches,
        ]);
    }

    public function preview(BulkMealCancellationPreviewRequest $request): View
    {
        $institution = $this->institution();
        $validated = $request->validated();
        $preview = $this->bulkMealCancellations->preview($institution, $validated);

        return view('dashboard.institution_admin.meal-cancellations.bulk.preview', [
            'institution' => $institution,
            'validated' => $validated,
            'preview' => $preview,
            'mealScopeOptions' => [
                BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED => 'Minden aznapra beállított étkezés',
            ],
            'resultBadgeClasses' => $this->resultBadgeClasses(),
        ]);
    }

    public function store(BulkMealCancellationStoreRequest $request): RedirectResponse
    {
        $institution = $this->institution();
        $batch = $this->bulkMealCancellations->store($institution, $request->validated(), $request->user());

        return redirect()
            ->route('dashboard.institution.meal-cancellations.bulk.show', $batch)
            ->with('success', 'A csoportos étkezéslemondás sikeresen mentve.');
    }

    public function operations(): View
    {
        $institution = $this->institution();
        $batches = BulkMealCancellationBatch::query()
            ->where('institution_id', $institution->id)
            ->with('creator:id,name')
            ->latest()
            ->paginate(20);

        return view('dashboard.institution_admin.meal-cancellations.bulk.index', [
            'institution' => $institution,
            'batches' => $batches,
        ]);
    }

    public function show(BulkMealCancellationBatch $batch): View
    {
        $institution = $this->institution();
        abort_if($batch->institution_id !== $institution->id, 403);

        $batch->load([
            'creator:id,name',
            'items' => fn ($query) => $query->orderBy('service_date')->orderBy('child_name'),
        ]);

        return view('dashboard.institution_admin.meal-cancellations.bulk.show', [
            'institution' => $institution,
            'batch' => $batch,
            'groupedItems' => $batch->items->groupBy('service_date'),
            'resultBadgeClasses' => $this->resultBadgeClasses(),
        ]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function filtersFromRequest(Request $request): array
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        return [
            'school_year' => filled($request->input('school_year')) ? trim((string) $request->input('school_year')) : null,
            'groups' => collect($request->input('groups', []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
            'grades' => collect($request->input('grades', []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all(),
            'search' => trim((string) $request->input('search')),
            'meal_status' => in_array((string) $request->input('meal_status', 'active'), ['active', 'upcoming', 'missing', 'all'], true)
                ? (string) $request->input('meal_status', 'active')
                : 'active',
            'date_from' => filled($dateFrom) ? (string) $dateFrom : null,
            'date_to' => filled($dateTo) ? (string) $dateTo : null,
            'anchor_date' => filled($dateFrom) ? (string) $dateFrom : now()->toDateString(),
        ];
    }

    private function filteredChildren(Institution $institution, array $filters): Collection
    {
        return Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->when($filters['school_year'], fn ($query) => $query->where('school_year', $filters['school_year']))
            ->when($filters['groups'] !== [], fn ($query) => $query->whereIn('group_name', $filters['groups']))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($subQuery) use ($filters) {
                    $subQuery->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('educational_identifier', 'like', '%'.$filters['search'].'%');
                });
            })
            ->with([
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();
    }

    private function overlapMap(Institution $institution, Collection $children, ?string $dateFrom, ?string $dateTo): Collection
    {
        if ($dateFrom === null || $dateTo === null || $children->isEmpty()) {
            return collect();
        }

        return MealCancellation::query()
            ->where('institution_id', $institution->id)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereIn('child_id', $children->pluck('id'))
            ->whereDate('service_date', '>=', $dateFrom)
            ->whereDate('service_date', '<=', $dateTo)
            ->get(['child_id', 'service_date'])
            ->groupBy('child_id')
            ->map(fn (Collection $rows) => [
                'count' => $rows->count(),
                'labels' => $rows->pluck('service_date')->map(fn ($date) => $date->format('Y.m.d.'))->all(),
            ]);
    }

    private function resultBadgeClasses(): array
    {
        return [
            BulkMealCancellationBatchItem::RESULT_CREATED => 'badge-success',
            BulkMealCancellationBatchItem::RESULT_ALREADY_CANCELLED => 'badge-secondary',
            BulkMealCancellationBatchItem::RESULT_NO_ACTIVE_MEAL => 'badge-warning',
            BulkMealCancellationBatchItem::RESULT_NON_SERVICE_DAY => 'badge-light',
            BulkMealCancellationBatchItem::RESULT_DEADLINE_BLOCKED => 'badge-danger',
            BulkMealCancellationBatchItem::RESULT_CLASS_CANCELLED => 'badge-info',
        ];
    }
}
