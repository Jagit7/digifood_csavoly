<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingPartner;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BillingPartnerController extends Controller
{
    public function index(): View
    {
        $billingPartners = BillingPartner::query()
            ->withCount('institutions')
            ->orderBy('name')
            ->get();

        return view('dashboard.superadmin.billing_partners.index', compact('billingPartners'));
    }

    public function create(): View
    {
        return view('dashboard.superadmin.billing_partners.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $validated['active'] = $request->boolean('active');

        BillingPartner::create($validated);

        return redirect()
            ->route('dashboard.billing-partners.index')
            ->with('success', 'Számlázási partner sikeresen létrehozva.');
    }

    public function edit(BillingPartner $billingPartner): View
    {
        $billingPartner->load('institutions:id,name,billing_partner_id');

        $institutions = Institution::query()
            ->with('billingPartner:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'billing_partner_id']);

        return view('dashboard.superadmin.billing_partners.edit', compact('billingPartner', 'institutions'));
    }

    public function update(Request $request, BillingPartner $billingPartner): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $validated['active'] = $request->boolean('active');

        $selectedInstitutionIds = collect($request->input('institution_ids', []))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $conflictingInstitutionNames = Institution::query()
            ->with('billingPartner:id,name')
            ->whereIn('id', $selectedInstitutionIds)
            ->whereNotNull('billing_partner_id')
            ->where('billing_partner_id', '!=', $billingPartner->id)
            ->get()
            ->map(function (Institution $institution) {
                $partnerName = $institution->billingPartner?->name;

                return $partnerName
                    ? $institution->name . ' (' . $partnerName . ')'
                    : $institution->name;
            })
            ->all();

        if ($conflictingInstitutionNames !== []) {
            return back()
                ->withErrors([
                    'institution_ids' => 'Az alábbi intézmények már másik partnerhez tartoznak: ' . implode(', ', $conflictingInstitutionNames) . '.',
                ])
                ->withInput();
        }

        DB::transaction(function () use ($billingPartner, $validated, $selectedInstitutionIds) {
            $billingPartner->update($validated);

            Institution::query()
                ->where('billing_partner_id', $billingPartner->id)
                ->whereNotIn('id', $selectedInstitutionIds)
                ->update(['billing_partner_id' => null]);

            if ($selectedInstitutionIds->isNotEmpty()) {
                Institution::query()
                    ->whereIn('id', $selectedInstitutionIds)
                    ->update(['billing_partner_id' => $billingPartner->id]);
            }
        });

        return redirect()
            ->route('dashboard.billing-partners.index')
            ->with('success', 'Számlázási partner sikeresen frissítve.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'billing_name' => ['nullable', 'string', 'max:191'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'billing_zip' => ['nullable', 'string', 'max:10'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['nullable', 'string', 'max:191'],
            'billing_email' => ['nullable', 'email', 'max:191'],
            'payment_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_note' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'institution_ids' => ['sometimes', 'array'],
            'institution_ids.*' => ['integer', 'exists:institutions,id'],
        ];
    }
}
