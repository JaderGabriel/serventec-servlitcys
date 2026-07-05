<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteSaebTrend;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteSaebTrendTest extends TestCase
{
    #[Test]
    public function analyze_detects_downward_trend(): void
    {
        $lp = [
            ['year' => 2023, 'value' => 210.0],
            ['year' => 2021, 'value' => 230.0],
            ['year' => 2019, 'value' => 240.0],
        ];
        $mat = [
            ['year' => 2023, 'value' => 205.0],
            ['year' => 2021, 'value' => 225.0],
        ];

        $result = HorizonteSaebTrend::analyze($lp, $mat);

        $this->assertSame(HorizonteSaebTrend::TREND_DOWN, $result['trend']);
        $this->assertSame(-30.0, $result['delta_lp']);
        $this->assertGreaterThan(70, $result['learning_trajectory_score']);
    }

    #[Test]
    public function analyze_detects_upward_trend(): void
    {
        $lp = [
            ['year' => 2023, 'value' => 250.0],
            ['year' => 2019, 'value' => 220.0],
        ];

        $result = HorizonteSaebTrend::analyze($lp, []);

        $this->assertSame(HorizonteSaebTrend::TREND_UP, $result['trend']);
        $this->assertSame(30.0, $result['delta_lp']);
        $this->assertLessThan(40, $result['learning_trajectory_score']);
    }
}
