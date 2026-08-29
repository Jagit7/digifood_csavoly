<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Services\Children\ChildGuardianPresentationService;
use App\Services\Meals\StudentMealSettingService;
use App\Services\Navigation\ChildListReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Alapadatok" - indulás előtti adategyeztető felület. A cél, hogy egy
 * helyen, gyermekenként egyetlen modalból el lehessen érni mindent, ami a
 * Digifood biztonságos induláshoz szükséges: alapadatok (név, osztály,
 * aktív), az étkezési beállítás megléte (átugrással az Étkezés menübe),
 * a gondviselők e-mail címe és aktivációs állapota, valamint a
 * számlázásra kijelölt gondviselő. A kedvezmény és az étel-érzékenységek
 * szándékosan NEM szerkeszthetők innen (azok kizárólag adminként, a
 * Gyermeklista teljes szerkesztő oldalán módosíthatók - ld.
 * ChildController::update()), csak megjelennek, hogy indulás előtt ez is
 * átlátható legyen egy helyen.
 */
class BillingAddressController extends Controller
{
    public function __construct(
        private readonly StudentMealSettingService $mealSettingService,
        private readonly ChildGuardianPresentationService $guardianPresentation,
        private readonly ChildListReturnService $childListReturnService
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $institution = $this->institution();
        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
        $listState = $this->childListReturnService->currentReturnState(
            ChildListReturnService::LIST_BASICS,
            $request->query(),
            $institution
        );
        $search = trim((string) $request->input('search'));
        $sort = $request->input('sort') === 'name' ? 'name' : 'class';
        $quality = (string) $request->input('quality');
        $discountTypeId = $request->input('discount_type_id');
        $dietaryRestrictionId = $request->input('dietary_restriction_id');

        $activeChildIds = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->pluck('id');
        $mealParticipationStatuses = $this->mealSettingService->mealParticipationStatusesForChildren(
            $institution,
            $activeChildIds,
            $today
        );
        $missingMealChildIds = $mealParticipationStatuses
            ->filter(fn (array $status) => $status['is_missing'])
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();

        // A gyermek "aktuális" kedvezménye nem mindig egyezik a
        // children.discount_type_id oszloppal: a ChildController::
        // syncDiscountPeriod() a legutóbb FELVETT periódus adatával írja
        // felül ezt az oszlopot, függetlenül attól, hogy az a periódus ma
        // valóban érvényes-e (pl. utólagos, múltbeli korrekció esetén nem).
        // A ténylegesen MA érvényes kedvezményt - ugyanúgy, mint
        // Child::discountTypeForDate() teszi - a ma érvényes periódusból,
        // ennek hiányában az oszlopból kell venni.
        $activeDiscountPeriodConstraint = function ($query) use ($today) {
            $query->where('valid_from', '<=', $today)
                ->where(function ($inner) use ($today) {
                    $inner->whereNull('valid_to')
                        ->orWhereDate('valid_to', '>=', $today);
                });
        };

        // Fontos: itt szándékosan a teljes "billing_profile_child.is_primary"
        // oszlopnevet használjuk a wherePivot() helyett. A wherePivot() csak
        // a with() eager-load kontextusában (a Relation objektumon) működik -
        // a whereDoesntHave()/whereHas() closure-jában, ahol ugyanez a
        // feltétel a lekérdezés kontextusaként fut, a wherePivot() egy nem
        // létező "pivot_is_primary" oszlopot próbál keresni, és
        // "Unknown column 'pivot'" SQL hibát dob.
        $primaryBillingConstraint = function ($query) use ($institution, $today) {
            $this->guardianPresentation->applyCurrentPrimaryBillingConstraint($query, $institution->id, $today);
        };

        $qualityCounts = $this->qualityCounts($institution, $primaryBillingConstraint, $missingMealChildIds);
        $totalActiveChildren = Child::where('institution_id', $institution->id)->where('active', true)->count();

        // A kedvezmény gyors módosításához (modal) és a szűrő legördülőhöz
        // is ugyanazt a listát adjuk át, mint amit a ChildController::
        // updateDiscount() (az "Étkező gyermekek" listáról ismert egysoros
        // kedvezmény-váltás) is felhasznál - ugyanoda posztol ez a modal is,
        // nem duplikáljuk a logikát.
        $discounts = DiscountType::where('institution_id', $institution->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderByDesc('percentage')
            ->get();

        // A szűrő legördülőjéhez az intézmény összes beállított
        // allergénje/érzékenysége - nem tévesztendő össze a
        // $child->dietaryRestrictions eager-load-dal, ami gyermekenként
        // a TÉNYLEGESEN hozzá rendelt érzékenységeket adja.
        $allDietaryRestrictions = DietaryRestriction::where('institution_id', $institution->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $noDiscountTypeId = $this->noDiscountTypeId($institution);

        // Darabszámok a szűrő legördülők soraiba (ugyanaz a minta, mint a
        // Hiányosság szűrőnél) - enélkül nem látszik, hogy egy adott
        // kedvezményt/érzékenységet ténylegesen hány gyermeknél állítottak
        // be, és a "nincs szűrve" alapértelmezett sor is félreérthető volt.
        // A "any" kulcs alatt a "Bármilyen kedvezmény" / "Bármilyen
        // érzékenység" összesítő szűrő darabszáma.
        $discountCounts = $this->discountCounts($institution, $activeDiscountPeriodConstraint, $discounts, $noDiscountTypeId);
        $dietaryRestrictionCounts = $this->dietaryRestrictionCounts($institution, $allDietaryRestrictions);

        $children = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('group_name', 'like', "%{$search}%");
                });
            })
            ->when($quality !== '', function ($query) use ($quality, $primaryBillingConstraint) {
                $this->applyQualityFilter($query, $quality, $primaryBillingConstraint, $missingMealChildIds);
            })
            ->when(filled($discountTypeId), function ($query) use ($discountTypeId, $activeDiscountPeriodConstraint, $noDiscountTypeId) {
                $this->applyDiscountFilter($query, $discountTypeId, $activeDiscountPeriodConstraint, $noDiscountTypeId);
            })
            ->when(filled($dietaryRestrictionId), function ($query) use ($dietaryRestrictionId) {
                $this->applyDietaryRestrictionFilter($query, $dietaryRestrictionId);
            })
            ->with([
                'billingProfiles' => function ($query) use ($primaryBillingConstraint) {
                    $primaryBillingConstraint($query);
                    $query->with('guardian');
                },
                'discountType',
                // A táblázatban és a modalban is a MA érvényes kedvezményt
                // mutatjuk - ld. fenti $activeDiscountPeriodConstraint
                // megjegyzés. Legfeljebb 1 sort ad vissza (a periódusok nem
                // fedhetik egymást), a Blade oldalon ->first() veszi ki.
                'discountPeriods' => function ($query) use ($activeDiscountPeriodConstraint) {
                    $activeDiscountPeriodConstraint($query);
                    $query->with('discountType');
                },
                'dietaryRestrictions',
                'guardians' => fn ($query) => $this->guardianPresentation->applyGuardianDisplayOrder($query),
            ])
            ->when($sort === 'name', function ($query) {
                $query->orderBy('name');
            }, function ($query) {
                $query->orderBy('group_name')->orderBy('name');
            })
            ->paginate(50)
            ->withQueryString();

        $children->setCollection(
            $this->guardianPresentation->decorateChildren($children->getCollection(), $institution->id, $today)
        );

        $children->getCollection()->transform(function (Child $child) use ($mealParticipationStatuses) {
            $child->setAttribute('meal_participation_status', $mealParticipationStatuses->get($child->id, [
                'status' => StudentMealSettingService::PARTICIPATION_STATUS_MISSING,
                'label' => 'Nincs beállítva',
                'current_setting' => null,
                'upcoming_setting' => null,
                'effective_setting' => null,
                'starts_on' => null,
                'starts_on_label' => null,
                'is_current' => false,
                'is_upcoming' => false,
                'is_missing' => true,
            ]));

            return $child;
        });

        if ($children->total() > 0 && $children->currentPage() > $children->lastPage()) {
            $redirectParams = $listState['params'];
            $redirectParams['page'] = $children->lastPage();

            return redirect()->route($listState['route'], $redirectParams);
        }

        return view('dashboard.institution_admin.billing-addresses.index', [
            'institution' => $institution,
            'children' => $children,
            'sort' => $sort,
            'quality' => $quality,
            'qualityCounts' => $qualityCounts,
            'discountTypeId' => $discountTypeId,
            'dietaryRestrictionId' => $dietaryRestrictionId,
            'totalActiveChildren' => $totalActiveChildren,
            'groups' => $this->groupNames($institution),
            'discounts' => $discounts,
            'allDietaryRestrictions' => $allDietaryRestrictions,
            'discountCounts' => $discountCounts,
            'dietaryRestrictionCounts' => $dietaryRestrictionCounts,
            'listState' => $listState,
        ]);
    }

    public function update(Request $request, BillingProfile $billingProfile): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($billingProfile->institution_id !== $institution->id, 403);

        $validated = $request->validate([
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $billingProfile->update($validated);

        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->input('return_list'),
            (string) $request->input('return_query', ''),
            $institution,
            ChildListReturnService::LIST_BASICS
        );

        return redirect()
            ->route($returnState['route'], $returnState['params'])
            ->with('success', 'A számlázási cím frissítve lett.');
    }

    /**
     * Minimális számlázási profil létrehozása egy gyermekhez, amikor még
     * egyáltalán nincs elsődleges profilja. A számlázási nevet a gondviselő
     * nevéből vesszük át, a fizető típusa alapértelmezetten "guardian" - a
     * teljes profil (adószám, fizetési mód stb.) továbbra is a gondviselő
     * szerkesztő oldalán, vagy a szülő saját felületén finomítható, ez a
     * modal csak a címet állítja be.
     */
    public function store(Request $request): RedirectResponse
    {
        $institution = $this->institution();

        $validated = $request->validate([
            'child_id' => ['required', 'integer'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $child = Child::query()
            ->where('institution_id', $institution->id)
            ->with(['guardians' => fn ($query) => $this->guardianPresentation->applyGuardianDisplayOrder($query)])
            ->findOrFail($validated['child_id']);

        $guardian = $child->guardians->first();

        if (! $guardian) {
            throw ValidationException::withMessages([
                'child_id' => 'Ehhez a gyermekhez nincs gondviselő rendelve, így nem hozható létre számlázási profil.',
            ]);
        }

        DB::transaction(function () use ($institution, $child, $guardian, $validated) {
            $profile = $this->assignBillingPayer($institution, $child, $guardian);
            $profile->update([
                'postal_code' => $validated['postal_code'] ?? null,
                'city' => $validated['city'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);
        });

        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->input('return_list'),
            (string) $request->input('return_query', ''),
            $institution,
            ChildListReturnService::LIST_BASICS
        );

        return redirect()
            ->route($returnState['route'], $returnState['params'])
            ->with('success', 'A számlázási profil és cím létrehozva.');
    }

    /**
     * Alapadatok gyors szerkesztése az "Alapadatok" oldal moduljából: név,
     * osztály/csoport, aktív státusz. Szándékosan NEM itt kezeli a
     * kedvezményt és az étel-érzékenységeket - azok logikája (kedvezmény-
     * periódusok, szinkronizálás) a teljes gyermek-szerkesztő oldalon marad
     * egy helyen, ld. ChildController::update()/updateDiscount().
     */
    public function updateBasics(Request $request, Child $child): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($child->institution_id !== $institution->id, 403);

        $groups = $this->groupNames($institution);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'group_name' => ['nullable', Rule::in($groups)],
            'active' => ['nullable', 'boolean'],
        ]);

        $child->update([
            'name' => trim($validated['name']),
            'group_name' => $validated['group_name'] ?? null,
            'active' => $request->boolean('active'),
        ]);

        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->input('return_list'),
            (string) $request->input('return_query', ''),
            $institution,
            ChildListReturnService::LIST_BASICS
        );

        return redirect()
            ->route($returnState['route'], $returnState['params'])
            ->with('success', 'A gyermek alapadatai frissültek.');
    }

    /**
     * A gyermekhez kapcsolt gondviselők e-mail címének gyors javítása. Csak
     * a gyermekhez ténylegesen kapcsolt gondviselők módosíthatók innen - a
     * Rule::in($linkedGuardianIds) ellenőrzés zárja ki, hogy egy manipulált
     * kéréssel más gyermek (vagy másik intézmény) gondviselőjét lehessen
     * módosítani.
     */
    public function updateGuardianEmails(Request $request, Child $child): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($child->institution_id !== $institution->id, 403);

        $linkedGuardianIds = $child->guardians()->pluck('guardians.id')->all();

        $validated = $request->validate([
            'guardians' => ['nullable', 'array'],
            'guardians.*.id' => ['required_with:guardians', 'integer', Rule::in($linkedGuardianIds)],
            'guardians.*.email' => ['nullable', 'email', 'max:191'],
        ]);

        foreach ($validated['guardians'] ?? [] as $entry) {
            Guardian::query()
                ->where('id', $entry['id'])
                ->where('institution_id', $institution->id)
                ->update(['email' => $this->emptyToNull($entry['email'] ?? null)]);
        }

        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->input('return_list'),
            (string) $request->input('return_query', ''),
            $institution,
            ChildListReturnService::LIST_BASICS
        );

        return redirect()
            ->route($returnState['route'], $returnState['params'])
            ->with('success', 'A gondviselői e-mail címek frissültek.');
    }

    /**
     * Kijelöli, melyik (a gyermekhez kapcsolt) gondviselő legyen a
     * számlázás címzettje. Ez tudatosan admin-jog marad: a szülői saját
     * felületén keresztül soha nem állítható be, hogy KI a fizető - ld.
     * ParentAccountService::updateBillingData(), ami csak a saját profilját
     * módosítja, a gyermek-hozzárendelést (billing_profile_child) nem
     * érinti. Üres guardian_id esetén a jelenlegi kijelölést vonja vissza.
     */
    public function setPrimaryGuardian(Request $request, Child $child): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($child->institution_id !== $institution->id, 403);

        $linkedGuardianIds = $child->guardians()->pluck('guardians.id')->all();

        $validated = $request->validate([
            'guardian_id' => ['nullable', 'integer', Rule::in($linkedGuardianIds)],
        ]);

        DB::transaction(function () use ($institution, $child, $validated) {
            if (empty($validated['guardian_id'])) {
                DB::table('billing_profile_child')
                    ->where('child_id', $child->id)
                    ->where('is_primary', true)
                    ->update([
                        'is_primary' => false,
                        'valid_to' => now()->toDateString(),
                        'updated_at' => now(),
                    ]);

                return;
            }

            $guardian = Guardian::where('institution_id', $institution->id)
                ->findOrFail($validated['guardian_id']);

            $this->assignBillingPayer($institution, $child, $guardian);
        });

        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->input('return_list'),
            (string) $request->input('return_query', ''),
            $institution,
            ChildListReturnService::LIST_BASICS
        );

        return redirect()
            ->route($returnState['route'], $returnState['params'])
            ->with('success', 'A számlázásra kijelölt gondviselő frissült.');
    }

    /**
     * A fizetőnek kijelölt gondviselő bankszámla-adatainak admin általi
     * módosítása az Alapadatok modaljából. A syncBankAccountData() ugyanazt
     * a titkosítást és verzió-történetet (GuardianBankAccountHistory) kezelő
     * logikát futtatja, mint a teljes gondviselő-szerkesztő oldal - ld.
     * ChildController::applyGuardianEdits() / ParentController::update() -,
     * nem duplikáljuk. A "titkárnő" jogkörű admin (isInstitutionSecretary())
     * itt sem módosíthatja, ugyanúgy, mint azokon a helyeken - a szülő saját
     * felületén ("Fiókom") ezek a mezők eleve "prohibited"-ek, ld.
     * ParentAccountController, tehát ott sosem módosítható.
     */
    public function updateGuardianBankAccount(Request $request, Guardian $guardian): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($guardian->institution_id !== $institution->id, 403);

        $canManageBilling = ! $request->user()?->isInstitutionSecretary();

        $validated = $request->validate([
            'bank_account_holder' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:200'],
            'bank_account_number' => [$canManageBilling ? 'nullable' : 'prohibited', 'string', 'max:64'],
        ]);

        if ($canManageBilling) {
            $guardian->syncBankAccountData(
                $validated['bank_account_holder'] ?? null,
                $validated['bank_account_number'] ?? null,
                $request->user()?->id,
                'institution_admin'
            );
        }

        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->input('return_list'),
            (string) $request->input('return_query', ''),
            $institution,
            ChildListReturnService::LIST_BASICS
        );

        return redirect()
            ->route($returnState['route'], $returnState['params'])
            ->with('success', 'A bankszámla-adatok frissültek.');
    }

    /**
     * Közös logika a számlázási profil hozzárendeléséhez (store() és
     * setPrimaryGuardian() is ezt hívja, hogy a két hely ne térhessen el
     * egymástól): ha a gondviselőnek már van aktív profilja, azt
     * használja, egyébként létrehoz egy minimálisat a nevéből/e-mailjéből.
     * A gyermeken lévő korábbi elsődleges kapcsolatot mindig lezárja, hogy
     * egy gyermeknek egyszerre csak egy elsődleges (aktív) számlázási
     * profilja legyen.
     */
    private function assignBillingPayer(Institution $institution, Child $child, Guardian $guardian): BillingProfile
    {
        DB::table('billing_profile_child')
            ->where('child_id', $child->id)
            ->where('is_primary', true)
            ->update([
                'is_primary' => false,
                'valid_to' => now()->toDateString(),
                'updated_at' => now(),
            ]);

        $profile = BillingProfile::where('institution_id', $institution->id)
            ->where('guardian_id', $guardian->id)
            ->where('active', true)
            ->latest('id')
            ->first();

        if (! $profile) {
            $profile = BillingProfile::create([
                'institution_id' => $institution->id,
                'guardian_id' => $guardian->id,
                'payer_type' => 'guardian',
                'billing_name' => $guardian->full_name,
                'email' => $guardian->email,
                'active' => true,
            ]);
        }

        $existingLink = DB::table('billing_profile_child')
            ->where('billing_profile_id', $profile->id)
            ->where('child_id', $child->id)
            ->exists();

        if ($existingLink) {
            DB::table('billing_profile_child')
                ->where('billing_profile_id', $profile->id)
                ->where('child_id', $child->id)
                ->update(['is_primary' => true, 'valid_to' => null, 'updated_at' => now()]);
        } else {
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

        return $profile;
    }

    /**
     * A "Hiányosság" szűrő - ld. ChildController::applyDataQualityFilter()
     * hasonló logikáját. Itt szándékosan szűkebb és az induláshoz
     * ténylegesen kritikus szempontokra fókuszál (gondviselő, e-mail,
     * fizető kijelölése, fiók aktiválása, osztály) - nem ismétli meg a
     * Gyermeklista telefon/cím hiányosság-szűrőjét, mert azoknak itt nincs
     * szerepük.
     */
    private function applyQualityFilter($query, string $quality, \Closure $primaryBillingConstraint, array $missingMealChildIds): void
    {
        $hasEmail = fn ($q) => $q->whereNotNull('email')->where('email', '!=', '');
        $hasActivatedAccount = fn ($q) => $q->whereNotNull('user_id');
        $missingGroup = fn ($q) => $q->whereNull('group_name')->orWhere('group_name', '');
        $missingMealSetting = fn ($q) => empty($missingMealChildIds)
            ? $q->whereRaw('1 = 0')
            : $q->whereIn('id', $missingMealChildIds);
        // A fizetőnek kijelölt gondviselő bankszámlaszáma hiányzik. Csak
        // akkor számít hiányosságnak, ha VAN kijelölt fizető - ha nincs, azt
        // már a "no_payer" jelzi, nem duplikáljuk ugyanazt a problémát két
        // különböző okként.
        $missingBankAccount = fn ($q) => $q->whereHas('billingProfiles', function ($q) use ($primaryBillingConstraint) {
            $primaryBillingConstraint($q);
            $q->whereHas('guardian', fn ($gq) => $gq->whereNull('bank_account_number'));
        });

        match ($quality) {
            'no_guardian' => $query->doesntHave('guardians'),
            'no_email' => $query->whereDoesntHave('guardians', $hasEmail),
            'no_payer' => $query->whereDoesntHave('billingProfiles', $primaryBillingConstraint),
            'not_activated' => $query->whereHas('guardians', $hasEmail)
                ->whereDoesntHave('guardians', $hasActivatedAccount),
            'no_meal_setting' => $query->where($missingMealSetting),
            'no_group' => $query->where($missingGroup),
            'no_bank_account' => $query->where($missingBankAccount),
            'any_issue' => $query->where(function ($query) use ($hasEmail, $primaryBillingConstraint, $missingGroup, $missingBankAccount, $missingMealSetting) {
                $query->doesntHave('guardians')
                    ->orWhereDoesntHave('guardians', $hasEmail)
                    ->orWhereDoesntHave('billingProfiles', $primaryBillingConstraint)
                    ->orWhere($missingMealSetting)
                    ->orWhere($missingGroup)
                    ->orWhere($missingBankAccount);
            }),
            default => null,
        };
    }

    /**
     * A "Hiányosság" szűrő legördülőjében soronként kiírt darabszámok -
     * hogy az adminisztrátor lássa, melyik hiányosságból mennyi van, mielőtt
     * rákattint a szűrőre. Ld. ChildController::dataQualityCounts() azonos
     * mintáját.
     */
    private function qualityCounts(Institution $institution, \Closure $primaryBillingConstraint, array $missingMealChildIds): array
    {
        $counts = [];

        foreach (['any_issue', 'no_guardian', 'no_email', 'no_payer', 'not_activated', 'no_meal_setting', 'no_group', 'no_bank_account'] as $key) {
            $query = Child::query()->where('institution_id', $institution->id)->where('active', true);
            $this->applyQualityFilter($query, $key, $primaryBillingConstraint, $missingMealChildIds);
            $counts[$key] = $query->count();
        }

        return $counts;
    }

    /**
     * A "Kedvezmény nélkül" (0%) az a "üres" alapértelmezett kedvezmény,
     * amit a Child::booted() minden gyermeknek automatikusan beállít
     * létrehozáskor, ha admin még nem adott meg mást - ld. Child.php.
     * A "Bármilyen kedvezmény" szűrőnél ezt kell kizárnunk, különben minden
     * gyermek "kedvezményezettnek" számítana.
     */
    private function noDiscountTypeId(Institution $institution): ?int
    {
        return DiscountType::where('institution_id', $institution->id)
            ->where('name', 'Kedvezmény nélkül')
            ->where('percentage', 0)
            ->value('id');
    }

    /**
     * A "Kedvezmény" szűrő WHERE-feltétele - a gyermek ma érvényes
     * kedvezményét nézi (ld. fenti $activeDiscountPeriodConstraint
     * megjegyzés), nem a nyers discount_type_id oszlopot. Külön metódusban,
     * hogy a listaszűréshez és a legördülő darabszámaihoz (discountCounts())
     * is ugyanaz a logika fusson. A "any" érték a "bármilyen (valódi)
     * kedvezménye van" összesítő szűrő - ld. noDiscountTypeId().
     */
    private function applyDiscountFilter($query, int|string $discountTypeId, \Closure $activeDiscountPeriodConstraint, ?int $noDiscountTypeId): void
    {
        if ($discountTypeId === 'any') {
            $query->where(function ($outer) use ($activeDiscountPeriodConstraint, $noDiscountTypeId) {
                $outer->whereHas('discountPeriods', function ($q) use ($activeDiscountPeriodConstraint, $noDiscountTypeId) {
                    $activeDiscountPeriodConstraint($q);
                    if ($noDiscountTypeId) {
                        $q->where('discount_type_id', '!=', $noDiscountTypeId);
                    }
                })->orWhere(function ($inner) use ($activeDiscountPeriodConstraint, $noDiscountTypeId) {
                    $inner->whereDoesntHave('discountPeriods', $activeDiscountPeriodConstraint);

                    if ($noDiscountTypeId) {
                        $inner->where('discount_type_id', '!=', $noDiscountTypeId);
                    } else {
                        $inner->whereNotNull('discount_type_id');
                    }
                });
            });

            return;
        }

        $query->where(function ($outer) use ($discountTypeId, $activeDiscountPeriodConstraint) {
            $outer->whereHas('discountPeriods', function ($q) use ($discountTypeId, $activeDiscountPeriodConstraint) {
                $activeDiscountPeriodConstraint($q);
                $q->where('discount_type_id', $discountTypeId);
            })->orWhere(function ($inner) use ($discountTypeId, $activeDiscountPeriodConstraint) {
                $inner->whereDoesntHave('discountPeriods', $activeDiscountPeriodConstraint)
                    ->where('discount_type_id', $discountTypeId);
            });
        });
    }

    /**
     * Az "Allergén / étel-érzékenység" szűrő WHERE-feltétele. Az "any" érték
     * a "van bármilyen beállított érzékenysége/allergénje" összesítő szűrő.
     */
    private function applyDietaryRestrictionFilter($query, int|string $dietaryRestrictionId): void
    {
        if ($dietaryRestrictionId === 'any') {
            $query->has('dietaryRestrictions');

            return;
        }

        $query->whereHas('dietaryRestrictions', function ($q) use ($dietaryRestrictionId) {
            $q->where('dietary_restrictions.id', $dietaryRestrictionId);
        });
    }

    /**
     * Darabszámok a "Kedvezmény" szűrő legördülőjének soraiba - hány aktív
     * gyermeknek van ma ténylegesen az adott kedvezménye, plusz egy "any"
     * kulcs alatt az összesítő "bármilyen kedvezménye van" darabszám.
     * Enélkül az alapértelmezett "nincs szűrve" sor és a konkrét
     * kedvezmények megkülönböztethetetlennek tűntek, mert nem látszott,
     * hogy a szűrés ténylegesen hatással van-e a listára.
     */
    private function discountCounts(Institution $institution, \Closure $activeDiscountPeriodConstraint, \Illuminate\Support\Collection $discounts, ?int $noDiscountTypeId): array
    {
        $counts = [];

        $anyQuery = Child::query()->where('institution_id', $institution->id)->where('active', true);
        $this->applyDiscountFilter($anyQuery, 'any', $activeDiscountPeriodConstraint, $noDiscountTypeId);
        $counts['any'] = $anyQuery->count();

        foreach ($discounts as $discount) {
            $query = Child::query()->where('institution_id', $institution->id)->where('active', true);
            $this->applyDiscountFilter($query, $discount->id, $activeDiscountPeriodConstraint, $noDiscountTypeId);
            $counts[$discount->id] = $query->count();
        }

        return $counts;
    }

    /**
     * Darabszámok az "Allergén / étel-érzékenység" szűrő legördülőjének
     * soraiba - ugyanazon okból, mint a discountCounts(), plusz egy "any"
     * kulcs alatt a "van bármilyen beállítva" összesítő darabszám.
     */
    private function dietaryRestrictionCounts(Institution $institution, \Illuminate\Support\Collection $restrictions): array
    {
        $counts = [];

        $anyQuery = Child::query()->where('institution_id', $institution->id)->where('active', true);
        $this->applyDietaryRestrictionFilter($anyQuery, 'any');
        $counts['any'] = $anyQuery->count();

        foreach ($restrictions as $restriction) {
            $query = Child::query()->where('institution_id', $institution->id)->where('active', true);
            $this->applyDietaryRestrictionFilter($query, $restriction->id);
            $counts[$restriction->id] = $query->count();
        }

        return $counts;
    }

    private function groupNames(Institution $institution): array
    {
        return Child::where('institution_id', $institution->id)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name')
            ->all();
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function emptyToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
