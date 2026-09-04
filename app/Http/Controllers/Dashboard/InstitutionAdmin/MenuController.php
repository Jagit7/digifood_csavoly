<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MenuController extends Controller
{
    /**
     * Heti étlapok listája
     */
    public function index()
    {
        $institution = auth()->user()->institutions()->first();

        $menus = Menu::where('institution_id', $institution->id)
            ->orderByDesc('week_start')
            ->paginate(15);

        $activeWeeklyMenus = Menu::where('institution_id', $institution->id)
            ->where('type', 'weekly')
            ->where('active', true)
            ->count();

        $dietaryMenus = Menu::where('institution_id', $institution->id)
            ->where('type', 'dietary')
            ->where('active', true)
            ->count();

        $abMenus = Menu::where('institution_id', $institution->id)
            ->where('type', 'ab')
            ->where('active', true)
            ->count();

        /*
        |----------------------------------------------------------
        | Következő feltöltendő hét
        |----------------------------------------------------------
        */

        $lastMenu = Menu::where('institution_id', $institution->id)
            ->where('type', 'weekly')
            ->orderByDesc('week_start')
            ->first();

        if ($lastMenu) {

            $nextMonday = $lastMenu->week_start->copy()->addWeek();

        } else {

            $today = now();

            $nextMonday = $today->copy()->startOfWeek();

            if ($today->gt($nextMonday->copy()->addDays(4))) {
                $nextMonday->addWeek();
            }
        }

        $nextWeekLabel =
            $nextMonday->format('Y.m.d.')
            .' - '.
            $nextMonday->copy()->addDays(4)->format('Y.m.d.');

        return view('dashboard.institution_admin.menus.index', compact(
            'institution',
            'menus',
            'activeWeeklyMenus',
            'dietaryMenus',
            'abMenus',
            'nextWeekLabel'
        ));
    }

    /**
     * Új heti étlap feltöltése
     */
    public function create()
    {
        $institution = Auth::user()->institutions()->first();

        return view('dashboard.institution_admin.menus.create', compact(
            'institution'
        ));
    }

    /**
     * Mentés
     */
    public function store(Request $request)
    {
        $institution = Auth::user()->institutions()->first();

        if (!$institution) {
            abort(403, 'Nincs intézmény hozzárendelve.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'week_start' => ['required', 'date'],
            'week_end' => ['required', 'date', 'after_or_equal:week_start'],
            'type' => ['required', 'in:weekly,dietary,ab'],
            'menu_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'description' => ['nullable', 'string'],
        ]);

        $file = $request->file('menu_file');

        $filePath = $file->store('menus', 'public');

        $weekStart = Carbon::parse($validated['week_start'])->startOfDay();
        $weekEnd = Carbon::parse($validated['week_end'])->startOfDay();

        $hasSaturday = $weekEnd->isSaturday();

        Menu::create([
            'institution_id' => $institution->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'has_saturday' => $hasSaturday,
            'saturday_date' => $hasSaturday ? $weekEnd : null,
            'saturday_note' => $hasSaturday ? 'Szombati étkezés is van.' : null,
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'active' => true,
            'published_at' => now(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('dashboard.institution.menus.index')
            ->with('success', 'Az étlap sikeresen feltöltve.');
    }

    public function edit(Menu $menu)
    {
        $institution = Auth::user()->institutions()->first();

        if (!$institution || $menu->institution_id !== $institution->id) {
            abort(403);
        }

        return view('dashboard.institution_admin.menus.edit', compact(
            'institution',
            'menu'
        ));
    }

    public function update(Request $request, Menu $menu)
    {
        $institution = Auth::user()->institutions()->first();

        if (!$institution || $menu->institution_id !== $institution->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'week_start' => ['required', 'date'],
            'week_end' => ['required', 'date', 'after_or_equal:week_start'],
            'type' => ['required', 'in:weekly,dietary,ab'],
            'menu_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $weekStart = Carbon::parse($validated['week_start'])->startOfDay();
        $weekEnd = Carbon::parse($validated['week_end'])->startOfDay();

        $hasSaturday = $weekEnd->isSaturday();

        $data = [
            'type' => $validated['type'],
            'title' => $validated['title'],
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'has_saturday' => $hasSaturday,
            'saturday_date' => $hasSaturday ? $weekEnd : null,
            'saturday_note' => $hasSaturday ? 'Szombati étkezés is van.' : null,
            'active' => $request->boolean('active'),
            'updated_by' => Auth::id(),
        ];

        if ($request->hasFile('menu_file')) {

            // 1. Régi fájl törlése
            if ($menu->file_path && Storage::disk('public')->exists($menu->file_path)) {
                Storage::disk('public')->delete($menu->file_path);
            }

            // 2. Új fájl mentése
            $file = $request->file('menu_file');

            $data['file_path'] = $file->store('menus', 'public');
            $data['file_name'] = $file->getClientOriginalName();
            $data['mime_type'] = $file->getMimeType();
            $data['file_size'] = $file->getSize();
        }

        $menu->update($data);

        return redirect()
            ->route('dashboard.institution.menus.index')
            ->with('success', 'Az étlap sikeresen módosítva.');
    }

    public function show(Menu $menu)
    {
        $institution = Auth::user()->institutions()->first();

        if (!$institution || $menu->institution_id !== $institution->id) {
            abort(403);
        }

        return view('dashboard.institution_admin.menus.show', compact(
            'institution',
            'menu'
        ));
    }

    public function destroy(Menu $menu)
    {
        $institution = Auth::user()->institutions()->first();

        if (!$institution || $menu->institution_id !== $institution->id) {
            abort(403);
        }

        if ($menu->file_path && Storage::disk('public')->exists($menu->file_path)) {
            Storage::disk('public')->delete($menu->file_path);
        }

        $menu->delete();

        return back()->with('success', 'Az étlap törölve.');
    }

    public function download(Menu $menu)
    {
        $institution = Auth::user()->institutions()->first();

        if (!$institution || $menu->institution_id !== $institution->id) {
            abort(403);
        }

        if (!$menu->file_path || !Storage::disk('public')->exists($menu->file_path)) {
            abort(404, 'A fájl nem található.');
        }

        return Storage::disk('public')->download(
            $menu->file_path,
            $menu->file_name
        );
    }

    /**
     * Az étlap fájljának böngészőben megjelenített (inline) előnézete.
     *
     * A fájlt közvetlenül a "public" storage lemezről olvassuk fel és
     * streameljük ki, ezért nem függ attól, hogy a public/storage
     * szimbolikus link létrejött-e a szerveren (php artisan storage:link).
     * A Content-Type-ot a mentett MIME-type (vagy megbízhatatlan MIME-type
     * hiányában a fájlkiterjesztés) alapján, nem törékeny string
     * összehasonlítással állapítjuk meg (lásd Menu::previewMimeType()).
     */
    public function preview(Menu $menu)
    {
        $institution = Auth::user()->institutions()->first();

        if (!$institution || $menu->institution_id !== $institution->id) {
            abort(403);
        }

        if (!$menu->file_path || !Storage::disk('public')->exists($menu->file_path)) {
            abort(404, 'A fájl nem található.');
        }

        return response()->file(
            Storage::disk('public')->path($menu->file_path),
            [
                'Content-Type' => $menu->previewMimeType(),
                'Content-Disposition' => 'inline; filename="'.$menu->file_name.'"',
            ]
        );
    }
}