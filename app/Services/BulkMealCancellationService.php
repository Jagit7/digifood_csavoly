<?php

namespace App\Services;

use App\Models\BulkMealCancellationBatch;
use App\Models\BulkMealCancellationBatchItem;
use App\Models\Child;
use App\Models\Institution;
use App\Models\MealCancellation;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\Children\ChildGradeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BulkMealCancellationService
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly MealCancellationService $mealCancellations,
        private readonly DailyMealHeadcountService $headcount,
        private readonly ChildGradeResolver $gradeResolver
    ) {}

    public function preview(Institution $institution, array $validated): array
    {
        $window = $this->mealCancellations->cancellationWindowOrFail($institution->id);
        $children = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->whereIn('id', $validated['selected_child_ids'])
            ->with([
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();

        $gradeMap = $this->gradeResolver->resolveForChildren($institution, $children);
        $dates = $this->requestedDates($validated['date_from'], $validated['date_to']);
        $serviceDays = $this->calendar
            ->serviceDaysBetween($institution->id, $dates->first(), $dates->last())
            ->map->toDateString()
            ->flip();
        $mealSettings = $this->headcount->mealSettingsForChildrenInPeriod(
            $institution->id,
            $children->pluck('id'),
            $validated['date_from'],
            $validated['date_to']
        );
        $existingCancellations = MealCancellation::query()
            ->where('institution_id', $institution->id)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereIn('child_id', $children->pluck('id'))
            ->whereDate('service_date', '>=', $validated['date_from'])
            ->whereDate('service_date', '<=', $validated['date_to'])
            ->get(['child_id', 'service_date'])
            ->groupBy('child_id')
            ->map(fn (Collection $rows) => $rows->pluck('service_date')->map(
                fn ($date) => CarbonImmutable::parse($date)->toDateString()
            )->flip());
        $classCancelledMap = $this->classCancelledDateMap($institution->id, $children->pluck('id'), $validated['date_from'], $validated['date_to']);
        $earliestCancellable = $window['earliest_cancellable_day']?->toDateString();

        $items = collect();

        foreach ($children as $child) {
            $childSettings = $mealSettings->get($child->id, collect());
            $existingForChild = $existingCancellations->get($child->id, collect());
            $classCancelledForChild = $classCancelledMap->get($child->id, collect());
            $gradeLabel = $gradeMap->get($child->id)['grade_label'] ?? null;

            foreach ($dates as $date) {
                $dateString = $date->toDateString();
                $resultCode = $this->resultCodeForDate(
                    $dateString,
                    $serviceDays,
                    $earliestCancellable,
                    $this->headcount->mealSettingForDate($childSettings, $dateString),
                    $classCancelledForChild->has($dateString),
                    $existingForChild->has($dateString)
                );

                $items->push([
                    'child_id' => $child->id,
                    'child_name' => $child->name,
                    'group_name' => $child->group_name,
                    'grade_label' => $gradeLabel,
                    'service_date' => $dateString,
                    'result_code' => $resultCode,
                    'result_label' => $this->resultLabel($resultCode),
                ]);
            }
        }

        $summary = [
            'selected_children_count' => $children->count(),
            'selected_group_names' => $children->pluck('group_name')->filter()->unique()->sort()->values()->all(),
            'service_days_count' => $serviceDays->count(),
            'planned_cancellation_count' => $items->where('result_code', BulkMealCancellationBatchItem::RESULT_CREATED)->count(),
            'created_cancellation_count' => $items->where('result_code', BulkMealCancellationBatchItem::RESULT_CREATED)->count(),
            'duplicate_count' => $items->where('result_code', BulkMealCancellationBatchItem::RESULT_ALREADY_CANCELLED)->count(),
            'missing_meal_setting_count' => $items->where('result_code', BulkMealCancellationBatchItem::RESULT_NO_ACTIVE_MEAL)->count(),
            'non_service_day_count' => $items->where('result_code', BulkMealCancellationBatchItem::RESULT_NON_SERVICE_DAY)->count(),
            'deadline_blocked_count' => $items->where('result_code', BulkMealCancellationBatchItem::RESULT_DEADLINE_BLOCKED)->count(),
            'class_cancelled_count' => $items->where('result_code', BulkMealCancellationBatchItem::RESULT_CLASS_CANCELLED)->count(),
        ];

        return [
            'children' => $children,
            'items' => $items,
            'summary' => $summary,
            'window' => $window,
            'grade_map' => $gradeMap,
            'dates' => $dates,
        ];
    }

    public function store(Institution $institution, array $validated, User $user): BulkMealCancellationBatch
    {
        return DB::transaction(function () use ($institution, $validated, $user) {
            $preview = $this->preview($institution, $validated);
            $children = $preview['children']->keyBy('id');

            $batch = BulkMealCancellationBatch::create([
                'institution_id' => $institution->id,
                'created_by' => $user->id,
                'event_name' => $validated['event_name'],
                'meal_scope' => $validated['meal_scope'],
                'reason' => $validated['reason'] ?? null,
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                ...$preview['summary'],
            ]);

            $createdCancellations = collect();
            $createdItemsByChild = $preview['items']
                ->where('result_code', BulkMealCancellationBatchItem::RESULT_CREATED)
                ->groupBy('child_id');

            foreach ($createdItemsByChild as $childId => $childItems) {
                $child = $children->get((int) $childId);

                if (! $child instanceof Child) {
                    continue;
                }

                $created = $this->mealCancellations->createActiveCancellations(
                    $institution,
                    $child,
                    $childItems->pluck('service_date')->all(),
                    $user,
                    $validated['reason'] ?? null
                );

                $createdCancellations->put((int) $childId, $created);
            }

            $itemRows = $preview['items']->map(function (array $item) use ($batch, $createdCancellations) {
                $cancellationId = null;

                if ($item['result_code'] === BulkMealCancellationBatchItem::RESULT_CREATED) {
                    $cancellationId = $createdCancellations
                        ->get($item['child_id'], collect())
                        ->get($item['service_date'])
                        ?->id;
                }

                return [
                    'bulk_meal_cancellation_batch_id' => $batch->id,
                    'child_id' => $item['child_id'],
                    'meal_cancellation_id' => $cancellationId,
                    'service_date' => $item['service_date'],
                    'child_name' => $item['child_name'],
                    'group_name' => $item['group_name'],
                    'grade_label' => $item['grade_label'],
                    'result_code' => $item['result_code'],
                    'result_label' => $item['result_label'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();

            DB::table('bulk_meal_cancellation_batch_items')->insert($itemRows);

            return $batch->fresh(['creator', 'items.child', 'items.cancellation']);
        });
    }

    private function requestedDates(string $from, string $to): Collection
    {
        $start = CarbonImmutable::parse($from, $this->calendar->timezone())->startOfDay();
        $end = CarbonImmutable::parse($to, $this->calendar->timezone())->startOfDay();
        $dates = collect();

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dates->push($date);
        }

        return $dates;
    }

    private function classCancelledDateMap(int $institutionId, Collection $childIds, string $from, string $to): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return DB::table('class_cancellations')
            ->join('class_group_memberships', 'class_group_memberships.class_group_id', '=', 'class_cancellations.class_group_id')
            ->join('class_groups', 'class_groups.id', '=', 'class_cancellations.class_group_id')
            ->where('class_cancellations.institution_id', $institutionId)
            ->where('class_groups.institution_id', $institutionId)
            ->where('class_group_memberships.status', 'active')
            ->whereIn('class_group_memberships.child_id', $childIds)
            ->whereDate('class_cancellations.date_from', '<=', $to)
            ->whereDate('class_cancellations.date_to', '>=', $from)
            ->get([
                'class_group_memberships.child_id',
                'class_cancellations.date_from',
                'class_cancellations.date_to',
            ])
            ->groupBy('child_id')
            ->map(function (Collection $rows) use ($from, $to) {
                $dates = collect();
                $start = CarbonImmutable::parse($from);
                $end = CarbonImmutable::parse($to);

                for ($date = $start; $date->lte($end); $date = $date->addDay()) {
                    $dateString = $date->toDateString();
                    $isCancelled = $rows->contains(fn ($row) => $row->date_from <= $dateString && $row->date_to >= $dateString);

                    if ($isCancelled) {
                        $dates->put($dateString, true);
                    }
                }

                return $dates;
            });
    }

    private function resultCodeForDate(
        string $date,
        Collection $serviceDays,
        ?string $earliestCancellable,
        ?StudentMealSetting $setting,
        bool $classCancelled,
        bool $alreadyCancelled
    ): string {
        if (! $serviceDays->has($date)) {
            return BulkMealCancellationBatchItem::RESULT_NON_SERVICE_DAY;
        }

        if ($earliestCancellable !== null && $date < $earliestCancellable) {
            return BulkMealCancellationBatchItem::RESULT_DEADLINE_BLOCKED;
        }

        if (! $setting) {
            return BulkMealCancellationBatchItem::RESULT_NO_ACTIVE_MEAL;
        }

        if ($classCancelled) {
            return BulkMealCancellationBatchItem::RESULT_CLASS_CANCELLED;
        }

        if ($alreadyCancelled) {
            return BulkMealCancellationBatchItem::RESULT_ALREADY_CANCELLED;
        }

        return BulkMealCancellationBatchItem::RESULT_CREATED;
    }

    private function resultLabel(string $resultCode): string
    {
        return match ($resultCode) {
            BulkMealCancellationBatchItem::RESULT_CREATED => 'Létrehozva',
            BulkMealCancellationBatchItem::RESULT_ALREADY_CANCELLED => 'Már korábban lemondva',
            BulkMealCancellationBatchItem::RESULT_NO_ACTIVE_MEAL => 'Nincs érvényes étkezési beállítás',
            BulkMealCancellationBatchItem::RESULT_NON_SERVICE_DAY => 'Nem étkezési nap',
            BulkMealCancellationBatchItem::RESULT_DEADLINE_BLOCKED => 'Lemondási határidőn kívül',
            BulkMealCancellationBatchItem::RESULT_CLASS_CANCELLED => 'Már csoportszintű lemondással érintett',
            default => $resultCode,
        };
    }
}
