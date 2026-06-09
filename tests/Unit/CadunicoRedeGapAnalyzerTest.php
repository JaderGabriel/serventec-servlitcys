<?php

namespace Tests\Unit;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Services\Cadunico\CadunicoRedeGapAnalyzer;
use App\Support\Dashboard\IeducarFilterState;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoRedeGapAnalyzerTest extends TestCase
{
    #[Test]
    public function calcula_lacuna_e_impacto_financeiro(): void
    {
        $city = new City(['name' => 'Teste', 'ibge_municipio' => '2910800']);
        $filters = new IeducarFilterState('2024', null, null, null);

        $snap = new CadunicoMunicipioSnapshot([
            'ibge_municipio' => '2910800',
            'ano_referencia' => 2024,
            'criancas_4_5' => 500,
            'criancas_6_10' => 1200,
            'criancas_11_14' => 800,
            'criancas_15_17' => 400,
            'populacao_escolar_estimada' => 2900,
        ]);

        $result = (new CadunicoRedeGapAnalyzer)->analyze(
            $city,
            $filters,
            2500,
            2400,
            [
                ['etapa' => 'Ensino Fundamental', 'matriculas' => 2000],
                ['etapa' => 'Educação Infantil', 'matriculas' => 500],
            ],
            $snap,
            null,
            5000.0,
        );

        $this->assertTrue($result['available']);
        $this->assertSame(2900, $result['cadunico_total_escolar']);
        $this->assertSame(2400, $result['ieducar_base_calculo']);
        $this->assertSame(500, $result['gap_total']);
        $this->assertNotEmpty($result['por_faixa']);
        $this->assertTrue($result['cenarios_financeiros']['available'] ?? false);
        $this->assertNotNull($result['impacto_financeiro']['gap_anual']);
    }

    #[Test]
    public function usa_faixa_por_idade_e_ajuste_censo_quando_disponivel(): void
    {
        $city = new City(['name' => 'Teste', 'ibge_municipio' => '2910800']);
        $filters = new IeducarFilterState('2024', null, null, null);

        $snap = new CadunicoMunicipioSnapshot([
            'ibge_municipio' => '2910800',
            'ano_referencia' => 2024,
            'criancas_4_5' => 500,
            'criancas_6_10' => 1200,
            'criancas_11_14' => 800,
            'criancas_15_17' => 400,
            'populacao_escolar_estimada' => 2900,
        ]);

        $censo = new \App\Models\InepCensoMunicipioMatricula([
            'matriculas_total' => 5000,
            'matriculas_nao_municipal' => 200,
        ]);

        $faixaCounts = [
            'available' => true,
            'metodo' => \App\Services\Cadunico\CadunicoFaixaEtariaMetodo::IDADE,
            'por_faixa' => [
                'criancas_4_5' => 480,
                'criancas_6_10' => 1100,
                'criancas_11_14' => 700,
                'criancas_15_17' => 320,
            ],
            'cobertura_nascimento_pct' => 98.5,
        ];

        $result = (new CadunicoRedeGapAnalyzer)->analyze(
            $city,
            $filters,
            2500,
            2400,
            [],
            $snap,
            5000,
            5000.0,
            [],
            $faixaCounts,
            $censo,
        );

        $this->assertSame(\App\Services\Cadunico\CadunicoFaixaEtariaMetodo::IDADE, $result['faixa_metodo']);
        $this->assertTrue($result['censo_ajuste_aplicado']);
        $this->assertSame(100, $result['gap_total']);
        $this->assertSame(300, $result['gap_bruto']);
        $this->assertSame(480, $result['por_faixa'][0]['ieducar']);
    }
}
