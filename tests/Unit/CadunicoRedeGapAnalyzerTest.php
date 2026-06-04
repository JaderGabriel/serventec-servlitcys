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
}
