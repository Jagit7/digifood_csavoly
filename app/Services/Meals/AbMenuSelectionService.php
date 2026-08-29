<?php

namespace App\Services\Meals;

use App\Models\AbMenuItem;
use App\Models\AbMenuPlan;
use App\Models\Child;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionSetting;
use App\Models\MenuChoice;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AbMenuSelectionService
{
    public const STATE_NOT_STARTED = 'not_started';
    public const STATE_ACTIVE = 'active';
    public const STATE_CLOSED = 'closed';
    public const CATEGORY_A = 'A';
    public const CATEGORY_B = 'B';
    public const CATEGORY_DIETARY = 'DIETARY';

    public function __construct(
        private readonly InstitutionCalendarService $calendar
    ) {
    }

    public function selectionDeadlineForPlan(AbMenuPlan $plan, ?InstitutionSetting $setting): ?CarbonImmutable
    {
        $deadlineDay = (int) ($setting?->ab_menu_choice_deadline_day ?? 0);

        if ($deadlineDay < 1 || $plan->valid_from === null) {
            return null;
        }

        $selectionMonth = CarbonImmutable::parse(
            $plan->valid_from->toDateString(),
            $this->calendar->timezone()
        )->startOfMonth()->subMonthNoOverflow();

        $day = min($deadlineDay, $selectionMonth->daysInMonth);

        return $selectionMonth->setDay($day)->endOfDay();
    }

    public function selectionState(
        AbMenuPlan $plan,
        ?InstitutionSetting $setting,
        CarbonInterface|string|null $at = null
    ): string {
        if ($plan->published_at === null) {
            return self::STATE_NOT_STARTED;
        }

        $deadline = $this->selectionDeadlineForPlan($plan, $setting);

        if ($deadline === null) {
            return self::STATE_ACTIVE;
        }

        return $this->at($at)->lte($deadline)
            ? self::STATE_ACTIVE
            : self::STATE_CLOSED;
    }

    public function selectionStateLabel(string $state): string
    {
        return match ($state) {
            self::STATE_NOT_STARTED => 'Választás nincs elindítva',
            self::STATE_ACTIVE => 'Választás nyitva',
            self::STATE_CLOSED => 'Választás lezárt',
            default => $state,
        };
    }

    public function canOpenSelection(AbMenuPlan $plan): bool
    {
        if (! $plan->active || $plan->published_at !== null) {
            return false;
        }

        if ($plan->relationLoaded('items')) {
            return $plan->items->isNotEmpty();
        }

        return $plan->items()->exists();
    }

    public function relevantPlanForInstitution(int $institutionId, CarbonInterface|string|null $today = null): ?AbMenuPlan
    {
        $todayString = $this->at($today)->toDateString();

        return AbMenuPlan::query()
            ->with(['items' => fn ($query) => $query->orderBy('menu_date')])
            ->where('institution_id', $institutionId)
            ->where('active', true)
            ->whereNotNull('published_at')
            ->whereDate('valid_to', '>=', $todayString)
            ->whereHas('items')
            ->orderBy('valid_from')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    public function resolveMenuItemForDate(int $institutionId, string $serviceDate): ?AbMenuItem
    {
        return AbMenuItem::query()
            ->select('ab_menu_items.*')
            ->join('ab_menu_plans', 'ab_menu_plans.id', '=', 'ab_menu_items.ab_menu_plan_id')
            ->where('ab_menu_plans.institution_id', $institutionId)
            ->where('ab_menu_plans.active', true)
            ->whereDate('ab_menu_plans.valid_from', '<=', $serviceDate)
            ->whereDate('ab_menu_plans.valid_to', '>=', $serviceDate)
            ->whereDate('ab_menu_items.menu_date', $serviceDate)
            ->orderByDesc('ab_menu_plans.published_at')
            ->orderByDesc('ab_menu_plans.id')
            ->first();
    }

    public function menuItemsForRange(
        int $institutionId,
        CarbonInterface|string $from,
        CarbonInterface|string $to
    ): Collection {
        $start = $this->at($from)->toDateString();
        $end = $this->at($to)->toDateString();

        return AbMenuItem::query()
            ->select('ab_menu_items.*')
            ->join('ab_menu_plans', 'ab_menu_plans.id', '=', 'ab_menu_items.ab_menu_plan_id')
            ->where('ab_menu_plans.institution_id', $institutionId)
            ->where('ab_menu_plans.active', true)
            ->whereDate('ab_menu_items.menu_date', '>=', $start)
            ->whereDate('ab_menu_items.menu_date', '<=', $end)
            ->orderBy('ab_menu_items.menu_date')
            ->orderByDesc('ab_menu_plans.published_at')
            ->orderByDesc('ab_menu_plans.id')
            ->orderByDesc('ab_menu_items.id')
            ->get()
            ->groupBy(fn (AbMenuItem $item) => $item->menu_date?->toDateString())
            ->map(fn (Collection $items) => $items->first());
    }

    public function resolveChoiceForDate(
        int $institutionId,
        int $childId,
        string $serviceDate,
        bool $isDietary
    ): array {
        $item = $this->resolveMenuItemForDate($institutionId, $serviceDate);

        if ($item === null || ! $this->isSelectableItem($item)) {
            return [
                'item' => $item,
                'choice' => null,
            ];
        }

        if ($isDietary) {
            return [
                'item' => $item,
                'choice' => null,
            ];
        }

        $choice = MenuChoice::query()
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
            ->where(function ($query) use ($item, $serviceDate) {
                $query->where('ab_menu_item_id', $item->id)
                    ->orWhere(function ($legacyQuery) use ($serviceDate) {
                        $legacyQuery->whereNull('ab_menu_item_id')
                            ->whereDate('menu_date', $serviceDate);
                    });
            })
            ->orderByDesc('ab_menu_item_id')
            ->orderByDesc('id')
            ->first();

        if ($choice === null) {
            return [
                'item' => $item,
                'choice' => MenuChoice::CHOICE_A,
            ];
        }

        if (! in_array($choice->choice, [MenuChoice::CHOICE_A, MenuChoice::CHOICE_B], true)) {
            return [
                'item' => $item,
                'error' => [
                    'status' => 'error',
                    'code' => 'manual_review_required',
                    'message' => 'Az A/B menü választása nem meghatározható, kézi ellenőrzés szükséges.',
                ],
            ];
        }

        return [
            'item' => $item,
            'choice' => $choice->choice,
            'record' => $choice,
        ];
    }

    public function choicesForChildrenAndPlan(Collection $childIds, AbMenuPlan $plan): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        $plan->loadMissing(['items' => fn ($query) => $query->orderBy('menu_date')]);

        return $this->choiceMapForChildrenAndItems($plan->institution_id, $childIds, $plan->items);
    }

    public function choiceMapForChildrenAndItems(
        int $institutionId,
        Collection $childIds,
        Collection $items
    ): Collection {
        if ($childIds->isEmpty() || $items->isEmpty()) {
            return collect();
        }

        $itemIds = $items->pluck('id')->filter()->all();
        $itemDates = $items->pluck('menu_date')->map(fn ($date) => $date?->toDateString())->filter()->all();

        return MenuChoice::query()
            ->where('institution_id', $institutionId)
            ->whereIn('child_id', $childIds->all())
            ->where(function ($query) use ($itemIds, $itemDates) {
                if ($itemIds !== []) {
                    $query->whereIn('ab_menu_item_id', $itemIds);
                }

                if ($itemDates !== []) {
                    $query->orWhere(function ($legacyQuery) use ($itemDates) {
                        $legacyQuery->whereNull('ab_menu_item_id')
                            ->whereIn('menu_date', $itemDates);
                    });
                }
            })
            ->get()
            ->keyBy(fn (MenuChoice $choice) => $this->choiceKey(
                (int) $choice->child_id,
                (int) ($choice->ab_menu_item_id ?: 0),
                $choice->menu_date?->toDateString()
            ));
    }

    public function explicitChoiceRecord(
        Collection $choiceMap,
        int $childId,
        AbMenuItem $item
    ): ?MenuChoice {
        return $choiceMap->get($this->choiceKey($childId, $item->id, $item->menu_date?->toDateString()));
    }

    public function effectiveChoiceForItem(
        Child $child,
        AbMenuItem $item,
        ?MenuChoice $choice = null
    ): ?string {
        if ($this->isDietaryChild($child)) {
            return null;
        }

        if (! $this->isSelectableItem($item)) {
            return null;
        }

        if ($choice === null) {
            return MenuChoice::CHOICE_A;
        }

        return $choice->choice === MenuChoice::CHOICE_B
            ? MenuChoice::CHOICE_B
            : MenuChoice::CHOICE_A;
    }

    public function effectiveCategoryForChildAndItem(
        Child $child,
        ?AbMenuItem $item,
        ?MenuChoice $choice = null
    ): ?string {
        if ($this->isDietaryChild($child)) {
            return self::CATEGORY_DIETARY;
        }

        if ($item === null || ! $this->isSelectableItem($item)) {
            return null;
        }

        return $this->effectiveChoiceForItem($child, $item, $choice);
    }

    public function countsForChildrenAndItem(
        Collection $children,
        ?AbMenuItem $item,
        Collection $choiceMap
    ): array {
        $counts = [
            'menu_a_count' => 0,
            'menu_b_count' => 0,
            'dietary_count' => 0,
        ];

        foreach ($children as $child) {
            if (! $child instanceof Child) {
                continue;
            }

            $choice = $item ? $this->explicitChoiceRecord($choiceMap, $child->id, $item) : null;
            $category = $this->effectiveCategoryForChildAndItem($child, $item, $choice);

            if ($category === self::CATEGORY_A) {
                $counts['menu_a_count']++;
            } elseif ($category === self::CATEGORY_B) {
                $counts['menu_b_count']++;
            } elseif ($category === self::CATEGORY_DIETARY) {
                $counts['dietary_count']++;
            }
        }

        return $counts;
    }

    public function isDietaryChild(Child $child): bool
    {
        if ($child->relationLoaded('dietaryRestrictions')) {
            return $child->dietaryRestrictions->isNotEmpty();
        }

        return $child->dietaryRestrictions()->where('active', true)->exists();
    }

    public function isSelectableItem(AbMenuItem $item): bool
    {
        return filled($item->menu_b);
    }

    public function choiceKey(int $childId, int $itemId, ?string $menuDate): string
    {
        return $childId.':'.$itemId.':'.$menuDate;
    }

    public function openSelection(AbMenuPlan $plan, int $userId): void
    {
        $plan->forceFill([
            'published_at' => now(),
            'updated_by' => $userId,
        ])->save();

        // Later we can dispatch the parent notification e-mail / queue job from this boundary.
    }

    /*
     * ---------------------------------------------------------------------
     * Dolgozói (tanári) A/B menüválasztás - ld. App\Http\Controllers\
     * EmployeePortal\EmployeeMenuChoiceController. Ezek a metódusok a fenti,
     * gyermek-alapú megfelelőik (isDietaryChild, choicesForChildrenAndPlan,
     * choiceMapForChildrenAndItems, explicitChoiceRecord,
     * effectiveChoiceForItem, choiceKey) pontos analógiái, de a MenuChoice
     * eater_type/eater_id (polimorf) mezőit használják a child_id helyett -
     * ld. 2026_08_26_190100_add_eater_columns_to_menu_choices_table migráció.
     * A fenti, gyermek-alapú metódusok EGYIKÉHEZ SEM nyúlnak és NEM
     * módosítják azok viselkedését - teljesen külön, additív kód.
     * ---------------------------------------------------------------------
     */

    public function isDietaryEmployee(InstitutionEmployee $employee): bool
    {
        if ($employee->relationLoaded('dietaryRestrictions')) {
            return $employee->dietaryRestrictions->isNotEmpty();
        }

        return $employee->dietaryRestrictions()->where('active', true)->exists();
    }

    public function choicesForEmployeesAndPlan(Collection $employeeIds, AbMenuPlan $plan): Collection
    {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        $plan->loadMissing(['items' => fn ($query) => $query->orderBy('menu_date')]);

        return $this->choiceMapForEmployeesAndItems($plan->institution_id, $employeeIds, $plan->items);
    }

    public function choiceMapForEmployeesAndItems(
        int $institutionId,
        Collection $employeeIds,
        Collection $items
    ): Collection {
        if ($employeeIds->isEmpty() || $items->isEmpty()) {
            return collect();
        }

        $itemIds = $items->pluck('id')->filter()->all();

        if ($itemIds === []) {
            return collect();
        }

        return MenuChoice::query()
            ->where('institution_id', $institutionId)
            ->where('eater_type', (new InstitutionEmployee())->getMorphClass())
            ->whereIn('eater_id', $employeeIds->all())
            ->whereIn('ab_menu_item_id', $itemIds)
            ->get()
            ->keyBy(fn (MenuChoice $choice) => $this->employeeChoiceKey(
                (int) $choice->eater_id,
                (int) $choice->ab_menu_item_id
            ));
    }

    public function explicitChoiceRecordForEmployee(
        Collection $choiceMap,
        int $employeeId,
        AbMenuItem $item
    ): ?MenuChoice {
        return $choiceMap->get($this->employeeChoiceKey($employeeId, $item->id));
    }

    public function effectiveChoiceForItemForEmployee(
        InstitutionEmployee $employee,
        AbMenuItem $item,
        ?MenuChoice $choice = null
    ): ?string {
        if ($this->isDietaryEmployee($employee)) {
            return null;
        }

        if (! $this->isSelectableItem($item)) {
            return null;
        }

        if ($choice === null) {
            return MenuChoice::CHOICE_A;
        }

        return $choice->choice === MenuChoice::CHOICE_B
            ? MenuChoice::CHOICE_B
            : MenuChoice::CHOICE_A;
    }

    public function employeeChoiceKey(int $employeeId, int $itemId): string
    {
        return $employeeId.':'.$itemId;
    }

    private function at(CarbonInterface|string|null $at = null): CarbonImmutable
    {
        if ($at instanceof CarbonInterface) {
            return CarbonImmutable::instance($at)->setTimezone($this->calendar->timezone());
        }

        if (is_string($at) && $at !== '') {
            return CarbonImmutable::parse($at, $this->calendar->timezone());
        }

        return $this->calendar->now();
    }
}
