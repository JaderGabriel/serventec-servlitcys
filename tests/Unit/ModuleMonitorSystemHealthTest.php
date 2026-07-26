<?php

namespace Tests\Unit;

use App\Support\Admin\ModuleMonitorSystemHealth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModuleMonitorSystemHealthTest extends TestCase
{
    #[Test]
    public function qualify_returns_high_grade_when_all_healthy(): void
    {
        $health = ModuleMonitorSystemHealth::qualify(
            ['total' => 10, 'healthy' => 10, 'warning' => 0, 'critical' => 0, 'unknown' => 0],
            ['status' => 'healthy', 'status_hint' => 'OK'],
            true,
            true,
            ['universe' => 100, 'triad' => 80, 'phases_ok' => 15, 'phases_total' => 17],
        );

        $this->assertSame('A', $health['grade']);
        $this->assertGreaterThanOrEqual(90, $health['score']);
        $this->assertCount(4, $health['dimensions']);
    }

    #[Test]
    public function qualify_penalizes_critical_modules_and_stale_snapshot(): void
    {
        $health = ModuleMonitorSystemHealth::qualify(
            ['total' => 10, 'healthy' => 5, 'warning' => 2, 'critical' => 3, 'unknown' => 0],
            ['status' => 'critical', 'status_hint' => 'falhas'],
            false,
            false,
            null,
        );

        $this->assertContains($health['grade'], ['D', 'F']);
        $this->assertLessThan(65, $health['score']);
    }
}
