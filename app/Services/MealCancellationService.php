<?php

namespace App\Services;

use App\Models\Child;
use App\Models\Institution;
use App\Models\MealCancellation;
use App\Models\RecurringCancellationRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MealCancellationService
{
    public function __construct(private readonly InstitutionCalendarService $calendar) {}

    public function recordSingle(
        Institution $institution,
        Child $child,
        string $date,
        User $creator,
        ?string $reason
    ): int {
        if (! $this->calendar->isServiceDay($institution->id, $date)) {
            throw ValidationException::withMessages([
                'service_date' => 'A kiválasztott nap nem étkezési nap vagy intézményi szünetre esik.',
            ]);
        }

        return $this->recordDates($institution, $child, [$date], $creator, $reason);
    }

    public function recordRange(
        Institution $institution,
        Child $child,
        string $from,
        string $to,
        User $creator,
        ?string $reason
    ): int {
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

        return $this->recordDates($institution, $child, $dates, $creator, $reason);
    }

    public function recordRecurring(
        Institution $institution,
        Child $child,
        int $weekday,
        string $startsOn,
        ?string $endsOn,
        User $creator,
        ?string $reason
    ): RecurringCancellationRule {
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

        $overlapExists = RecurringCancellationRule::query()
            ->where('institution_id', $institution->id)
            ->where('child_id', $child->id)
            ->where('weekday', $weekday)
            ->where('status', RecurringCancellationRule::STATUS_ACTIVE)
            ->whereDate('starts_on', '<=', $end?->toDateString() ?? '9999-12-31')
            ->where(function ($query) use ($start) {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $start->toDateString());
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'weekday' => 'Erre a gyermekre és hétköznapra már van átfedő rendszeres lemondás.',
            ]);
        }

        return RecurringCancellationRule::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'weekday' => $weekday,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end?->toDateString(),
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => RecurringCancellationRule::STATUS_ACTIVE,
            'reason' => $this->reason($reason),
            'created_by' => $creator->id,
        ]);
    }

    public function revokeCancellation(MealCancellation $cancellation, User $user): void
    {
        $window = $this->configuredWindow($cancellation->institution_id);

        if ($cancellation->service_date->lt($window['earliest_cancellable_day'])) {
            throw ValidationException::withMessages([
                'service_date' => 'A határidő után a lemondás már nem vonható vissza.',
            ]);
        }

        $cancellation->update([
            'status' => MealCancellation::STATUS_REVOKED,
            'revoked_by' => $user->id,
            'revoked_at' => now(),
        ]);
    }

    public function revokeRecurringRule(RecurringCancellationRule $rule, User $user): void
    {
        $window = $this->configuredWindow($rule->institution_id);
        $effectiveEnd = $window['earliest_cancellable_day']->subDay();

        if ($effectiveEnd->lt($rule->starts_on)) {
            $status = RecurringCancellationRule::STATUS_REVOKED;
            $endsOn = $rule->ends_on;
        } else {
            $status = RecurringCancellationRule::STATUS_ENDED;
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

    public function effectiveCancellationCount(int $institutionId, string $serviceDate): int
    {
        if (! $this->calendar->isServiceDay($institutionId, $serviceDate)) {
            return 0;
        }

        $date = CarbonImmutable::parse($serviceDate)->toDateString();
        $weekday = CarbonImmutable::parse($date)->dayOfWeekIso;
        $union = $this->effectiveCancellationUnion($institutionId, $date, $weekday);

        return DB::query()
            ->fromSub($union, 'effective_cancellations')
            ->distinct()
            ->count('child_id');
    }

    public function cancellationWindowOrFail(int $institutionId): array
    {
        return $this->configuredWindow($institutionId);
    }

    public function classCancelledDatesForChild(int $institutionId, int $childId, array $dates): array
    {
        return $this->classCancelledDates($institutionId, $childId, $dates);
    }

    public function activeCancellationDatesForChild(int $childId, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        return MealCancellation::query()
            ->where('child_id', $childId)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereIn('service_date', $dates)
            ->pluck('service_date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->all();
    }

    public function createActiveCancellations(
        Institution $institution,
        Child $child,
        array $dates,
        User $creator,
        ?string $reason
    ) {
        $dates = collect($dates)
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return collect();
        }

        $timestamp = now();
        DB::table('meal_cancellations')->insert($dates->map(fn ($date) => [
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'service_date' => $date,
            'source' => MealCancellation::SOURCE_ADMIN,
            'status' => MealCancellation::STATUS_ACTIVE,
            'reason' => $this->reason($reason),
            'created_by' => $creator->id,
            'revoked_by' => null,
            'revoked_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all());

        return MealCancellation::query()
            ->where('institution_id', $institution->id)
            ->where('child_id', $child->id)
            ->whereIn('service_date', $dates->all())
            ->get()
            ->keyBy(fn (MealCancellation $cancellation) => $cancellation->service_date->toDateString());
    }

    private function recordDates(
        Institution $institution,
        Child $child,
        array $dates,
        User $creator,
        ?string $reason
    ): int {
        $window = $this->configuredWindow($institution->id);
        $dates = collect($dates)
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values();
        $tooEarly = $dates->filter(fn ($date) => $date < $window['earliest_cancellable_day']->toDateString()
        );

        if ($tooEarly->isNotEmpty()) {
            throw ValidationException::withMessages([
                'service_date' => 'A következő lemondható étkezési nap: '
                    .$window['earliest_cancellable_day']->format('Y.m.d.'),
            ]);
        }

        $blockedDates = $this->classCancelledDates($institution->id, $child->id, $dates->all());

        if ($blockedDates) {
            throw ValidationException::withMessages([
                'service_date' => 'A következő napokon már osztály- vagy csoportszintű lemondás van: '
                    .implode(', ', $blockedDates),
            ]);
        }

        $duplicates = collect($this->activeCancellationDatesForChild($child->id, $dates->all()))
            ->map(fn ($date) => CarbonImmutable::parse($date)->format('Y.m.d.'))
            ->all();

        if ($duplicates) {
            throw ValidationException::withMessages([
                'service_date' => 'A következő napokra már van egyéni lemondás: '.implode(', ', $duplicates),
            ]);
        }

        $this->createActiveCancellations($institution, $child, $dates->all(), $creator, $reason);

        return $dates->count();
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

    private function classCancelledDates(int $institutionId, int $childId, array $dates): array
    {
        if (! $dates) {
            return [];
        }

        $groupIds = DB::table('class_group_memberships')
            ->join('class_groups', 'class_groups.id', '=', 'class_group_memberships.class_group_id')
            ->where('class_group_memberships.child_id', $childId)
            ->where('class_group_memberships.status', 'active')
            ->where('class_groups.institution_id', $institutionId)
            ->pluck('class_group_memberships.class_group_id');

        if ($groupIds->isEmpty()) {
            return [];
        }

        $first = min($dates);
        $last = max($dates);
        $periods = DB::table('class_cancellations')
            ->where('institution_id', $institutionId)
            ->whereIn('class_group_id', $groupIds)
            ->whereDate('date_from', '<=', $last)
            ->whereDate('date_to', '>=', $first)
            ->get(['date_from', 'date_to']);

        return collect($dates)
            ->filter(fn ($date) => $periods->contains(fn ($period) => $date >= $period->date_from && $date <= $period->date_to
            ))
            ->map(fn ($date) => CarbonImmutable::parse($date)->format('Y.m.d.'))
            ->values()
            ->all();
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

    private function effectiveCancellationUnion(int $institutionId, string $date, int $weekday): Builder
    {
        $individual = DB::table('meal_cancellations')
            ->select('child_id')
            ->where('institution_id', $institutionId)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereDate('service_date', $date);

        $recurring = DB::table('recurring_cancellation_rules')
            ->select('child_id')
            ->where('institution_id', $institutionId)
            ->whereIn('status', [
                RecurringCancellationRule::STATUS_ACTIVE,
                RecurringCancellationRule::STATUS_ENDED,
            ])
            ->where('weekday', $weekday)
            ->whereDate('starts_on', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date);
            });

        $classLevel = DB::table('class_cancellations')
            ->join('class_group_memberships', 'class_group_memberships.class_group_id', '=', 'class_cancellations.class_group_id')
            ->join('class_groups', 'class_groups.id', '=', 'class_cancellations.class_group_id')
            ->select('class_group_memberships.child_id')
            ->where('class_cancellations.institution_id', $institutionId)
            ->where('class_groups.institution_id', $institutionId)
            ->where('class_group_memberships.status', 'active')
            ->whereDate('class_cancellations.date_from', '<=', $date)
            ->whereDate('class_cancellations.date_to', '>=', $date);

        return $individual->union($recurring)->union($classLevel);
    }

    private function reason(?string $reason): ?string
    {
        return filled($reason) ? trim((string) $reason) : null;
    }
}
