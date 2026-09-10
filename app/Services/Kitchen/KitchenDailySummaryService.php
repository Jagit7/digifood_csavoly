<?php

namespace App\Services\Kitchen;

use App\Models\ClassGroup;
use App\Models\Institution;
use App\Models\InstitutionMealSetting;
use App\Models\StudentMealSetting;
use App\Services\DailyMealHeadcountService;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class KitchenDailySummaryService
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly DailyMealHeadcountService $headcount,
        private readonly EmployeeDailyMealHeadcountService $employeeHeadcount
    ) {}

    /**
     * Eldönti, hogy "ma" ki kell-e menjen a konyhai összefoglaló, és ha igen,
     * melyik napról szóljon. Szándékosan NEM a szülői lemondási határidő
     * (cancellationWindow()) logikáját használja a NAP kiválasztásához -
     * korábban ez a metódus onnan vette át a "next_service_day" értéket, ami
     * rejtett, áttételes függést jelentett egy másik funkciótól. A küldés
     * IDŐPONTJÁT (óra:perc) továbbra is az intézmény lemondási határideje
     * adja (ha az nincs beállítva, nem megy ki levél - ez a korábbi
     * viselkedés, ezen nem változtattunk).
     *
     * A NAP kiválasztásának szabálya - FONTOS: a konyhán hétvégén és szünet
     * idején senki sem dolgozik, tehát (a) csak egy olyan napon van értelme
     * kiküldeni a levelet, amikor a konyha ténylegesen ott van (MA magának is
     * tanítási/szolgáltatási napnak kell lennie), és (b) egy hétvége vagy
     * szünet előtt már az UTOLSÓ MUNKANAPON értesíteni kell a konyhát a
     * kimaradás utáni első tanítási napról - nem a hétvége/szünet valamelyik
     * napján, hiszen azt senki nem olvasná el időben (pl. péntek délután kell
     * tudni, hogy hétfőn hányan esznek, nem vasárnap).
     *
     *  1) Ha MA nem szolgáltatási nap (hétvége vagy szünet) - nem megy ki
     *     levél, a konyha úgyis zárva van, és az illetékes tájékoztatás már
     *     korábban, az utolsó munkanapon kiment.
     *  2) Ha MA szolgáltatási nap és HOLNAP is az - a megszokott, mindennapos
     *     eset: erről kell tájékoztatni (pl. kedden a szerdáról).
     *  3) Ha MA szolgáltatási nap, de HOLNAP már nem (hétvége vagy szünet
     *     kezdődik) - ma, az utolsó munkanapon kell tájékoztatni a kimaradás
     *     UTÁNI első tanítási napról, még ha az nem is holnapra esik (pl.
     *     péntek a hétfőről, vagy egy szünet előtti utolsó tanítási nap a
     *     szünet + egy közvetlenül utána eső hétvége utáni első napról).
     */
    public function scheduledNotificationContext(Institution $institution, ?CarbonImmutable $now = null): ?array
    {
        $now ??= $this->calendar->now();

        $setting = InstitutionMealSetting::query()
            ->where('institution_id', $institution->id)
            ->first();

        if ($setting?->cancellation_hour === null || $setting->cancellation_minute === null) {
            return null;
        }

        $serviceDate = $this->nextNotificationServiceDate($institution->id, $now->startOfDay());

        if ($serviceDate === null) {
            return null;
        }

        $cutoff = $now->setTime($setting->cancellation_hour, $setting->cancellation_minute);
        $sendAt = $cutoff->addMinute()->startOfMinute();

        return [
            'service_date' => $serviceDate->startOfDay(),
            'cutoff' => $cutoff,
            'send_at' => $sendAt,
            'is_due' => $now->startOfMinute()->equalTo($sendAt),
        ];
    }

    private function nextNotificationServiceDate(int $institutionId, CarbonImmutable $today): ?CarbonImmutable
    {
        // A konyha hétvégén/szünetben nem dolgozik - ha ma sem
        // tanítási/szolgáltatási nap, nincs kinek/mikor kiküldeni a levelet
        // (a szükséges tájékoztatás már korábban, az utolsó munkanapon
        // kiment, lásd lent).
        if (! $this->calendar->isServiceDay($institutionId, $today)) {
            return null;
        }

        $tomorrow = $today->addDay();

        if ($this->calendar->isServiceDay($institutionId, $tomorrow)) {
            return $tomorrow;
        }

        // Holnap már nem szolgáltatási nap (hétvége vagy szünet kezdődik) -
        // mivel ma az utolsó munkanap a kimaradás előtt, most kell
        // tájékoztatni a kimaradás UTÁNI első tanítási napról, akkor is ha az
        // több nappal későbbre esik (pl. szünet + az azt követő hétvége).
        $horizon = $tomorrow->addDays((int) config('digifood.cancellation_horizon_days', 400));

        return $this->calendar->serviceDaysBetween($institutionId, $tomorrow, $horizon)->first();
    }

    public function buildSummary(Institution $institution, CarbonInterface|string $serviceDate): array
    {
        $childData = $this->headcount->forDate($institution->id, $serviceDate);
        $employeeData = $this->employeeHeadcount->forDate($institution->id, $serviceDate);
        $date = CarbonImmutable::instance($childData['date'])->locale('hu');
        $childRows = $childData['rows'];
        $employeeRows = $employeeData['rows'];
        $rows = $childRows->concat($employeeRows)->values();
        $stats = $this->combinedStats($childData['stats'], $employeeData['stats']);

        $this->loadMealSettingRelations($rows);

        $menuItem = $childRows->pluck('menu_item')->first(fn ($item) => $item !== null);
        $cutoff = $this->calendar->cancellationDeadline($institution->id, $date);

        $summary = [
            'institution' => $institution,
            'service_date' => $date,
            'service_date_label' => $date->translatedFormat('Y. F j., l'),
            'cutoff' => $cutoff,
            'cutoff_label' => $cutoff?->locale('hu')->translatedFormat('Y. F j. H:i'),
            'stats' => $stats,
            'has_ab_menu' => $menuItem !== null && filled($menuItem->menu_b),
            'meal_type_counts' => $this->mealTypeCounts($childRows, $employeeRows),
            'dietary_breakdown' => $this->dietaryBreakdown($childRows, $employeeRows),
            'dietary_note' => $stats['employee_daily_eaters'] > 0
                ? 'A fenti sorok a pontos allergia-/érzékenység-kombinációnként MENTESEN elkészítendő adagok számát mutatják; egy gyermek vagy dolgozó mindig csak egy sorban, a teljes saját kombinációjával szerepel.'
                : 'A fenti sorok a pontos allergia-/érzékenység-kombinációnként MENTESEN elkészítendő adagok számát mutatják; egy gyermek mindig csak egy sorban, a teljes saját kombinációjával szerepel.',
            'subject' => sprintf(
                'Digifood – %s – Konyhai létszám – %s – %d fő',
                $institution->name,
                $date->format('Y.m.d.'),
                (int) ($stats['daily_eaters'] ?? 0)
            ),
        ];

        if ($institution->type === Institution::TYPE_SCHOOL) {
            $summary['school_summary'] = $this->schoolSummary($institution, $childRows);
        }

        return $summary;
    }

    /** Group the existing daily statuses; do not calculate eligibility or cancellations again. */
    private function schoolSummary(Institution $institution, Collection $childRows): array
    {
        $childRows = $childRows->unique(fn (array $row) => $row['child']->id)->values();
        // Daily operation uses group_name; class transfers also update this field.
        // Include registered active classes even when they have no children yet.
        $names = ClassGroup::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->pluck('name')
            ->concat($childRows->pluck('child.group_name'))
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn (string $name) => $name !== '')
            ->unique()
            ->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))
            ->values();

        $eligibleRows = $childRows->whereIn('status', [
            DailyMealHeadcountService::STATUS_EATING,
            DailyMealHeadcountService::STATUS_CANCELLED,
        ]);
        $grouped = $eligibleRows->groupBy(fn (array $row) => trim((string) $row['child']->group_name));
        if ($grouped->has('')) {
            $names->push('');
        }
        $collator = class_exists(\Collator::class) ? new \Collator('hu_HU') : null;
        $normalize = fn (string $name) => strtr(mb_strtolower($name, 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
            'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        ]);
        $classes = $names->map(function (string $name) use ($grouped, $collator, $normalize) {
            $rows = $grouped->get($name, collect());
            $absent = $rows->where('status', DailyMealHeadcountService::STATUS_CANCELLED)
                ->map(fn (array $row) => ['id' => $row['child']->id, 'name' => $row['child']->name])
                ->sort(function (array $a, array $b) use ($collator, $normalize) {
                    $order = $collator ? $collator->compare($a['name'], $b['name']) : strcmp($normalize($a['name']), $normalize($b['name']));

                    return $order ?: ($a['id'] <=> $b['id']);
                })->values();

            return [
                'name' => $name !== '' ? $name : 'Osztály nélkül',
                'total' => $rows->count(),
                'absent' => $absent->count(),
                'eating' => $rows->count() - $absent->count(),
                'absent_children' => $absent,
            ];
        });

        return [
            'total' => $classes->sum('total'),
            'absent' => $classes->sum('absent'),
            'eating' => $classes->sum('eating'),
            'classes' => $classes,
        ];
    }

    public function recipientEmails(Institution $institution): array
    {
        return $institution->setting?->kitchenNotificationEmails() ?? [];
    }

    private function loadMealSettingRelations(Collection $rows): void
    {
        $mealSettings = $rows
            ->pluck('meal_setting')
            ->filter(fn ($setting) => $setting instanceof StudentMealSetting)
            ->unique('id')
            ->values();

        if ($mealSettings->isEmpty()) {
            return;
        }

        (new EloquentCollection($mealSettings->all()))
            ->loadMissing([
                'mealTypes.mealType',
                'mealPackage.mealTypes.mealType',
            ]);
    }

    private function mealTypeCounts(Collection $childRows, Collection $employeeRows): Collection
    {
        $counts = collect();

        $this->appendMealTypeCounts($counts, $childRows, 'child_count');
        $this->appendMealTypeCounts($counts, $employeeRows, 'employee_count');

        return $counts
            ->sortBy([
                ['order', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * A konyhának nem az a hasznos, hogy összesen hány embert érint
     * egyenként egy-egy allergén (pl. "Glutén: 2 fő", "Tej: 2 fő"), hanem
     * az, hogy pontosan hány adagot kell elkészítenie egy adott
     * allergén-EGYÜTTESTŐL mentesen. Ezért itt személyenként állítjuk elő a
     * teljes allergia-/érzékenység-kombinációt (nem allergénenként külön
     * számolunk), és csak az AZONOS kombinációjú személyeket vonjuk össze
     * egy sorba - egy személy mindig pontosan egy kombináció darabszámát
     * növeli. A kombináció azonosítója az érintett allergén-ID-k rendezett
     * halmaza, így a felsorolási sorrend (pl. "glutén, tej" vs "tej,
     * glutén") nem hoz létre külön csoportot.
     */
    private function dietaryBreakdown(Collection $childRows, Collection $employeeRows): Collection
    {
        $combinations = collect();

        foreach ($childRows->concat($employeeRows) as $row) {
            if (($row['status'] ?? null) !== DailyMealHeadcountService::STATUS_EATING) {
                continue;
            }

            if (! ($row['is_dietary'] ?? false)) {
                continue;
            }

            $eater = $row['child'] ?? $row['employee'] ?? null;

            if ($eater === null) {
                continue;
            }

            $restrictions = collect($eater->dietaryRestrictions)
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['name', 'asc'],
                ])
                ->values();

            if ($restrictions->isEmpty()) {
                continue;
            }

            $key = $restrictions->pluck('id')->sort()->values()->implode('-');

            $entry = $combinations->get($key, [
                'names' => $restrictions->pluck('name')->all(),
                'min_sort_order' => (int) $restrictions->min('sort_order'),
                'count' => 0,
            ]);
            $entry['count']++;
            $combinations->put($key, $entry);
        }

        return $combinations
            ->map(fn (array $entry) => [
                'name' => $this->formatDietaryCombinationLabel($entry['names']),
                'count' => $entry['count'],
                'sort_order' => $entry['min_sort_order'],
            ])
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * Konyhai szempontból egyértelmű, "-mentes" megfogalmazású címkét ad egy
     * allergia-/érzékenység-kombinációhoz, pl.:
     * ["Glutén"] -> "Gluténmentes"
     * ["Tej", "Tojás"] -> "Tej- és tojásmentes"
     * ["Glutén", "Tej"] -> "Glutén- és tejmentes"
     * ["Glutén", "Tej", "Tojás"] -> "Glutén-, tej- és tojásmentes"
     *
     * @param  array<int, string>  $names  Az érintett allergének/érzékenységek
     *                                     nevei, már rendezett sorrendben.
     */
    private function formatDietaryCombinationLabel(array $names): string
    {
        $names = array_values($names);
        $count = count($names);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $names[0].'mentes';
        }

        $parts = [];

        foreach ($names as $index => $name) {
            $isFirst = $index === 0;
            $isLast = $index === $count - 1;
            $text = $isFirst ? $name : mb_strtolower($name, 'UTF-8');
            $parts[] = $isLast ? $text.'mentes' : $text.'-';
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' és '.$last;
    }

    private function appendMealTypeCounts(Collection $counts, Collection $rows, string $counterKey): void
    {
        foreach ($rows as $row) {
            if (($row['status'] ?? null) !== DailyMealHeadcountService::STATUS_EATING) {
                continue;
            }

            $mealSetting = $row['meal_setting'] ?? null;

            if (! $mealSetting instanceof StudentMealSetting) {
                continue;
            }

            $mealTypes = $mealSetting->mealTypes->isNotEmpty()
                ? $mealSetting->mealTypes
                : ($mealSetting->mealPackage?->mealTypes ?? collect());

            foreach ($mealTypes as $institutionMealType) {
                $mealType = $institutionMealType->mealType;

                if (! $mealType) {
                    continue;
                }

                $key = (string) $mealType->id;
                $entry = $counts->get($key, [
                    'name' => $mealType->name,
                    'count' => 0,
                    'child_count' => 0,
                    'employee_count' => 0,
                    'order' => (int) ($institutionMealType->pivot->display_order ?? $institutionMealType->display_order ?? 0),
                ]);
                $entry['count']++;
                $entry[$counterKey]++;
                $counts->put($key, $entry);
            }
        }
    }

    private function combinedStats(array $childStats, array $employeeStats): array
    {
        return [
            'child_daily_eaters' => (int) ($childStats['daily_eaters'] ?? 0),
            'employee_daily_eaters' => (int) ($employeeStats['daily_eaters'] ?? 0),
            'daily_eaters' => (int) ($childStats['daily_eaters'] ?? 0) + (int) ($employeeStats['daily_eaters'] ?? 0),
            'child_cancelled_meals' => (int) ($childStats['cancelled_meals'] ?? 0),
            'employee_cancelled_meals' => (int) ($employeeStats['cancelled_meals'] ?? 0),
            'cancelled_meals' => (int) ($childStats['cancelled_meals'] ?? 0) + (int) ($employeeStats['cancelled_meals'] ?? 0),
            'child_active_eaters' => (int) ($childStats['active_eaters'] ?? 0),
            'employee_active_eaters' => (int) ($employeeStats['active_eaters'] ?? 0),
            'active_eaters' => (int) ($childStats['active_eaters'] ?? 0) + (int) ($employeeStats['active_eaters'] ?? 0),
            'missing_children' => (int) ($childStats['missing_children'] ?? 0),
            'missing_employees' => (int) ($employeeStats['missing_employees'] ?? 0),
            'child_dietary_eaters' => (int) ($childStats['dietary_eaters'] ?? 0),
            'employee_dietary_eaters' => (int) ($employeeStats['dietary_eaters'] ?? 0),
            'dietary_eaters' => (int) ($childStats['dietary_eaters'] ?? 0) + (int) ($employeeStats['dietary_eaters'] ?? 0),
            'child_menu_a_count' => (int) ($childStats['menu_a_count'] ?? 0),
            'child_menu_b_count' => (int) ($childStats['menu_b_count'] ?? 0),
            'child_dietary_count' => (int) ($childStats['dietary_count'] ?? 0),
            'menu_a_count' => (int) ($childStats['menu_a_count'] ?? 0),
            'menu_b_count' => (int) ($childStats['menu_b_count'] ?? 0),
            'employee_dietary_count' => (int) ($employeeStats['dietary_eaters'] ?? 0),
            'dietary_count' => (int) ($childStats['dietary_count'] ?? 0) + (int) ($employeeStats['dietary_eaters'] ?? 0),
        ];
    }
}
