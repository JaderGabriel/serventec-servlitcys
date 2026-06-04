<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Support\Funding\FundebExtratoVisualBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebExtratoVisualBuilderTest extends TestCase
{
    #[Test]
    public function agrupa_por_ciclo_com_resumo_mensal_e_comparativo(): void
    {
        $city = new City(['name' => 'Teste', 'uf' => 'BA', 'ibge_municipio' => '2911105']);

        $csv = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB',
            'fonte' => 'tesouro_csv',
            'valor' => 300.0,
            'meta' => json_encode(['mensal' => ['1' => 100.0, '2' => 200.0]]),
        ]);

        $sisweb = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB',
            'fonte' => 'sisweb_ckan',
            'valor' => 300.0,
            'meta' => json_encode(['mensal' => ['1' => 100.0, '2' => 200.0]]),
        ]);

        $result = (new FundebExtratoVisualBuilder)->build([$csv, $sisweb], $city, 2025, 1200.0);

        $this->assertCount(2, $result['cycles']);
        $this->assertCount(2, $result['cycles'][0]['lines']);
        $this->assertCount(2, $result['cycles'][0]['by_period']);
        $this->assertSame(100.0, $result['cycles'][0]['by_period'][0]['credit']);
        $this->assertSame('positive', $result['cycles'][0]['comparativo']['delta_sign']);
        $this->assertArrayHasKey('consolidado', $result);
        $this->assertCount(2, $result['consolidado']['by_period']);
    }
}
