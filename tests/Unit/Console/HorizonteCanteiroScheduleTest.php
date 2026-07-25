<?php

namespace Tests\Unit\Console;

use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteCanteiroScheduleTest extends TestCase
{
    #[Test]
    public function schedule_includes_canteiro_alerts_and_obras_sync(): void
    {
        config([
            'horizonte.obras.enabled' => true,
            'horizonte.obras.schedule.enabled' => true,
            'horizonte.obras.alerts.enabled' => true,
        ]);

        $this->artisan('schedule:list')->assertSuccessful();

        $events = app(Schedule::class)->events();
        $alerts = collect($events)->contains(
            static fn ($e) => str_contains((string) ($e->command ?? ''), 'horizonte:canteiro-alerts'),
        );
        $obras = collect($events)->contains(
            static fn ($e) => str_contains((string) ($e->command ?? ''), 'horizonte:sync-obras'),
        );

        $this->assertTrue($alerts, 'Expected horizonte-canteiro-alerts in the schedule.');
        $this->assertTrue($obras, 'Expected horizonte:sync-obras in the schedule.');
    }
}
