<?php

namespace Tests\Unit;

use App\Support\Analytics\CadunicoPrevisaoExportRowsBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoPrevisaoExportRowsBuilderTest extends TestCase
{
    #[Test]
    public function gera_linhas_a_partir_do_relatorio(): void
    {
        $rows = CadunicoPrevisaoExportRowsBuilder::fromReport([
            'city_name' => 'Teste',
            'year_label' => '2024',
            'kpis' => [
                ['label' => 'Lacuna', 'value' => '100'],
            ],
            'gap' => [
                'por_etapa' => [
                    [
                        'etapa' => 'Fundamental',
                        'cadunico_estimado' => 500,
                        'ieducar_matriculas' => 400,
                        'gap_fmt' => '100',
                        'fundeb_gap_label' => 'R$ 1.000,00',
                    ],
                ],
                'impacto_financeiro' => [
                    'gap_anual_label' => 'R$ 5.000,00',
                    'formula' => 'teste',
                ],
            ],
            'informe' => [
                'blocos' => [
                    ['titulo' => 'Cobertura', 'status_label' => 'ok', 'paragrafos' => ['Texto']],
                ],
            ],
        ]);

        $this->assertNotEmpty($rows);
        $this->assertSame('Teste', $rows[0]['cidade']);
        $this->assertTrue(
            collect($rows)->contains(static fn (array $r): bool => ($r['secao'] ?? '') === __('Por etapa')),
        );
    }
}
