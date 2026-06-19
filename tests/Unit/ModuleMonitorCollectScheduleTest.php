<?php

namespace Tests\Unit;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

final class ModuleMonitorCollectScheduleTest extends TestCase
{
    public function test_schedule_includes_module_monitor_collect_every_ten_minutes(): void
    {
        config([
            'module_monitor.enabled' => true,
            'module_monitor.schedule.enabled' => true,
            'module_monitor.schedule.interval_minutes' => 10,
        ]);

        $this->artisan('schedule:list')->assertSuccessful();

        $events = app(Schedule::class)->events();
        $event = collect($events)->first(
            static fn (Event $e) => str_contains((string) ($e->command ?? ''), 'module-monitor:collect'),
        );

        $this->assertNotNull($event, 'Expected module-monitor:collect in the schedule.');
        $this->assertSame('*/10 * * * *', $event->expression);
        $this->assertSame('module-monitor-collect', $event->description ?? $event->mutexName());
    }
}
