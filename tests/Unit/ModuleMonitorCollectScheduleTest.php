<?php

namespace Tests\Unit;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

final class ModuleMonitorCollectScheduleTest extends TestCase
{
    public function test_schedule_includes_module_monitor_collect(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        $events = app(Schedule::class)->events();
        $found = collect($events)->contains(
            static fn (Event $e) => str_contains((string) ($e->command ?? ''), 'module-monitor:collect'),
        );

        $this->assertTrue($found, 'Expected module-monitor:collect in the schedule.');
    }
}
