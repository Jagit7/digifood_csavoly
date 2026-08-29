<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionMealType;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InstitutionMealPriceController extends Controller
{
    public function create(InstitutionMealType $institutionMealType): View
    {
        $institution = $this->institution();
        $this->authorizeMealType($institutionMealType, $institution);
        $institutionMealType->load('mealType');

        return view('dashboard.institution_admin.institution.meal-types.prices.create', [
            'institution' => $institution,
            'institutionMealType' => $institutionMealType,
        ]);
    }

    public function store(Request $request, InstitutionMealType $institutionMealType): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeMealType($institutionMealType, $institution);

        // A "note" mezőnév ütközne az étkezéstípus-beállítások (Aktív,
        // Szülő választhatja stb.) form saját "note" mezőjével, mióta az
        // árfelvitel is ugyanazon az egyesített szerkesztő oldalon
        // jelenik meg - ezért itt (és az edit()-ben, valamint a megosztott
        // _form.blade.php partialban) "price_note" a mezőnév, hogy a két
        // form validációs hibái ne keveredjenek egymásba.
        $validated = $request->validate([
            'price' => ['required', 'integer', 'min:1'],
            'valid_from' => ['required', 'date'],
            'price_note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request, $institutionMealType) {
            $openPrice = InstitutionMealPrice::query()
                ->where('institution_meal_type_id', $institutionMealType->id)
                ->whereNull('valid_to')
                ->orderByDesc('valid_from')
                ->orderByDesc('id')
                ->first();

            if ($openPrice) {
                $openPrice->update([
                    'valid_to' => Carbon::parse($validated['valid_from'])->subDay()->toDateString(),
                ]);
            }

            InstitutionMealPrice::create([
                'institution_meal_type_id' => $institutionMealType->id,
                'price' => $validated['price'],
                'valid_from' => $validated['valid_from'],
                'valid_to' => null,
                'created_by' => $request->user()->id,
                'note' => $validated['price_note'] ?? null,
            ]);
        });

        // Korábban ide az önálló Ártörténet oldalra irányítottunk vissza -
        // mostantól az árfelvitel az étkezéstípus egyesített szerkesztő
        // oldalán (aktív/kötelező kapcsolók + aktuális ár egy helyen)
        // történik, úgyhogy oda térünk vissza, hogy a felhasználó egyben
        // lássa az imént elmentett új árat is.
        return redirect()
            ->route('dashboard.institution.meal-types.edit', $institutionMealType)
            ->with('success', 'Az új ár rögzítve lett.');
    }

    public function edit(InstitutionMealType $institutionMealType, InstitutionMealPrice $price): View
    {
        $institution = $this->institution();
        $this->authorizeMealType($institutionMealType, $institution);
        $this->authorizePrice($institutionMealType, $price);
        $institutionMealType->load('mealType');

        return view('dashboard.institution_admin.institution.meal-types.prices.edit', [
            'institution' => $institution,
            'institutionMealType' => $institutionMealType,
            'price' => $price,
        ]);
    }

    public function update(Request $request, InstitutionMealType $institutionMealType, InstitutionMealPrice $price): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeMealType($institutionMealType, $institution);
        $this->authorizePrice($institutionMealType, $price);

        $validated = $request->validate([
            'price' => ['required', 'integer', 'min:1'],
            'valid_from' => ['required', 'date'],
            'price_note' => ['nullable', 'string'],
        ]);

        $price->update([
            'price' => $validated['price'],
            'valid_from' => $validated['valid_from'],
            'note' => $validated['price_note'] ?? null,
        ]);

        return redirect()
            ->route('dashboard.institution.meal-types.prices.history', $institutionMealType)
            ->with('success', 'Az ár módosítva lett.');
    }

    public function history(InstitutionMealType $institutionMealType): View
    {
        $institution = $this->institution();
        $this->authorizeMealType($institutionMealType, $institution);

        $institutionMealType->load('mealType');
        $prices = InstitutionMealPrice::query()
            ->with('createdBy')
            ->where('institution_meal_type_id', $institutionMealType->id)
            ->orderByDesc('valid_from')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('dashboard.institution_admin.institution.meal-types.prices.history', [
            'institution' => $institution,
            'institutionMealType' => $institutionMealType,
            'prices' => $prices,
        ]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function authorizeMealType(InstitutionMealType $institutionMealType, Institution $institution): void
    {
        abort_if($institutionMealType->institution_id !== $institution->id, 403);
    }

    private function authorizePrice(InstitutionMealType $institutionMealType, InstitutionMealPrice $price): void
    {
        abort_if($price->institution_meal_type_id !== $institutionMealType->id, 403);
    }
}
