<?php

namespace Tests\Unit;

use App\Support\Admin\ModuleMonitorHorizonteProbe;
use Tests\TestCase;

final class ModuleMonitorHorizonteProbeTest extends TestCase
{
    public function test_probe_flags_missing_feed(): void
    {
        $probe = ModuleMonitorHorizonteProbe::probe([
            'enabled' => true,
            'coverage' => ['universe_municipios' => 100, 'with_full_triad' => 50, 'microdados_ok' => true],
            'phases' => [['ok' => true], ['ok' => true]],
            'last_feed' => null,
            'pipeline' => null,
        ]);

        $this->assertSame('degraded', $probe['signal']);
    }

    public function test_probe_operational_when_feed_recent_and_phases_ok(): void
    {
        $probe = ModuleMonitorHorizonteProbe::probe([
            'enabled' => true,
            'coverage' => ['universe_municipios' => 100, 'with_full_triad' => 80, 'microdados_ok' => true],
            'phases' => [['ok' => true], ['ok' => true], ['ok' => true]],
            'last_feed' => ['success' => true, 'finished_at' => now()->subDays(5)->toIso8601String()],
            'pipeline' => ['status' => 'completed'],
        ]);

        $this->assertSame('operational', $probe['signal']);
    }

    public function test_probe_degraded_when_pipeline_running(): void
    {
        $probe = ModuleMonitorHorizonteProbe::probe([
            'enabled' => true,
            'coverage' => ['universe_municipios' => 100, 'with_full_triad' => 80, 'microdados_ok' => true],
            'phases' => [['ok' => true]],
            'last_feed' => ['success' => true, 'finished_at' => now()->subDay()->toIso8601String()],
            'pipeline' => ['status' => 'running', 'current_phase' => 'saeb_planilhas'],
        ]);

        $this->assertSame('degraded', $probe['signal']);
    }

    public function test_kpi_summary_extracts_counts(): void
    {
        $kpi = ModuleMonitorHorizonteProbe::kpiSummary([
            'coverage' => ['universe_municipios' => 200, 'with_full_triad' => 120],
            'phases' => [['ok' => true], ['ok' => false]],
            'pipeline' => ['status' => 'idle'],
            'last_feed' => ['finished_at' => now()->subDays(3)->toIso8601String()],
        ]);

        $this->assertSame(120, $kpi['triad']);
        $this->assertSame(200, $kpi['universe']);
        $this->assertSame(1, $kpi['phases_ok']);
        $this->assertSame(2, $kpi['phases_total']);
        $this->assertSame(3, $kpi['feed_age_days']);
    }
}
