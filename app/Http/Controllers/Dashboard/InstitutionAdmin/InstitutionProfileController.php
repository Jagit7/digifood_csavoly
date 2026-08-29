<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstitutionProfileController extends Controller
{
    public function edit(): View
    {
        $institution = auth()->user()
            ->institutions()
            ->with([
                'users' => fn ($query) => $query
                    ->whereIn('role', ['institution_admin', 'kitchen', 'municipality'])
                    ->orderBy('name'),
                'adminInvitations' => fn ($query) => $query
                    ->whereNull('accepted_at')
                    ->orderByDesc('created_at'),
            ])
            ->firstOrFail();

        $roleLabels = [
            'institution_admin' => 'Intézményi admin',
            'kitchen' => 'Konyha',
            'municipality' => 'Önkormányzat',
        ];

        return view('dashboard.institution_admin.institution.profile', [
            'institution' => $institution,
            'roleLabels' => $roleLabels,
            'activeUsersCount' => $institution->users->where('is_active', true)->count(),
            'pendingInvitationsCount' => $institution->adminInvitations->count(),
            'billingCompleteness' => $this->billingCompleteness($institution),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $institution = $this->institution();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:ovoda,iskola,bolcsode'],
            'om_identifier' => [
                'nullable',
                'digits:6',
                'unique:institutions,om_identifier,'.$institution->id,
            ],
            'address_zip' => ['nullable', 'string', 'max:10'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_tax_number' => ['nullable', 'string', 'max:50'],
            'billing_zip' => ['nullable', 'string', 'max:10'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_payment_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        if (! empty($validated['om_identifier'])) {
            $validated['om_identifier'] = preg_replace('/\s+/', '', $validated['om_identifier']);
        }

        $institution->update($validated);

        return redirect()
            ->route('dashboard.institution.profile.edit')
            ->with('success', 'Az intézmény profilja frissítve lett.');
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function billingCompleteness(Institution $institution): int
    {
        $fields = [
            $institution->billing_name,
            $institution->billing_tax_number,
            $institution->billing_zip,
            $institution->billing_city,
            $institution->billing_address,
            $institution->billing_payment_due_days,
        ];

        $filled = collect($fields)->filter(fn ($value) => filled($value))->count();

        return (int) round(($filled / count($fields)) * 100);
    }
}
