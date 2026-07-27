<?php

namespace Tests\Unit;

use App\Support\Scheduling\ApplicationScheduleBootstrap;
use App\Support\Scheduling\ScheduledJobsCatalog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApplicationScheduleBootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationScheduleBootstrap::resetForTests();
        ScheduledJobsCatalog::forgetCache();

        parent::tearDown();
    }

    #[Test]
    public function ensure_registered_populates_schedule_after_http_bootstrap(): void
    {
        $this->app->make(HttpKernel::class)->bootstrap();

        $schedule = $this->app->make(Schedule::class);
        $this->assertSame([], $schedule->events());

        ApplicationScheduleBootstrap::resetForTests();
        $schedule = ApplicationScheduleBootstrap::ensureRegistered($schedule);

        $this->assertNotEmpty($schedule->events());
    }

    #[Test]
    public function build_fresh_lists_jobs_after_http_bootstrap(): void
    {
        $this->app->make(HttpKernel::class)->bootstrap();
        ApplicationScheduleBootstrap::resetForTests();
        ScheduledJobsCatalog::forgetCache();

        $catalog = ScheduledJobsCatalog::buildFresh();

        $this->assertGreaterThan(0, $catalog['registered']);
        $this->assertNotEmpty($catalog['groups']);

        $first = collect($catalog['jobs'])->first();
        $this->assertIsArray($first);
        $this->assertTrue($first['registered']);
        $this->assertNotNull($first['next_run_human'] ?? null);
    }
}
