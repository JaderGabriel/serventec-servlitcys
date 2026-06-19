<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteFortnightlyFeedScheduleCadence;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteFortnightlyFeedScheduleTest extends TestCase
{
    #[Test]
    public function schedule_includes_horizonte_staged_feed_commands(): void
    {
        config([
            'horizonte.fortnightly_feed.enabled' => true,
            'horizonte.fortnightly_feed.schedule.enabled' => true,
            'horizonte.fortnightly_feed.staged' => true,
        ]);

        $this->artisan('schedule:list')->assertSuccessful();

        $events = app(Schedule::class)->events();
        $commands = collect($events)
            ->map(static fn ($e) => (string) ($e->command ?? ''))
            ->filter(static fn (string $c): bool => str_contains($c, 'horizonte:fortnightly-feed'))
            ->all();

        $this->assertTrue(
            collect($commands)->contains(static fn (string $c): bool => str_contains($c, '--staged --reset')),
            'Expected horizonte staged reset in schedule.',
        );
        $this->assertTrue(
            collect($commands)->contains(static fn (string $c): bool => str_contains($c, '--staged --continue')),
            'Expected horizonte staged continue in schedule.',
        );
    }

    #[Test]
    public function bimonthly_start_uses_cron_on_odd_months(): void
    {
        config([
            'horizonte.fortnightly_feed.schedule.day' => 1,
            'horizonte.fortnightly_feed.schedule.months' => [1, 3, 5, 7, 9, 11],
            'horizonte.fortnightly_feed.schedule.time' => '03:00',
        ]);

        $this->assertSame('0 3 1 1,3,5,7,9,11 *', HorizonteFortnightlyFeedScheduleCadence::cronExpression());
    }
}
