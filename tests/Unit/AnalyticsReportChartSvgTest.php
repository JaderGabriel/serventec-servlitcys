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

    #[Test]
    public function it_renders_line_multi_chart_treating_null_as_zero(): void
    {
        $svg = AnalyticsReportChartSvg::render([
            'type' => 'line',
            'title' => 'Matrículas — Censo INEP',
            'labels' => ['2021', '2022', '2023'],
            'datasets' => [
                [
                    'label' => 'Total',
                    'data' => [100, null, 140],
                    'borderColor' => '#0f766e',
                ],
                [
                    'label' => 'Regular',
                    'data' => [80, 90, 110],
                    'borderColor' => '#4338ca',
                ],
            ],
        ]);

        $this->assertNotNull($svg);
        $this->assertStringContainsString('Matrículas — Censo INEP', $svg);
        $this->assertStringContainsString('<polyline', $svg);
        $this->assertStringContainsString('Total', $svg);
        $this->assertStringContainsString('2021', $svg);
        $this->assertStringContainsString('#0f766e', $svg);
    }

    #[Test]
    public function it_renders_line_multi_as_png_data_uri_for_dompdf(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Extensão gd necessária.');
        }

        $uri = AnalyticsReportChartSvg::renderDataUri([
            'type' => 'line',
            'title' => 'Matrículas — Censo INEP (Municipal)',
            'labels' => ['2021', '2022', '2023', '2024', '2025'],
            'datasets' => [
                [
                    'label' => 'Total',
                    'data' => [4152, 3114, 2076, 1038, 1200],
                    'borderColor' => '#0f766e',
                ],
                [
                    'label' => 'Regular',
                    'data' => [3000, 2500, 1800, 900, 1000],
                    'borderColor' => '#4338ca',
                ],
            ],
        ]);

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $binary = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
        $this->assertNotFalse($binary);
        $this->assertGreaterThan(500, strlen($binary));
        $this->assertSame("\x89PNG", substr($binary, 0, 4));
    }
}
