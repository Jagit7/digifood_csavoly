<?php

namespace App\Services;

use App\Models\InstitutionMealSetting;
use App\Models\SchoolBreak;
use App\Models\WorkingDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class InstitutionCalendarService
{
    public function __construct(private readonly HungarianHolidayService $holidays)
    {
    }

    public function timezone(): string
    {
        return (string) config('digifood.business_timezone', 'Europe/Budapest');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    public function serviceDaysBetween(
        int $institutionId,
        CarbonInterface|string $from,
        CarbonInterface|string $to
    ): Collection {
        $start = $this->date($from);
        $end = $this->date($to);

        if ($end->lt($start)) {
            return collect();
        }

        $workingDays = WorkingDay::query()
            ->where('institution_id', $institutionId)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->pluck('date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->flip();

        $breaks = SchoolBreak::query()
            ->where('institution_id', $institutionId)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date']);

        $publicHolidays = $this->holidays->between($start, $end);

        $days = collect();

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dateString = $date->toDateString();
            $isBreak = $breaks->contains(fn (SchoolBreak $break) =>
                $dateString >= $break->start_date->toDateString()
                && $dateString <= $break->end_date->toDateString()
            );

            if ($isBreak || $publicHolidays->has($dateString)) {
                continue;
            }

            if ($date->isWeekday() || $workingDays->has($dateString)) {
                $days->push($date);
            }
        }

        return $days;
    }

    public function isServiceDay(int $institutionId, CarbonInterface|string $date): bool
    {
        $day = $this->date($date);

        return $this->serviceDaysBetween($institutionId, $day, $day)->isNotEmpty();
    }

    public function cancellationWindow(int $institutionId, ?CarbonImmutable $now = null): array
    {
        $setting = InstitutionMealSetting::query()
            ->where('institution_id', $institutionId)
            ->first();

        if ($setting?->cancellation_hour === null || $setting->cancellation_minute === null) {
            return [
                'configured' => false,
                'setting' => $setting,
                'next_service_day' => null,
                'earliest_cancellable_day' => null,
                'cutoff' => null,
            ];
        }

        $now ??= $this->now();
        $tomorrow = $now->startOfDay()->addDay();
        $horizon = $tomorrow->addDays((int) config('digifood.cancellation_horizon_days', 400));
        $serviceDays = $this->serviceDaysBetween($institutionId, $tomorrow, $horizon);
        $nextServiceDay = $serviceDays->first();
        $cutoff = $now->setTime($setting->cancellation_hour, $setting->cancellation_minute);
        $earliest = $now->lt($cutoff) ? $nextServiceDay : $serviceDays->get(1);

        return [
            'configured' => true,
            'setting' => $setting,
            'next_service_day' => $nextServiceDay,
            'earliest_cancellable_day' => $earliest,
            'cutoff' => $cutoff,
        ];
    }

    public function cancellationDeadline(int $institutionId, CarbonInterface|string $serviceDate): ?CarbonImmutable
    {
        $setting = InstitutionMealSetting::query()
            ->where('institution_id', $institutionId)
            ->first();

        if ($setting?->cancellation_hour === null || $setting->cancellation_minute === null) {
            return null;
        }

        $date = $this->date($serviceDate);

        if (! $this->isServiceDay($institutionId, $date)) {
            return null;
        }

        $searchStart = $date->subDays((int) config('digifood.cancellation_horizon_days', 400) + 31);
        $previousServiceDay = $this->serviceDaysBetween($institutionId, $searchStart, $date->subDay())->last();

        if (! $previousServiceDay) {
            return null;
        }

        return $previousServiceDay->setTime($setting->cancellation_hour, $setting->cancellation_minute);
    }

    public function cancellationAvailability(
        int $institutionId,
        CarbonInterface|string $serviceDate,
        ?CarbonImmutable $now = null
    ): array {
        $date = $this->date($serviceDate);
        $now ??= $this->now();
        $deadline = $this->cancellationDeadline($institutionId, $date);
        $isServiceDay = $this->isServiceDay($institutionId, $date);

        if (! $isServiceDay) {
            return [
                'service_day' => false,
                'deadline' => null,
                'cancellable' => false,
                'expired' => false,
                'reason' => 'non_service_day',
            ];
        }

        if (! $deadline) {
            return [
                'service_day' => true,
                'deadline' => null,
                'cancellable' => false,
                'expired' => false,
                'reason' => 'missing_deadline',
            ];
        }

        if ($date->lte($now->startOfDay())) {
            return [
                'service_day' => true,
                'deadline' => $deadline,
                'cancellable' => false,
                'expired' => true,
                'reason' => 'past_day',
            ];
        }

        $cancellable = $now->lte($deadline);

        return [
            'service_day' => true,
            'deadline' => $deadline,
            'cancellable' => $cancellable,
            'expired' => ! $cancellable,
            'reason' => $cancellable ? null : 'deadline_passed',
        ];
    }

    private function date(CarbonInterface|string $date): CarbonImmutable
    {
        if ($date instanceof CarbonInterface) {
            return CarbonImmutable::instance($date)->setTimezone($this->timezone())->startOfDay();
        }

        return CarbonImmutable::parse($date, $this->timezone())->startOfDay();
    }
}
