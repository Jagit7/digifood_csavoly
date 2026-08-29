<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealType;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstitutionMealPackageController extends Controller
{
    public function index(): View
    {
        $institution = $this->institution();
        $packages = InstitutionMealPackage::query()
            ->with(['mealTypes.mealType'])
            ->where('institution_id', $institution->id)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => $packages->count(),
            'active' => $packages->where('is_active', true)->count(),
            'default' => $packages->where('is_default', true)->count(),
            'custom_price' => $packages->where('pricing_mode', InstitutionMealPackage::PRICING_MODE_CUSTOM_PRICE)->count(),
        ];

        return view('dashboard.institution_admin.institution.meal-packages.index', [
            'institution' => $institution,
            'packages' => $packages,
            'stats' => $stats,
            'pricingModeLabels' => InstitutionMealPackage::PRICING_MODE_LABELS,
        ]);
    }

    public function create(): View
    {
        $institution = $this->institution();

        return view('dashboard.institution_admin.institution.meal-packages.create', [
            'institution' => $institution,
            'package' => new InstitutionMealPackage([
                'is_active' => true,
                'is_default' => false,
                'display_order' => 0,
                'pricing_mode' => InstitutionMealPackage::PRICING_MODE_COMPONENT_SUM,
            ]),
            'availableMealTypes' => $this->availableMealTypes($institution),
            'selectedMealTypeIds' => [],
            'pricingModeLabels' => InstitutionMealPackage::PRICING_MODE_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $validated = $this->validateData($request, $institution);
        $wantsDefault = $request->boolean('is_default');

        try {
            DB::transaction(function () use ($request, $institution, $validated, $wantsDefault) {
                // A siblingek "is_default" flag-jét MINDIG a saját sor
                // létrehozása/frissítése ELŐTT töröljük - az
                // institution_meal_packages tábla default_institution_id
                // generált oszlopán lévő unique index (ld. migráció)
                // intézményenként csak egyetlen is_default=true sort enged.
                // Ha fordított sorrendben járnánk el (előbb a saját sor,
                // utána a többi törlése), a saját sor beszúrása/módosítása
                // azonnal unique-ütközésbe futna, amíg a régi alapértelmezett
                // csomag még be van jelölve.
                if ($wantsDefault) {
                    $this->clearOtherDefaults($institution->id, null);
                }

                $package = InstitutionMealPackage::create([
                    'institution_id' => $institution->id,
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'is_active' => $request->boolean('is_active', true),
                    'is_default' => $wantsDefault,
                    'display_order' => $validated['display_order'],
                    'pricing_mode' => $validated['pricing_mode'],
                    'custom_price' => $this->resolveCustomPrice($validated),
                    'created_by' => $request->user()->id,
                ]);

                $this->syncMealTypes($package, $validated['institution_meal_type_ids']);
            });
        } catch (QueryException) {
            return redirect()
                ->route('dashboard.institution.meal-packages.index')
                ->with('error', 'A menücsomag mentése időközben ütközött egy másik módosítással. Kérjük, próbálja újra.');
        }

        return redirect()
            ->route('dashboard.institution.meal-packages.index')
            ->with('success', 'A menücsomag létrehozva.');
    }

    public function show(InstitutionMealPackage $mealPackage): View
    {
        $institution = $this->institution();
        $this->authorizeMealPackage($mealPackage, $institution);

        $mealPackage->load(['mealTypes.mealType', 'createdBy']);

        return view('dashboard.institution_admin.institution.meal-packages.show', [
            'institution' => $institution,
            'package' => $mealPackage,
            'pricingModeLabels' => InstitutionMealPackage::PRICING_MODE_LABELS,
        ]);
    }

    public function edit(InstitutionMealPackage $mealPackage): View
    {
        $institution = $this->institution();
        $this->authorizeMealPackage($mealPackage, $institution);
        $mealPackage->load('mealTypes');

        return view('dashboard.institution_admin.institution.meal-packages.edit', [
            'institution' => $institution,
            'package' => $mealPackage,
            'availableMealTypes' => $this->availableMealTypes($institution),
            'selectedMealTypeIds' => $mealPackage->mealTypes->pluck('id')->all(),
            'pricingModeLabels' => InstitutionMealPackage::PRICING_MODE_LABELS,
        ]);
    }

    public function update(Request $request, InstitutionMealPackage $mealPackage): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeMealPackage($mealPackage, $institution);
        $validated = $this->validateData($request, $institution, $mealPackage);
        $wantsDefault = $request->boolean('is_default');

        try {
            DB::transaction(function () use ($request, $mealPackage, $validated, $wantsDefault) {
                if ($wantsDefault) {
                    $this->clearOtherDefaults($mealPackage->institution_id, $mealPackage->id);
                }

                $mealPackage->update([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'is_active' => $request->boolean('is_active'),
                    'is_default' => $wantsDefault,
                    'display_order' => $validated['display_order'],
                    'pricing_mode' => $validated['pricing_mode'],
                    'custom_price' => $this->resolveCustomPrice($validated),
                ]);

                $this->syncMealTypes($mealPackage, $validated['institution_meal_type_ids']);
            });
        } catch (QueryException) {
            return redirect()
                ->route('dashboard.institution.meal-packages.index')
                ->with('error', 'A menücsomag mentése időközben ütközött egy másik módosítással. Kérjük, próbálja újra.');
        }

        return redirect()
            ->route('dashboard.institution.meal-packages.index')
            ->with('success', 'A menücsomag frissítve lett.');
    }

    public function destroy(InstitutionMealPackage $mealPackage): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeMealPackage($mealPackage, $institution);

        try {
            $mealPackage->delete();
        } catch (QueryException $exception) {
            return redirect()
                ->route('dashboard.institution.meal-packages.index')
                ->with('error', 'A menücsomag jelenleg nem törölhető, mert más rekordok hivatkoznak rá.');
        }

        return redirect()
            ->route('dashboard.institution.meal-packages.index')
            ->with('success', 'A menücsomag törölve lett.');
    }

    private function validateData(
        Request $request,
        Institution $institution,
        ?InstitutionMealPackage $mealPackage = null
    ): array {
        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('institution_meal_packages', 'name')
                    ->where('institution_id', $institution->id)
                    ->ignore($mealPackage?->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'pricing_mode' => ['required', Rule::in(array_keys(InstitutionMealPackage::PRICING_MODE_LABELS))],
            'custom_price' => [
                Rule::requiredIf(fn () => $request->input('pricing_mode') === InstitutionMealPackage::PRICING_MODE_CUSTOM_PRICE),
                'nullable',
                'integer',
                'min:1',
                'max:2147483647',
            ],
            'institution_meal_type_ids' => ['required', 'array', 'min:1'],
            'institution_meal_type_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('institution_meal_types', 'id')
                    ->where(fn ($query) => $query
                        ->where('institution_id', $institution->id)
                        ->where('is_active', true)),
            ],
        ]);
    }

    private function availableMealTypes(Institution $institution)
    {
        return InstitutionMealType::query()
            ->with('mealType')
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->whereHas('mealType')
            ->join('meal_types', 'meal_types.id', '=', 'institution_meal_types.meal_type_id')
            ->orderBy('institution_meal_types.display_order')
            ->orderBy('meal_types.default_order')
            ->orderBy('meal_types.name')
            ->select('institution_meal_types.*')
            ->get();
    }

    private function syncMealTypes(InstitutionMealPackage $mealPackage, array $institutionMealTypeIds): void
    {
        $syncData = [];

        foreach (array_values($institutionMealTypeIds) as $displayOrder => $institutionMealTypeId) {
            $syncData[$institutionMealTypeId] = [
                'display_order' => $displayOrder,
            ];
        }

        $mealPackage->mealTypes()->sync($syncData);
    }

    /**
     * A 'custom_price' mezőt kizárólag 'custom_price' árképzési mód esetén
     * tároljuk - ha az admin visszavált 'component_sum' módra, a korábban
     * esetleg megadott egyedi ár nem maradhat "árva" adat a rekordon (ld.
     * PaymentObligationCalculatorService::resolveDailyPrice() - az ottani
     * logika a pricing_mode alapján dönt, de a felesleges custom_price
     * megőrzése félrevezető lenne pl. exportokban/riportokban).
     */
    private function resolveCustomPrice(array $validated): ?int
    {
        if ($validated['pricing_mode'] !== InstitutionMealPackage::PRICING_MODE_CUSTOM_PRICE) {
            return null;
        }

        return $validated['custom_price'];
    }

    private function clearOtherDefaults(int $institutionId, ?int $exceptId): void
    {
        InstitutionMealPackage::query()
            ->where('institution_id', $institutionId)
            ->where('is_default', true)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function authorizeMealPackage(InstitutionMealPackage $mealPackage, Institution $institution): void
    {
        abort_if($mealPackage->institution_id !== $institution->id, 403);
    }
}
