<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstitutionEmployeeController extends Controller
{
    private const LIST_QUERY_PARAMS = [
        'page',
        'search',
        'email',
        'status',
        'diet_filter',
        'discount_filter',
    ];

    public function index(Request $request): View
    {
        $institution = $this->institution();
        $baseQuery = InstitutionEmployee::query()->where('institution_id', $institution->id);
        $dietaryRestrictions = $this->dietaryRestrictions($institution->id);
        $discountTypes = $this->discountTypes($institution->id);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('active', true)->count(),
            'inactive' => (clone $baseQuery)->where('active', false)->count(),
            'with_discount' => (clone $baseQuery)->whereNotNull('discount_type_id')->count(),
        ];

        $employees = $this->filteredEmployeesQuery($request, $institution)
            ->with(['discountType', 'dietaryRestrictions'])
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('dashboard.institution_admin.employees.index', compact(
            'institution',
            'employees',
            'stats',
            'dietaryRestrictions',
            'discountTypes'
        ));
    }

    public function create(): View
    {
        $institution = $this->institution();

        return view('dashboard.institution_admin.employees.create', [
            'institution' => $institution,
            'employee' => null,
            'discountTypes' => $this->discountTypes($institution->id),
            'dietaryRestrictions' => $this->dietaryRestrictions($institution->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $validated = $this->validateEmployee($request, $institution->id);
        $dietaryRestrictionIds = array_map('intval', $validated['dietary_restriction_ids'] ?? []);
        unset($validated['dietary_restriction_ids']);

        $employee = InstitutionEmployee::create([
            ...$this->normalizePayload($validated, $request),
            'institution_id' => $institution->id,
            'source_type' => 'manual',
        ]);

        $employee->dietaryRestrictions()->sync($dietaryRestrictionIds);

        return redirect()
            ->route('dashboard.institution.employees.index')
            ->with('success', 'A dolgozó sikeresen létrehozva.');
    }

    public function edit(Request $request, InstitutionEmployee $employee): View
    {
        $institution = $this->institution();
        abort_if($employee->institution_id !== $institution->id, 403);

        $employee->load('dietaryRestrictions', 'discountType');
        $returnQuery = $this->buildReturnQuery(
            $this->parseReturnQuery((string) $request->query('return_query', ''))
        );

        return view('dashboard.institution_admin.employees.edit', [
            'institution' => $institution,
            'employee' => $employee,
            'discountTypes' => $this->discountTypes($institution->id, $employee->discount_type_id),
            'dietaryRestrictions' => $this->dietaryRestrictions(
                $institution->id,
                $employee->dietaryRestrictions->pluck('id')->all()
            ),
            'returnQuery' => $returnQuery,
        ]);
    }

    public function update(Request $request, InstitutionEmployee $employee): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($employee->institution_id !== $institution->id, 403);

        $employee->loadMissing('dietaryRestrictions');
        $validated = $this->validateEmployee($request, $institution->id, $employee);
        $dietaryRestrictionIds = array_map('intval', $validated['dietary_restriction_ids'] ?? []);
        unset($validated['dietary_restriction_ids']);

        $employee->update($this->normalizePayload($validated, $request));
        $employee->dietaryRestrictions()->sync($dietaryRestrictionIds);
        $returnParams = $this->parseReturnQuery((string) $request->input('return_query', ''));

        return redirect()
            ->route('dashboard.institution.employees.index', $returnParams)
            ->with('success', 'A dolgozó adatai sikeresen frissültek.');
    }

    public function toggleActive(Request $request, InstitutionEmployee $employee): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($employee->institution_id !== $institution->id, 403);

        $employee->update([
            'active' => ! $employee->active,
        ]);

        return redirect()
            ->route('dashboard.institution.employees.index', $this->listReturnParams($request))
            ->with('success', $employee->active ? 'A dolgozó aktiválva lett.' : 'A dolgozó inaktiválva lett.');
    }

    /**
     * A dolgozói lista aktuális oldalát és szűrőit ("page", "search",
     * "status") adja vissza, amiket az edit/update és a toggle-active
     * form rejtett mezőként küld tovább - enélkül a szerkesztés/állapot-
     * váltás után a lista mindig az 1. oldalra ugrana vissza a szűrők
     * elvesztésével együtt.
     */
    private function listReturnParams(Request $request): array
    {
        return array_filter(
            $request->only(self::LIST_QUERY_PARAMS),
            fn ($value) => filled($value)
        );
    }

    private function parseReturnQuery(string $returnQuery): array
    {
        $sanitizedReturnQuery = trim((string) preg_replace('/[\r\n]+/', '', $returnQuery));

        if ($sanitizedReturnQuery === '') {
            return [];
        }

        parse_str($sanitizedReturnQuery, $params);

        return array_filter(
            collect(self::LIST_QUERY_PARAMS)
                ->mapWithKeys(function (string $key) use ($params) {
                    $value = $params[$key] ?? null;

                    return is_scalar($value) ? [$key => (string) $value] : [$key => null];
                })
                ->all(),
            fn ($value) => filled($value)
        );
    }

    private function buildReturnQuery(array $params): string
    {
        return http_build_query($params);
    }

    private function filteredEmployeesQuery(Request $request, Institution $institution): Builder
    {
        $search = trim((string) $request->input('search', ''));
        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $dietFilter = (string) $request->input('diet_filter', '');
        $discountFilter = (string) $request->input('discount_filter', '');

        $validDietRestrictionIds = $this->dietaryRestrictions($institution->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validDiscountTypeIds = $this->discountTypes($institution->id)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return InstitutionEmployee::query()
            ->where('institution_id', $institution->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($email !== '', function ($query) use ($email) {
                $query->whereRaw('LOWER(email) like ?', ['%'.$email.'%']);
            })
            ->when(in_array($request->input('status'), ['active', 'inactive'], true), function ($query) use ($request) {
                $query->where('active', $request->input('status') === 'active');
            })
            ->when($dietFilter !== '', function ($query) use ($dietFilter, $validDietRestrictionIds) {
                if ($dietFilter === 'with_diet') {
                    $query->whereHas('dietaryRestrictions', function ($query) {
                        $query->where('dietary_restrictions.active', true);
                    });

                    return;
                }

                if ($dietFilter === 'without_diet') {
                    $query->whereDoesntHave('dietaryRestrictions', function ($query) {
                        $query->where('dietary_restrictions.active', true);
                    });

                    return;
                }

                if (str_starts_with($dietFilter, 'restriction_')) {
                    $restrictionId = (int) substr($dietFilter, strlen('restriction_'));

                    if (! in_array($restrictionId, $validDietRestrictionIds, true)) {
                        return;
                    }

                    $query->whereHas('dietaryRestrictions', function ($query) use ($restrictionId) {
                        $query->where('dietary_restrictions.id', $restrictionId)
                            ->where('dietary_restrictions.active', true);
                    });
                }
            })
            ->when($discountFilter !== '', function ($query) use ($discountFilter, $validDiscountTypeIds) {
                if ($discountFilter === 'with_discount') {
                    $query->whereNotNull('discount_type_id');

                    return;
                }

                if ($discountFilter === 'without_discount') {
                    $query->whereNull('discount_type_id');

                    return;
                }

                if (str_starts_with($discountFilter, 'discount_')) {
                    $discountTypeId = (int) substr($discountFilter, strlen('discount_'));

                    if (! in_array($discountTypeId, $validDiscountTypeIds, true)) {
                        return;
                    }

                    $query->where('discount_type_id', $discountTypeId);
                }
            });
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function validateEmployee(Request $request, int $institutionId, ?InstitutionEmployee $employee = null): array
    {
        $selectedRestrictionIds = $employee?->dietaryRestrictions?->pluck('id') ?? collect();
        $currentDiscountTypeId = $employee?->discount_type_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_type' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'street_name' => ['nullable', 'string', 'max:191'],
            'street_type' => ['nullable', 'string', 'max:50'],
            'house_number' => ['nullable', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:20'],
            'door' => ['nullable', 'string', 'max:20'],
            'bank_account_holder' => ['nullable', 'string', 'max:191'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'discount_type_id' => [
                'nullable',
                'integer',
                Rule::exists('discount_types', 'id')
                    ->where(fn ($query) => $query
                        ->where('institution_id', $institutionId)
                        ->where(fn ($query) => $query
                            ->where('active', true)
                            ->when($currentDiscountTypeId, fn ($query) => $query->orWhere('id', $currentDiscountTypeId)))),
            ],
            'dietary_restriction_ids' => ['nullable', 'array'],
            'dietary_restriction_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('dietary_restrictions', 'id')
                    ->where(fn ($query) => $query
                        ->where('institution_id', $institutionId)
                        ->where(fn ($query) => $query
                            ->where('active', true)
                            ->when(
                                $selectedRestrictionIds->isNotEmpty(),
                                fn ($query) => $query->orWhereIn('id', $selectedRestrictionIds)
                            ))),
            ],
            'active' => ['nullable', 'boolean'],
        ], [
            'discount_type_id.exists' => 'A kiválasztott kedvezménytípus nem az aktuális intézményhez tartozik.',
            'dietary_restriction_ids.*.exists' => 'A kiválasztott diéta vagy érzékenység nem az aktuális intézményhez tartozik.',
        ]);
    }

    private function normalizePayload(array $validated, Request $request): array
    {
        foreach ([
            'email',
            'phone',
            'address_type',
            'country',
            'postal_code',
            'city',
            'street_name',
            'street_type',
            'house_number',
            'floor',
            'door',
            'bank_account_holder',
            'bank_account_number',
        ] as $field) {
            $validated[$field] = $this->emptyToNull($validated[$field] ?? null);
        }

        $validated['name'] = trim($validated['name']);
        $validated['discount_type_id'] = filled($validated['discount_type_id'] ?? null)
            ? (int) $validated['discount_type_id']
            : null;
        $validated['active'] = $request->boolean('active', true);

        return $validated;
    }

    private function discountTypes(int $institutionId, ?int $selectedId = null)
    {
        return DiscountType::query()
            ->where('institution_id', $institutionId)
            ->where(function ($query) use ($selectedId) {
                $query->where('active', true)
                    ->when($selectedId, fn ($query) => $query->orWhere('id', $selectedId));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderByDesc('percentage')
            ->get();
    }

    private function dietaryRestrictions(int $institutionId, array $selectedIds = [])
    {
        return DietaryRestriction::query()
            ->where('institution_id', $institutionId)
            ->where(function ($query) use ($selectedIds) {
                $query->where('active', true)
                    ->when($selectedIds !== [], fn ($query) => $query->orWhereIn('id', $selectedIds));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function emptyToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
