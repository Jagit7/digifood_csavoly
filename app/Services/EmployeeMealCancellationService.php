<?php

namespace App\Services;

use App\Models\EmployeeMealCancellation;
use App\Models\EmployeeRecurringCancellationRule;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeMealCancellationService
{
    public function __construct(private readonly InstitutionCalendarService $calendar)
    {
    }

    public function recordSingle(
        Institution $institution,
        InstitutionEmployee $employee,
        string $date,
        User $creator,
        ?string $reason
    ): EmployeeMealCancellation {
        $serviceDate = CarbonImmutable::parse($date, $this->calendar->timezone())->startOfDay();
        $submittedAt = $this->calendar->now();

        $this->ensureEmployeeCanCancel($institution, $employee, $serviceDate, $submittedAt);

        return EmployeeMealCancellation::query()->updateOrCreate(
            [
                'institution_employee_id' => $employee->id,
                'service_date' => $serviceDate->toDateString(),
            ],
            [
                'institution_id' => $institution->id,
                'source' => EmployeeMealCancellation::SOURCE_ADMIN,
                'status' => EmployeeMealCancellation::STATUS_ACTIVE,
                'reason' => $this->reason($reason),
                'created_by' => $creator->id,
                'revoked_by' => null,
                'revoked_at' => null,
            ]
        );
    }

    public function recordRange(
        Institution $institution,
        InstitutionEmployee $employee,
        string $from,
        string $to,
        User $creator,
        ?string $reason
    ): int {
        $this->ensureInstitutionMatch($institution, $employee);

        $start = CarbonImmutable::parse($from, $this->calendar->timezone())->startOfDay();
        $end = CarbonImmutable::parse($to, $this->calendar->timezone())->startOfDay();
        $maximum = (int) config('digifood.maximum_bulk_cancellation_days', 366);

        if ($start->diffInDays($end) + 1 > $maximum) {
            throw ValidationException::withMessages([
                'date_to' => "Egyszerre legfeljebb {$maximum} napos időszak rögzíthető.",
            ]);
        }

        $dates = $this->calendar
            ->serviceDaysBetween($institution->id, $start, $end)
            ->map->toDateString()
            ->all();

        if (! $dates) {
            throw ValidationException::withMessages([
                'date_from' => 'A megadott időszakban nincs lemondható étkezési nap.',
            ]);
        }

        return $this->recordDates($institution, $employee, $dates, $creator, $reason);
    }

    public function recordRecurring(
        Institution $institution,
        InstitutionEmployee $employee,
        int $weekday,
        string $startsOn,
        ?string $endsOn,
        User $creator,
        ?string $reason
    ): EmployeeRecurringCancellationRule {
        $this->ensureInstitutionMatch($institution, $employee);

        $window = $this->configuredWindow($institution->id);
        $start = CarbonImmutable::parse($startsOn, $this->calendar->timezone())->startOfDay();
        $end = $endsOn
            ? CarbonImmutable::parse($endsOn, $this->calendar->timezone())->startOfDay()
            : null;
        $firstOccurrence = $this->firstServiceOccurrence(
            $institution->id,
            $weekday,
            $start,
            $end
        );

        if (! $firstOccurrence) {
            throw ValidationException::withMessages([
                'starts_on' => 'A megadott időszakban nincs a kiválasztott napra eső étkezési nap.',
            ]);
        }

        if ($firstOccurrence->lt($window['earliest_cancellable_day'])) {
            throw ValidationException::withMessages([
                'starts_on' => 'A rendszeres lemondás első alkalma már a lemondási határidőn kívül esik.',
            ]);
        }

        $overlapExists = EmployeeRecurringCancellationRule::query()
            ->where('institution_id', $institution->id)
            ->where('institution_employee_id', $employee->id)
            ->where('weekday', $weekday)
            ->where('status', EmployeeRecurringCancellationRule::STATUS_ACTIVE)
            ->whereDate('starts_on', '<=', $end?->toDateString() ?? '9999-12-31')
            ->where(function ($query) use ($start) {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $start->toDateString());
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'weekday' => 'Erre a dolgozóra és hétköznapra már van átfedő rendszeres lemondás.',
            ]);
        }

        return EmployeeRecurringCancellationRule::create([
            'institution_id' => $institution->id,
            'institution_employee_id' => $employee->id,
            'weekday' => $weekday,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end?->toDateString(),
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'status' => EmployeeRecurringCancellationRule::STATUS_ACTIVE,
            'reason' => $this->reason($reason),
            'created_by' => $creator->id,
        ]);
    }

    /**
     * A dolgozói portál önkiszolgáló lemondása - a recordSingle() párja, de
     * SOURCE_EMPLOYEE forrással és a bejelentkezett dolgozó felhasználójával
     * mint létrehozóval jelöli a rekordot. Ugyanazokat a jogosultsági/
     * naptár-ellenőrzéseket futtatja (ensureEmployeeCanCancel()), mint az
     * admin oldali rögzítés.
     */
    public function recordSingleAsEmployee(
        Institution $institution,
        InstitutionEmployee $employee,
        string $date,
        User $employeeUser,
        ?string $reason = null
    ): EmployeeMealCancellation {
        $serviceDate = CarbonImmutable::parse($date, $this->calendar->timezone())->startOfDay();
        $submittedAt = $this->calendar->now();

        $this->ensureEmployeeCanCancel($institution, $employee, $serviceDate, $submittedAt);

        return EmployeeMealCancellation::query()->updateOrCreate(
            [
                'institution_employee_id' => $employee->id,
                'service_date' => $serviceDate->toDateString(),
            ],
            [
                'institution_id' => $institution->id,
                'source' => EmployeeMealCancellation::SOURCE_EMPLOYEE,
                'status' => EmployeeMealCancellation::STATUS_ACTIVE,
                'reason' => $this->reason($reason),
                'created_by' => $employeeUser->id,
                'revoked_by' => null,
                'revoked_at' => null,
            ]
        );
    }

    /**
     * A dolgozói portál önkiszolgáló visszaállítása - a revoke() párja, de
     * kizárólag a saját maga (SOURCE_EMPLOYEE, created_by = a bejelentkezett
     * felhasználó) által korábban rögzített lemondást engedi visszavonni -
     * ld. App\Http\Controllers\ParentPortal\ParentMealCancellationController::
     * ensureChildCanRestore() "source !== SOURCE_PARENT" ellenőrzésének
     * dolgozói megfelelője.
     */
    public function revokeAsEmployee(EmployeeMealCancellation $cancellation, User $employeeUser): void
    {
        $serviceDate = CarbonImmutable::parse($cancellation->service_date, $this->calendar->timezone())->startOfDay();
        $submittedAt = $this->calendar->now();
        $employee = $cancellation->employee;
        $institution = $cancellation->institution;

        if (! $employee || ! $institution) {
            throw ValidationException::withMessages([
                'service_date' => 'A lemondás nem állítható vissza, mert a kapcsolódó dolgozó vagy intézmény nem található.',
            ]);
        }

        if ($cancellation->source !== EmployeeMealCancellation::SOURCE_EMPLOYEE
            || (int) $cancellation->created_by !== (int) $employeeUser->id) {
            throw ValidationException::withMessages([
                'service_date' => 'Csak a dolgozó saját maga által rögzített lemondás vonható vissza.',
            ]);
        }

        $this->ensureEmployeeCanRestore($institution, $employee, $cancellation, $serviceDate, $submittedAt);

        $cancellation->update([
            'status' => EmployeeMealCancellation::STATUS_REVOKED,
            'revoked_by' => $employeeUser->id,
            'revoked_at' => now(),
        ]);
    }

    public function revoke(EmployeeMealCancellation $cancellation, User $user): void
    {
        $serviceDate = CarbonImmutable::parse($cancellation->service_date, $this->calendar->timezone())->startOfDay();
        $submittedAt = $this->calendar->now();
        $employee = $cancellation->employee;
        $institution = $cancellation->institution;

        if (! $employee || ! $institution) {
            throw ValidationException::withMessages([
                'service_date' => 'A lemondás nem állítható vissza, mert a kapcsolódó dolgozó vagy intézmény nem található.',
            ]);
        }

        $this->ensureEmployeeCanRestore($institution, $employee, $cancellation, $serviceDate, $submittedAt);

        $cancellation->update([
            'status' => EmployeeMealCancellation::STATUS_REVOKED,
            'revoked_by' => $user->id,
            'revoked_at' => now(),
        ]);
    }

    public function revokeRecurringRule(EmployeeRecurringCancellationRule $rule, User $user): void
    {
        $window = $this->configuredWindow($rule->institution_id);
        $effectiveEnd = $window['earliest_cancellable_day']->subDay();

        if ($effectiveEnd->lt($rule->starts_on)) {
            $status = EmployeeRecurringCancellationRule::STATUS_REVOKED;
            $endsOn = $rule->ends_on;
        } else {
            $status = EmployeeRecurringCancellationRule::STATUS_ENDED;
            $endsOn = $rule->ends_on && $rule->ends_on->lt($effectiveEnd)
                ? $rule->ends_on
                : $effectiveEnd;
        }

        $rule->update([
            'status' => $status,
            'ends_on' => $endsOn?->toDateString(),
            'revoked_by' => $user->id,
            'revoked_at' => now(),
        ]);
    }

    public function activeForEmployeeOnDate(int $institutionId, int $employeeId, CarbonImmutable|string $date): ?EmployeeMealCancellation
    {
        $serviceDate = $date instanceof CarbonImmutable
            ? $date->toDateString()
            : CarbonImmutable::parse($date, $this->calendar->timezone())->toDateString();

        return EmployeeMealCancellation::query()
            ->where('institution_id', $institutionId)
            ->where('institution_employee_id', $employeeId)
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereDate('service_date', $serviceDate)
            ->first();
    }

    /**
     * Egy adott intézményi étkezési napra érvényes aktív dolgozói lemondások száma.
     * A gyermekeknél használt "effectiveCancellationCount"-tal analóg: az adott napra
     * szóló egyszeri lemondásokat és az adott napra eső rendszeres szabályokat egyaránt
     * figyelembe veszi, dolgozónkénti duplikáció nélkül.
     */
    public function effectiveCancellationCount(int $institutionId, string $serviceDate): int
    {
        if (! $this->calendar->isServiceDay($institutionId, $serviceDate)) {
            return 0;
        }

        $date = CarbonImmutable::parse($serviceDate)->toDateString();
        $weekday = CarbonImmutable::parse($date)->dayOfWeekIso;

        $individual = DB::table('employee_meal_cancellations')
            ->select('institution_employee_id')
            ->where('institution_id', $institutionId)
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereDate('service_date', $date);

        $recurring = DB::table('employee_recurring_cancellation_rules')
            ->select('institution_employee_id')
            ->where('institution_id', $institutionId)
            ->whereIn('status', [
                EmployeeRecurringCancellationRule::STATUS_ACTIVE,
                EmployeeRecurringCancellationRule::STATUS_ENDED,
            ])
            ->where('weekday', $weekday)
            ->whereDate('starts_on', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date);
            });

        return DB::query()
            ->fromSub($individual->union($recurring), 'effective_employee_cancellations')
            ->distinct()
            ->count('institution_employee_id');
    }

    private function ensureEmployeeCanCancel(
        Institution $institution,
        InstitutionEmployee $employee,
        CarbonImmutable $serviceDate,
        CarbonImmutable $submittedAt
    ): void {
        $this->ensureInstitutionMatch($institution, $employee);
        $this->ensureOpenFinancialPeriod($institution, $employee, $serviceDate);
        $this->ensureServiceDayAvailability($institution, $serviceDate, $submittedAt);

        if (! $this->hasActiveMealSetting($institution, $employee, $serviceDate)) {
            throw ValidationException::withMessages([
                'service_date' => 'A kiválasztott napon nincs aktív dolgozói étkezési beállítás.',
            ]);
        }

        $duplicate = $this->activeForEmployeeOnDate($institution->id, $employee->id, $serviceDate);

        if ($duplicate !== null) {
            throw ValidationException::withMessages([
                'service_date' => 'Erre a napra már létezik aktív dolgozói lemondás.',
            ]);
        }
    }

    private function ensureEmployeeCanRestore(
        Institution $institution,
        InstitutionEmployee $employee,
        EmployeeMealCancellation $cancellation,
        CarbonImmutable $serviceDate,
        CarbonImmutable $submittedAt
    ): void {
        $this->ensureInstitutionMatch($institution, $employee);
        $this->ensureOpenFinancialPeriod($institution, $employee, $serviceDate);
        $this->ensureServiceDayAvailability($institution, $serviceDate, $submittedAt);

        if (
            $cancellation->institution_id !== $institution->id
            || $cancellation->institution_employee_id !== $employee->id
            || $cancellation->status !== EmployeeMealCancellation::STATUS_ACTIVE
        ) {
            throw ValidationException::withMessages([
                'service_date' => 'Érvénytelen dolgozói lemondás-visszaállítási kérés.',
            ]);
        }
    }

    private function ensureInstitutionMatch(Institution $institution, InstitutionEmployee $employee): void
    {
        if ($employee->institution_id !== $institution->id) {
            throw ValidationException::withMessages([
                'institution_employee_id' => 'A kiválasztott dolgozó nem az aktuális intézményhez tartozik.',
            ]);
        }
    }

    private function ensureServiceDayAvailability(
        Institution $institution,
        CarbonImmutable $serviceDate,
        CarbonImmutable $submittedAt
    ): void {
        $availability = $this->calendar->cancellationAvailability($institution->id, $serviceDate, $submittedAt);

        if (! ($availability['service_day'] ?? false)) {
            throw ValidationException::withMessages([
                'service_date' => 'A kiválasztott nap nem étkezési nap.',
            ]);
        }

        if (! ($availability['cancellable'] ?? false)) {
            throw ValidationException::withMessages([
                'service_date' => $this->deadlineMessage($availability),
            ]);
        }
    }

    private function ensureOpenFinancialPeriod(
        Institution $institution,
        InstitutionEmployee $employee,
        CarbonImmutable $serviceDate
    ): void {
        if ($this->hasClosedFinancialPeriod($institution, $employee, $serviceDate)) {
            throw ValidationException::withMessages([
                'service_date' => 'Ehhez a naphoz tartozó dolgozói pénzügyi időszak már le van zárva, ezért a lemondás nem módosítható.',
            ]);
        }
    }

    private function hasClosedFinancialPeriod(
        Institution $institution,
        InstitutionEmployee $employee,
        CarbonImmutable $serviceDate
    ): bool {
        $paymentPeriod = $serviceDate->copy()->subMonthNoOverflow()->startOfMonth();

        return EmployeeMonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('institution_employee_id', $employee->id)
            ->where('year', $paymentPeriod->year)
            ->where('month', $paymentPeriod->month)
            ->where('status', EmployeeMonthlyPaymentStatement::STATUS_CLOSED)
            ->exists();
    }

    private function recordDates(
        Institution $institution,
        InstitutionEmployee $employee,
        array $dates,
        User $creator,
        ?string $reason
    ): int {
        $now = $this->calendar->now();
        $dates = collect($dates)
            ->map(fn ($date) => CarbonImmutable::parse($date, $this->calendar->timezone())->toDateString())
            ->unique()
            ->sort()
            ->values();

        $closedDates = $dates->filter(
            fn ($date) => $this->hasClosedFinancialPeriod($institution, $employee, CarbonImmutable::parse($date))
        );

        if ($closedDates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'service_date' => 'A következő napokhoz tartozó dolgozói pénzügyi időszak már le van zárva: '
                    .$closedDates->map(fn ($date) => CarbonImmutable::parse($date)->format('Y.m.d.'))->implode(', '),
            ]);
        }

        $notCancellableDates = $dates->filter(function ($date) use ($institution, $now) {
            $availability = $this->calendar->cancellationAvailability($institution->id, $date, $now);

            return ! ($availability['cancellable'] ?? false);
        });

        if ($notCancellableDates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'service_date' => 'A következő napok már nem mondhatók le (nem étkezési nap vagy lejárt a határidő): '
                    .$notCancellableDates->map(fn ($date) => CarbonImmutable::parse($date)->format('Y.m.d.'))->implode(', '),
            ]);
        }

        $noSettingDates = $dates->filter(
            fn ($date) => ! $this->hasActiveMealSetting($institution, $employee, CarbonImmutable::parse($date))
        );

        if ($noSettingDates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'service_date' => 'A következő napokon nincs aktív dolgozói étkezési beállítás: '
                    .$noSettingDates->map(fn ($date) => CarbonImmutable::parse($date)->format('Y.m.d.'))->implode(', '),
            ]);
        }

        $duplicates = EmployeeMealCancellation::query()
            ->where('institution_employee_id', $employee->id)
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereIn('service_date', $dates)
            ->pluck('service_date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->format('Y.m.d.'))
            ->all();

        if ($duplicates) {
            throw ValidationException::withMessages([
                'service_date' => 'A következő napokra már van dolgozói lemondás: '.implode(', ', $duplicates),
            ]);
        }

        $timestamp = now();
        $rows = $dates->map(fn ($date) => [
            'institution_id' => $institution->id,
            'institution_employee_id' => $employee->id,
            'service_date' => $date,
            'source' => EmployeeMealCancellation::SOURCE_ADMIN,
            'status' => EmployeeMealCancellation::STATUS_ACTIVE,
            'reason' => $this->reason($reason),
            'created_by' => $creator->id,
            'revoked_by' => null,
            'revoked_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();

        DB::table('employee_meal_cancellations')->upsert(
            $rows,
            ['institution_employee_id', 'service_date'],
            ['institution_id', 'source', 'status', 'reason', 'created_by', 'revoked_by', 'revoked_at', 'updated_at']
        );

        return count($rows);
    }

    private function configuredWindow(int $institutionId): array
    {
        $window = $this->calendar->cancellationWindow($institutionId);

        if (! $window['configured']) {
            throw ValidationException::withMessages([
                'deadline' => 'Előbb állítsd be a következő napi lemondási határidőt.',
            ]);
        }

        if (! $window['earliest_cancellable_day']) {
            throw ValidationException::withMessages([
                'deadline' => 'A következő időszakban nem található lemondható étkezési nap.',
            ]);
        }

        return $window;
    }

    private function firstServiceOccurrence(
        int $institutionId,
        int $weekday,
        CarbonImmutable $start,
        ?CarbonImmutable $end
    ): ?CarbonImmutable {
        $delta = ($weekday - $start->dayOfWeekIso + 7) % 7;
        $candidate = $start->addDays($delta);
        $limit = $end ?? $start->addYears(2);

        return $this->calendar
            ->serviceDaysBetween($institutionId, $candidate, $limit)
            ->first(fn (CarbonImmutable $date) => $date->dayOfWeekIso === $weekday);
    }

    /**
     * Publikus: az EmployeePortal\EmployeeMealCancellationController (saját
     * étkezés-lemondás önkiszolgáló nézete) is felhasználja ugyanezt az
     * ellenőrzést a napi állapot megjelenítéséhez - korábban ez csak belső
     * (private) segédfüggvény volt az admin oldali rögzítéshez.
     */
    public function hasActiveMealSetting(
        Institution $institution,
        InstitutionEmployee $employee,
        CarbonImmutable $serviceDate
    ): bool {
        $setting = StudentMealSetting::query()
            ->with(['mealPackage.items', 'items'])
            ->forEater($employee)
            ->where('institution_id', $institution->id)
            ->whereDate('valid_from', '<=', $serviceDate->toDateString())
            ->where(function ($query) use ($serviceDate) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $serviceDate->toDateString());
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();

        if ($setting === null) {
            return false;
        }

        if ($setting->mode === StudentMealSetting::MODE_INSTITUTION_DEFAULT) {
            return $institution->mealPackages()
                ->where('is_active', true)
                ->where('is_default', true)
                ->whereHas('items')
                ->exists();
        }

        if ($setting->mode === StudentMealSetting::MODE_PACKAGE) {
            return $setting->mealPackage !== null && $setting->mealPackage->items->isNotEmpty();
        }

        return $setting->items->isNotEmpty();
    }

    private function deadlineMessage(array $availability): string
    {
        $deadline = $availability['deadline'] ?? null;

        if (! $deadline instanceof CarbonImmutable) {
            return 'Ehhez a naphoz nincs érvényes lemondási határidő.';
        }

        $label = $deadline->locale('hu')->translatedFormat('Y. m. d. H:i');

        return 'A lemondási határidő lejárt. Határidő: '.$label;
    }

    private function reason(?string $reason): ?string
    {
        return filled($reason) ? trim((string) $reason) : null;
    }
}
