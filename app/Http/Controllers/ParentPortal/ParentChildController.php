<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\AbMenuItem;
use App\Models\Child;
use App\Models\InstitutionMealPackage;
use App\Models\MenuChoice;
use App\Models\StudentMealSetting;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ParentChildController extends Controller
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar
    ) {
    }

    public function index(): View
    {
        $user = auth()->user();
        $guardianIds = $user->guardians()->where('active', true)->pluck('guardians.id');
        $today = $this->calendar->now()->startOfDay();

        $children = Child::query()
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', $guardianIds))
            ->with([
                'institution.setting',
                'institution.mealSetting',
                'institution.mealPackages' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['mealTypes.mealType'])
                    ->orderByDesc('is_default')
                    ->orderBy('display_order')
                    ->orderBy('name'),
                'discountType',
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'guardians' => fn ($query) => $query->whereIn('guardians.id', $guardianIds)->orderBy('last_name')->orderBy('first_name'),
                'mealSettings' => fn ($query) => $query
                    ->with([
                        'mealPackage.mealTypes.mealType',
                        'mealTypes.mealType',
                        'closedBy',
                    ])
                    ->orderByDesc('valid_from')
                    ->orderByDesc('id'),
            ])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $institutionWindows = $children->getCollection()
            ->pluck('institution')
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn ($institution) => [
                $institution->id => $this->calendar->cancellationWindow($institution->id, $today),
            ]);

        $childCards = $children->getCollection()
            ->map(fn (Child $child) => $this->buildChildOverview($child, $today, $institutionWindows->get($child->institution_id)));

        $stats = [
            'children_count' => $children->total(),
            'institutions_count' => $children->getCollection()
                ->pluck('institution_id')
                ->filter()
                ->unique()
                ->count(),
            'active_meal_count' => $childCards
                ->filter(fn (array $card) => $card['meal_data']['has_active_setting'])
                ->count(),
            'active_discount_count' => $childCards
                ->filter(fn (array $card) => $card['discount']['has_active_discount'])
                ->count(),
        ];

        return view('parent.children.index', [
            'children' => $children,
            'childCards' => $childCards,
            'stats' => $stats,
        ]);
    }

    public function show(int $child): View
    {
        $user = auth()->user();
        $guardianIds = $user->guardians()->where('active', true)->pluck('guardians.id');
        $today = $this->calendar->now()->startOfDay();

        $childModel = Child::query()
            ->whereKey($child)
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', $guardianIds))
            ->with([
                'institution',
                'institution.setting',
                'institution.mealPackages' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['mealTypes.mealType'])
                    ->orderByDesc('is_default')
                    ->orderBy('display_order')
                    ->orderBy('name'),
                'discountType',
                'guardians' => fn ($query) => $query->whereIn('guardians.id', $guardianIds)->orderBy('last_name')->orderBy('first_name'),
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'mealSettings' => fn ($query) => $query
                    ->with([
                        'mealPackage.mealTypes.mealType',
                        'mealTypes.mealType',
                        'closedBy',
                    ])
                    ->orderByDesc('valid_from')
                    ->orderByDesc('id'),
            ])
            ->firstOrFail();

        return view('parent.children.show', [
            'child' => $childModel,
            'mealData' => $this->buildMealData($childModel, $today),
        ]);
    }

    private function buildMealData(Child $child, CarbonImmutable $today): array
    {
        $currentMealSetting = $this->currentMealSetting($child, $today);
        $latestMealSetting = $child->mealSettings->first();
        $closedMealSetting = $currentMealSetting === null
            && $latestMealSetting?->wasClosedManually()
            && $latestMealSetting->isClosedForDate($today)
                ? $latestMealSetting
                : null;
        // Ha ma még nincs érvényes beállítás, de van egy jövőben induló,
        // rögzített (nem lezárt) beállítás, azt "ütemezett étkezés"-ként
        // jelezzük, nem "nincs aktív étkezés"-ként - ugyanígy jelenik meg
        // ez az admin felületen is (ld.
        // Dashboard\InstitutionAdmin\ChildMealSettingController /
        // resources/views/dashboard/institution_admin/children/meal-settings/index.blade.php
        // $isUpcomingSetting számítása), a két felületnek egységesnek kell lennie.
        $upcomingMealSetting = $currentMealSetting === null
            && $closedMealSetting === null
            && $latestMealSetting !== null
            && ! $latestMealSetting->wasClosedManually()
            && $latestMealSetting->valid_from->toDateString() > $today->toDateString()
                ? $latestMealSetting
                : null;
        $resolvedMealTypes = $this->resolveMealTypes($child, $currentMealSetting);
        $dietaryNames = $child->dietaryRestrictions->pluck('name')->values();
        $package = $this->resolveMealPackage($child, $currentMealSetting);

        return [
            'status_label' => $this->mealStatusLabel($child, $currentMealSetting, $closedMealSetting, $upcomingMealSetting),
            'mode_label' => $currentMealSetting?->modeLabel(),
            'package_name' => $package?->name,
            'has_active_setting' => $currentMealSetting !== null,
            'has_package' => filled($package?->name),
            'meal_types' => $resolvedMealTypes->pluck('mealType.name')->filter()->values(),
            'dietary_names' => $dietaryNames,
            'is_dietary' => $dietaryNames->isNotEmpty(),
            'valid_from_label' => $currentMealSetting?->valid_from?->format('Y.m.d.'),
            'valid_to_label' => ($currentMealSetting?->valid_to ?? $closedMealSetting?->valid_to)?->format('Y.m.d.'),
            'note' => $currentMealSetting?->note,
            'closure_reason_label' => $closedMealSetting?->closureReasonLabel(),
            'closure_note' => $closedMealSetting?->closure_note,
            'closed_at_label' => $closedMealSetting?->closed_at?->timezone(config('app.timezone'))->format('Y.m.d. H:i'),
            'closed_by_name' => $closedMealSetting?->closedBy?->name,
            'is_closed_meal_relationship' => $closedMealSetting !== null,
            'is_upcoming_setting' => $upcomingMealSetting !== null,
            'upcoming_mode_label' => $upcomingMealSetting?->modeLabel(),
            'upcoming_valid_from_label' => $upcomingMealSetting?->valid_from?->format('Y.m.d.'),
            'show_missing_package_notice' => ($currentMealSetting === null && $closedMealSetting === null && $upcomingMealSetting === null)
                || (
                    $currentMealSetting !== null
                    && in_array($currentMealSetting->mode, [
                        StudentMealSetting::MODE_INSTITUTION_DEFAULT,
                        StudentMealSetting::MODE_PACKAGE,
                    ], true)
                    && blank($package?->name)
                ),
            'ab_menu' => $currentMealSetting ? $this->resolveAbMenuData($child, $today) : null,
        ];
    }

    private function buildChildOverview(Child $child, CarbonImmutable $today, ?array $institutionWindow): array
    {
        $mealData = $this->buildMealData($child, $today);
        $discount = $this->buildDiscountData($child);

        return [
            'child' => $child,
            'meal_data' => $mealData,
            'discount' => $discount,
            'institution_type_label' => $this->institutionTypeLabel($child),
            'guardian_names' => $child->guardians->pluck('full_name')->filter()->values(),
            'linked_at_label' => $child->guardians
                ->pluck('pivot.created_at')
                ->filter()
                ->sort()
                ->first()?->format('Y.m.d.'),
            'barcode_status' => [
                'label' => $child->barcodeStatusLabel(),
                'tone' => $child->hasActiveBarcode()
                    ? 'success'
                    : ($child->hasDisabledBarcode() ? 'warning' : 'secondary'),
                'generated_at' => $child->barcodeGeneratedAtLabel(),
                'entry_enabled' => $child->institution?->setting?->barcodeEntryEnabled() ?? false,
            ],
            'status_items' => $this->buildStatusItems($child, $mealData, $discount),
            'next_meal_day_label' => $institutionWindow && $institutionWindow['next_service_day']
                ? $institutionWindow['next_service_day']->locale('hu')->isoFormat('YYYY. MMMM D.')
                : null,
            'next_cancellation_deadline_label' => $institutionWindow && $institutionWindow['cutoff']
                ? $this->formatDeadlineLabel($institutionWindow['cutoff'])
                : null,
            'meal_actions' => $this->mealActionLinks($child, $today),
        ];
    }

    private function buildDiscountData(Child $child): array
    {
        $discountType = $child->discountType;
        $percentage = (int) ($discountType?->percentage ?? 0);
        $hasActiveDiscount = $discountType !== null && $percentage > 0;

        return [
            'name' => $discountType?->name,
            'percentage_label' => $hasActiveDiscount ? $percentage.'%' : null,
            'has_active_discount' => $hasActiveDiscount,
            'status_label' => $hasActiveDiscount ? 'Aktív kedvezmény' : 'Nincs aktív kedvezmény',
        ];
    }

    private function buildStatusItems(Child $child, array $mealData, array $discount): array
    {
        $items = collect([
            [
                'label' => $child->active ? 'Aktív gyermek' : 'Inaktív gyermek',
                'tone' => $child->active ? 'success' : 'secondary',
                'icon' => $child->active ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle',
            ],
            [
                'label' => match (true) {
                    $mealData['has_active_setting'] => 'Aktív étkezés',
                    $mealData['is_closed_meal_relationship'] => 'Étkezési jogviszony lezárva',
                    $mealData['is_upcoming_setting'] => 'Ütemezett étkezés',
                    default => 'Nincs aktív étkezés',
                },
                'tone' => match (true) {
                    $mealData['has_active_setting'] => 'primary',
                    $mealData['is_closed_meal_relationship'] => 'warning',
                    $mealData['is_upcoming_setting'] => 'info',
                    default => 'light',
                },
                'icon' => match (true) {
                    $mealData['has_active_setting'] => 'fa-solid fa-utensils',
                    $mealData['is_closed_meal_relationship'] => 'fa-solid fa-calendar-xmark',
                    $mealData['is_upcoming_setting'] => 'fa-solid fa-calendar-check',
                    default => 'fa-solid fa-utensils-slash',
                },
            ],
            $discount['has_active_discount'] ? [
                'label' => 'Kedvezmény rögzítve',
                'tone' => 'info',
                'icon' => 'fa-solid fa-badge-percent',
            ] : null,
            $mealData['is_dietary'] ? [
                'label' => 'Diétás étkezés',
                'tone' => 'warning',
                'icon' => 'fa-solid fa-shield-heart',
            ] : null,
        ])->filter()->values();

        if ($items->count() === 2 && ! $discount['has_active_discount'] && ! $mealData['is_dietary']) {
            $items->push([
                'label' => 'Nincs szükséges szülői teendő',
                'tone' => 'success',
                'icon' => 'fa-solid fa-hand-holding-heart',
            ]);
        }

        return $items->all();
    }

    private function mealActionLinks(Child $child, CarbonImmutable $today): array
    {
        $baseParams = [
            'child_id' => $child->id,
            'date' => $today->toDateString(),
        ];

        return [
            [
                'label' => 'Részletek',
                'icon' => 'fa-solid fa-arrow-up-right-from-square',
                'class' => 'btn btn-outline-primary btn-sm',
                'url' => route('parent.children.show', $child),
            ],
            [
                'label' => 'Étkezések és lemondások',
                'icon' => 'fa-solid fa-utensils',
                'class' => 'btn btn-primary btn-sm',
                'url' => route('parent.meal-cancellations', $baseParams + ['view' => 'week']),
            ],
            [
                'label' => 'Heti nézet',
                'icon' => 'fa-regular fa-calendar',
                'class' => 'btn btn-outline-secondary btn-sm',
                'url' => route('parent.meal-cancellations', $baseParams + ['view' => 'week']),
            ],
            [
                'label' => 'Havi nézet',
                'icon' => 'fa-regular fa-calendar-days',
                'class' => 'btn btn-outline-secondary btn-sm',
                'url' => route('parent.meal-cancellations', $baseParams + ['view' => 'month']),
            ],
        ];
    }

    private function institutionTypeLabel(Child $child): ?string
    {
        return match ($child->institution?->type) {
            'iskola' => 'Iskola',
            'ovoda' => 'Óvoda',
            null => null,
            default => Str::headline((string) $child->institution?->type),
        };
    }

    private function formatDeadlineLabel(CarbonImmutable $deadline): string
    {
        $today = $this->calendar->now()->startOfDay();
        $deadlineDay = $deadline->startOfDay();

        if ($deadlineDay->equalTo($today)) {
            return 'Ma '.$deadline->format('H:i');
        }

        if ($deadlineDay->equalTo($today->addDay())) {
            return 'Holnap '.$deadline->format('H:i');
        }

        return $deadline->locale('hu')->isoFormat('YYYY. MMMM D. HH:mm');
    }

    private function currentMealSetting(Child $child, CarbonImmutable $today): ?StudentMealSetting
    {
        return $child->mealSettings->first(function (StudentMealSetting $setting) use ($today) {
            $validFrom = CarbonImmutable::parse($setting->valid_from, $this->calendar->timezone())->startOfDay();
            $validTo = $setting->valid_to
                ? CarbonImmutable::parse($setting->valid_to, $this->calendar->timezone())->startOfDay()
                : null;

            return $validFrom->lte($today) && ($validTo === null || $validTo->gte($today));
        });
    }

    private function resolveMealPackage(Child $child, ?StudentMealSetting $currentMealSetting): ?InstitutionMealPackage
    {
        if ($currentMealSetting === null) {
            return null;
        }

        if ($currentMealSetting->mode === StudentMealSetting::MODE_PACKAGE) {
            return $currentMealSetting->mealPackage;
        }

        if ($currentMealSetting->mode === StudentMealSetting::MODE_INSTITUTION_DEFAULT) {
            return $child->institution?->mealPackages?->firstWhere('is_default', true);
        }

        return null;
    }

    private function resolveMealTypes(Child $child, ?StudentMealSetting $currentMealSetting): Collection
    {
        if ($currentMealSetting === null) {
            return collect();
        }

        if ($currentMealSetting->mode === StudentMealSetting::MODE_PACKAGE) {
            return $currentMealSetting->mealPackage?->mealTypes
                ?->filter(fn ($mealType) => $mealType->is_active && $mealType->mealType)
                ->values() ?? collect();
        }

        if ($currentMealSetting->mode === StudentMealSetting::MODE_INSTITUTION_DEFAULT) {
            $defaultPackage = $child->institution?->mealPackages?->firstWhere('is_default', true);

            return $defaultPackage?->mealTypes
                ?->filter(fn ($mealType) => $mealType->is_active && $mealType->mealType)
                ->values() ?? collect();
        }

        return $currentMealSetting->mealTypes
            ->filter(fn ($mealType) => $mealType->is_active && $mealType->mealType)
            ->values();
    }

    private function resolveAbMenuData(Child $child, CarbonImmutable $today): ?array
    {
        if (! filled($child->institution?->setting?->ab_menu_choice_deadline_day)) {
            return null;
        }

        $menuChoice = MenuChoice::query()
            ->where('institution_id', $child->institution_id)
            ->where('child_id', $child->id)
            ->whereDate('menu_date', '>=', $today->toDateString())
            ->orderBy('menu_date')
            ->first();

        if (! $menuChoice || ! in_array($menuChoice->choice, [MenuChoice::CHOICE_A, MenuChoice::CHOICE_B], true)) {
            return null;
        }

        $abMenuItem = AbMenuItem::query()
            ->select('ab_menu_items.*')
            ->join('ab_menu_plans', 'ab_menu_plans.id', '=', 'ab_menu_items.ab_menu_plan_id')
            ->where('ab_menu_plans.institution_id', $child->institution_id)
            ->where('ab_menu_plans.active', true)
            ->whereDate('ab_menu_plans.valid_from', '<=', $menuChoice->menu_date->toDateString())
            ->whereDate('ab_menu_plans.valid_to', '>=', $menuChoice->menu_date->toDateString())
            ->whereDate('ab_menu_items.menu_date', $menuChoice->menu_date->toDateString())
            ->whereNotNull('ab_menu_items.menu_b')
            ->where('ab_menu_items.menu_b', '!=', '')
            ->orderByDesc('ab_menu_plans.published_at')
            ->orderByDesc('ab_menu_plans.id')
            ->first();

        if ($abMenuItem === null) {
            return null;
        }

        return [
            'date_label' => $menuChoice->menu_date->locale('hu')->isoFormat('YYYY. MMMM D.'),
            'choice' => $menuChoice->choice,
            'choice_label' => 'Menü '.$menuChoice->choice,
            'menu_a' => $abMenuItem->menu_a,
            'menu_b' => $abMenuItem->menu_b,
            'menu_dietary' => $abMenuItem->menu_dietary,
        ];
    }

    private function mealStatusLabel(
        Child $child,
        ?StudentMealSetting $currentMealSetting,
        ?StudentMealSetting $closedMealSetting,
        ?StudentMealSetting $upcomingMealSetting = null
    ): string
    {
        if (! $child->active) {
            return 'Inaktív';
        }

        if ($closedMealSetting) {
            return 'Étkezési jogviszony lezárva';
        }

        if (! $currentMealSetting) {
            return $upcomingMealSetting
                ? 'Ütemezett étkezés ('.$upcomingMealSetting->valid_from->format('Y.m.d.').'-től)'
                : 'Nincs aktív étkeztetés';
        }

        return match ($currentMealSetting->mode) {
            StudentMealSetting::MODE_PACKAGE => 'Aktív menücsomag',
            StudentMealSetting::MODE_CUSTOM => 'Egyedi étkezések',
            default => 'Intézményi alapértelmezett',
        };
    }
}
