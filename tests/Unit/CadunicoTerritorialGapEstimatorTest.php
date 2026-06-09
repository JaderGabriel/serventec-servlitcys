<?php

namespace Tests\Unit;

use App\Models\CadunicoTerritorioSnapshot;
use App\Services\Cadunico\CadunicoFaixaEtariaMetodo;
use App\Services\Cadunico\CadunicoTerritorialGapEstimator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoTerritorialGapEstimatorTest extends TestCase
{
    #[Test]
    public function detecta_fonte_ibge_como_rateio(): void
    {
        $rows = collect([
            new CadunicoTerritorioSnapshot(['fonte' => 'ibge_censo_2022_wfs']),
        ]);

        $this->assertTrue(CadunicoTerritorialGapEstimator::isIbgeRateioCollection($rows));
    }

    #[Test]
    public function csv_territorial_usa_lacuna_direta(): void
    {
        $row = new CadunicoTerritorioSnapshot([
            'fonte' => 'csv_territorio',
            'criancas_4_17' => 2000,
        ]);

        $gap = [
            'gap_total' => 500,
            'cadunico_total_escolar' => 8000,
            'ieducar_base_calculo' => 6000,
            'ieducar_matriculas' => 6200,
            'faixa_metodo' => CadunicoFaixaEtariaMetodo::RATEIO,
            'por_faixa' => [],
        ];

        $est = CadunicoTerritorialGapEstimator::estimateForTerritory(
            $row,
            $gap,
            6000,
            8000,
            500,
            false,
        );

        $this->assertSame(500, $est);
    }

    #[Test]
    public function ibge_rateia_gap_municipal(): void
    {
        $row = new CadunicoTerritorioSnapshot([
            'fonte' => 'ibge_censo_2022_wfs',
            'criancas_4_17' => 500,
        ]);

        $gap = [
            'gap_total' => 1000,
            'ieducar_base_calculo' => 6000,
            'faixa_metodo' => CadunicoFaixaEtariaMetodo::RATEIO,
            'por_faixa' => [],
        ];

        $est = CadunicoTerritorialGapEstimator::estimateForTerritory(
            $row,
            $gap,
            6000,
            2000,
            1000,
            true,
        );

        $this->assertSame(250, $est);
    }
}
