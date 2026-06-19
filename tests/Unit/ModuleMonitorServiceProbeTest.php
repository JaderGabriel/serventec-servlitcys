<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\Admin\ModuleMonitorProbeService;
use App\Services\Admin\ModuleMonitorService;
use App\Support\Admin\ModuleMonitorCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ModuleMonitorServiceProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_idle_probe_marks_consultoria_module_healthy_without_pulse(): void
    {
        City::factory()->create(['is_active' => true]);

        app(ModuleMonitorProbeService::class)->collect();

        $report = app(ModuleMonitorService::class)->build('24h');
        $analytics = collect($report['modules'])->firstWhere('id', 'analytics');

        $this->assertNotNull($analytics);
        $this->assertSame('healthy', $analytics['status']);
        $this->assertNotSame('unknown', $analytics['status']);
        $this->assertNotNull($analytics['probe_detail']);
    }

    public function test_build_includes_snapshot_metadata(): void
    {
        City::factory()->create(['is_active' => true]);

        app(ModuleMonitorProbeService::class)->collect();

        $report = app(ModuleMonitorService::class)->build('24h');

        $this->assertNotNull($report['snapshot_collected_at']);
        $this->assertTrue($report['snapshot_fresh']);
        $this->assertSame(count(ModuleMonitorCatalog::modules()), count($report['modules']));
    }
}
