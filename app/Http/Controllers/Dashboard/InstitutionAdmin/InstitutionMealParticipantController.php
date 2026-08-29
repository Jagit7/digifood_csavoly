<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\StudentMealSetting;
use App\Services\Meals\StudentMealSettingService;
use App\Services\Navigation\ChildListReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstitutionMealParticipantController extends Controller
{
    public function __construct(
        private readonly StudentMealSettingService $mealSettingService,
        private readonly ChildListReturnService $childListReturnService
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $institution = $this->institution();
        $today = $this->today();
        $listState = $this->childListReturnService->currentReturnState(
            ChildListReturnService::LIST_EATERS,
            $request->query(),
            $institution
        );

        $groups = Child::query()
            ->where('institution_id', $institution->id)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');

        $dietaryRestrictions = DietaryRestriction::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $allergens = $dietaryRestrictions->where('type', DietaryRestriction::TYPE_ALLERGEN);
        $intolerances = $dietaryRestrictions->where('type', DietaryRestriction::TYPE_INTOLERANCE);

        $discountTypes = DiscountType::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $children = $this->filteredChildrenQuery($request, $institution, $today)
            ->with([
                'discountType',
                'discountPeriods' => fn ($query) => $query
                    ->with('discountType')
                    ->whereDate('valid_from', '<=', $today)
                    ->where(function ($query) use ($today) {
                        $query->whereNull('valid_to')
                            ->orWhereDate('valid_to', '>=', $today);
                    }),
                'dietaryRestrictions',
                'recurringCancellationRules' => fn ($query) => $query
                    ->select(['id', 'child_id', 'weekday', 'starts_on', 'ends_on'])
                    ->where('status', 'active')
                    ->where(fn ($query) => $query
                        ->whereNull('ends_on')
                        ->orWhereDate('ends_on', '>=', $today))
                    ->orderBy('weekday'),
            ])
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $mealParticipationStatuses = $this->mealSettingService->mealParticipationStatusesForChildren(
            $institution,
            $children->getCollection()->pluck('id'),
            $today
        );
        $defaultPackage = $this->defaultPackage($institution);

        $children->getCollection()->transform(function (Child $child) use ($mealParticipationStatuses, $defaultPackage) {
            $mealParticipationStatus = $mealParticipationStatuses->get($child->id, [
                'status' => StudentMealSettingService::PARTICIPATION_STATUS_MISSING,
                'label' => 'Nem étkező',
                'current_setting' => null,
                'upcoming_setting' => null,
                'effective_setting' => null,
                'starts_on' => null,
                'starts_on_label' => null,
                'is_current' => false,
                'is_upcoming' => false,
                'is_missing' => true,
            ]);
            $currentSetting = $mealParticipationStatus['current_setting'];
            $upcomingSetting = $mealParticipationStatus['upcoming_setting'];

            $child->setRelation('currentMealSetting', $currentSetting);
            $child->setRelation('upcomingMealSetting', $upcomingSetting);
            /*
            * A "Aktuális menübeállítás" oszlop egysoros megjelenítéséhez
            * (mód + rövid részlet) szükséges adatokat itt, a controllerben
            * állítjuk össze, nem a Blade nézetben - egy korábbi, a nézetbe
            * írt többsoros @php blokk a nagy/összetett kifejezések miatt a
            * Blade fordítóban rendszeresen "Undefined variable" hibát
            * okozott (feltehetően a direktíva-illesztő reguláris kifejezés
            * elakadása/limitje miatt egy ilyen méretű fájlban) - ezt a
            * kockázatot zárjuk ki azzal, hogy a nézet csak egy kész,
            * egyszerű tömböt kap.
            */
            $child->setRelation(
                'mealSettingSummary',
                $this->mealSettingSummary($mealParticipationStatus['effective_setting'], $defaultPackage)
            );
            $child->setAttribute('meal_participation_status', $mealParticipationStatus);

            return $child;
        });

        if ($children->total() > 0 && $children->currentPage() > $children->lastPage()) {
            $redirectParams = $listState['params'];
            $redirectParams['page'] = $children->lastPage();

            return redirect()->route($listState['route'], $redirectParams);
        }

        $stats = [
            'participants' => $this->participantCount($institution, $today),
            'non_participants' => Child::query()
                ->where('institution_id', $institution->id)
                ->count() - $this->participantCount($institution, $today),
            'active_children' => Child::query()
                ->where('institution_id', $institution->id)
                ->where('active', true)
                ->count(),
            'default_package' => $defaultPackage?->name ?? 'Nincs beállítva',
        ];

        return view('dashboard.institution_admin.children.meal-participants.index', [
            'institution' => $institution,
            'children' => $children,
            'groups' => $groups,
            'allergens' => $allergens,
            'intolerances' => $intolerances,
            'discountTypes' => $discountTypes,
            'stats' => $stats,
            'defaultPackage' => $defaultPackage,
            'modeLabels' => StudentMealSetting::MODE_LABELS,
            'weekdayLabels' => [
                1 => 'Hétfő', 2 => 'Kedd', 3 => 'Szerda', 4 => 'Csütörtök',
                5 => 'Péntek', 6 => 'Szombat', 7 => 'Vasárnap',
            ],
            'today' => $today,
            'listState' => $listState,
        ]);
    }

    public function enable(Request $request, Child $child): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeChild($child, $institution);
        $validated = $request->validate([
            'valid_from' => ['required', 'date'],
        ]);
        $validFrom = Carbon::parse($validated['valid_from'])->startOfDay();

        DB::transaction(function () use ($request, $child, $institution, $validFrom) {
            $this->assertDefaultPackageExists($institution);
            $this->createInstitutionDefaultSetting(
                $child,
                $institution,
                $validFrom,
                $request->user()->id
            );
        });

        return redirect()
            ->route('dashboard.institution.children.meal-participants.index', $this->indexQueryParameters($request))
            ->with('success', 'Az étkeztetés sikeresen bekapcsolva.');
    }

    public function disable(Request $request, Child $child): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeChild($child, $institution);
        $today = $this->today();
        $setting = $this->currentSetting($child, $institution, $today);

        if (! $setting) {
            return redirect()
                ->route('dashboard.institution.children.meal-participants.index', $this->indexQueryParameters($request))
                ->with('error', 'A gyermek jelenleg nem étkező.');
        }

        $validated = $request->validate([
            'last_meal_day' => ['required', 'date', 'after_or_equal:'.$today],
        ]);
        $lastMealDay = Carbon::parse($validated['last_meal_day'])->startOfDay();

        if ($lastMealDay->lt($setting->valid_from)) {
            throw ValidationException::withMessages([
                'last_meal_day' => 'Az utolsó étkezési nap nem lehet korábbi, mint az aktuális időszak kezdete.',
            ]);
        }

        $setting->update([
            'valid_to' => $lastMealDay->toDateString(),
        ]);

        return redirect()
            ->route('dashboard.institution.children.meal-participants.index', $this->indexQueryParameters($request))
            ->with('success', 'Az étkeztetés megszüntetése rögzítve lett.');
    }

    public function bulkEnable(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $today = $this->today();

        /*
        * A táblázat oldalanként 50 gyermeket mutat (ld. index() ->paginate(50)),
        * a fejléc "összes kijelölése" checkbox viszont a böngészőben csak azt
        * tudja bejelölni, ami éppen a DOM-ban van - tehát csak az AKTUÁLIS
        * oldalt. Ha az intézménynek 50-nél több gyermeke van, egy admin
        * könnyen azt hihette, hogy "mindenkit" kijelölt és bekapcsolt,
        * miközben valójában csak az első oldal esett át a műveleten, a többi
        * gyermeknél pedig semmi nem történt - ezt észre sem lehetett venni,
        * amíg valaki rá nem keresett egy konkrét, más oldalon lévő gyermekre.
        * A "select_all_matching" jelző erre a helyzetre ad megoldást: ekkor
        * nem a böngészőben összegyűjtött child_ids listát használjuk, hanem
        * ugyanazokkal a szűrőkkel (search / csoport / státusz / diéta /
        * kedvezmény) újra lekérdezzük a TELJES, az oldalazástól független
        * találati listát a szerveren.
        */
        $selectAllMatching = $request->boolean('select_all_matching');

        $validated = $request->validate([
            'select_all_matching' => ['nullable', 'boolean'],
            'child_ids' => [$selectAllMatching ? 'nullable' : 'required', 'array', 'max:1000'],
            'child_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('children', 'id')
                    ->where(fn ($query) => $query->where('institution_id', $institution->id)),
            ],
            'valid_from' => ['required', 'date'],
        ]);
        $validFrom = Carbon::parse($validated['valid_from'])->startOfDay();

        $childIds = $selectAllMatching
            ? $this->matchingChildIds($request, $institution, $today)
            : $validated['child_ids'];

        $children = Child::query()
            ->where('institution_id', $institution->id)
            ->whereIn('id', $childIds)
            ->orderBy('name')
            ->get();

        $enabledCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($request, $institution, $validFrom, $children, &$enabledCount, &$skippedCount) {
            $this->assertDefaultPackageExists($institution);

            foreach ($children as $child) {
                /*
                * Korábban itt egy hasSettingOnDate() előszűrés futott, ami
                * kihagyott minden gyermeket, akinek MÁR VAN a kezdődátumot
                * lefedő (akár egy tavalyról nyitva maradt, valid_to=null)
                * beállítása - ez a visszatérő, folytatólagosan étkező
                * gyermekeket is kihagyta a tömeges bekapcsolásból, holott a
                * createInstitutionDefaultSetting() maga is le tudja zárni a
                * régi nyitott időszakot az új kezdés előtti napon, és utána
                * felveszi az újat. Most ugyanazt az átfedés-ellenőrzést
                * (guardAgainstOverlaps) használjuk, mint az egyedi
                * bekapcsolásnál - ez csak valódi ütközésnél (pl. már van
                * egy, az adott napon vagy később kezdődő nyitott időszak,
                * vagy a dátum egy lezárt időszakba esik) dobja ki a
                * gyermeket, a folytatólagos étkezőket átmenetivel kezeli.
                */
                try {
                    $this->createInstitutionDefaultSetting(
                        $child,
                        $institution,
                        $validFrom,
                        $request->user()->id
                    );

                    $enabledCount++;
                } catch (ValidationException) {
                    $skippedCount++;
                }
            }
        });

        return redirect()
            ->route('dashboard.institution.children.meal-participants.index', $this->indexQueryParameters($request))
            ->with('success', "Tömeges bekapcsolás kész: {$enabledCount} gyermeknél bekapcsolva, {$skippedCount} kihagyva.");
    }

    public function bulkDisable(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $today = $this->today();
        $selectAllMatching = $request->boolean('select_all_matching');

        $validated = $request->validate([
            'select_all_matching' => ['nullable', 'boolean'],
            'child_ids' => [$selectAllMatching ? 'nullable' : 'required', 'array', 'max:1000'],
            'child_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('children', 'id')
                    ->where(fn ($query) => $query->where('institution_id', $institution->id)),
            ],
            'last_meal_day' => ['required', 'date', 'after_or_equal:'.$today],
        ]);
        $lastMealDay = Carbon::parse($validated['last_meal_day'])->startOfDay();

        $childIds = $selectAllMatching
            ? $this->matchingChildIds($request, $institution, $today)
            : $validated['child_ids'];

        $currentSettings = StudentMealSetting::query()
            ->whereIn('student_id', $childIds)
            ->where('institution_id', $institution->id)
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

        $disabledCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($currentSettings, $lastMealDay, &$disabledCount, &$skippedCount) {
            foreach ($currentSettings as $setting) {
                if ($lastMealDay->lt($setting->valid_from)) {
                    $skippedCount++;

                    continue;
                }

                $setting->update([
                    'valid_to' => $lastMealDay->toDateString(),
                ]);

                $disabledCount++;
            }
        });

        $skippedCount += count($childIds) - $currentSettings->count();

        return redirect()
            ->route('dashboard.institution.children.meal-participants.index', $this->indexQueryParameters($request))
            ->with('success', "Tömeges megszüntetés kész: {$disabledCount} gyermeknél lezárva, {$skippedCount} kihagyva.");
    }

    private function createInstitutionDefaultSetting(
        Child $child,
        Institution $institution,
        Carbon $validFrom,
        int $userId
    ): StudentMealSetting {
        $this->guardAgainstOverlaps($child, $institution, $validFrom);

        $openSetting = StudentMealSetting::query()
            ->where('student_id', $child->id)
            ->where('institution_id', $institution->id)
            ->whereNull('valid_to')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();

        if ($openSetting && $openSetting->valid_from->lt($validFrom)) {
            $openSetting->update([
                'valid_to' => $validFrom->copy()->subDay()->toDateString(),
            ]);
        }

        return StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'institution_meal_package_id' => null,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => $validFrom->toDateString(),
            'valid_to' => null,
            'created_by' => $userId,
            'note' => null,
        ]);
    }

    private function guardAgainstOverlaps(Child $child, Institution $institution, Carbon $validFrom): void
    {
        $date = $validFrom->toDateString();

        $existsFuture = StudentMealSetting::query()
            ->where('student_id', $child->id)
            ->where('institution_id', $institution->id)
            ->whereDate('valid_from', '>', $date)
            ->exists();

        if ($existsFuture) {
            throw ValidationException::withMessages([
                'valid_from' => 'Nem hozható létre olyan beállítás, amely későbbi rögzített időszakokkal átfedne.',
            ]);
        }

        $existsClosedOverlap = StudentMealSetting::query()
            ->where('student_id', $child->id)
            ->where('institution_id', $institution->id)
            ->whereNotNull('valid_to')
            ->whereDate('valid_from', '<=', $date)
            ->whereDate('valid_to', '>=', $date)
            ->exists();

        if ($existsClosedOverlap) {
            throw ValidationException::withMessages([
                'valid_from' => 'A megadott kezdődátum egy meglévő időszakkal átfedésben van.',
            ]);
        }

        $existsOpenOverlap = StudentMealSetting::query()
            ->where('student_id', $child->id)
            ->where('institution_id', $institution->id)
            ->whereNull('valid_to')
            ->whereDate('valid_from', '>=', $date)
            ->exists();

        if ($existsOpenOverlap) {
            throw ValidationException::withMessages([
                'valid_from' => 'A megadott kezdődátum nem lehet meglévő nyitott időszak kezdete előtt vagy azzal azonos.',
            ]);
        }
    }

    /**
     * A "jelenleg étkező" definíciója - egy student_meal_settings sor,
     * amelynek időszaka lefedi a mai napot. Közös segédmetódus a
     * meal_status és a discount_filter szűréshez, hogy ne kelljen kétszer
     * karbantartani ugyanazt a whereExists/whereNotExists feltételt.
     */
    private function participantSubquery($subQuery, Institution $institution, string $today): void
    {
        $subQuery->selectRaw('1')
            ->from('student_meal_settings')
            ->whereColumn('student_meal_settings.student_id', 'children.id')
            ->where('student_meal_settings.institution_id', $institution->id)
            ->whereDate('student_meal_settings.valid_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('student_meal_settings.valid_to')
                    ->orWhereDate('student_meal_settings.valid_to', '>=', $today);
            });
    }

    /**
     * A Keresés és szűrés kártya összes feltételét (search / csoport /
     * intézményi státusz / diéta / étkezési státusz / kedvezmény) egy
     * helyen tartja - így az index() listázás ÉS a "select_all_matching"
     * tömeges kijelölés (ld. matchingChildIds()) garantáltan pontosan
     * ugyanazokat a gyermekeket adja vissza, nincs esély arra, hogy a kettő
     * eltérjen egymástól.
     */
    private function filteredChildrenQuery(Request $request, Institution $institution, string $today): Builder
    {
        $dietFilter = (string) $request->input('diet_filter');
        $discountFilter = $request->filled('discount_filter') ? (int) $request->input('discount_filter') : null;

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
            ->when($dietFilter !== '', function ($query) use ($dietFilter) {
                if ($dietFilter === 'any_allergen' || $dietFilter === 'any_intolerance') {
                    $type = $dietFilter === 'any_allergen'
                        ? DietaryRestriction::TYPE_ALLERGEN
                        : DietaryRestriction::TYPE_INTOLERANCE;

                    $query->whereHas('dietaryRestrictions', function ($query) use ($type) {
                        $query->where('type', $type);
                    });

                    return;
                }

                if (str_starts_with($dietFilter, 'restriction_')) {
                    $restrictionId = (int) substr($dietFilter, strlen('restriction_'));

                    $query->whereHas('dietaryRestrictions', function ($query) use ($restrictionId) {
                        $query->where('dietary_restrictions.id', $restrictionId);
                    });
                }
            })
            ->when(in_array($request->input('meal_status'), ['participant', 'non_participant'], true), function ($query) use ($request, $institution, $today) {
                $method = $request->input('meal_status') === 'participant' ? 'whereExists' : 'whereNotExists';

                $query->{$method}(function ($subQuery) use ($institution, $today) {
                    $this->participantSubquery($subQuery, $institution, $today);
                });
            })
            // A kedvezmény szerinti szűrés csak az aktuálisan étkező gyermekek
            // között értelmes (a lista lényege az étkezők nyomon követése),
            // ezért ez a feltétel mindig hozzáadja az "étkező" megkötést is,
            // függetlenül attól, hogy az Étkezési státusz szűrő be van-e állítva.
            ->when($discountFilter !== null, function ($query) use ($discountFilter, $today, $institution) {
                $query->whereExists(function ($subQuery) use ($institution, $today) {
                    $this->participantSubquery($subQuery, $institution, $today);
                });

                $query->where(function ($query) use ($discountFilter, $today) {
                    $query->whereExists(function ($subQuery) use ($discountFilter, $today) {
                        $subQuery->selectRaw('1')
                            ->from('child_discount_periods')
                            ->whereColumn('child_discount_periods.child_id', 'children.id')
                            ->where('child_discount_periods.discount_type_id', $discountFilter)
                            ->whereDate('child_discount_periods.valid_from', '<=', $today)
                            ->where(function ($query) use ($today) {
                                $query->whereNull('child_discount_periods.valid_to')
                                    ->orWhereDate('child_discount_periods.valid_to', '>=', $today);
                            });
                    })->orWhere(function ($query) use ($discountFilter, $today) {
                        // Nincs dátum szerint aktív egyedi kedvezményperiódusa ->
                        // a gyermek alapértelmezett (children.discount_type_id)
                        // kedvezménye számít, ugyanúgy, mint a
                        // Child::discountTypeForDate() metódusban.
                        $query->where('children.discount_type_id', $discountFilter)
                            ->whereNotExists(function ($subQuery) use ($today) {
                                $subQuery->selectRaw('1')
                                    ->from('child_discount_periods')
                                    ->whereColumn('child_discount_periods.child_id', 'children.id')
                                    ->whereDate('child_discount_periods.valid_from', '<=', $today)
                                    ->where(function ($query) use ($today) {
                                        $query->whereNull('child_discount_periods.valid_to')
                                            ->orWhereDate('child_discount_periods.valid_to', '>=', $today);
                                    });
                            });
                    });
                });
            });
    }

    /**
     * Az aktuális szűrőknek megfelelő ÖSSZES gyermek id-ja, oldalazás
     * nélkül - a "Mind a X találat kijelölése" tömeges művelethez kell,
     * hogy ne csak a táblázat éppen látható (max. 50 soros) oldalára
     * hasson a Bekapcsolás / Megszüntetés.
     */
    private function matchingChildIds(Request $request, Institution $institution, string $today): array
    {
        return $this->filteredChildrenQuery($request, $institution, $today)
            ->pluck('id')
            ->all();
    }

    private function currentSetting(Child $child, Institution $institution, string $date): ?StudentMealSetting
    {
        return StudentMealSetting::query()
            ->where('student_id', $child->id)
            ->where('institution_id', $institution->id)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    private function currentSettingsForChildren(Institution $institution, Collection $childIds, string $date): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage',
                'mealTypes.mealType',
            ])
            ->whereIn('student_id', $childIds)
            ->where('institution_id', $institution->id)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }

    /**
     * Gyermekenként a legutóbbi (legnagyobb valid_from-ú) étkezési
     * beállítást adja vissza, dátumszűrés nélkül - ez lehet egy jövőben
     * induló (még nem "aktuális") beállítás is, hogy a lista meg tudja
     * különböztetni a "nem étkező"-t az "ütemezve, még nem kezdődött el"
     * állapottól (ld. meal-participants/index.blade.php).
     */
    private function latestSettingsForChildren(Institution $institution, Collection $childIds): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage',
                'mealTypes.mealType',
            ])
            ->whereIn('student_id', $childIds)
            ->where('institution_id', $institution->id)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }

    private function participantCount(Institution $institution, string $date): int
    {
        return StudentMealSetting::query()
            ->where('institution_id', $institution->id)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $date);
            })
            ->distinct()
            ->count('student_id');
    }

    /**
     * A lista "Aktuális menübeállítás" oszlopának rövid, egysoros
     * összefoglalója egy adott (aktuális vagy ütemezett) beállításhoz -
     * ld. a index() metódusban lévő megjegyzést, miért itt, a controllerben
     * számoljuk ki, nem a Blade nézetben.
     */
    private function mealSettingSummary(?StudentMealSetting $setting, ?InstitutionMealPackage $defaultPackage): ?array
    {
        if (! $setting) {
            return null;
        }

        /*
        * $detail csak akkor kap "üres" jelzést helyettesítő szöveget
        * (pl. "—"), ha az ténylegesen informatív (pl. hiányzik az
        * alapértelmezett csomag - ezt jó, ha látja az admin). A puszta
        * "nincs adat" esetekben (nincs csomag kiválasztva / nincs
        * kiválasztott egyedi étkezés) inkább null-t adunk vissza, hogy a
        * nézet ne írjon ki egy plusz, önmagában semmitmondó kötőjelet a
        * mód neve mellé - ld. meal-participants/index.blade.php.
        */
        if ($setting->mode === StudentMealSetting::MODE_INSTITUTION_DEFAULT) {
            $detail = $defaultPackage?->name ?? 'Nincs alapértelmezett csomag';
        } elseif ($setting->mode === StudentMealSetting::MODE_PACKAGE) {
            $detail = $setting->mealPackage?->name;
        } else {
            $detail = $setting->mealTypes->map(fn ($mealType) => $mealType->mealType->name)->implode(', ');
            $detail = $detail !== '' ? $detail : null;
        }

        return [
            'mode_label' => StudentMealSetting::MODE_LABELS[$setting->mode] ?? $setting->mode,
            'detail' => $detail,
        ];
    }

    private function assertDefaultPackageExists(Institution $institution): void
    {
        if ($this->defaultPackage($institution)) {
            return;
        }

        throw ValidationException::withMessages([
            'valid_from' => 'Az étkeztetés bekapcsolásához előbb állítson be egy aktív alapértelmezett menücsomagot.',
        ]);
    }

    private function defaultPackage(Institution $institution): ?InstitutionMealPackage
    {
        return InstitutionMealPackage::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->first();
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function authorizeChild(Child $child, Institution $institution): void
    {
        abort_if($child->institution_id !== $institution->id, 403);
    }

    private function today(): string
    {
        return now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
    }

    private function indexQueryParameters(Request $request): array
    {
        return $request->only(['search', 'group_name', 'meal_status', 'status', 'diet_filter', 'discount_filter', 'page']);
    }
}
