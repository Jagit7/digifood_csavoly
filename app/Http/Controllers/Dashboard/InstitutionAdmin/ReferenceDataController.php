<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReferenceDataController extends Controller
{
    public function index(): View
    {
        $institution = $this->institution();
        $mealSetting = InstitutionMealSetting::firstOrCreate([
            'institution_id' => $institution->id,
        ]);

        if ($mealSetting->wasRecentlyCreated) {
            $this->ensureDefaults($institution);
        }

        $discounts = DiscountType::where('institution_id', $institution->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderByDesc('percentage')
            ->get();
        $allergens = DietaryRestriction::where('institution_id', $institution->id)
            ->where('type', DietaryRestriction::TYPE_ALLERGEN)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $intolerances = DietaryRestriction::where('institution_id', $institution->id)
            ->where('type', DietaryRestriction::TYPE_INTOLERANCE)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('dashboard.institution_admin.reference-data.index', compact(
            'institution',
            'mealSetting',
            'discounts',
            'allergens',
            'intolerances'
        ));
    }

    public function updateCancellation(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $validated = $request->validate([
            'cancellation_hour' => ['required', 'integer', 'between:0,23'],
            'cancellation_minute' => ['required', 'integer', Rule::in([0, 15, 30, 45])],
        ]);

        InstitutionMealSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            $validated
        );

        return back()->with('success', 'A lemondási határidő frissítve.');
    }

    public function storeDiscount(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $validated = $this->validateDiscount($request, $institution);

        DiscountType::create([
            ...$validated,
            'institution_id' => $institution->id,
            'active' => $request->boolean('active'),
            'sort_order' => (int) DiscountType::where('institution_id', $institution->id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'A kedvezménytípus létrehozva.');
    }

    public function updateDiscount(Request $request, DiscountType $discountType): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeInstitutionRecord($discountType->institution_id, $institution);
        $validated = $this->validateDiscount($request, $institution, $discountType);

        $discountType->update([
            ...$validated,
            'active' => $request->boolean('active'),
        ]);

        return back()->with('success', 'A kedvezménytípus módosítva.');
    }

    public function destroyDiscount(DiscountType $discountType): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeInstitutionRecord($discountType->institution_id, $institution);
        $discountType->delete();

        return back()->with('success', 'A kedvezménytípus törölve.');
    }

    public function storeRestriction(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $validated = $this->validateRestriction($request, $institution);

        DietaryRestriction::create([
            ...$validated,
            'institution_id' => $institution->id,
            'active' => $request->boolean('active'),
            'sort_order' => (int) DietaryRestriction::where('institution_id', $institution->id)
                ->where('type', $validated['type'])
                ->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Az étrendi törzsadat létrehozva.');
    }

    public function updateRestriction(Request $request, DietaryRestriction $dietaryRestriction): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeInstitutionRecord($dietaryRestriction->institution_id, $institution);
        $validated = $this->validateRestriction($request, $institution, $dietaryRestriction);

        $dietaryRestriction->update([
            ...$validated,
            'active' => $request->boolean('active'),
        ]);

        return back()->with('success', 'Az étrendi törzsadat módosítva.');
    }

    public function destroyRestriction(DietaryRestriction $dietaryRestriction): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeInstitutionRecord($dietaryRestriction->institution_id, $institution);
        $dietaryRestriction->delete();

        return back()->with('success', 'Az étrendi törzsadat törölve.');
    }

    private function validateDiscount(
        Request $request,
        Institution $institution,
        ?DiscountType $discountType = null
    ): array {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('discount_types', 'name')
                    ->where('institution_id', $institution->id)
                    ->where('percentage', $request->input('percentage'))
                    ->ignore($discountType?->id),
            ],
            'percentage' => ['required', 'integer', 'between:0,100'],
        ], [
            'name.unique' => 'Ez a név és kedvezményszázalék már szerepel a listában.',
        ]);
    }

    private function validateRestriction(
        Request $request,
        Institution $institution,
        ?DietaryRestriction $dietaryRestriction = null
    ): array {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('dietary_restrictions', 'name')
                    ->where('institution_id', $institution->id)
                    ->where('type', $request->input('type'))
                    ->ignore($dietaryRestriction?->id),
            ],
            'type' => ['required', Rule::in([
                DietaryRestriction::TYPE_ALLERGEN,
                DietaryRestriction::TYPE_INTOLERANCE,
            ])],
        ], [
            'name.unique' => 'Ez a megnevezés már szerepel a kiválasztott listában.',
        ]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function authorizeInstitutionRecord(int $recordInstitutionId, Institution $institution): void
    {
        abort_if($recordInstitutionId !== $institution->id, 403);
    }

    private function ensureDefaults(Institution $institution): void
    {
        if (! DiscountType::where('institution_id', $institution->id)->exists()) {
            $discounts = [
                ['Kedvezmény nélkül', 0],
                ['Rendszeres gyermekvédelmi kedvezmény', 100],
                ['Rendszeres gyermekvédelmi kedvezmény', 50],
                ['Nevelésbe vett gyermek', 100],
                ['Nagycsaládos kedvezmény', 100],
                ['Nagycsaládos kedvezmény', 50],
                ['Egy főre jutó jövedelem alapján meghatározott kedvezmény', 100],
                ['Tartós beteg', 100],
                ['Tartós beteg', 50],
                ['SNI', 50],
                ['Tartós beteg a családban', 100],
            ];

            foreach ($discounts as $sortOrder => [$name, $percentage]) {
                DiscountType::create([
                    'institution_id' => $institution->id,
                    'name' => $name,
                    'percentage' => $percentage,
                    'active' => true,
                    'sort_order' => $sortOrder,
                ]);
            }
        }

        if (! DietaryRestriction::where('institution_id', $institution->id)->exists()) {
            foreach ([
                ['Glutén', DietaryRestriction::TYPE_INTOLERANCE],
                ['Tej', DietaryRestriction::TYPE_INTOLERANCE],
                ['Tojás', DietaryRestriction::TYPE_ALLERGEN],
                ['Mogyoró', DietaryRestriction::TYPE_ALLERGEN],
            ] as $sortOrder => [$name, $type]) {
                DietaryRestriction::create([
                    'institution_id' => $institution->id,
                    'name' => $name,
                    'type' => $type,
                    'active' => true,
                    'sort_order' => $sortOrder,
                ]);
            }
        }
    }
}
