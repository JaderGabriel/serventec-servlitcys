<?php

namespace Tests\Unit;

use App\Support\Dashboard\ChartPayload;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Payloads Chart.js uniformes para exportação PNG/SVG e render no painel.
 */
final class ChartPayloadTest extends TestCase
{
    /**
     * Cenário: paleta vazia no config — fallback para cores padrão do projeto.
     */
    #[Test]
    public function palette_retorna_default_se_config_vazio(): void
    {
        config(['ieducar.chart_colors' => []]);

        $palette = ChartPayload::palette();

        $this->assertNotEmpty($palette);
        $this->assertStringStartsWith('#', $palette[0]);
    }

    /**
     * Cenário: gráfico de barras para resumo de discrepâncias.
     * Esperado: estrutura type/labels/datasets compatível com chart-panel.blade.
     */
    #[Test]
    public function bar_monta_dataset_com_cores_da_paleta(): void
    {
        $chart = ChartPayload::bar('Título', 'Série', ['A', 'B'], [10, 20]);

        $this->assertSame('bar', $chart['type']);
        $this->assertSame(['A', 'B'], $chart['labels']);
        $this->assertCount(2, $chart['datasets'][0]['data']);
        $this->assertCount(2, $chart['datasets'][0]['backgroundColor']);
    }

    /**
     * Cenário: ranking de escolas com nomes longos — barras horizontais.
     * Esperado: indexAxis=y no options (Chart.js v3+).
     */
    #[Test]
    public function bar_horizontal_define_index_axis_y(): void
    {
        $chart = ChartPayload::barHorizontal('Escolas', 'Ocorr.', ['Escola X'], [5]);

        $this->assertSame('y', $chart['options']['indexAxis'] ?? null);
    }

    /**
     * Cenário: gráfico doughnut para percentuais (conformidade, NEE).
     */
    /**
     * Cenário: gráfico doughnut para percentuais (conformidade, NEE).
     */
    #[Test]
    public function bar_stacked_define_escalas_empilhadas_no_eixo_y(): void
    {
        $chart = ChartPayload::barStacked('Título', 'Milhões de R$', ['A', 'B'], [
            ['label' => 'S1', 'data' => [1.0, 2.0]],
            ['label' => 'S2', 'data' => [3.0, 4.0]],
        ]);

        $this->assertSame('bar', $chart['type']);
        $this->assertTrue($chart['options']['scales']['x']['stacked'] ?? false);
        $this->assertTrue($chart['options']['scales']['y']['stacked'] ?? false);
        $this->assertSame('Milhões de R$', $chart['options']['scales']['y']['title']['text'] ?? null);
        $this->assertArrayNotHasKey('indexAxis', $chart['options']);
    }

    #[Test]
    public function doughnut_aceita_labels_e_valores(): void
    {
        $chart = ChartPayload::doughnut('Distribuição', ['OK', 'Pend.'], [80, 20]);

        $this->assertSame('doughnut', $chart['type']);
        $this->assertCount(2, $chart['labels']);
    }
}
