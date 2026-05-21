<?php

namespace Tests\Unit;

use App\Support\Scheduling\ScheduleIntervals;
use PHPUnit\Framework\TestCase;

class ScheduleIntervalsTest extends TestCase
{
    public function test_normalize_daily_times_parses_two_slots(): void
    {
        $times = ScheduleIntervals::normalizeDailyTimes(['06:00', '18:00', '', '6']);

        $this->assertSame(['06:00', '18:00'], $times);
    }
}
