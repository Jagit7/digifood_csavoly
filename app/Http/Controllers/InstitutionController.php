<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BillingPartner;
use App\Models\Institution;
use App\Models\InstitutionSetting;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function index()
    {
        $institutions = Institution::query()
            ->orderByDesc('created_at')
            ->get();

        return view('dashboard.superadmin.institutions.index', compact('institutions'));
    }

    public function create()
    {
        $billingPartners = BillingPartner::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('dashboard.superadmin.institutions.create', compact('billingPartners'));
    }

    /**
     * Store a newly created resource in storage.
     * POST /dashboard/institutions
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $validated['active'] = $request->boolean('active');

        if (! empty($validated['om_identifier'])) {
            $validated['om_identifier'] = preg_replace('/\s+/', '', $validated['om_identifier']);
        }

        $validated['institution_code'] ??= $this->generateInstitutionCode($validated['name'] ?? null);

        $institution = Institution::create($validated);
        $newValues = $this->auditableInstitutionValues($institution);

        AuditLogger::log(
            action: AuditLog::ACTION_INSTITUTION_CREATED,
            description: 'Intézmény létrehozva: '.$institution->name,
            subject: $institution,
            institutionId: $institution->id,
            newValues: $newValues,
        );

        return redirect()
            ->route('dashboard.institutions.index')
            ->with('success', 'Intézmény sikeresen létrehozva. Azonosító: '.($institution->institution_code ?? ''));
    }

    public function edit(Institution $institution)
    {
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        $billingPartners = BillingPartner::query()
            ->where('active', true)
            ->when($institution->billing_partner_id, function ($query) use ($institution) {
                $query->orWhere('id', $institution->billing_partner_id);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('dashboard.superadmin.institutions.edit', compact('institution', 'setting', 'billingPartners'));
    }

    public function update(Request $request, Institution $institution)
    {
        $beforeValues = $this->auditableInstitutionValues($institution);
        $validated = $request->validate($this->rules($institution->id));

        $validated['active'] = $request->boolean('active');

        if (! empty($validated['om_identifier'])) {
            $validated['om_identifier'] = preg_replace('/\s+/', '', $validated['om_identifier']);
        }

        $institution->update($validated);
        $afterValues = $this->auditableInstitutionValues($institution->fresh());
        [$oldValues, $newValues] = $this->diffAuditValues($beforeValues, $afterValues);

       $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        $setting->update([
            'barcode_entry_enabled' => $request->boolean('barcode_entry_enabled'),
            // Csak superadmin éri el ezt a route-ot (role:super_admin
            // middleware, ld. routes/web.php) - egy intézményi admin nem
            // tudja saját magának ki-/bekapcsolni ezt a mezőt, mert az
            // intézmény szerkesztő űrlapjához egyáltalán nincs hozzáférése.
            'admin_browser_restriction_enabled' => $request->boolean('admin_browser_restriction_enabled'),
        ]);

        if ($oldValues !== [] || $newValues !== []) {
            AuditLogger::log(
                action: AuditLog::ACTION_INSTITUTION_UPDATED,
                description: 'Intézmény módosítva: '.$institution->name,
                subject: $institution,
                institutionId: $institution->id,
                oldValues: $oldValues,
                newValues: $newValues,
            );
        }

        return redirect()
            ->route('dashboard.institutions.index')
            ->with('success', 'Intézmény sikeresen frissítve.');
    }

    public function destroy(Institution $institution)
    {
        if ((bool) $institution->active === true) {
            $institution->update(['active' => false]);
        }

        $institution->delete();

        AuditLogger::log(
            action: AuditLog::ACTION_INSTITUTION_DELETED,
            description: 'Intézmény kukába helyezve: '.$institution->name,
            subject: $institution,
            institutionId: $institution->id,
            oldValues: ['active' => true],
            newValues: ['active' => false, 'deleted_at' => now()->toDateTimeString()],
        );

        return back()->with('success', 'Intézmény kukába helyezve.');
    }

    public function toggleActive(Institution $institution)
    {
        $wasActive = (bool) $institution->active;

        $institution->update([
            'active' => ! $wasActive,
        ]);

        AuditLogger::log(
            action: AuditLog::ACTION_INSTITUTION_STATUS_CHANGED,
            description: 'Intézmény állapota módosítva: '.$institution->name,
            subject: $institution,
            institutionId: $institution->id,
            oldValues: ['active' => $wasActive],
            newValues: ['active' => ! $wasActive],
        );

        return back()->with('success', 'Intézmény állapota módosítva.');
    }

    public function trashed()
    {
        $institutions = Institution::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->get();

        return view('dashboard.superadmin.institutions.trashed', compact('institutions'));
    }

    public function restore(int $id)
    {
        $institution = Institution::onlyTrashed()->findOrFail($id);

        $institution->restore();
        $institution->update(['active' => true]);

        AuditLogger::log(
            action: AuditLog::ACTION_INSTITUTION_RESTORED,
            description: 'Intézmény visszaállítva: '.$institution->name,
            subject: $institution,
            institutionId: $institution->id,
            oldValues: ['active' => false, 'deleted_at' => 'törölve volt'],
            newValues: ['active' => true, 'deleted_at' => null],
        );

        return back()->with('success', 'Intézmény visszaállítva és aktiválva.');
    }

    /**
     * Validation rules for store/update.
     * $ignoreId: update esetén az OM unique kivételhez
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            // alapadatok
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:ovoda,iskola,bolcsode'],

            // intézmény címe (strukturált)
            'address_zip' => ['nullable', 'string', 'max:10'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_line' => ['nullable', 'string', 'max:255'],

            // OM azonosító (unique, de update-nél saját rekord kivétel)
            'om_identifier' => array_filter([
                'nullable',
                'digits:6',
                'unique:institutions,om_identifier'.($ignoreId ? ','.$ignoreId : ''),
            ]),

            // cégjegyzékszám (impresszumhoz - banki ellenőrzés visszajelzése alapján)
            'company_registration_number' => ['nullable', 'string', 'max:100'],

            // kapcsolattartó
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],

            // számlázás
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_tax_number' => ['nullable', 'string', 'max:50'],

            'billing_zip' => ['nullable', 'string', 'max:10'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['nullable', 'string', 'max:255'],

            'billing_payment_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'billing_partner_id' => ['nullable', 'integer', 'exists:billing_partners,id'],
            'saas_fee_per_active_eater' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],

            // integrációk
            'kreta_code' => ['nullable', 'string', 'max:100'],
            'szamlazz_partner_id' => ['nullable', 'string', 'max:100'],
            'invoice_prefix' => ['nullable', 'string', 'max:32'],

            // státusz
            'active' => ['required', 'boolean'],
        ];
    }

    private function generateInstitutionCode(string $name): string
    {
        $prefix = strtoupper(
            substr(
                preg_replace('/[^A-Z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $name)),
                0,
                3
            )
        );

        $prefix = str_pad($prefix, 3, 'X');
        $datePart = now()->format('md');
        $random = random_int(100, 999);

        return "{$prefix}-{$datePart}-{$random}";
    }

    private function auditableInstitutionValues(Institution $institution): array
    {
        return [
            'name' => $institution->name,
            'type' => $institution->type,
            'institution_code' => $institution->institution_code,
            'om_identifier' => $institution->om_identifier,
            'company_registration_number' => $institution->company_registration_number,
            'address_zip' => $institution->address_zip,
            'address_city' => $institution->address_city,
            'address_line' => $institution->address_line,
            'contact_name' => $institution->contact_name,
            'email' => $institution->email,
            'phone' => $institution->phone,
            'billing_name' => $institution->billing_name,
            'billing_tax_number' => $institution->billing_tax_number,
            'billing_zip' => $institution->billing_zip,
            'billing_city' => $institution->billing_city,
            'billing_address' => $institution->billing_address,
            'billing_payment_due_days' => $institution->billing_payment_due_days,
            'billing_partner_id' => $institution->billing_partner_id,
            'saas_fee_per_active_eater' => $institution->saas_fee_per_active_eater,
            'kreta_code' => $institution->kreta_code,
            'invoice_prefix' => $institution->invoice_prefix,
            'active' => (bool) $institution->active,
        ];
    }

    private function diffAuditValues(array $beforeValues, array $afterValues): array
    {
        $oldValues = [];
        $newValues = [];

        foreach ($afterValues as $key => $value) {
            $beforeValue = $beforeValues[$key] ?? null;

            if ($beforeValue !== $value) {
                $oldValues[$key] = $beforeValue;
                $newValues[$key] = $value;
            }
        }

        return [$oldValues, $newValues];
    }
}
