<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SuperAdmin\UpsertInstitutionBillingRateRequest;
use App\Models\Institution;
use App\Models\InstitutionBillingRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InstitutionBillingRateController extends Controller
{
    public function index(Institution $institution): View
    {
        $institution->load('billingPartner:id,name');

        $billingRates = $institution->billingRates()
            ->orderByDesc('valid_from')
            ->get();

        return view('dashboard.superadmin.institution_billing_rates.index', [
            'institution' => $institution,
            'billingRates' => $billingRates,
            'today' => today(),
        ]);
    }

    public function create(Institution $institution): View
    {
        $institution->load('billingPartner:id,name');

        return view('dashboard.superadmin.institution_billing_rates.create', [
            'institution' => $institution,
        ]);
    }

    public function store(UpsertInstitutionBillingRateRequest $request, Institution $institution): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated());
        $validated['institution_id'] = $institution->id;

        InstitutionBillingRate::create($validated);

        return redirect()
            ->route('dashboard.institutions.billing-rates.index', $institution)
            ->with('success', 'Díjszabás sikeresen létrehozva.');
    }

    public function edit(Institution $institution, InstitutionBillingRate $billingRate): View
    {
        $this->ensureRateBelongsToInstitution($institution, $billingRate);
        $institution->load('billingPartner:id,name');

        return view('dashboard.superadmin.institution_billing_rates.edit', [
            'institution' => $institution,
            'billingRate' => $billingRate,
        ]);
    }

    public function update(
        UpsertInstitutionBillingRateRequest $request,
        Institution $institution,
        InstitutionBillingRate $billingRate
    ): RedirectResponse {
        $this->ensureRateBelongsToInstitution($institution, $billingRate);

        $billingRate->update($this->normalizePayload($request->validated()));

        return redirect()
            ->route('dashboard.institutions.billing-rates.index', $institution)
            ->with('success', 'Díjszabás sikeresen frissítve.');
    }

    private function ensureRateBelongsToInstitution(Institution $institution, InstitutionBillingRate $billingRate): void
    {
        abort_if($billingRate->institution_id !== $institution->id, 404);
    }

    private function normalizePayload(array $validated): array
    {
        return array_merge([
            'price_per_child' => null,
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => null,
            'valid_to' => null,
            'note' => null,
        ], $validated);
    }
}
