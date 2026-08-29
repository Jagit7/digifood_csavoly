<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class HungarianHolidayService
{
    public function between(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $holidays = collect();

        for ($year = $from->year; $year <= $to->year; $year++) {
            $holidays = $holidays->merge($this->forYear($year));
        }

        return $holidays->filter(
            fn (string $name, string $date) => $date >= $from->toDateString() && $date <= $to->toDateString()
        );
    }

    public function forYear(int $year): Collection
    {
        $easterSunday = $this->easterSunday($year);

        return collect([
            "{$year}-01-01" => 'Újév',
            "{$year}-03-15" => 'Nemzeti ünnep',
            $easterSunday->subDays(2)->toDateString() => 'Nagypéntek',
            $easterSunday->addDay()->toDateString() => 'Húsvéthétfő',
            "{$year}-05-01" => 'A munka ünnepe',
            $easterSunday->addDays(50)->toDateString() => 'Pünkösdhétfő',
            "{$year}-08-20" => 'Államalapítás ünnepe',
            "{$year}-10-23" => 'Nemzeti ünnep',
            "{$year}-11-01" => 'Mindenszentek',
            "{$year}-12-25" => 'Karácsony',
            "{$year}-12-26" => 'Karácsony másnapja',
        ]);
    }

    private function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }
}
