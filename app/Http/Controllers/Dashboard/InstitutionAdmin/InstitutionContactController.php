<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstitutionContactController extends Controller
{
    public function index(): View
    {
        $institution = $this->institution();

        $contacts = InstitutionContact::query()
            ->where('institution_id', $institution->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15);

        $stats = [
            'total' => InstitutionContact::where('institution_id', $institution->id)->count(),
            'active' => InstitutionContact::where('institution_id', $institution->id)->where('is_active', true)->count(),
            'primary' => InstitutionContact::where('institution_id', $institution->id)->where('is_primary', true)->count(),
            'with_email' => InstitutionContact::where('institution_id', $institution->id)->whereNotNull('email')->where('email', '!=', '')->count(),
        ];

        return view('dashboard.institution_admin.contacts.index', compact(
            'institution',
            'contacts',
            'stats'
        ));
    }

    public function create(): View
    {
        return view('dashboard.institution_admin.contacts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $validated = $this->validateData($request);

        if ($request->boolean('is_primary')) {
            $this->clearPrimaryContact($institution->id);
        }

        InstitutionContact::create([
            ...$validated,
            'institution_id' => $institution->id,
            'is_primary' => $request->boolean('is_primary'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('dashboard.institution.contacts.index')
            ->with('success', 'A kapcsolattartó létrehozva.');
    }

    public function edit(InstitutionContact $contact): View
    {
        $institution = $this->institution();
        abort_if($contact->institution_id !== $institution->id, 403);

        return view('dashboard.institution_admin.contacts.edit', compact('contact'));
    }

    public function update(Request $request, InstitutionContact $contact): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($contact->institution_id !== $institution->id, 403);

        $validated = $this->validateData($request);

        if ($request->boolean('is_primary')) {
            $this->clearPrimaryContact($institution->id, $contact->id);
        }

        $contact->update([
            ...$validated,
            'is_primary' => $request->boolean('is_primary'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('dashboard.institution.contacts.index')
            ->with('success', 'A kapcsolattartó frissítve lett.');
    }

    public function destroy(InstitutionContact $contact): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($contact->institution_id !== $institution->id, 403);

        $contact->delete();

        return redirect()
            ->route('dashboard.institution.contacts.index')
            ->with('success', 'A kapcsolattartó törölve lett.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'role' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function clearPrimaryContact(int $institutionId, ?int $exceptId = null): void
    {
        InstitutionContact::query()
            ->where('institution_id', $institutionId)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['is_primary' => false]);
    }
}
