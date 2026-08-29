<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ParentHandbookController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        // Ugyanaz a logika, mint a szülői oldalsávban (parent.partials.sidebar) -
        // egy vagy több kapcsolt intézmény nevét jeleníti meg egységesen.
        $institutions = $user->guardians()
            ->where('active', true)
            ->with('institution')
            ->get()
            ->pluck('institution.name')
            ->filter()
            ->unique()
            ->values();

        $institutionLabel = match (true) {
            $institutions->isEmpty() => 'Kapcsolt intézmény',
            $institutions->count() === 1 => $institutions->first(),
            default => $institutions->count().' kapcsolt intézmény',
        };

        $pageDate = now(config('digifood.business_timezone', config('app.timezone')));

        return view('parent.handbook.index', compact('institutionLabel', 'pageDate'));
    }
}
