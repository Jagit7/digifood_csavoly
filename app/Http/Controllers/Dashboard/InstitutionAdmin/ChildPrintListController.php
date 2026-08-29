<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\InstitutionMealPackage;
use App\Models\StudentMealSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ChildPrintListController extends Controller
{
    /**
     * Az összes opcionálisan bekapcsolható oszlop, a szűrő/beállító oldalon
     * megjelenő sorrendben. A sorszám és a név mindig szerepel a listán,
     * ezért azok nincsenek felsorolva itt.
     */
    public const COLUMNS = [
        'educational_identifier' => 'Oktatási azonosító (OM)',
        'group_name' => 'Osztály / csoport',
        'school_year' => 'Tanév',
        'meal_status' => 'Étkezési státusz',
        'meal_package' => 'Menücsomag',
        'discount' => 'Kedvezmény',
        'diet' => 'Allergia / étkezési korlátozás',
        'guardian_names' => 'Gondviselő(k) neve',
        'guardian_phones' => 'Gondviselő(k) telefonszáma',
        'guardian_emails' => 'Gondviselő(k) e-mail címe',
        'guardian_address' => 'Gondviselő(k) lakcíme',
        'billing_address' => 'Számlázási cím',
        'barcode_status' => 'Vonalkód állapota',
        'source_type' => 'Adatforrás',
    ];

    private const DEFAULT_COLUMNS = ['educational_identifier', 'guardian_phones', 'discount', 'diet'];

    public function index(): View
    {
        $institution = $this->currentAdminInstitution();

        $groups = Child::where('institution_id', $institution->id)
            ->where('active', true)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');

        return view('dashboard.institution_admin.children.print-list-config', [
            'institution' => $institution,
            'groups' => $groups,
            'columns' => self::COLUMNS,
            'defaultColumns' => self::DEFAULT_COLUMNS,
        ]);
    }

    public function generate(Request $request): View
    {
        $institution = $this->currentAdminInstitution();
        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();

        $groupName = trim((string) $request->input('group_name'));

        $selectedColumns = collect($request->input('columns', []))
            ->filter(fn ($column) => array_key_exists($column, self::COLUMNS))
            ->values()
            ->all();

        if (empty($selectedColumns)) {
            $selectedColumns = self::DEFAULT_COLUMNS;
        }

        // Az egyszerűség és a megbízhatóság kedvéért mindig betöltjük a
        // lehetséges oszlopokhoz szükséges kapcsolatokat - egy osztálynyi
        // gyermek esetén ez elhanyagolható terhelés, cserébe nem kell az
        // oszlopválasztástól függő, hibalehetőségekkel teli feltételes
        // eager loadingot írni.
        $children = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->when($groupName !== '', fn ($query) => $query->where('group_name', $groupName))
            ->with([
                'discountType',
                'discountPeriods' => fn ($query) => $query
                    ->with('discountType')
                    ->whereDate('valid_from', '<=', $today)
                    ->where(function ($query) use ($today) {
                        $query->whereNull('valid_to')
                            ->orWhereDate('valid_to', '>=', $today);
                    }),
                'dietaryRestrictions',
                'guardians' => fn ($query) => $query
                    ->orderByDesc('child_guardian.is_legal_representative')
                    ->orderBy('guardians.last_name')
                    ->orderBy('guardians.first_name'),
                'billingProfiles' => fn ($query) => $query
                    ->where('billing_profiles.active', true)
                    ->wherePivot('is_primary', true)
                    ->where(function ($inner) use ($today) {
                        $inner->whereNull('billing_profile_child.valid_to')
                            ->orWhereDate('billing_profile_child.valid_to', '>=', $today);
                    }),
            ])
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();

        $currentMealSettings = $this->currentMealSettingsForChildren($institution->id, $children->pluck('id'), $today);
        $latestMealSettings = $this->latestMealSettingsForChildren($institution->id, $children->pluck('id'));

        $children->each(function (Child $child) use ($currentMealSettings, $latestMealSettings) {
            $child->setRelation('currentMealSetting', $currentMealSettings->get($child->id));
            $child->setRelation('latestMealSetting', $latestMealSettings->get($child->id));
        });

        $defaultMealPackage = InstitutionMealPackage::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->first();

        $groupedChildren = $children->groupBy(
            fn (Child $child) => $child->group_name ?: 'Nincs megadva osztály/csoport'
        );

        return view('dashboard.institution_admin.children.print-list', [
            'institution' => $institution,
            'groupedChildren' => $groupedChildren,
            'selectedGroupName' => $groupName !== '' ? $groupName : null,
            'selectedColumns' => $selectedColumns,
            'columnLabels' => self::COLUMNS,
            'defaultMealPackage' => $defaultMealPackage,
            'today' => $today,
        ]);
    }

    private function currentMealSettingsForChildren(int $institutionId, Collection $childIds, string $today): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage:id,name',
                'mealTypes' => fn ($query) => $query->with('mealType:id,name'),
            ])
            ->whereIn('student_id', $childIds)
            ->where('institution_id', $institutionId)
            ->whereDate('valid_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $today);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }

    private function latestMealSettingsForChildren(int $institutionId, Collection $childIds): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage:id,name',
                'mealTypes' => fn ($query) => $query->with('mealType:id,name'),
                'closedBy:id,name',
            ])
            ->whereIn('student_id', $childIds)
            ->where('institution_id', $institutionId)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }
}
