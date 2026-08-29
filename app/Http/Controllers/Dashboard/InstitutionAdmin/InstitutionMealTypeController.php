<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionMealType;
use App\Models\MealType;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstitutionMealTypeController extends Controller
{
    public function index(): View
    {
        $institution = $this->institution();
        $this->ensureMealTypes($institution);

        $today = Carbon::today();
        $mealTypes = InstitutionMealType::query()
            ->with([
                'mealType',
                'mealPrices' => fn ($query) => $query->orderByDesc('valid_from')->orderByDesc('id'),
            ])
            ->where('institution_id', $institution->id)
            ->join('meal_types', 'meal_types.id', '=', 'institution_meal_types.meal_type_id')
            ->orderBy('institution_meal_types.display_order')
            ->orderBy('meal_types.default_order')
            ->orderBy('meal_types.name')
            ->select('institution_meal_types.*')
            ->get()
            ->map(function (InstitutionMealType $institutionMealType) use ($today) {
                $currentPrice = $institutionMealType->mealPrices
                    ->first(function ($price) use ($today) {
                        return $price->valid_from->lte($today)
                            && ($price->valid_to === null || $price->valid_to->gte($today));
                    });

                $nextPrice = $institutionMealType->mealPrices
                    ->filter(fn ($price) => $price->valid_from->gt($today))
                    ->sortBy('valid_from')
                    ->first();

                $institutionMealType->setRelation('currentPrice', $currentPrice);
                $institutionMealType->setRelation('nextPrice', $nextPrice);

                return $institutionMealType;
            });

        $stats = [
            'total' => $mealTypes->count(),
            'active' => $mealTypes->where('is_active', true)->count(),
            'selectable' => $mealTypes->where('is_parent_selectable', true)->count(),
            'priced' => $mealTypes->filter(fn ($mealType) => $mealType->getRelation('currentPrice') !== null)->count(),
        ];

        return view('dashboard.institution_admin.institution.meal-types.index', [
            'institution' => $institution,
            'mealTypes' => $mealTypes,
            'stats' => $stats,
        ]);
    }

    public function edit(InstitutionMealType $institutionMealType): View
    {
        $institution = $this->institution();
        $this->authorizeMealType($institutionMealType, $institution);

        $institutionMealType->load('mealType');

        // Az árat korábban külön oldalakon (Új ár / Ártörténet) kellett
        // kezelni, elszakítva az "Aktív" stb. kapcsolóktól - ez UX
        // szempontból nem volt praktikus, mert egy étkezéstípus teljes
        // beállításához (aktív-e, mennyibe kerül) több oldal között kellett
        // ugrálni. Mostantól az aktuális/következő ár és az új ár felvitele
        // is ezen az egy oldalon történik - ld. lent a $currentPrice /
        // $nextPrice / $recentPrices átadását a view-nak.
        $today = Carbon::today();
        $prices = InstitutionMealPrice::query()
            ->with('createdBy')
            ->where('institution_meal_type_id', $institutionMealType->id)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get();

        $currentPrice = $prices->first(function (InstitutionMealPrice $price) use ($today) {
            return $price->valid_from->lte($today)
                && ($price->valid_to === null || $price->valid_to->gte($today));
        });

        $nextPrice = $prices
            ->filter(fn (InstitutionMealPrice $price) => $price->valid_from->gt($today))
            ->sortBy('valid_from')
            ->first();

        return view('dashboard.institution_admin.institution.meal-types.edit', [
            'institution' => $institution,
            'institutionMealType' => $institutionMealType,
            'currentPrice' => $currentPrice,
            'nextPrice' => $nextPrice,
            'recentPrices' => $prices->take(5),
        ]);
    }

    public function update(Request $request, InstitutionMealType $institutionMealType): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeMealType($institutionMealType, $institution);

        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'is_parent_selectable' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'note' => ['nullable', 'string'],
        ]);

        $institutionMealType->update([
            'is_active' => $request->boolean('is_active'),
            'is_parent_selectable' => $request->boolean('is_parent_selectable'),
            'is_required' => $request->boolean('is_required'),
            'display_order' => $validated['display_order'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('dashboard.institution.meal-types.edit', $institutionMealType)
            ->with('success', 'Az étkezéstípus beállításai frissítve lettek.');
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function ensureMealTypes(Institution $institution): void
    {
        MealType::query()
            ->orderBy('default_order')
            ->get()
            ->each(function (MealType $mealType) use ($institution) {
                InstitutionMealType::firstOrCreate(
                    [
                        'institution_id' => $institution->id,
                        'meal_type_id' => $mealType->id,
                    ],
                    [
                        'display_order' => $mealType->default_order,
                    ]
                );
            });
    }

    private function authorizeMealType(InstitutionMealType $institutionMealType, Institution $institution): void
    {
        abort_if($institutionMealType->institution_id !== $institution->id, 403);
    }
}
