<?php

namespace Tests\Unit;

use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteFortnightlyFeedScheduleTest extends TestCase
{
    #[Test]
    public function schedule_includes_horizonte_fortnightly_feed(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        $events = app(Schedule::class)->events();
        $found = collect($events)->contains(
            static fn ($e) => str_contains((string) ($e->command ?? ''), 'horizonte:fortnightly-feed'),
        );

        $this->assertTrue($found, 'Expected horizonte:fortnightly-feed in the schedule.');
    }
}
