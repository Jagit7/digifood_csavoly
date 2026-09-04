<?php

namespace App\Services\DailyHeadcount;

use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionDailyHeadcountEmailGroupSetting;
use App\Models\InstitutionDailyHeadcountEmailRecipient;
use App\Models\InstitutionSetting;
use App\Services\DailyMealHeadcountService;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * A "Napi létszám e-mailek" funkció üzleti logikája - osztályonként/
 * csoportonként, iskolai és óvodai intézményeknél egyaránt.
 *
 * Szándékosan NEM ír új napi létszám-számítási logikát: az aznapi étkezői
 * létszámhoz ugyanazt a DailyMealHeadcountService::forDate() hívást
 * használja, amit a "Napi működés -> Mai létszám" oldal is (ld.
 * DailyOperationController::todayCounts()), és amit a meglévő óvodai "napi
 * jelenléti ív" funkció (DailyAttendanceEmailService) is használ. Ez a
 * service kizárólag az osztályonkénti/csoportonkénti szűrést, az e-mailhez
 * szükséges összesítést, valamint a beállítások (közös küldési idő,
 * osztályonkénti/csoportonkénti be-/kikapcsolás, címzettek) kezelését adja.
 *
 * A csoport/osztály azonosítására szándékosan a Child::group_name mezőt
 * használja - ugyanazt a mezőt, amit a DailyMealHeadcountService, a "Mai
 * létszám" oldal és a meglévő "napi jelenléti ív" funkció is használ -,
 * NEM a class_groups.id-t. Ez biztosítja, hogy a kiküldött létszám mindig
 * pontosan ugyanazt az osztályt/csoportot jelentse, amit az admin a Napi
 * működés oldalon is lát, tanévváltástól függetlenül.
 */
class DailyHeadcountEmailService
{
    public const DEFAULT_SEND_TIME = '07:30:00';

    public function __construct(
        private readonly DailyMealHeadcountService $headcount,
        private readonly InstitutionCalendarService $calendar,
    ) {}

    /**
     * Az intézményben ténylegesen létező (aktív gyermekhez rendelt)
     * osztály-/csoportnevek - ugyanaz a forrás, amit a ChildPrintListController
     * és a DailyAttendanceEmailService is használ. Új osztály/csoport
     * automatikusan megjelenik itt, mihelyt egy aktív gyermeknek be van
     * állítva - nincs szükség külön adatbázis-migrálásra.
     */
    public function groupNames(int $institutionId): Collection
    {
        return Child::query()
            ->where('institution_id', $institutionId)
            ->where('active', true)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');
    }

    /**
     * Csoportnév => be van-e kapcsolva a napi létszám e-mail küldése.
     * Egy csoport, amihez még nem tartozik beállítás-sor, alapból
     * kikapcsoltnak számít (ld. isGroupEnabled()).
     *
     * @return Collection<string, bool>
     */
    public function groupEnabledMap(int $institutionId): Collection
    {
        return InstitutionDailyHeadcountEmailGroupSetting::query()
            ->where('institution_id', $institutionId)
            ->get()
            ->mapWithKeys(fn (InstitutionDailyHeadcountEmailGroupSetting $setting) => [
                (string) $setting->group_name => (bool) $setting->enabled,
            ]);
    }

    public function isGroupEnabled(int $institutionId, string $groupName): bool
    {
        return (bool) InstitutionDailyHeadcountEmailGroupSetting::query()
            ->where('institution_id', $institutionId)
            ->where('group_name', $groupName)
            ->value('enabled');
    }

    public function setGroupEnabled(int $institutionId, string $groupName, bool $enabled): void
    {
        InstitutionDailyHeadcountEmailGroupSetting::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'group_name' => $groupName],
            ['enabled' => $enabled]
        );
    }

    /**
     * Csoportnév => e-mail címek (rendezett) - az intézmény összes mentett
     * napi létszám e-mail címzettje, osztályonként/csoportonként
     * csoportosítva.
     *
     * @return Collection<string, Collection<int, string>>
     */
    public function recipientsByGroup(int $institutionId): Collection
    {
        return InstitutionDailyHeadcountEmailRecipient::query()
            ->where('institution_id', $institutionId)
            ->orderBy('group_name')
            ->orderBy('email')
            ->get()
            ->groupBy('group_name')
            ->map(fn (Collection $recipients) => $recipients->pluck('email')->values());
    }

    /**
     * @return array<int, string>
     */
    public function recipientEmailsForGroup(int $institutionId, string $groupName): array
    {
        return InstitutionDailyHeadcountEmailRecipient::query()
            ->where('institution_id', $institutionId)
            ->where('group_name', $groupName)
            ->orderBy('email')
            ->pluck('email')
            ->all();
    }

    /**
     * Elmenti egy osztály/csoport címzettjeit. A megadott lista a csoport
     * TELJES új állapota - a korábbi, itt nem szereplő címzettek törlődnek,
     * az újak létrejönnek.
     *
     * @param  array<int, string>  $emails
     */
    public function syncGroupRecipients(int $institutionId, string $groupName, array $emails): void
    {
        $normalized = collect($emails)
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email) => $email !== '')
            ->unique()
            ->values();

        InstitutionDailyHeadcountEmailRecipient::query()
            ->where('institution_id', $institutionId)
            ->where('group_name', $groupName)
            ->delete();

        if ($normalized->isEmpty()) {
            return;
        }

        $now = now();

        InstitutionDailyHeadcountEmailRecipient::query()->insert(
            $normalized->map(fn (string $email) => [
                'institution_id' => $institutionId,
                'group_name' => $groupName,
                'email' => $email,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    /**
     * Egyetlen mentési művelettel frissíti egy osztály/csoport
     * be-/kikapcsolt állapotát ÉS címzettjeit (ld. admin felület: egy
     * "mentés" gomb csoportonként).
     *
     * @param  array<int, string>  $emails
     */
    public function updateGroupConfiguration(int $institutionId, string $groupName, bool $enabled, array $emails): void
    {
        $this->setGroupEnabled($institutionId, $groupName, $enabled);
        $this->syncGroupRecipients($institutionId, $groupName, $emails);
    }

    /**
     * Azok az osztályok/csoportok, amelyekre ténylegesen mehet e-mail: be
     * vannak kapcsolva ÉS van legalább egy címzettjük. Ezt hívja a
     * scheduler parancs (DispatchDailyHeadcountEmailsCommand).
     *
     * @return Collection<string, array<int, string>>
     */
    public function enabledGroupsWithRecipients(int $institutionId): Collection
    {
        $enabledGroups = $this->groupEnabledMap($institutionId)
            ->filter(fn (bool $enabled) => $enabled);

        if ($enabledGroups->isEmpty()) {
            return collect();
        }

        return $this->recipientsByGroup($institutionId)
        ->only($enabledGroups->keys()->all())
        ->filter(fn (Collection $emails) => $emails->isNotEmpty())
        ->map(fn (Collection $emails) => $emails->all());
    }

    public function updateSchedule(Institution $institution, bool $enabled, ?string $sendTime): void
    {
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        $normalizedSendTime = $this->normalizeSendTime($sendTime)
            ?? $setting->daily_headcount_email_send_time
            ?? self::DEFAULT_SEND_TIME;

        $setting->update([
            'daily_headcount_email_enabled' => $enabled,
            'daily_headcount_email_send_time' => $normalizedSendTime,
        ]);
    }

    /**
     * "H:i" vagy "H:i:s" formátumú bemenetből "H:i:s" formátumú, tárolható
     * időt állít elő, vagy null-t, ha a bemenet nem értelmezhető.
     */
    public function normalizeSendTime(?string $sendTime): ?string
    {
        if (! filled($sendTime)) {
            return null;
        }

        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)/', trim($sendTime), $matches)) {
            return null;
        }

        return sprintf('%s:%s:00', $matches[1], $matches[2]);
    }

    public function sendTimeLabel(?string $sendTime): string
    {
        $raw = $sendTime ?: self::DEFAULT_SEND_TIME;

        return substr((string) $raw, 0, 5);
    }

    public function isServiceDay(int $institutionId, CarbonInterface|string $date): bool
    {
        return $this->calendar->isServiceDay($institutionId, $date);
    }

    /**
     * A DailyMealHeadcountService adott napi eredménye - egyszer számolva,
     * hogy egy intézmény több osztályának/csoportjának összesítője is
     * ugyanabból az adatból épüljön fel (ne fusson le csoportonként újra a
     * lekérdezés).
     */
    public function dailyDataForInstitution(int $institutionId, CarbonInterface|string $date): array
    {
        return $this->headcount->forDate($institutionId, $date);
    }

    /**
     * Egy adott osztály/csoport adott napi étkezési létszám-összesítője.
     * Ugyanezt hívja a scheduler parancs (éles kiküldés), az előnézet és a
     * tesztküldés is, hogy mindenhol pontosan ugyanaz az adat jelenjen meg.
     *
     * A "eaters" mező az adott napon ténylegesen étkező gyermekek ABC
     * sorrendbe rendezett névsora - ugyanabból a $groupRows szűrésből
     * származik, mint az "eaters_count", tehát nincs kétféle, egymástól
     * eltérhető számítás: az "eaters_count" mindig pontosan az "eaters"
     * lista elemszáma.
     *
     * @return array{
     *     institution: Institution,
     *     group_name: string,
     *     date: CarbonImmutable,
     *     date_label: string,
     *     eaters: Collection<int, string>,
     *     eaters_count: int,
     *     is_service_day: bool,
     *     subject: string,
     * }
     */
    public function buildGroupSummary(
        Institution $institution,
        string $groupName,
        CarbonInterface|string $date,
        ?array $dailyData = null
    ): array {
        $dailyData ??= $this->dailyDataForInstitution($institution->id, $date);
        $day = CarbonImmutable::instance($dailyData['date'])->locale('hu');

        $groupRows = $dailyData['rows']
            ->filter(fn (array $row) => (string) $row['child']->group_name === $groupName)
            ->values();

        $eatingRows = $groupRows->where('status', DailyMealHeadcountService::STATUS_EATING);

        $eaterNames = $this->sortNamesHungarian(
            $eatingRows
                ->pluck('child')
                ->filter(fn ($child) => $child instanceof Child)
                ->map(fn (Child $child) => (string) $child->name)
        );

        return [
            'institution' => $institution,
            'group_name' => $groupName,
            'date' => $day,
            'date_label' => $day->translatedFormat('Y. F j., l'),
            'eaters' => $eaterNames,
            'eaters_count' => $eaterNames->count(),
            'is_service_day' => (bool) $dailyData['meta']['is_service_day'],
            'subject' => sprintf(
                'Digifood – Napi étkezési létszám – %s – %s',
                $groupName,
                $day->format('Y.m.d.')
            ),
        ];
    }

    /**
     * ABC sorrendbe rendezi a neveket, magyar ábécé szerint (á, é, í, ó,
     * ö, ő, ú, ü, ű a megfelelő alapbetű mellé kerül, nem a végére) - az
     * intl kiterjesztés Collator osztályával, ha elérhető a szerveren;
     * ha nem, egy ékezet-normalizáló tartalék rendezéssel, ami legalább
     * megközelítőleg helyes sorrendet ad.
     *
     * @param  Collection<int, string>  $names
     * @return Collection<int, string>
     */
    private function sortNamesHungarian(Collection $names): Collection
    {
        $values = $names->values()->all();

        if (class_exists(\Collator::class)) {
            $collator = new \Collator('hu_HU');
            usort($values, fn (string $a, string $b) => $collator->compare($a, $b));

            return collect($values);
        }

        $normalize = fn (string $value) => str_replace(
            ['á', 'é', 'í', 'ó', 'ö', 'ő', 'ú', 'ü', 'ű'],
            ['a', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'u'],
            mb_strtolower($value)
        );

        usort($values, fn (string $a, string $b) => $normalize($a) <=> $normalize($b));

        return collect($values);
    }
}
