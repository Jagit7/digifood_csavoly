<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DietaryMenuController extends Controller
{
    /**
     * A diétás menü kezelése (külön útvonalon: /institution-admin/menus/dietary)
     * jelenleg nincs kidolgozva - eddig üres osztály volt, ami routolva van,
     * tehát meghívás esetén 500-as hibát dobott volna. Amíg a tényleges
     * funkció (saját diétás menütáblázat, import stb.) nincs megtervezve,
     * biztonságosan visszairányítunk a normál menükezelésre, hogy ne
     * dobjon hibát senkinek.
     */
    public function index(): RedirectResponse
    {
        return redirect()
            ->route('dashboard.institution.menus.index')
            ->with('info', 'A diétás menü önálló kezelése még nincs kialakítva.');
    }

    public function create(): RedirectResponse
    {
        return redirect()
            ->route('dashboard.institution.menus.index')
            ->with('info', 'A diétás menü önálló kezelése még nincs kialakítva.');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()
            ->route('dashboard.institution.menus.index')
            ->with('info', 'A diétás menü önálló kezelése még nincs kialakítva.');
    }
}
