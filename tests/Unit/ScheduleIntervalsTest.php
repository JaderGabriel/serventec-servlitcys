<?php

namespace Tests\Unit;

use App\Support\Scheduling\ScheduleIntervals;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleIntervalsTest extends TestCase
{
    public function test_every_minutes_applies_three_minute_cron(): void
    {
        $schedule = new Schedule;
        $event = $schedule->command('inspire');
        ScheduleIntervals::everyMinutes($event, 3);

        $this->assertStringContainsString('*/3', (string) $event->expression);
    }

    public function test_every_hours_applies_hourly_cron(): void
    {
        $schedule = new Schedule;
        $event = $schedule->command('inspire');
        ScheduleIntervals::everyHours($event, 1);

        $this->assertSame('0 * * * *', (string) $event->expression);
    }
}
