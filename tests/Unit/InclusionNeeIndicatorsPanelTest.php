<?php

namespace Tests\Unit;

use App\Support\Ieducar\InclusionNeeIndicatorsPanel;
use Tests\TestCase;

class InclusionNeeIndicatorsPanelTest extends TestCase
{
    public function test_build_unifies_kpis_gauges_and_catalog(): void
    {
        $panel = InclusionNeeIndicatorsPanel::build(
            [
                'grupos' => [
                    'deficiencias' => 40,
                    'sindromes_tea' => 10,
                    'ne_altas_habilidades' => 5,
                ],
                'matriculas_nee' => 100,
                'matriculas_com_cadastro_nee' => 80,
                'uses_fisica' => true,
            ],
            [
                [
                    'chart' => ['type' => 'gauge'],
                    'caption' => 'Deficiências 40%',
                ],
            ],
            1000,
            100,
            null,
            [
                ['chart_id' => 'nee_catalogo', 'labels' => ['A'], 'datasets' => []],
            ],
        );

        $this->assertNotNull($panel);
        $this->assertCount(5, $panel['kpis']);
        $this->assertCount(1, $panel['gauges']);
        $this->assertSame('nee_catalogo', $panel['catalog_chart']['chart_id'] ?? null);
        $this->assertNotEmpty($panel['legend']);
    }

    public function test_build_returns_null_when_empty(): void
    {
        $this->assertNull(InclusionNeeIndicatorsPanel::build(null, [], null, null, null, []));
    }
}
