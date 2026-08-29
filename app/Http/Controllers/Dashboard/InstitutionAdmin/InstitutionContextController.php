<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Support\AdminInstitutionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstitutionContextController extends Controller
{
    public function __construct(
        private readonly AdminInstitutionContext $context
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', 'integer'],
        ]);

        $institution = $this->context->switchToInstitution(
            $request->user(),
            (int) $validated['institution_id']
        );

        if (! $institution) {
            abort(403, 'A kiválasztott intézmény nem érhető el ezzel a fiókkal.');
        }

        return redirect()->back()->with('success', 'Aktív intézmény frissítve.');
    }
}
