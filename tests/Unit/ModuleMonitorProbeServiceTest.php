<?php

namespace Tests\Unit;

use App\Services\Admin\ModuleMonitorProbeService;
use App\Support\Admin\ModuleMonitorSnapshotCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ModuleMonitorProbeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_collect_stores_snapshot_for_all_modules(): void
    {
        $snapshot = app(ModuleMonitorProbeService::class)->collect();

        $this->assertArrayHasKey('collected_at', $snapshot);
        $this->assertArrayHasKey('modules', $snapshot);
        $this->assertArrayHasKey('analytics', $snapshot['modules']);
        $this->assertArrayHasKey('geo', $snapshot['modules']);
        $this->assertContains(
            $snapshot['modules']['analytics']['signal'],
            ['operational', 'idle', 'degraded', 'failed', 'unknown'],
        );

        $cached = ModuleMonitorSnapshotCache::get();
        $this->assertNotNull($cached);
        $this->assertSame($snapshot['collected_at'], $cached['collected_at']);
    }

    public function test_snapshot_freshness_respects_config(): void
    {
        config(['cache.default' => 'array']);

        ModuleMonitorSnapshotCache::put([
            'collected_at' => now()->subHours(2)->toIso8601String(),
            'modules' => [],
        ]);

        $this->assertTrue(ModuleMonitorSnapshotCache::isFresh());

        ModuleMonitorSnapshotCache::put([
            'collected_at' => now()->subHours(48)->toIso8601String(),
            'modules' => [],
        ]);

        $this->assertFalse(ModuleMonitorSnapshotCache::isFresh());
    }
}
