<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteSiconfiScheduleCadence;
use App\Support\Horizonte\HorizonteSiconfiSyncProgress;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteSiconfiSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function sync_progress_tracks_national_run_lifecycle(): void
    {
        $year = 2024;
        $period = 6;

        $this->assertFalse(HorizonteSiconfiSyncProgress::isActive($year, $period));
        $this->assertFalse(HorizonteSiconfiSyncProgress::isComplete($year, $period));

        HorizonteSiconfiSyncProgress::start($year, $period);
        $this->assertTrue(HorizonteSiconfiSyncProgress::isActive($year, $period));

        HorizonteSiconfiSyncProgress::markComplete($year, $period);
        $this->assertFalse(HorizonteSiconfiSyncProgress::isActive($year, $period));
        $this->assertTrue(HorizonteSiconfiSyncProgress::isComplete($year, $period));

        HorizonteSiconfiSyncProgress::reset($year, $period);
        $this->assertNull(HorizonteSiconfiSyncProgress::get($year, $period));
    }

    #[Test]
    public function schedule_cadence_defaults_to_twice_per_year(): void
    {
        $this->assertSame([1, 7], HorizonteSiconfiScheduleCadence::months());
        $this->assertSame(15, HorizonteSiconfiScheduleCadence::day());
        $this->assertStringContainsString('1,7', HorizonteSiconfiScheduleCadence::cronExpression());
    }

    #[Test]
    public function schedule_includes_siconfi_sync_commands(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        $events = app(Schedule::class)->events();
        $start = collect($events)->contains(
            static fn ($e) => str_contains((string) ($e->command ?? ''), 'horizonte:sync-siconfi --reset --continue'),
        );
        $step = collect($events)->contains(
            static fn ($e) => str_contains((string) ($e->command ?? ''), 'horizonte:sync-siconfi --continue'),
        );

        $this->assertTrue($start, 'Expected horizonte-siconfi-sync-start in the schedule.');
        $this->assertTrue($step, 'Expected horizonte-siconfi-sync-step in the schedule.');
    }
}
