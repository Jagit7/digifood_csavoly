<?php

namespace App\Services\DailyAttendance;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionDailyAttendanceRecipient;
use App\Models\InstitutionSetting;
use App\Services\DailyMealHeadcountService;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Az óvodai "napi jelenléti ív e-mailben" funkció üzleti logikája.
 *
 * Szándékosan NEM ír új jelenlét-/lemondás-számítási logikát: a jelenlévők
 * és hiányzók meghatározásához ugyanazt a DailyMealHeadcountService::forDate()
 * hívást használja, amit a Napi működés → Mai létszám oldal is (ld.
 * DailyOperationController::todayCounts() / printTodayCounts()). Ez a
 * service kizárólag a csoportonkénti bontást és az e-mailhez szükséges
 * extra (diéta megnevezés, kedvezmény) mezőket állítja elő.
 *
 * A scheduler parancs, az előnézet és a tesztküldés is ugyanezt a service-t
 * használja, hogy mindenhol pontosan ugyanaz az adat jelenjen meg.
 */
class DailyAttendanceEmailService
{
    public const DEFAULT_SEND_TIME = '07:30:00';

    public function __construct(
        private readonly DailyMealHeadcountService $headcount,
        private readonly InstitutionCalendarService $calendar,
    ) {}

    public function isAvailableFor(Institution $institution): bool
    {
        return $institution->isKindergarten();
    }

    /**
     * Az intézményben ténylegesen létező (aktív gyermekhez rendelt)
     * csoportnevek, a gyermekek group_name mezője alapján - ugyanaz a
     * forrás, amit a ChildPrintListController is használ a csoportszűrő
     * feltöltéséhez. Új csoport automatikusan megjelenik itt, mihelyt egy
     * aktív gyermeknek be van állítva - nincs szükség külön adatbázis-
     * migrálásra vagy manuális csoportfelvitelre.
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
     * Csoportnév => e-mail címek (rendezett) - az intézmény összes mentett
     * napi jelenléti ív címzettje, csoportonként csoportosítva.
     *
     * @return Collection<string, Collection<int, string>>
     */
    public function recipientsByGroup(int $institutionId): Collection
    {
        return InstitutionDailyAttendanceRecipient::query()
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
        return InstitutionDailyAttendanceRecipient::query()
            ->where('institution_id', $institutionId)
            ->where('group_name', $groupName)
            ->orderBy('email')
            ->pluck('email')
            ->all();
    }

    /**
     * Elmenti egy csoport címzettjeit. A megadott lista a csoport TELJES új
     * állapota - a korábbi, itt nem szereplő címzettek törlődnek, az újak
     * létrejönnek (ez adja az "új e-mail cím hozzáadása" / "meglévő
     * törlése" funkciót egyetlen mentési művelettel).
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

        InstitutionDailyAttendanceRecipient::query()
            ->where('institution_id', $institutionId)
            ->where('group_name', $groupName)
            ->delete();

        if ($normalized->isEmpty()) {
            return;
        }

        $now = now();

        InstitutionDailyAttendanceRecipient::query()->insert(
            $normalized->map(fn (string $email) => [
                'institution_id' => $institutionId,
                'group_name' => $groupName,
                'email' => $email,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function updateSchedule(Institution $institution, bool $enabled, ?string $sendTime): void
    {
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        $normalizedSendTime = $this->normalizeSendTime($sendTime)
            ?? $setting->daily_attendance_email_send_time
            ?? self::DEFAULT_SEND_TIME;

        $setting->update([
            'daily_attendance_email_enabled' => $enabled,
            'daily_attendance_email_send_time' => $normalizedSendTime,
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
     * hogy egy intézmény több csoportjának összesítője is ugyanabból az
     * adatból épüljön fel (ne fusson le csoportonként újra a lekérdezés).
     */
    public function dailyDataForInstitution(int $institutionId, CarbonInterface|string $date): array
    {
        return $this->headcount->forDate($institutionId, $date);
    }

    /**
     * Egy adott csoport adott napi jelenléti-ív összesítője. Ugyanezt hívja
     * a scheduler parancs (éles kiküldés), az előnézet és a tesztküldés is.
     *
     * @return array{
     *     institution: Institution,
     *     group_name: string,
     *     date: CarbonImmutable,
     *     date_label: string,
     *     present_count: int,
     *     absent_count: int,
     *     present_children: Collection,
     *     absent_children: Collection,
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
        $dayString = $day->toDateString();

        $groupRows = $dailyData['rows']
            ->filter(fn (array $row) => (string) $row['child']->group_name === $groupName)
            ->values();

        $presentRows = $groupRows
            ->where('status', DailyMealHeadcountService::STATUS_EATING)
            ->values();
        $absentRows = $groupRows
            ->where('status', '!=', DailyMealHeadcountService::STATUS_EATING)
            ->values();

        $this->loadDiscountRelations($presentRows->pluck('child'));

        $presentChildren = $presentRows
            ->map(fn (array $row) => [
                'name' => $row['child']->name,
                'diet_label' => $this->dietLabel($row['child']),
                'discount_label' => $this->discountLabel($row['child']->discountTypeForDate($dayString)),
            ])
            ->values();

        $absentChildren = $absentRows
            ->map(fn (array $row) => ['name' => $row['child']->name])
            ->values();

        return [
            'institution' => $institution,
            'group_name' => $groupName,
            'date' => $day,
            'date_label' => $day->translatedFormat('Y. F j., l'),
            'present_count' => $presentChildren->count(),
            'absent_count' => $absentChildren->count(),
            'present_children' => $presentChildren,
            'absent_children' => $absentChildren,
            'subject' => sprintf(
                'Digifood – Napi jelenléti ív – %s – %s',
                $groupName,
                $day->format('Y.m.d.')
            ),
        ];
    }

    private function dietLabel(Child $child): string
    {
        $names = $child->dietaryRestrictions->pluck('name')->filter()->values();

        if ($names->isEmpty()) {
            return 'Nem';
        }

        return 'Igen – '.$names->implode(', ');
    }

    private function discountLabel(?DiscountType $discountType): string
    {
        if ($discountType === null || (int) $discountType->percentage === 0) {
            return 'Nincs';
        }

        $label = $discountType->percentage.'%';

        if (filled($discountType->name) && $discountType->name !== 'Kedvezmény nélkül') {
            $label .= ' – '.$discountType->name;
        }

        return $label;
    }

    private function loadDiscountRelations(Collection $children): void
    {
        $models = $children
            ->filter(fn ($child) => $child instanceof Child)
            ->unique('id')
            ->values();

        if ($models->isEmpty()) {
            return;
        }

        (new EloquentCollection($models->all()))->loadMissing([
            'discountPeriods.discountType',
            'discountType',
        ]);
    }
}
