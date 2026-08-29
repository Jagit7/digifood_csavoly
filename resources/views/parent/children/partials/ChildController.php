<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\ClassGroup;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\SchoolYear;
use App\Models\StudentMealSetting;
use App\Services\Children\ChildGuardianPresentationService;
use App\Services\Meals\StudentMealSettingService;
use App\Services\Navigation\ChildListReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChildController extends Controller
{
    public function __construct(
        private readonly StudentMealSettingService $mealSettingService,
        private readonly ChildGuardianPresentationService $guardianPresentation,
        private readonly ChildListReturnService $childListReturnService
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $institution = auth()->user()->institutions()->firstOrFail();
        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
        $listState = $this->childListReturnService->currentReturnState(
            ChildListReturnService::LIST_CHILDREN,
            $request->query(),
            $institution
        );

        $stats = [
            'active' => Child::where('institution_id', $institution->id)
                ->where('active', true)
                ->count(),
            'groups' => Child::where('institution_id', $institution->id)
                ->where('active', true)
                ->whereNotNull('group_name')
                ->where('group_name', '!=', '')
                ->distinct()
                ->count('group_name'),
            'without_guardian' => Child::where('institution_id', $institution->id)
                ->where('active', true)
                ->doesntHave('guardians')
                ->count(),
            'inactive' => Child::where('institution_id', $institution->id)
                ->where('active', false)
                ->count(),
            'unverified' => Child::where('institution_id', $institution->id)
                ->where('active', true)
                ->whereNull('data_verified_at')
                ->count(),
        ];

        $dataQualityCounts = $this->dataQualityCounts($institution);

        $groups = Child::where('institution_id', $institution->id)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');

        $duplicateGuardianIds = $this->duplicateGuardianIds($institution);

        /*
         * A Gyermeklista a tanuló azonosító adataira, címére, gondviselőire
         * és számlázási adataira fókuszál - az étkezéssel kapcsolatos
         * beállítások (menücsomag, kedvezmény, allergia/diéta, rendszeres
         * lemondás, étkezési státusz) az "Étkező gyermekek" listán jelennek
         * meg (ld. InstitutionMealParticipantController@index), hogy ez a
         * lista ne legyen még szélesebb, és a két nézet célja is elváljon.
         */
        $children = $this->filteredChildrenQuery($request, $institution)
            ->with([
                'guardians' => fn ($query) => $this->guardianPresentation->applyGuardianDisplayOrder($query),
                'billingProfiles' => fn ($query) => $query
                    ->orderByPivot('is_primary', 'desc')
                    ->orderByDesc('billing_profile_child.valid_from')
                    ->orderBy('billing_profiles.id')
                    ->with('guardian'),
            ])
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $children->setCollection(
            $this->guardianPresentation->decorateChildren($children->getCollection(), $institution->id, $today)
        );

        if ($children->total() > 0 && $children->currentPage() > $children->lastPage()) {
            $redirectParams = $listState['params'];
            $redirectParams['page'] = $children->lastPage();

            return redirect()->route($listState['route'], $redirectParams);
        }

        return view('dashboard.institution_admin.children.index', compact(
            'institution',
            'children',
            'groups',
            'stats',
            'dataQualityCounts',
            'duplicateGuardianIds',
            'today',
            'listState'
        ));
    }

    public function create(): View
    {
        $institution = auth()->user()->institutions()->firstOrFail();
        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
        $groups = ClassGroup::where('institution_id', $institution->id)
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();
        $schoolYears = SchoolYear::where('institution_id', $institution->id)
            ->orderByDesc('starts_on')
            ->pluck('name');
        $discounts = DiscountType::where('institution_id', $institution->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderByDesc('percentage')
            ->get();
        $restrictions = DietaryRestriction::where('institution_id', $institution->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $allergens = $restrictions->where('type', DietaryRestriction::TYPE_ALLERGEN);
        $intolerances = $restrictions->where('type', DietaryRestriction::TYPE_INTOLERANCE);
        $existingGuardians = Guardian::where('institution_id', $institution->id)
            ->where('active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'prefix', 'last_name', 'first_name', 'email', 'phone']);
        $relationshipTypes = Guardian::RELATIONSHIP_TYPES;
        $canManageBilling = ! auth()->user()->isInstitutionSecretary();
        $payerTypes = BillingProfile::PAYER_TYPES;
        $paymentMethods = BillingProfile::PAYMENT_METHODS;

        return view('dashboard.institution_admin.children.create', compact(
            'institution',
            'groups',
            'schoolYears',
            'discounts',
            'allergens',
            'intolerances',
            'existingGuardians',
            'relationshipTypes',
            'canManageBilling',
            'payerTypes',
            'paymentMethods',
            'today'
        ));
    }

    public function edit(Request $request, Child $child): View
    {
        $institution = auth()->user()->institutions()->firstOrFail();
        abort_if($child->institution_id !== $institution->id, 403);
        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->query('return_list'),
            (string) $request->query('return_query', ''),
            $institution,
            ChildListReturnService::LIST_CHILDREN
        );

        $child->load([
            'guardians',
            'dietaryRestrictions',
            'discountPeriods' => fn ($query) => $query->with('discountType')->orderByDesc('valid_from')->orderByDesc('id'),
        ]);
        $relationshipTypes = Guardian::RELATIONSHIP_TYPES;
        $canManageBilling = ! auth()->user()->isInstitutionSecretary();
        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
        $groups = ClassGroup::where('institution_id', $institution->id)
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();
        $schoolYears = SchoolYear::where('institution_id', $institution->id)
            ->orderByDesc('starts_on')
            ->pluck('name');
        $discounts = DiscountType::where('institution_id', $institution->id)
            ->where(function ($query) use ($child) {
                $query->where('active', true)
                    ->when($child->discount_type_id, fn ($query) => $query->orWhere('id', $child->discount_type_id));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderByDesc('percentage')
            ->get();
        $selectedRestrictionIds = $child->dietaryRestrictions->pluck('id');
        $restrictions = DietaryRestriction::where('institution_id', $institution->id)
            ->where(function ($query) use ($selectedRestrictionIds) {
                $query->where('active', true)
                    ->when($selectedRestrictionIds->isNotEmpty(), fn ($query) => $query->orWhereIn('id', $selectedRestrictionIds));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $allergens = $restrictions->where('type', DietaryRestriction::TYPE_ALLERGEN);
        $intolerances = $restrictions->where('type', DietaryRestriction::TYPE_INTOLERANCE);
        $currentMealSetting = $this->currentMealSettingsForChildren($institution->id, collect([$child->id]), $today)->get($child->id);
        $latestMealSetting = $this->latestMealSettingsForChildren($institution->id, collect([$child->id]))->get($child->id);
        $child->setRelation('currentMealSetting', $currentMealSetting);
        $child->setRelation('latestMealSetting', $latestMealSetting);

        return view('dashboard.institution_admin.children.edit', [
            'institution' => $institution,
            'child' => $child,
            'groups' => $groups,
            'schoolYears' => $schoolYears,
            'discounts' => $discounts,
            'allergens' => $allergens,
            'intolerances' => $intolerances,
            'relationshipTypes' => $relationshipTypes,
            'canManageBilling' => $canManageBilling,
            'returnList' => $returnState['list'],
            'returnQuery' => $returnState['query'],
            'returnUrl' => $returnState['url'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = auth()->user()->institutions()->firstOrFail();
        $canManageBilling = ! $request->user()?->isInstitutionSecretary();

        $validated = $this->validateChild($request, $institution->id);
        $mealSettingPayload = $this->validateInitialMealSetting($request, $institution);
        $dietaryRestrictionIds = array_map('intval', $validated['dietary_restriction_ids'] ?? []);
        unset($validated['dietary_restriction_ids']);
        [$validated, $schoolYear, $classGroup] = $this->prepareChildPayload($validated, $request, $institution->id);
        $guardianAttachment = $this->validateGuardianAttachment($request, $institution->id, $canManageBilling);

        DB::transaction(function () use (
            $validated,
            $mealSettingPayload,
            $dietaryRestrictionIds,
            $schoolYear,
            $classGroup,
            $institution,
            $guardianAttachment,
            $canManageBilling,
            $request
        ) {
            $discountPeriod = $this->extractDiscountPeriodPayload($validated, 'manual');

            $child = Child::create([
                ...$validated,
                'institution_id' => $institution->id,
            ]);

            if ($mealSettingPayload !== null) {
                $this->mealSettingService->createSetting(
                    $child,
                    $institution,
                    $mealSettingPayload,
                    (int) $request->user()->id
                );
            }

            $child->dietaryRestrictions()->sync($dietaryRestrictionIds);
            $this->syncDiscountPeriod($child, $discountPeriod);
            $this->syncClassGroupMembership($child, $schoolYear, $classGroup);
            $this->attachGuardian($child, $guardianAttachment, $institution->id, $canManageBilling, $request->user()?->id);
        });

        return redirect()
            ->route('dashboard.institution.children.index')
            ->with('success', 'A gyermek sikeresen létrehozva.');
    }

    public function update(Request $request, Child $child): RedirectResponse
    {
        $institution = auth()->user()->institutions()->firstOrFail();
        abort_if($child->institution_id !== $institution->id, 403);
        $child->loadMissing('dietaryRestrictions', 'discountPeriods', 'guardians');

        $canManageBilling = ! $request->user()?->isInstitutionSecretary();
        $linkedGuardianIds = $child->guardians->pluck('id')->all();

        $validated = $this->validateChild($request, $institution->id, $child);
        $dietaryRestrictionIds = array_map('intval', $validated['dietary_restriction_ids'] ?? []);
        unset($validated['dietary_restriction_ids']);

        [$validated, $schoolYear, $classGroup] = $this->prepareChildPayload($validated, $request, $institution->id);
        $guardianEdits = $this->validateGuardianEdits($request, $canManageBilling);

        DB::transaction(function () use (
            $child,
            $validated,
            $dietaryRestrictionIds,
            $schoolYear,
            $classGroup,
            $guardianEdits,
            $linkedGuardianIds,
            $institution,
            $canManageBilling,
            $request
        ) {
            $discountPeriod = $this->extractDiscountPeriodPayload($validated, $child->source_type ?? 'manual');

            $child->update($validated);
            $child->dietaryRestrictions()->sync($dietaryRestrictionIds);
            $this->syncDiscountPeriod($child, $discountPeriod);
            $this->syncClassGroupMembership($child, $schoolYear, $classGroup);
            $this->applyGuardianEdits(
                $child,
                $guardianEdits,
                $linkedGuardianIds,
                $institution->id,
                $canManageBilling,
                $request->user()?->id
            );
        });

        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->input('return_list'),
            (string) $request->input('return_query', ''),
            $institution,
            ChildListReturnService::LIST_CHILDREN
        );

        return redirect()->route($returnState['route'], $returnState['params'])
            ->with('success', 'A gyermek adatai sikeresen frissültek.');
    }

    /**
     * Az indulás előtti adattisztításhoz: az adminisztrátor bepipálhatja,
     * hogy egy gyermek adatait (cím, gondviselő, számlázás) már manuálisan
     * átnézte és rendben találta - ld. 2026_08_20_090000 migráció.
     */
    public function markVerified(Request $request, Child $child): RedirectResponse
    {
        $institution = auth()->user()->institutions()->firstOrFail();
        abort_if($child->institution_id !== $institution->id, 403);

        $child->update([
            'data_verified_at' => now(),
            'data_verified_by' => $request->user()?->id,
        ]);

        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->input('return_list'),
            (string) $request->input('return_query', ''),
            $institution,
            ChildListReturnService::LIST_CHILDREN
        );

        return redirect()->route($returnState['route'], $returnState['params'])
            ->with('success', 'A gyermek adatai ellenőrzöttként megjelölve.');
    }

    public function unmarkVerified(Request $request, Child $child): RedirectResponse
    {
        $institution = auth()->user()->institutions()->firstOrFail();
        abort_if($child->institution_id !== $institution->id, 403);

        $child->update([
            'data_verified_at' => null,
            'data_verified_by' => null,
        ]);

        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->input('return_list'),
            (string) $request->input('return_query', ''),
            $institution,
            ChildListReturnService::LIST_CHILDREN
        );

        return redirect()->route($returnState['route'], $returnState['params'])
            ->with('success', 'Az ellenőrzési jelölés visszavonva.');
    }

    /**
     * Gyors, egysoros kedvezmény-módosítás az Étkező gyermekek listáról -
     * ugyanazt a periódus-alapú logikát használja (syncDiscountPeriod), mint
     * a teljes szerkesztő oldal, hogy a két hely ne térhessen el egymástól.
     * A kezdődátum mindig a mai nap - ha korábbi dátumtól kellene indítani
     * egy kedvezményváltást, azt továbbra is a szerkesztő oldalon kell
     * elvégezni.
     */
    public function updateDiscount(Request $request, Child $child): RedirectResponse
    {
        $institution = auth()->user()->institutions()->firstOrFail();
        abort_if($child->institution_id !== $institution->id, 403);

        $validated = $request->validate([
            'discount_type_id' => [
                'required',
                Rule::exists('discount_types', 'id')->where(
                    fn ($query) => $query->where('institution_id', $institution->id)
                ),
            ],
        ]);

        $this->syncDiscountPeriod($child, [
            'discount_type_id' => (int) $validated['discount_type_id'],
            'valid_from' => now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString(),
            'valid_to' => null,
            'source_type' => 'manual',
            'note' => null,
        ]);

        if ($request->filled('return_list') || $request->filled('return_query')) {
            $returnState = $this->childListReturnService->resolveReturnDestination(
                $request->input('return_list'),
                (string) $request->input('return_query', ''),
                $institution,
                ChildListReturnService::LIST_CHILDREN
            );

            return redirect()->route($returnState['route'], $returnState['params'])
                ->with('success', 'A kedvezmény frissítve lett.');
        }

        return redirect()->back()->with('success', 'A kedvezmény frissítve lett.');
    }

    /**
     * A Gyermeklista aktuális szűrésének (keresés, osztály, állapot,
     * hiányosság, ellenőrzési státusz) megfelelő CSV export - egyeztetéshez,
     * telefonos szülői adategyeztetéshez nyomtatható/tovább szerkeszthető
     * formátumban.
     */
    public function export(Request $request): StreamedResponse
    {
        $institution = auth()->user()->institutions()->firstOrFail();

        $children = $this->filteredChildrenQuery($request, $institution)
            ->with([
                'guardians' => fn ($query) => $this->guardianPresentation->applyGuardianDisplayOrder($query),
                'billingProfiles' => fn ($query) => $query
                    ->orderByPivot('is_primary', 'desc')
                    ->orderByDesc('billing_profile_child.valid_from')
                    ->orderBy('billing_profiles.id'),
            ])
            ->orderBy('name')
            ->get();

        $children = $this->guardianPresentation->decorateChildren($children, $institution->id);

        $filename = 'gyermeklista-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($children) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Név', 'Osztály / csoport', 'Állapot', 'Ellenőrizve',
                'Számlázási név', 'Számlázási cím', 'Számlázási e-mail',
                'Gondviselő 1 neve', 'Gondviselő 1 telefon', 'Gondviselő 1 e-mail',
                'Gondviselő 2 neve', 'Gondviselő 2 telefon', 'Gondviselő 2 e-mail',
            ], ';');

            foreach ($children as $child) {
                $billing = $child->display_billing_profile;
                $guardian1 = $child->display_guardian_1;
                $guardian2 = $child->display_guardian_2;
                $billingAddress = $billing
                    ? trim(($billing->postal_code ?? '').' '.($billing->city ?? '').($billing->address ? ', '.$billing->address : ''))
                    : '';

                fputcsv($handle, [
                    $child->name,
                    $child->group_name,
                    $child->active ? 'Aktív' : 'Inaktív',
                    $child->isDataVerified() ? 'Igen' : 'Nem',
                    $billing->billing_name ?? '',
                    $billingAddress,
                    $billing->email ?? '',
                    $guardian1?->full_name,
                    $guardian1?->phone,
                    $guardian1?->email,
                    $guardian2?->full_name,
                    $guardian2?->phone,
                    $guardian2?->email,
                ], ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A Gyermeklista index() és export() akciója ugyanazokat a
     * kereső/szűrő paramétereket használja - itt egy helyen tartjuk a
     * feltételek összeállítását, hogy a kettő ne csúszhasson szét.
     */
    private function filteredChildrenQuery(Request $request, Institution $institution)
    {
        $dataQuality = (string) $request->input('data_quality');
        $verifiedStatus = $request->input('verified_status');

        return Child::query()
            ->where('institution_id', $institution->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('educational_identifier', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('group_name'), function ($query) use ($request) {
                $query->where('group_name', $request->input('group_name'));
            })
            ->when(in_array($request->input('status'), ['active', 'inactive'], true), function ($query) use ($request) {
                $query->where('active', $request->input('status') === 'active');
            })
            ->when(in_array($verifiedStatus, ['verified', 'unverified'], true), function ($query) use ($verifiedStatus) {
                if ($verifiedStatus === 'verified') {
                    $query->whereNotNull('data_verified_at');
                } else {
                    $query->whereNull('data_verified_at');
                }
            })
            ->when($dataQuality !== '', function ($query) use ($dataQuality) {
                $this->applyDataQualityFilter($query, $dataQuality);
            });
    }

    /**
     * A "Hiányosság" szűrő - a gondviselő/számlázás/kapcsolat adatok közül
     * melyik hiányzik teljesen a gyermeknél. A "no_email"/"no_phone"/
     * "no_address" feltételek a gondviselő ÉS a számlázási profil adatait
     * együtt nézik, mert bármelyik helyen elég, ha megvan az adat.
     */
    private function applyDataQualityFilter($query, string $dataQuality): void
    {
        $hasGuardianWith = fn ($column) => fn ($q) => $q->whereNotNull($column)->where($column, '!=', '');
        $hasBillingWith = fn ($column) => fn ($q) => $q->whereNotNull($column)->where($column, '!=', '');

        match ($dataQuality) {
            'no_guardian' => $query->doesntHave('guardians'),
            'no_billing' => $query->doesntHave('billingProfiles'),
            'no_email' => $query
                ->whereDoesntHave('guardians', $hasGuardianWith('email'))
                ->whereDoesntHave('billingProfiles', $hasBillingWith('email')),
            'no_phone' => $query->whereDoesntHave('guardians', $hasGuardianWith('phone')),
            'no_address' => $query
                ->whereDoesntHave('guardians', $hasGuardianWith('street_name'))
                ->whereDoesntHave('billingProfiles', $hasBillingWith('address')),
            'any_issue' => $query->where(function ($query) use ($hasGuardianWith, $hasBillingWith) {
                $query->doesntHave('guardians')
                    ->orDoesntHave('billingProfiles')
                    ->orWhereDoesntHave('guardians', $hasGuardianWith('phone'))
                    ->orWhere(function ($query) use ($hasGuardianWith, $hasBillingWith) {
                        // "nincs e-mail" csak akkor igaz, ha se a gondviselőnél,
                        // se a számlázási profilnál nincs kitöltve - ugyanaz a
                        // logika, mint a "no_email" önálló szűrőnél.
                        $query->whereDoesntHave('guardians', $hasGuardianWith('email'))
                            ->whereDoesntHave('billingProfiles', $hasBillingWith('email'));
                    })
                    ->orWhere(function ($query) use ($hasGuardianWith, $hasBillingWith) {
                        $query->whereDoesntHave('guardians', $hasGuardianWith('street_name'))
                            ->whereDoesntHave('billingProfiles', $hasBillingWith('address'));
                    });
            }),
            default => null,
        };
    }

    /**
     * A "Hiányosság" szűrő legördülőjében soronként kiírt darabszámok -
     * hogy az adminisztrátor lássa, melyik hiányosságból mennyi van, mielőtt
     * rákattint a szűrőre.
     */
    private function dataQualityCounts(Institution $institution): array
    {
        $counts = [];

        foreach (['no_guardian', 'no_billing', 'no_email', 'no_phone', 'no_address', 'any_issue'] as $key) {
            $query = Child::query()->where('institution_id', $institution->id)->where('active', true);
            $this->applyDataQualityFilter($query, $key);
            $counts[$key] = $query->count();
        }

        return $counts;
    }

    /**
     * Azonos név + azonos telefon/e-mail alapján egyező gondviselő-rekordok
     * - jellemzően elgépelt duplikáció (pl. ugyanaz a szülő kétszer rögzítve
     * két gyermeknél, kicsit eltérő adatokkal). Csak akkor számít egyezésnek,
     * ha a telefon vagy az e-mail is tényleg ki van töltve mindkét oldalon,
     * különben minden "nincs megadva" gondviselő egy csoportba kerülne.
     */
    private function duplicateGuardianIds(Institution $institution): Collection
    {
        $byPhone = Guardian::query()
            ->where('institution_id', $institution->id)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->selectRaw('GROUP_CONCAT(id) as ids')
            ->groupBy(DB::raw('LOWER(TRIM(last_name))'), DB::raw('LOWER(TRIM(first_name))'), 'phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('ids');

        $byEmail = Guardian::query()
            ->where('institution_id', $institution->id)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->selectRaw('GROUP_CONCAT(id) as ids')
            ->groupBy(DB::raw('LOWER(TRIM(last_name))'), DB::raw('LOWER(TRIM(first_name))'), DB::raw('LOWER(TRIM(email))'))
            ->havingRaw('COUNT(*) > 1')
            ->pluck('ids');

        return $byPhone->merge($byEmail)
            ->flatMap(fn ($ids) => explode(',', (string) $ids))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function validateChild(Request $request, int $institutionId, ?Child $child = null): array
    {
        $selectedRestrictionIds = $child?->dietaryRestrictions?->pluck('id') ?? collect();
        $currentDiscountTypeId = $child?->discount_type_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'educational_identifier' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('children', 'educational_identifier')
                    ->where(fn ($query) => $query->where('institution_id', $institutionId))
                    ->when($child, fn ($rule) => $rule->ignore($child->id)),
            ],
            'group_name' => [
                'nullable',
                'string',
                'max:100',
                Rule::exists('class_groups', 'name')
                    ->where(fn ($query) => $query->where('institution_id', $institutionId)),
            ],
            'school_year' => [
                'nullable',
                'string',
                'required_with:group_name',
                Rule::exists('school_years', 'name')
                    ->where(fn ($query) => $query->where('institution_id', $institutionId)),
            ],
            'discount_type_id' => [
                'required',
                'integer',
                Rule::exists('discount_types', 'id')
                    ->where(fn ($query) => $query
                        ->where('institution_id', $institutionId)
                        ->where(fn ($query) => $query
                            ->where('active', true)
                            ->when($currentDiscountTypeId, fn ($query) => $query->orWhere('id', $currentDiscountTypeId)))),
            ],
            'discount_valid_from' => ['required', 'date'],
            'discount_valid_to' => ['nullable', 'date', 'after_or_equal:discount_valid_from'],
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
            'is_eater' => ['required', 'boolean'],
        ], [
            'school_year.exists' => 'A kiválasztott tanév nem tartozik az intézményhez.',
            'educational_identifier.unique' => 'Ez az oktatási azonosító már másik gyermekhez tartozik.',
            'group_name.exists' => 'A kiválasztott osztály vagy csoport nem tartozik az intézményhez.',
            'discount_type_id.exists' => 'A kiválasztott kedvezmény nem aktív, vagy nem tartozik az intézményhez.',
            'discount_valid_to.after_or_equal' => 'A kedvezmény Ig dátuma nem lehet korábbi a Tól dátumnál.',
            'dietary_restriction_ids.*.exists' => 'A kiválasztott allergén vagy ételérzékenység nem aktív, vagy nem tartozik az intézményhez.',
            'is_eater.required' => 'Az étkezési státusz kiválasztása kötelező.',
        ]);
    }

    private function validateInitialMealSetting(Request $request, Institution $institution): ?array
    {
        $validated = $request->validate([
            'is_eater' => ['required', 'boolean'],
            'meal_valid_from' => ['nullable', 'date', 'required_if:is_eater,1'],
            'institution_meal_package_id' => ['prohibited'],
            'mode' => ['prohibited'],
            'institution_meal_type_ids' => ['prohibited'],
        ], [
            'meal_valid_from.required_if' => 'Étkező gyermeknél az étkezés kezdete kötelező.',
            'meal_valid_from.date' => 'Az étkezés kezdete csak érvényes dátum lehet.',
            'institution_meal_package_id.prohibited' => 'Az új gyermek űrlapon csak a saját intézmény alapértelmezett étkezési csomagja használható.',
            'mode.prohibited' => 'Az új gyermek űrlapon az étkezési mód nem adható meg közvetlenül.',
            'institution_meal_type_ids.prohibited' => 'Az új gyermek űrlapon az egyedi étkezések nem adhatók meg közvetlenül.',
        ]);

        if (! (bool) $validated['is_eater']) {
            return null;
        }

        $defaultPackage = $this->mealSettingService->defaultPackage($institution);

        if (! $defaultPackage) {
            throw ValidationException::withMessages([
                'is_eater' => 'Nem hozható létre étkező gyermek, mert az intézményhez nincs aktív alapértelmezett étkezési csomag beállítva.',
            ]);
        }

        return [
            'mode' => StudentMealSetting::MODE_PACKAGE,
            'valid_from' => $validated['meal_valid_from'],
            'institution_meal_package_id' => $defaultPackage->id,
        ];
    }

    /**
     * Új gyermek felvitelekor opcionálisan egy gondviselő is hozzákapcsolható
     * - vagy egy már létező (csak a rokonsági adatokat kérjük be), vagy egy
     * teljesen új gondviselő (ilyenkor a személyes/cím/bank/számlázási
     * adatok is itt vehetők fel, ld. ParentController@update ugyanezekkel a
     * mezőkkel - szándékosan innen nem hívjuk azt a metódust, mert ott egy
     * MÁR LÉTEZŐ gondviselő frissítése és több gyermek egyidejű
     * rokonság-szerkesztése történik, itt viszont egyetlen, most létrehozott
     * gyermekhez tartozó, egyszeri hozzákapcsolásról van szó).
     */
    private function validateGuardianAttachment(Request $request, int $institutionId, bool $canManageBilling): array
    {
        $validated = $request->validate([
            'guardian_mode' => ['required', Rule::in(['none', 'existing', 'new'])],
            'existing_guardian_id' => [
                'nullable',
                'required_if:guardian_mode,existing',
                'integer',
                Rule::exists('guardians', 'id')
                    ->where(fn ($query) => $query
                        ->where('institution_id', $institutionId)
                        ->where('active', true)),
            ],
            'relationship_type' => ['nullable', Rule::in(Guardian::RELATIONSHIP_TYPES)],
            'is_legal_representative' => ['nullable', 'boolean'],
            'has_no_custody' => ['nullable', 'boolean'],
            'is_emergency_contact' => ['nullable', 'boolean'],
            'receives_family_allowance' => ['nullable', 'boolean'],

            'guardian_prefix' => ['nullable', 'string', 'max:30'],
            'guardian_last_name' => ['nullable', 'required_if:guardian_mode,new', 'string', 'max:100'],
            'guardian_first_name' => ['nullable', 'required_if:guardian_mode,new', 'string', 'max:100'],
            'guardian_email' => ['nullable', 'email', 'max:191'],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'guardian_phone_type' => ['nullable', Rule::in(['Ismeretlen', 'Vezetékes', 'Mobil', 'Munkahelyi', 'Fax'])],
            'guardian_bank_account_holder' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:200'],
            'guardian_bank_account_number' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:64'],
            'guardian_address_type' => ['nullable', 'string', 'max:50'],
            'guardian_country' => ['nullable', 'string', 'max:100'],
            'guardian_postal_code' => ['nullable', 'string', 'max:10'],
            'guardian_city' => ['nullable', 'string', 'max:100'],
            'guardian_street_name' => ['nullable', 'string', 'max:255'],
            'guardian_street_type' => ['nullable', 'string', 'max:50'],
            'guardian_house_number' => ['nullable', 'string', 'max:30'],
            'guardian_floor' => ['nullable', 'string', 'max:20'],
            'guardian_door' => ['nullable', 'string', 'max:20'],

            'billing_enabled' => [$canManageBilling ? 'nullable' : 'prohibited', 'boolean'],
            'payer_type' => [$canManageBilling ? 'required_if:billing_enabled,1' : 'prohibited', 'nullable', Rule::in(array_keys(BillingProfile::PAYER_TYPES))],
            'billing_name' => [$canManageBilling ? 'required_if:billing_enabled,1' : 'prohibited', 'nullable', 'string', 'max:255'],
            'tax_number' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:50'],
            'billing_postal_code' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:10'],
            'billing_city' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:100'],
            'billing_address' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'billing_email' => [$canManageBilling ? 'nullable' : 'prohibited', 'email', 'max:191'],
            'payment_method' => [$canManageBilling ? 'nullable' : 'prohibited', Rule::in(array_keys(BillingProfile::PAYMENT_METHODS))],
            'employer_reference' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:255'],
        ], [
            'existing_guardian_id.required_if' => 'Válassz ki egy meglévő gondviselőt, vagy válts "Új gondviselő" módra.',
            'existing_guardian_id.exists' => 'A kiválasztott gondviselő nem található, vagy nem tartozik az intézményhez.',
            'guardian_last_name.required_if' => 'Új gondviselő felvitelénél a vezetéknév kötelező.',
            'guardian_first_name.required_if' => 'Új gondviselő felvitelénél a keresztnév kötelező.',
            'billing_name.required_if' => 'Aktív számlázási profilnál a számlázási név kötelező.',
            'payer_type.required_if' => 'Aktív számlázási profilnál a fizető típusát ki kell választani.',
        ]);

        return $validated;
    }

    private function attachGuardian(
        Child $child,
        array $guardianAttachment,
        int $institutionId,
        bool $canManageBilling,
        ?int $actingUserId
    ): void {
        if ($guardianAttachment['guardian_mode'] === 'none') {
            return;
        }

        $relation = [
            'relationship_type' => $guardianAttachment['relationship_type'] ?? null,
            'is_legal_representative' => (bool) ($guardianAttachment['is_legal_representative'] ?? false),
            'has_no_custody' => (bool) ($guardianAttachment['has_no_custody'] ?? false),
            'is_emergency_contact' => (bool) ($guardianAttachment['is_emergency_contact'] ?? false),
            'receives_family_allowance' => (bool) ($guardianAttachment['receives_family_allowance'] ?? false),
        ];

        if ($guardianAttachment['guardian_mode'] === 'existing') {
            $guardian = Guardian::query()
                ->where('institution_id', $institutionId)
                ->findOrFail($guardianAttachment['existing_guardian_id']);

            $child->guardians()->syncWithoutDetaching([$guardian->id => $relation]);

            return;
        }

        // guardian_mode === 'new'
        $guardian = Guardian::create([
            'institution_id' => $institutionId,
            'prefix' => $this->emptyToNull($guardianAttachment['guardian_prefix'] ?? null),
            'last_name' => trim((string) $guardianAttachment['guardian_last_name']),
            'first_name' => trim((string) $guardianAttachment['guardian_first_name']),
            'email' => $this->emptyToNull($guardianAttachment['guardian_email'] ?? null),
            'phone' => $this->emptyToNull($guardianAttachment['guardian_phone'] ?? null),
            'phone_type' => $this->emptyToNull($guardianAttachment['guardian_phone_type'] ?? null),
            'address_type' => $this->emptyToNull($guardianAttachment['guardian_address_type'] ?? null),
            'country' => $this->emptyToNull($guardianAttachment['guardian_country'] ?? null),
            'postal_code' => $this->emptyToNull($guardianAttachment['guardian_postal_code'] ?? null),
            'city' => $this->emptyToNull($guardianAttachment['guardian_city'] ?? null),
            'street_name' => $this->emptyToNull($guardianAttachment['guardian_street_name'] ?? null),
            'street_type' => $this->emptyToNull($guardianAttachment['guardian_street_type'] ?? null),
            'house_number' => $this->emptyToNull($guardianAttachment['guardian_house_number'] ?? null),
            'floor' => $this->emptyToNull($guardianAttachment['guardian_floor'] ?? null),
            'door' => $this->emptyToNull($guardianAttachment['guardian_door'] ?? null),
            'source_type' => 'manual',
            'active' => true,
        ]);

        if ($canManageBilling) {
            $guardian->syncBankAccountData(
                $guardianAttachment['guardian_bank_account_holder'] ?? null,
                $guardianAttachment['guardian_bank_account_number'] ?? null,
                $actingUserId,
                'institution_admin'
            );
        }

        $child->guardians()->syncWithoutDetaching([$guardian->id => $relation]);

        if ($canManageBilling && ! empty($guardianAttachment['billing_enabled'])) {
            $profile = BillingProfile::create([
                'institution_id' => $institutionId,
                'guardian_id' => $guardian->id,
                'payer_type' => $guardianAttachment['payer_type'],
                'billing_name' => trim((string) $guardianAttachment['billing_name']),
                'tax_number' => $this->emptyToNull($guardianAttachment['tax_number'] ?? null),
                'postal_code' => $this->emptyToNull($guardianAttachment['billing_postal_code'] ?? null),
                'city' => $this->emptyToNull($guardianAttachment['billing_city'] ?? null),
                'address' => $this->emptyToNull($guardianAttachment['billing_address'] ?? null),
                'email' => $this->emptyToNull($guardianAttachment['billing_email'] ?? null),
                'payment_method' => $this->emptyToNull($guardianAttachment['payment_method'] ?? null),
                'employer_reference' => $this->emptyToNull($guardianAttachment['employer_reference'] ?? null),
                'active' => true,
            ]);

            // A gyermek most jött létre, tehát nem lehet neki korábbi
            // elsődleges számlafogadója - a meglévő gondviselőknél
            // (ParentController@update) szükséges "korábbi elsődleges
            // lecserélése" lépés itt szándékosan elmarad.
            DB::table('billing_profile_child')->insert([
                'billing_profile_id' => $profile->id,
                'child_id' => $child->id,
                'is_primary' => true,
                'valid_from' => now()->toDateString(),
                'valid_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function emptyToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function validateGuardianEdits(Request $request, bool $canManageBilling): array
    {
        $validated = $request->validate([
            'guardians' => ['nullable', 'array'],
            'guardians.*.prefix' => ['nullable', 'string', 'max:30'],
            'guardians.*.last_name' => ['required', 'string', 'max:100'],
            'guardians.*.first_name' => ['required', 'string', 'max:100'],
            'guardians.*.email' => ['nullable', 'email', 'max:191'],
            'guardians.*.phone' => ['nullable', 'string', 'max:50'],
            'guardians.*.phone_type' => ['nullable', Rule::in(['Ismeretlen', 'Vezetékes', 'Mobil', 'Munkahelyi', 'Fax'])],
            'guardians.*.bank_account_holder' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:200'],
            'guardians.*.bank_account_number' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:64'],
            'guardians.*.address_type' => ['nullable', 'string', 'max:50'],
            'guardians.*.country' => ['nullable', 'string', 'max:100'],
            'guardians.*.postal_code' => ['nullable', 'string', 'max:10'],
            'guardians.*.city' => ['nullable', 'string', 'max:100'],
            'guardians.*.street_name' => ['nullable', 'string', 'max:255'],
            'guardians.*.street_type' => ['nullable', 'string', 'max:50'],
            'guardians.*.house_number' => ['nullable', 'string', 'max:30'],
            'guardians.*.floor' => ['nullable', 'string', 'max:20'],
            'guardians.*.door' => ['nullable', 'string', 'max:20'],
            'guardians.*.active' => ['nullable', 'boolean'],
            'guardians.*.relationship_type' => ['nullable', Rule::in(Guardian::RELATIONSHIP_TYPES)],
            'guardians.*.is_legal_representative' => ['nullable', 'boolean'],
            'guardians.*.has_no_custody' => ['nullable', 'boolean'],
            'guardians.*.is_emergency_contact' => ['nullable', 'boolean'],
            'guardians.*.receives_family_allowance' => ['nullable', 'boolean'],
        ], [
            'guardians.*.last_name.required' => 'A gondviselő vezetékneve kötelező.',
            'guardians.*.first_name.required' => 'A gondviselő keresztneve kötelező.',
        ]);

        return $validated['guardians'] ?? [];
    }

    private function applyGuardianEdits(
        Child $child,
        array $guardianEdits,
        array $linkedGuardianIds,
        int $institutionId,
        bool $canManageBilling,
        ?int $actingUserId
    ): void {
        foreach ($linkedGuardianIds as $guardianId) {
            $data = $guardianEdits[$guardianId] ?? null;

            if ($data === null) {
                continue;
            }

            $guardian = Guardian::query()
                ->where('institution_id', $institutionId)
                ->find($guardianId);

            if (! $guardian) {
                continue;
            }

            if ($canManageBilling) {
                $guardian->syncBankAccountData(
                    $data['bank_account_holder'] ?? null,
                    $data['bank_account_number'] ?? null,
                    $actingUserId,
                    'institution_admin'
                );
            }

            $guardian->update([
                'prefix' => $this->emptyToNull($data['prefix'] ?? null),
                'last_name' => trim((string) $data['last_name']),
                'first_name' => trim((string) $data['first_name']),
                'email' => $this->emptyToNull($data['email'] ?? null),
                'phone' => $this->emptyToNull($data['phone'] ?? null),
                // A "Telefon típusa" mező kikerült az űrlapokból (felesleges
                // adatnak ítéltük), de a meglévő értéket nem akarjuk törölni -
                // csak akkor írjuk felül, ha az űrlap ténylegesen küld ilyen
                // kulcsot (pl. egy jövőbeli API hívás still elküldheti).
                'phone_type' => array_key_exists('phone_type', $data)
                    ? $this->emptyToNull($data['phone_type'])
                    : $guardian->phone_type,
                'address_type' => $this->emptyToNull($data['address_type'] ?? null),
                'country' => $this->emptyToNull($data['country'] ?? null),
                'postal_code' => $this->emptyToNull($data['postal_code'] ?? null),
                'city' => $this->emptyToNull($data['city'] ?? null),
                'street_name' => $this->emptyToNull($data['street_name'] ?? null),
                'street_type' => $this->emptyToNull($data['street_type'] ?? null),
                'house_number' => $this->emptyToNull($data['house_number'] ?? null),
                'floor' => $this->emptyToNull($data['floor'] ?? null),
                'door' => $this->emptyToNull($data['door'] ?? null),
                'active' => ! empty($data['active']),
            ]);

            DB::table('child_guardian')
                ->where('child_id', $child->id)
                ->where('guardian_id', $guardianId)
                ->update([
                    'relationship_type' => $this->emptyToNull($data['relationship_type'] ?? null),
                    'is_legal_representative' => ! empty($data['is_legal_representative']),
                    'has_no_custody' => ! empty($data['has_no_custody']),
                    'is_emergency_contact' => ! empty($data['is_emergency_contact']),
                    'receives_family_allowance' => ! empty($data['receives_family_allowance']),
                    'updated_at' => now(),
                ]);
        }
    }

    private function prepareChildPayload(array $validated, Request $request, int $institutionId): array
    {
        foreach (['educational_identifier', 'group_name', 'school_year'] as $field) {
            $validated[$field] = filled($validated[$field] ?? null)
                ? trim((string) $validated[$field])
                : null;
        }

        $validated['name'] = trim($validated['name']);
        $validated['active'] = $request->boolean('active');

        $schoolYear = filled($validated['school_year'] ?? null)
            ? SchoolYear::where('institution_id', $institutionId)
                ->where('name', $validated['school_year'])
                ->firstOrFail()
            : null;
        $classGroup = null;

        if (filled($validated['group_name'] ?? null)) {
            $classGroup = ClassGroup::where('institution_id', $institutionId)
                ->where('school_year_id', $schoolYear?->id)
                ->where('name', $validated['group_name'])
                ->first();

            if (! $classGroup) {
                throw ValidationException::withMessages([
                    'group_name' => 'A kiválasztott osztály nem létezik a megadott tanévben.',
                ]);
            }
        }

        return [$validated, $schoolYear, $classGroup];
    }

    private function extractDiscountPeriodPayload(array &$validated, ?string $sourceType): array
    {
        $discountPeriod = [
            'discount_type_id' => (int) $validated['discount_type_id'],
            'valid_from' => $validated['discount_valid_from'],
            'valid_to' => $validated['discount_valid_to'] ?? null,
            'source_type' => $sourceType,
            'note' => null,
        ];

        unset($validated['discount_valid_from'], $validated['discount_valid_to']);

        return $discountPeriod;
    }

    private function syncDiscountPeriod(Child $child, array $discountPeriod): void
    {
        $newValidFrom = Carbon::parse($discountPeriod['valid_from'])->startOfDay();
        $newValidTo = filled($discountPeriod['valid_to'])
            ? Carbon::parse($discountPeriod['valid_to'])->startOfDay()
            : null;

        $currentOpenPeriod = $child->discountPeriods()
            ->whereNull('valid_to')
            ->latest('valid_from')
            ->latest('id')
            ->first();

        if (
            $currentOpenPeriod
            && (int) $currentOpenPeriod->discount_type_id === (int) $discountPeriod['discount_type_id']
            && $currentOpenPeriod->valid_from?->toDateString() === $newValidFrom->toDateString()
            && $currentOpenPeriod->valid_to?->toDateString() === $newValidTo?->toDateString()
        ) {
            $child->forceFill([
                'discount_type_id' => $discountPeriod['discount_type_id'],
            ])->save();

            return;
        }

        if ($currentOpenPeriod) {
            $closeDate = $newValidFrom->copy();

            if ($closeDate->greaterThan($currentOpenPeriod->valid_from)) {
                $closeDate->subDay();
            }

            $currentOpenPeriod->update([
                'valid_to' => $closeDate->toDateString(),
            ]);
        }

        $child->discountPeriods()->create([
            'discount_type_id' => $discountPeriod['discount_type_id'],
            'valid_from' => $newValidFrom->toDateString(),
            'valid_to' => $newValidTo?->toDateString(),
            'source_type' => $discountPeriod['source_type'],
            'note' => $discountPeriod['note'],
        ]);

        $child->forceFill([
            'discount_type_id' => $discountPeriod['discount_type_id'],
        ])->save();
    }

    private function syncClassGroupMembership(Child $child, ?SchoolYear $schoolYear, ?ClassGroup $classGroup): void
    {
        if (! $schoolYear) {
            return;
        }

        DB::table('class_group_memberships')
            ->join('class_groups', 'class_groups.id', '=', 'class_group_memberships.class_group_id')
            ->where('class_group_memberships.child_id', $child->id)
            ->where('class_groups.school_year_id', $schoolYear->id)
            ->when($classGroup, fn ($query) => $query->where('class_groups.id', '!=', $classGroup->id))
            ->update([
                'class_group_memberships.status' => 'transferred',
                'class_group_memberships.left_on' => now()->toDateString(),
                'class_group_memberships.updated_at' => now(),
            ]);

        if (! $classGroup) {
            return;
        }

        $membership = DB::table('class_group_memberships')
            ->where('class_group_id', $classGroup->id)
            ->where('child_id', $child->id)
            ->first();

        if ($membership) {
            DB::table('class_group_memberships')
                ->where('id', $membership->id)
                ->update([
                    'status' => 'active',
                    'left_on' => null,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('class_group_memberships')->insert([
            'class_group_id' => $classGroup->id,
            'child_id' => $child->id,
            'status' => 'active',
            'joined_on' => $schoolYear->starts_on->toDateString(),
            'left_on' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function currentMealSettingsForChildren(int $institutionId, Collection $childIds, string $today): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage:id,name',
                'mealTypes' => fn ($query) => $query->with('mealType:id,name'),
            ])
            ->whereIn('student_id', $childIds)
            ->where('institution_id', $institutionId)
            ->whereDate('valid_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $today);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }

    private function latestMealSettingsForChildren(int $institutionId, Collection $childIds): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage:id,name',
                'mealTypes' => fn ($query) => $query->with('mealType:id,name'),
                'closedBy:id,name',
            ])
            ->whereIn('student_id', $childIds)
            ->where('institution_id', $institutionId)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }
}
