<?php

namespace Tests\Unit;

use App\Support\Analytics\AnalyticsReportFundebReferenceTables;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsReportFundebReferenceTablesTest extends TestCase
{
    #[Test]
    public function gera_quadros_portaria_e_complementacao_a_partir_do_perfil(): void
    {
        $profile = [
            'years' => [
                2025 => [
                    'label' => '2025',
                    'receita' => [
                        'disponivel' => true,
                        'total' => 10_000_000,
                        'complementacao_vaaf' => 1_000_000,
                        'complementacao_vaat' => 200_000,
                        'complementacao_vaar' => 300_000,
                        'ano_publicacao' => 2025,
                    ],
                    'matriculas' => ['usado' => 2000, 'fonte_usada' => 'ieducar'],
                    'vaaf_estimado' => ['valor' => 5000.0],
                    'previsao_recursos' => ['base_anual' => 10_000_000.0],
                    'db_reference' => null,
                ],
            ],
            'alerts' => [],
        ];

        $fundeb = [
            'resource_projection' => [
                'totais' => [
                    'fundeb_base_anual' => 9_000_000,
                    'complementacao_vaar' => 500_000,
                    'total_com_complemento' => 9_500_000,
                ],
                'distribuicao_legal' => [
                    'referencia_legal' => 'Lei teste',
                    'itens' => [
                        ['titulo' => 'Remuneração', 'percentual' => 70, 'valor' => 6_300_000, 'descricao' => 'Folha'],
                    ],
                ],
            ],
        ];

        $bundle = (new AnalyticsReportFundebReferenceTables())->build($profile, $fundeb);

        $this->assertTrue($bundle['portaria_exercicios']['available']);
        $this->assertCount(1, $bundle['portaria_exercicios']['rows']);
        $this->assertTrue($bundle['complementacao_eixos']['available']);
        $this->assertTrue($bundle['cenarios_previsao']['available']);
        $this->assertTrue($bundle['distribuicao_legal']['available']);

        $scopeTables = (new AnalyticsReportFundebReferenceTables())->asScopeTables($bundle);
        $this->assertGreaterThanOrEqual(3, count($scopeTables));
    }
}
