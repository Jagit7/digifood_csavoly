<?php

namespace App\Services\Meals;

use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealType;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentMealSettingService
{
    public const PARTICIPATION_STATUS_ACTIVE = 'active';

    public const PARTICIPATION_STATUS_UPCOMING = 'upcoming';

    public const PARTICIPATION_STATUS_MISSING = 'missing';

    public function __construct(
        private readonly PaymentObligationCalculatorService $paymentObligationCalculator
    ) {}

    public function settingsForEater(Model $eater, Institution $institution): Collection
    {
        return StudentMealSetting::query()
            ->with([
                'mealPackage',
                'mealTypes.mealType',
                'createdBy',
                'closedBy',
            ])
            ->forEater($eater)
            ->where('institution_id', $institution->id)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get();
    }

    public function currentSettingForEater(Model $eater, Institution $institution, Carbon|string $date): ?StudentMealSetting
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : (string) $date;

        return StudentMealSetting::query()
            ->forEater($eater)
            ->where('institution_id', $institution->id)
            ->activeOn($dateString)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    public function mealParticipationStatusesForChildren(
        Institution $institution,
        Collection $childIds,
        Carbon|string $date
    ): Collection {
        if ($childIds->isEmpty()) {
            return collect();
        }

        $dateString = $date instanceof Carbon ? $date->toDateString() : (string) $date;
        $currentSettings = $this->currentSettingsForChildren($institution, $childIds, $dateString);
        $upcomingSettings = $this->upcomingSettingsForChildren($institution, $childIds, $dateString);

        return $childIds
            ->unique()
            ->values()
            ->mapWithKeys(function ($childId) use ($currentSettings, $upcomingSettings) {
                $currentSetting = $currentSettings->get($childId);
                $upcomingSetting = $upcomingSettings->get($childId);
                $effectiveSetting = $currentSetting ?? $upcomingSetting;

                if ($currentSetting) {
                    $status = self::PARTICIPATION_STATUS_ACTIVE;
                    $label = 'Étkező';
                } elseif ($upcomingSetting) {
                    $status = self::PARTICIPATION_STATUS_UPCOMING;
                    $label = 'Étkező (ütemezve)';
                } else {
                    $status = self::PARTICIPATION_STATUS_MISSING;
                    $label = 'Nincs beállítva';
                }

                return [(int) $childId => [
                    'status' => $status,
                    'label' => $label,
                    'current_setting' => $currentSetting,
                    'upcoming_setting' => $upcomingSetting,
                    'effective_setting' => $effectiveSetting,
                    'starts_on' => $effectiveSetting?->valid_from,
                    'starts_on_label' => $effectiveSetting?->valid_from?->format('Y.m.d'),
                    'is_current' => $currentSetting !== null,
                    'is_upcoming' => $currentSetting === null && $upcomingSetting !== null,
                    'is_missing' => $effectiveSetting === null,
                ]];
            });
    }

    public function createSetting(
        Model $eater,
        Institution $institution,
        array $validated,
        int $userId
    ): StudentMealSetting {
        $validFrom = Carbon::parse($validated['valid_from'])->startOfDay();

        return DB::transaction(function () use ($eater, $institution, $validated, $userId, $validFrom) {
            /*
            * Csak valódi átfedést / azonos kezdődátumot tiltunk.
            * Egy későbbi beállítás önmagában nem hiba.
            */
            $this->guardAgainstOverlaps(
                $eater,
                $institution,
                $validFrom
            );

            /*
            * Megkeressük a következő, már előre rögzített beállítást.
            *
            * Ha van ilyen, az új időszak automatikusan
            * az előtte levő napon zárul.
            */
            $nextSetting = StudentMealSetting::query()
                ->forEater($eater)
                ->where('institution_id', $institution->id)
                ->whereDate('valid_from', '>', $validFrom->toDateString())
                ->orderBy('valid_from')
                ->orderBy('id')
                ->first();

            $newValidTo = $nextSetting
                ? $nextSetting->valid_from
                    ->copy()
                    ->subDay()
                    ->toDateString()
                : null;

            /*
            * Ha van egy korábbi nyitott időszak,
            * azt az új beállítás előtti napon lezárjuk.
            */
            $openSetting = StudentMealSetting::query()
                ->forEater($eater)
                ->where('institution_id', $institution->id)
                ->whereNull('valid_to')
                ->whereDate('valid_from', '<', $validFrom->toDateString())
                ->orderByDesc('valid_from')
                ->orderByDesc('id')
                ->first();

            if ($openSetting) {
                $openSetting->update([
                    'valid_to' => $validFrom
                        ->copy()
                        ->subDay()
                        ->toDateString(),
                ]);
            }

            $setting = StudentMealSetting::create([
                /*
                * Child esetén legacy kompatibilitás miatt
                * továbbra is töltjük a student_id mezőt.
                */
                'student_id' => $eater instanceof Child
                    ? $eater->id
                    : null,

                /*
                * Az új közös eater kapcsolat.
                */
                'eater_type' => $eater->getMorphClass(),
                'eater_id' => $eater->getKey(),

                'institution_id' => $institution->id,

                'institution_meal_package_id' => $validated['mode'] === StudentMealSetting::MODE_PACKAGE
                        ? $validated['institution_meal_package_id']
                        : null,

                'mode' => $validated['mode'],
                'valid_from' => $validFrom->toDateString(),
                'valid_to' => $newValidTo,
                'created_by' => $userId,
                'note' => $validated['note'] ?? null,
            ]);

            $this->syncMealTypes(
                $setting,
                $this->mealTypeIdsForSettingPayload($institution, $validated)
            );

            return $setting;
        });
    }

    /**
     * Egy MÁR LÉTEZŐ beállítás módosítása (mód / csomag / egyedi
     * étkezések / kezdődátum / megjegyzés) - korábban erre semmilyen
     * lehetőség nem volt, csak új beállítás felvétele vagy lezárás/
     * újranyitás. Direkt, valódi szerkesztést tesz lehetővé akár egy már
     * ELKEZDŐDÖTT (aktív vagy lezárt/történeti) időszaknál is - ezért a
     * szomszédos beállításokkal való összefüggést (a megelőző nyitott
     * időszak zárónapja, a rákövetkező beállítás előtti nap) minden
     * módosításnál újraszámoljuk, és - ha a dátumváltozás már lezárt havi
     * elszámolást is érinthet - ugyanúgy jelezzük, mint lezáráskor
     * (ld. closeSetting() / refreshAffectedDraftStatementsFrom()).
     */
    public function updateSetting(
        StudentMealSetting $setting,
        array $validated,
        int $userId
    ): array {
        $eater = $setting->eater;
        $institution = $setting->institution;
        $oldValidFrom = $setting->valid_from->copy();
        $newValidFrom = Carbon::parse($validated['valid_from'])->startOfDay();

        return DB::transaction(function () use ($setting, $eater, $institution, $validated, $oldValidFrom, $newValidFrom) {
            $this->guardAgainstOverlaps($eater, $institution, $newValidFrom, $setting->id);

            /*
            * A megelőző (más, korábban kezdődő) beállítást - ha az eddig
            * nyitott volt, vagy a zárónapja már belelógna az új
            * kezdődátumba - az új kezdés előtti napon lezárjuk, ugyanúgy,
            * mint amikor egy teljesen új beállítást veszünk fel
            * (createSetting()). Egy szándékosan, korábbi dátummal lezárt
            * megelőző időszakot nem nyúlunk hozzá.
            */
            $prevSetting = StudentMealSetting::query()
                ->forEater($eater)
                ->where('institution_id', $institution->id)
                ->where('id', '!=', $setting->id)
                ->whereDate('valid_from', '<', $newValidFrom->toDateString())
                ->orderByDesc('valid_from')
                ->orderByDesc('id')
                ->first();

            if ($prevSetting && ($prevSetting->valid_to === null || $prevSetting->valid_to->gte($newValidFrom))) {
                $prevSetting->update([
                    'valid_to' => $newValidFrom->copy()->subDay()->toDateString(),
                ]);
            }

            /*
            * A rákövetkező beállítás határozza meg, hogy ennek a
            * szerkesztett beállításnak automatikusan hol kell zárulnia -
            * ha nincs ilyen, a beállítás saját, eddigi zárónapját (pl. egy
            * korábbi kézi lezárást) megtartjuk.
            */
            $nextSetting = StudentMealSetting::query()
                ->forEater($eater)
                ->where('institution_id', $institution->id)
                ->where('id', '!=', $setting->id)
                ->whereDate('valid_from', '>', $newValidFrom->toDateString())
                ->orderBy('valid_from')
                ->orderBy('id')
                ->first();

            $newValidTo = $nextSetting
                ? $nextSetting->valid_from->copy()->subDay()->toDateString()
                : $setting->valid_to?->toDateString();

            if ($newValidTo !== null && Carbon::parse($newValidTo)->lt($newValidFrom)) {
                throw ValidationException::withMessages([
                    'valid_from' => 'A kezdődátum nem lehet későbbi, mint az időszak jelenlegi záró napja. Előbb módosítsd vagy töröld a zárást.',
                ]);
            }

            $setting->update([
                'institution_meal_package_id' => $validated['mode'] === StudentMealSetting::MODE_PACKAGE
                    ? $validated['institution_meal_package_id']
                    : null,
                'mode' => $validated['mode'],
                'valid_from' => $newValidFrom->toDateString(),
                'valid_to' => $newValidTo,
                'note' => $validated['note'] ?? null,
            ]);

            $this->syncMealTypes(
                $setting,
                $this->mealTypeIdsForSettingPayload($institution, $validated)
            );

            /*
            * A dátumváltozás miatt érintett tartomány a régi és az új
            * kezdődátum közül a korábbival kezdődik - ez fedi le mind azt
            * az esetet, amikor korábbra toljuk a kezdést (új napok
            * kerülnek be), mind azt, amikor későbbre (napok esnek ki).
            */
            $affectedFrom = $oldValidFrom->lt($newValidFrom) ? $oldValidFrom : $newValidFrom;

            return $this->refreshAffectedDraftStatementsFrom($setting, $affectedFrom);
        });
    }

    public function validateData($request, Institution $institution): array
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(array_keys(StudentMealSetting::MODE_LABELS))],
            'valid_from' => ['required', 'date'],
            'institution_meal_package_id' => [
                'nullable',
                'integer',
                Rule::exists('institution_meal_packages', 'id')
                    ->where(fn ($query) => $query
                        ->where('institution_id', $institution->id)
                        ->where('is_active', true)),
            ],
            'institution_meal_type_ids' => ['nullable', 'array'],
            'institution_meal_type_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('institution_meal_types', 'id')
                    ->where(fn ($query) => $query
                        ->where('institution_id', $institution->id)
                        ->where('is_active', true)),
            ],
            'note' => ['nullable', 'string'],
        ]);

        $mode = $validated['mode'];
        $selectedMealTypeIds = $validated['institution_meal_type_ids'] ?? [];
        $packageId = $validated['institution_meal_package_id'] ?? null;

        if ($mode === StudentMealSetting::MODE_INSTITUTION_DEFAULT) {
            if ($packageId !== null) {
                throw ValidationException::withMessages([
                    'institution_meal_package_id' => 'Intézményi alapértelmezett módnál nem választható külön menücsomag.',
                ]);
            }

            if (! empty($selectedMealTypeIds)) {
                throw ValidationException::withMessages([
                    'institution_meal_type_ids' => 'Intézményi alapértelmezett módnál nem adhatók meg egyedi étkezések.',
                ]);
            }

            if (! $this->defaultPackage($institution)) {
                throw ValidationException::withMessages([
                    'mode' => 'Az étkeztetés bekapcsolásához előbb állíts be egy aktív alapértelmezett menücsomagot.',
                ]);
            }
        }

        if ($mode === StudentMealSetting::MODE_PACKAGE) {
            if (! $packageId) {
                throw ValidationException::withMessages([
                    'institution_meal_package_id' => 'Menücsomag módnál kötelező aktív menücsomagot választani.',
                ]);
            }

            if (! empty($selectedMealTypeIds)) {
                throw ValidationException::withMessages([
                    'institution_meal_type_ids' => 'Menücsomag módnál nem adhatók meg egyedi étkezések.',
                ]);
            }
        }

        if ($mode === StudentMealSetting::MODE_CUSTOM) {
            if ($packageId !== null) {
                throw ValidationException::withMessages([
                    'institution_meal_package_id' => 'Egyedi módnál nem választható menücsomag.',
                ]);
            }

            if (count($selectedMealTypeIds) < 1) {
                throw ValidationException::withMessages([
                    'institution_meal_type_ids' => 'Egyedi módnál legalább egy aktív étkezéstípust ki kell választani.',
                ]);
            }
        }

        return $validated;
    }

    public function availablePackages(Institution $institution): Collection
    {
        return InstitutionMealPackage::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    public function availableMealTypes(Institution $institution): Collection
    {
        return InstitutionMealType::query()
            ->with('mealType')
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->join('meal_types', 'meal_types.id', '=', 'institution_meal_types.meal_type_id')
            ->orderBy('institution_meal_types.display_order')
            ->orderBy('meal_types.default_order')
            ->orderBy('meal_types.name')
            ->select('institution_meal_types.*')
            ->get();
    }

    public function defaultPackage(Institution $institution): ?InstitutionMealPackage
    {
        return InstitutionMealPackage::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->first();
    }

    public function guardAgainstOverlaps(
        Model $eater,
        Institution $institution,
        Carbon $validFrom,
        ?int $excludeSettingId = null
    ): void {
        $date = $validFrom->toDateString();

        /*
        * Nem kezdődhet új beállítás egy már lezárt
        * időszak belsejében.
        *
        * $excludeSettingId módosításnál (updateSetting()) a
        * szerkesztett bejegyzés saját magával való "ütközését"
        * zárja ki - létrehozáskor (createSetting()) nincs ilyen id,
        * akkor minden meglévő beállítást figyelembe veszünk.
        */
        $existsClosedOverlap = StudentMealSetting::query()
            ->forEater($eater)
            ->where('institution_id', $institution->id)
            ->when($excludeSettingId !== null, fn ($query) => $query->where('id', '!=', $excludeSettingId))
            ->whereNotNull('valid_to')
            ->whereDate('valid_from', '<=', $date)
            ->whereDate('valid_to', '>=', $date)
            ->exists();

        if ($existsClosedOverlap) {
            throw ValidationException::withMessages([
                'valid_from' => 'A megadott kezdődátum egy meglévő étkezési időszakkal átfedésben van.',
            ]);
        }

        /*
        * Azonos napon már ne lehessen még egy
        * beállítást létrehozni.
        *
        * Nyitott korábbi rekord viszont megengedett:
        * azt a createSetting() / updateSetting() automatikusan
        * lezárja az új kezdődátum előtti nappal.
        */
        $existsSameStart = StudentMealSetting::query()
            ->forEater($eater)
            ->where('institution_id', $institution->id)
            ->when($excludeSettingId !== null, fn ($query) => $query->where('id', '!=', $excludeSettingId))
            ->whereDate('valid_from', $date)
            ->exists();

        if ($existsSameStart) {
            throw ValidationException::withMessages([
                'valid_from' => 'Erre a kezdődátumra már létezik étkezési beállítás.',
            ]);
        }
    }

    public function syncMealTypes(StudentMealSetting $setting, array $institutionMealTypeIds): void
    {
        $syncData = [];

        foreach (array_values($institutionMealTypeIds) as $displayOrder => $institutionMealTypeId) {
            $syncData[$institutionMealTypeId] = [
                'display_order' => $displayOrder,
            ];
        }

        $setting->mealTypes()->sync($syncData);
    }

    private function mealTypeIdsForSettingPayload(Institution $institution, array $validated): array
    {
        if ($validated['mode'] === StudentMealSetting::MODE_CUSTOM) {
            return array_values($validated['institution_meal_type_ids'] ?? []);
        }

        if ($validated['mode'] !== StudentMealSetting::MODE_PACKAGE) {
            return [];
        }

        $packageId = $validated['institution_meal_package_id'] ?? null;

        if (! $packageId) {
            return [];
        }

        return InstitutionMealPackage::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->whereKey($packageId)
            ->first()?->mealTypes()
            ->orderByPivot('display_order')
            ->pluck('institution_meal_types.id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];
    }

    public function closeSetting(
        StudentMealSetting $setting,
        Carbon|string $lastMealDay,
        string $closureReason,
        ?string $closureNote,
        int $userId
    ): array {
        $lastMealDay = $lastMealDay instanceof Carbon
            ? $lastMealDay->copy()->startOfDay()
            : Carbon::parse($lastMealDay)->startOfDay();

        if ($lastMealDay->lt($setting->valid_from)) {
            throw ValidationException::withMessages([
                'last_meal_day' => 'Az utolsó étkezési nap nem lehet korábbi, mint az aktuális időszak kezdete.',
            ]);
        }

        /*
        * Felhasználói kérés: "visszamenőleges dátumra nem lehet
        * megszüntetni" - a controller (ChildMealSettingController::close())
        * ugyanezt már a validáláskor (after_or_equal:today) kiszűri, de
        * mivel closeSetting() más hívótól (pl. jövőbeli API/parancssori
        * eszköz) is elérhető, itt, a szolgáltatás szintjén is védünk
        * ellene - ne lehessen megkerülni a controller mellett.
        */
        if ($lastMealDay->lt(Carbon::today())) {
            throw ValidationException::withMessages([
                'last_meal_day' => 'Az utolsó étkezési nap nem lehet a mai napnál korábbi - visszamenőlegesen nem szüntethető meg az étkezési jogviszony.',
            ]);
        }

        return DB::transaction(function () use ($setting, $lastMealDay, $closureReason, $closureNote, $userId) {
            $setting->update([
                'valid_to' => $lastMealDay->toDateString(),
                'closed_by' => $userId,
                'closed_at' => now(),
                'closure_reason' => $closureReason,
                'closure_note' => $closureReason === StudentMealSetting::CLOSURE_REASON_OTHER
                    ? $this->normalizeText($closureNote)
                    : null,
            ]);

            $affectedFrom = $setting->valid_to?->copy()->addDay();

            if ($affectedFrom === null) {
                return ['recalculated_months' => [], 'locked_months' => []];
            }

            return $this->refreshAffectedDraftStatementsFrom($setting, $affectedFrom);
        });
    }

    public function reopenSetting(StudentMealSetting $setting): array
    {
        return DB::transaction(function () use ($setting) {
            $nextSetting = StudentMealSetting::query()
                ->forEater($setting->eater)
                ->where('institution_id', $setting->institution_id)
                ->whereDate('valid_from', '>', $setting->valid_from->toDateString())
                ->orderBy('valid_from')
                ->orderBy('id')
                ->first();

            $setting->update([
                'valid_to' => $nextSetting?->valid_from?->copy()->subDay()?->toDateString(),
                'closed_by' => null,
                'closed_at' => null,
                'closure_reason' => null,
                'closure_note' => null,
            ]);

            $affectedFrom = $setting->valid_to?->copy()->addDay();

            if ($affectedFrom === null) {
                return ['recalculated_months' => [], 'locked_months' => []];
            }

            return $this->refreshAffectedDraftStatementsFrom($setting, $affectedFrom);
        });
    }

    public function latestSettingForEater(Model $eater, Institution $institution): ?StudentMealSetting
    {
        return StudentMealSetting::query()
            ->with(['mealPackage', 'mealTypes.mealType', 'createdBy', 'closedBy'])
            ->forEater($eater)
            ->where('institution_id', $institution->id)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    private function currentSettingsForChildren(Institution $institution, Collection $childIds, string $date): Collection
    {
        return StudentMealSetting::query()
            ->with([
                'mealPackage',
                'mealTypes.mealType',
                'createdBy',
                'closedBy',
            ])
            ->whereIn('student_id', $childIds)
            ->where('institution_id', $institution->id)
            ->activeOn($date)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }

    private function upcomingSettingsForChildren(Institution $institution, Collection $childIds, string $date): Collection
    {
        return StudentMealSetting::query()
            ->with([
                'mealPackage',
                'mealTypes.mealType',
                'createdBy',
                'closedBy',
            ])
            ->whereIn('student_id', $childIds)
            ->where('institution_id', $institution->id)
            ->startingAfter($date)
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }

    /**
     * Egy adott naptól kezdve újraszámolja (vagy lezárt hónap esetén
     * jelzi) az érintett havi elszámolásokat - ugyanaz a mag, amit
     * korábban closeSetting() kizárólag saját magára (valid_to + 1 nap)
     * használt. updateSetting() ide már egy explicit, a régi és az új
     * kezdődátum közül a korábbival számolt dátumot ad át, hogy egy
     * dátummódosítás mindkét irányú hatását (napok bekerülése ÉS
     * kiesése) lefedje.
     */
    private function refreshAffectedDraftStatementsFrom(StudentMealSetting $setting, Carbon $affectedFrom): array
    {
        if (! $setting->eater instanceof Child) {
            return [
                'recalculated_months' => [],
                'locked_months' => [],
            ];
        }

        $recalculatedMonths = [];
        $lockedMonths = [];

        $statements = MonthlyPaymentStatement::query()
            ->where('institution_id', $setting->institution_id)
            ->where('child_id', $setting->student_id)
            ->with(['days' => fn ($query) => $query
                ->whereDate('date', '>=', $affectedFrom->toDateString())
                ->orderBy('date')])
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        foreach ($statements as $statement) {
            if ($statement->days->isEmpty()) {
                continue;
            }

            $paymentMonth = Carbon::create($statement->year, $statement->month, 1)->startOfMonth();

            if ($statement->isClosed()) {
                $lockedMonths[] = $paymentMonth->format('Y-m');

                continue;
            }

            $this->paymentObligationCalculator->recalculateMonth(
                $setting->institution,
                $paymentMonth,
                collect([$setting->student_id])
            );

            $recalculatedMonths[] = $paymentMonth->format('Y-m');
        }

        return [
            'recalculated_months' => array_values(array_unique($recalculatedMonths)),
            'locked_months' => array_values(array_unique($lockedMonths)),
        ];
    }

    private function normalizeText(?string $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
