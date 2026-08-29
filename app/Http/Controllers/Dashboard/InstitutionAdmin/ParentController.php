<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingProfile;
use App\Models\Guardian;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParentController extends Controller
{
    public function index(Request $request): View
    {
        $institution = $this->currentAdminInstitution();
        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
        $canManageBilling = ! auth()->user()->isInstitutionSecretary();
        $base = fn () => Guardian::where('institution_id', $institution->id);

        $stats = [
            'total' => $base()->where('active', true)->count(),
            'invoice_recipients' => $this->currentPrimaryBillingChildCount($institution, $today),
            'missing_email' => $base()
                ->where('active', true)
                ->where(fn ($query) => $query->whereNull('email')->orWhere('email', ''))
                ->count(),
            'missing_phone' => $base()
                ->where('active', true)
                ->where(fn ($query) => $query->whereNull('phone')->orWhere('phone', ''))
                ->count(),
        ];

        $guardians = $base()
            ->with([
                'children' => fn ($query) => $query->orderBy('name'),
                'billingProfiles' => fn ($query) => $query
                    ->when($canManageBilling, fn ($query) => $this->applyCurrentPrimaryBillingProfileConstraint(
                        $query,
                        $institution->id,
                        $today
                    ))
                    ->with(['children' => fn ($query) => $this->applyCurrentPrimaryBillingChildConstraint(
                        $query,
                        $institution->id,
                        $today
                    )]),
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($query) use ($search) {
                    $query->where('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(in_array($request->input('status'), ['active', 'inactive'], true), function ($query) use ($request) {
                $query->where('active', $request->input('status') === 'active');
            })
            ->when($canManageBilling && in_array($request->input('billing'), ['yes', 'no'], true), function ($query) use ($request, $institution, $today) {
                $method = $request->input('billing') === 'yes' ? 'whereHas' : 'whereDoesntHave';
                $query->{$method}('billingProfiles', fn ($query) => $this->applyCurrentPrimaryBillingProfileConstraint(
                    $query,
                    $institution->id,
                    $today
                ));
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.institution_admin.parents.index', compact(
            'institution',
            'guardians',
            'stats',
            'canManageBilling'
        ));
    }

    public function edit(Guardian $parent): View
    {
        $institution = $this->currentAdminInstitution();
        abort_if($parent->institution_id !== $institution->id, 403);

        $parent->load(['children' => fn ($query) => $query->orderBy('name')]);
        $billingProfile = $parent->billingProfiles()->latest()->first();
        $billingChildIds = $billingProfile
            ? $billingProfile->children()->wherePivot('is_primary', true)->pluck('children.id')->all()
            : [];
        $canManageBilling = ! auth()->user()->isInstitutionSecretary();
        $relationshipTypes = Guardian::RELATIONSHIP_TYPES;
        $payerTypes = BillingProfile::PAYER_TYPES;
        $paymentMethods = BillingProfile::PAYMENT_METHODS;

        return view('dashboard.institution_admin.parents.edit', compact(
            'institution',
            'parent',
            'billingProfile',
            'billingChildIds',
            'canManageBilling',
            'relationshipTypes',
            'payerTypes',
            'paymentMethods'
        ));
    }

    public function update(Request $request, Guardian $parent): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
        abort_if($parent->institution_id !== $institution->id, 403);

        $parent->load('children');
        $childIds = $parent->children->pluck('id')->all();
        $existingBillingProfile = $parent->billingProfiles()->latest()->first();
        $canManageBilling = ! $request->user()?->isInstitutionSecretary();

        if ($canManageBilling && ! $existingBillingProfile && $this->hasBillingInput($request)) {
            $request->merge(['billing_enabled' => '1']);
        }

        $validated = $request->validate([
            'prefix' => ['nullable', 'string', 'max:30'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:200'],
            'bank_account_number' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:64'],
            'phone_type' => ['nullable', Rule::in(['Ismeretlen', 'Vezetékes', 'Mobil', 'Munkahelyi', 'Fax'])],
            'address_type' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'street_name' => ['nullable', 'string', 'max:255'],
            'street_type' => ['nullable', 'string', 'max:50'],
            'house_number' => ['nullable', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:20'],
            'door' => ['nullable', 'string', 'max:20'],
            'active' => ['nullable', 'boolean'],
            'relations' => ['nullable', 'array'],
            'relations.*.relationship_type' => ['nullable', Rule::in(Guardian::RELATIONSHIP_TYPES)],
            'relations.*.is_legal_representative' => ['nullable', 'boolean'],
            'relations.*.has_no_custody' => ['nullable', 'boolean'],
            'relations.*.is_emergency_contact' => ['nullable', 'boolean'],
            'relations.*.receives_family_allowance' => ['nullable', 'boolean'],
            'billing_enabled' => [$canManageBilling ? 'nullable' : 'prohibited', 'boolean'],
            'payer_type' => [$canManageBilling ? 'required_if:billing_enabled,1' : 'prohibited', Rule::in(array_keys(BillingProfile::PAYER_TYPES))],
            'billing_name' => [$canManageBilling ? 'required_if:billing_enabled,1' : 'prohibited', 'nullable', 'string', 'max:255'],
            'tax_number' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:50'],
            'billing_postal_code' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:10'],
            'billing_city' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:100'],
            'billing_address' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'billing_email' => [$canManageBilling ? 'nullable' : 'prohibited', 'email', 'max:191'],
            'payment_method' => [$canManageBilling ? 'nullable' : 'prohibited', Rule::in(array_keys(BillingProfile::PAYMENT_METHODS))],
            'employer_reference' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'billing_children' => [$canManageBilling ? 'nullable' : 'prohibited', 'array'],
            'billing_children.*' => [$canManageBilling ? 'integer' : 'prohibited', Rule::in($childIds)],
        ], [
            'billing_name.required_if' => 'Aktív számlázási profilnál a számlázási név kötelező.',
            'payer_type.required_if' => 'Aktív számlázási profilnál a fizető típusát ki kell választani.',
            'billing_children.*.in' => 'Csak a gondviselőhöz kapcsolt gyermek jelölhető számlafogadáshoz.',
        ]);

        DB::transaction(function () use ($request, $validated, $parent, $institution, $childIds, $canManageBilling) {
            if ($canManageBilling) {
                $parent->syncBankAccountData(
                    $validated['bank_account_holder'] ?? null,
                    $validated['bank_account_number'] ?? null,
                    $request->user()?->id,
                    'institution_admin'
                );
            }

            $parent->update([
                'prefix' => $this->emptyToNull($validated['prefix'] ?? null),
                'last_name' => trim($validated['last_name']),
                'first_name' => trim($validated['first_name']),
                'email' => $this->emptyToNull($validated['email'] ?? null),
                'phone' => $this->emptyToNull($validated['phone'] ?? null),
                // A "Telefon típusa" mező kikerült az űrlapból (felesleges
                // adatnak ítéltük), ezért nem küldi a kérés - itt csak akkor
                // írjuk felül, ha ténylegesen jön ilyen kulcs, nehogy a
                // meglévő értéket minden mentésnél NULL-ra cseréljük.
                'phone_type' => array_key_exists('phone_type', $validated)
                    ? $this->emptyToNull($validated['phone_type'])
                    : $parent->phone_type,
                'address_type' => $this->emptyToNull($validated['address_type'] ?? null),
                'country' => $this->emptyToNull($validated['country'] ?? null),
                'postal_code' => $this->emptyToNull($validated['postal_code'] ?? null),
                'city' => $this->emptyToNull($validated['city'] ?? null),
                'street_name' => $this->emptyToNull($validated['street_name'] ?? null),
                'street_type' => $this->emptyToNull($validated['street_type'] ?? null),
                'house_number' => $this->emptyToNull($validated['house_number'] ?? null),
                'floor' => $this->emptyToNull($validated['floor'] ?? null),
                'door' => $this->emptyToNull($validated['door'] ?? null),
                'active' => $request->boolean('active'),
            ]);

            foreach ($childIds as $childId) {
                $relation = $validated['relations'][$childId] ?? [];
                DB::table('child_guardian')
                    ->where('guardian_id', $parent->id)
                    ->where('child_id', $childId)
                    ->update([
                        'relationship_type' => $this->emptyToNull($relation['relationship_type'] ?? null),
                        'is_legal_representative' => ! empty($relation['is_legal_representative']),
                        'has_no_custody' => ! empty($relation['has_no_custody']),
                        'is_emergency_contact' => ! empty($relation['is_emergency_contact']),
                        'receives_family_allowance' => ! empty($relation['receives_family_allowance']),
                        'updated_at' => now(),
                    ]);
            }

            $profile = $parent->billingProfiles()->latest()->first();

            if ($canManageBilling && $request->boolean('billing_enabled')) {
                $profile ??= new BillingProfile([
                    'institution_id' => $institution->id,
                    'guardian_id' => $parent->id,
                ]);
                $profile->fill([
                    'payer_type' => $validated['payer_type'],
                    'billing_name' => trim($validated['billing_name']),
                    'tax_number' => $this->emptyToNull($validated['tax_number'] ?? null),
                    'postal_code' => $this->emptyToNull($validated['billing_postal_code'] ?? null),
                    'city' => $this->emptyToNull($validated['billing_city'] ?? null),
                    'address' => $this->emptyToNull($validated['billing_address'] ?? null),
                    'email' => $this->emptyToNull($validated['billing_email'] ?? null),
                    'payment_method' => $this->emptyToNull($validated['payment_method'] ?? null),
                    'employer_reference' => $this->emptyToNull($validated['employer_reference'] ?? null),
                    'active' => true,
                ])->save();

                $selectedChildren = collect($validated['billing_children'] ?? [])->map(fn ($id) => (int) $id);

                foreach ($childIds as $childId) {
                    if ($selectedChildren->contains($childId)) {
                        DB::table('billing_profile_child')
                            ->where('child_id', $childId)
                            ->where('is_primary', true)
                            ->update([
                                'is_primary' => false,
                                'valid_to' => now()->toDateString(),
                                'updated_at' => now(),
                            ]);

                        $existingLink = DB::table('billing_profile_child')
                            ->where('billing_profile_id', $profile->id)
                            ->where('child_id', $childId)
                            ->exists();

                        if ($existingLink) {
                            DB::table('billing_profile_child')
                                ->where('billing_profile_id', $profile->id)
                                ->where('child_id', $childId)
                                ->update([
                                    'is_primary' => true,
                                    'valid_to' => null,
                                    'updated_at' => now(),
                                ]);
                        } else {
                            DB::table('billing_profile_child')->insert([
                                'billing_profile_id' => $profile->id,
                                'child_id' => $childId,
                                'is_primary' => true,
                                'valid_from' => now()->toDateString(),
                                'valid_to' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    } else {
                        DB::table('billing_profile_child')
                            ->where('billing_profile_id', $profile->id)
                            ->where('child_id', $childId)
                            ->update([
                                'is_primary' => false,
                                'valid_to' => now()->toDateString(),
                                'updated_at' => now(),
                            ]);
                    }
                }
            } elseif ($canManageBilling && $profile) {
                $profile->update(['active' => false]);
                DB::table('billing_profile_child')
                    ->where('billing_profile_id', $profile->id)
                    ->where('is_primary', true)
                    ->update([
                        'is_primary' => false,
                        'valid_to' => now()->toDateString(),
                        'updated_at' => now(),
                    ]);
            }
        });

        return redirect()
            ->route('dashboard.institution.parents.index')
            ->with('success', 'A gondviselő adatai sikeresen frissültek.');
    }

    private function emptyToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function hasBillingInput(Request $request): bool
    {
        $fields = [
            'payer_type',
            'billing_name',
            'tax_number',
            'billing_postal_code',
            'billing_city',
            'billing_address',
            'billing_email',
            'payment_method',
            'employer_reference',
        ];

        return collect($request->only($fields))->contains(fn ($value) => filled($value))
            || collect($request->input('billing_children', []))->filter()->isNotEmpty();
    }

    private function currentPrimaryBillingChildCount(Institution $institution, string $today): int
    {
        return DB::table('billing_profile_child')
            ->join('children', 'children.id', '=', 'billing_profile_child.child_id')
            ->join('billing_profiles', 'billing_profiles.id', '=', 'billing_profile_child.billing_profile_id')
            ->where('children.institution_id', $institution->id)
            ->where('billing_profiles.active', true)
            ->where('billing_profile_child.is_primary', true)
            ->where(function ($query) use ($today) {
                $query->whereNull('billing_profile_child.valid_from')
                    ->orWhereDate('billing_profile_child.valid_from', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('billing_profile_child.valid_to')
                    ->orWhereDate('billing_profile_child.valid_to', '>=', $today);
            })
            ->distinct()
            ->count('billing_profile_child.child_id');
    }

    private function applyCurrentPrimaryBillingProfileConstraint($query, int $institutionId, string $today)
    {
        return $query
            ->where('billing_profiles.active', true)
            ->whereHas('children', fn ($childrenQuery) => $this->applyCurrentPrimaryBillingChildConstraint(
                $childrenQuery,
                $institutionId,
                $today
            ));
    }

    private function applyCurrentPrimaryBillingChildConstraint($query, int $institutionId, string $today)
    {
        return $query
            ->where('children.institution_id', $institutionId)
            ->where('billing_profile_child.is_primary', true)
            ->where(function ($inner) use ($today) {
                $inner->whereNull('billing_profile_child.valid_from')
                    ->orWhereDate('billing_profile_child.valid_from', '<=', $today);
            })
            ->where(function ($inner) use ($today) {
                $inner->whereNull('billing_profile_child.valid_to')
                    ->orWhereDate('billing_profile_child.valid_to', '>=', $today);
            });
    }
}
