<?php

namespace Tests\Unit;

use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OperationalAlertsScheduleTest extends TestCase
{
    #[Test]
    public function schedule_includes_operational_alerts_check(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        $events = app(Schedule::class)->events();
        $found = collect($events)->contains(
            static fn ($e) => str_contains((string) ($e->command ?? ''), 'notifications:operational-alerts'),
        );

        $this->assertTrue($found, 'Expected notifications:operational-alerts in the schedule.');
    }
}
