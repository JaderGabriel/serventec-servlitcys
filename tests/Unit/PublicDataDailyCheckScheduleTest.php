<?php

namespace Tests\Unit;

use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PublicDataDailyCheckScheduleTest extends TestCase
{
    #[Test]
    public function schedule_includes_public_data_daily_check(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        $events = app(Schedule::class)->events();
        $found = collect($events)->contains(
            static fn ($e) => str_contains((string) ($e->command ?? ''), 'public-data:check-official'),
        );

        $this->assertTrue($found, 'Expected public-data:check-official in the schedule.');
    }
}
