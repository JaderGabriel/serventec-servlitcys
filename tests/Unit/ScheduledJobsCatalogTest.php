<?php

namespace Tests\Unit;

use App\Support\Cadunico\CadunicoBeneficiosPortalScheduleCadence;
use App\Support\Scheduling\ScheduledJobsCatalog;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ScheduledJobsCatalogTest extends TestCase
{
    #[Test]
    public function build_lists_registered_horizonte_and_cadunico_jobs(): void
    {
        config([
            'horizonte.fortnightly_feed.enabled' => true,
            'horizonte.fortnightly_feed.schedule.enabled' => true,
            'horizonte.fortnightly_feed.staged' => true,
            'ieducar.cadunico.beneficios_portal.enabled' => true,
            'ieducar.cadunico.beneficios_portal.schedule.enabled' => true,
            'ieducar.admin_sync.schedule.enabled' => true,
        ]);

        $this->artisan('schedule:list')->assertSuccessful();

        ScheduledJobsCatalog::forgetCache();
        $catalog = ScheduledJobsCatalog::buildFresh(app(Schedule::class));

        $this->assertGreaterThanOrEqual(5, $catalog['registered']);
        $this->assertNotEmpty($catalog['groups']);
        $this->assertArrayHasKey('cron', $catalog['runner']);

        $names = collect($catalog['jobs'])->pluck('name');
        $this->assertTrue($names->contains('horizonte-fortnightly-feed-start'));
        $this->assertTrue($names->contains('horizonte-fortnightly-feed-step'));
        $this->assertTrue($names->contains('cadunico-beneficios-portal'));
        $this->assertTrue($names->contains('admin-sync-scheduled-work'));

        $feed = collect($catalog['jobs'])->firstWhere('name', 'horizonte-fortnightly-feed-start');
        $this->assertNotNull($feed);
        $this->assertSame('horizonte', $feed['group']);
        $this->assertStringContainsString('procurement', strtolower((string) ($feed['description'] ?? '')));
        $this->assertTrue($feed['registered']);
    }

    #[Test]
    public function beneficios_portal_cadence_has_summary(): void
    {
        config([
            'ieducar.cadunico.beneficios_portal.schedule.day' => 9,
            'ieducar.cadunico.beneficios_portal.schedule.months' => [1, 3, 5, 7, 9, 11],
            'ieducar.cadunico.beneficios_portal.schedule.time' => '05:30',
        ]);

        $this->assertSame('30 5 9 1,3,5,7,9,11 *', CadunicoBeneficiosPortalScheduleCadence::cronExpression());
        $this->assertStringContainsString('09', CadunicoBeneficiosPortalScheduleCadence::summary());
    }
}
