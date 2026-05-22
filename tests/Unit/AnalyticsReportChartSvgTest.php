<?php

namespace Tests\Unit;

use App\Support\Analytics\AnalyticsReportChartSvg;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsReportChartSvgTest extends TestCase
{
    #[Test]
    public function it_renders_vertical_bar_chart_with_dark_labels(): void
    {
        $svg = AnalyticsReportChartSvg::render([
            'title' => 'Pendências por tipo',
            'labels' => ['A', 'B'],
            'datasets' => [
                ['data' => [10, 25], 'backgroundColor' => ['#0f766e', '#4338ca']],
            ],
        ]);

        $this->assertNotNull($svg);
        $this->assertStringContainsString('Pendências por tipo', $svg);
        $this->assertStringContainsString('fill="#0f172a"', $svg);
        $this->assertStringContainsString('stroke="#94a3b8"', $svg);
    }

    #[Test]
    public function it_renders_horizontal_bar_chart(): void
    {
        $svg = AnalyticsReportChartSvg::render([
            'title' => 'Programas',
            'labels' => ['Programa longo'],
            'datasets' => [
                ['data' => [3]],
            ],
            'options' => ['indexAxis' => 'y'],
        ]);

        $this->assertNotNull($svg);
        $this->assertStringContainsString('Programa longo', $svg);
        $this->assertStringContainsString('3', $svg);
    }
}
