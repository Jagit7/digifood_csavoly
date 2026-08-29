<?php

namespace Tests\Unit;

use App\Services\HungarianHolidayService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class HungarianHolidayServiceTest extends TestCase
{
    public function test_it_contains_fixed_and_movable_hungarian_public_holidays(): void
    {
        $holidays = (new HungarianHolidayService())->forYear(2026);

        $this->assertSame('Nemzeti ünnep', $holidays->get('2026-03-15'));
        $this->assertSame('Nagypéntek', $holidays->get('2026-04-03'));
        $this->assertSame('Húsvéthétfő', $holidays->get('2026-04-06'));
        $this->assertSame('Pünkösdhétfő', $holidays->get('2026-05-25'));
        $this->assertSame('Karácsony másnapja', $holidays->get('2026-12-26'));
    }

    public function test_it_only_returns_holidays_inside_the_requested_period(): void
    {
        $holidays = (new HungarianHolidayService())->between(
            CarbonImmutable::parse('2026-04-01'),
            CarbonImmutable::parse('2026-04-30')
        );

        $this->assertSame([
            '2026-04-03' => 'Nagypéntek',
            '2026-04-06' => 'Húsvéthétfő',
        ], $holidays->all());
    }
}
