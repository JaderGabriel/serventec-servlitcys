<?php

namespace Tests\Unit;

use App\Support\Ieducar\InclusionFundebImpact;
use Tests\TestCase;

class InclusionFundebImpactTest extends TestCase
{
    public function test_peso_educacao_especial_default_e_1_20(): void
    {
        $this->assertEqualsWithDelta(1.2, InclusionFundebImpact::pesoEducacaoEspecial(), 0.001);
    }

    public function test_incremento_vaaf_20_por_cento_sobre_base(): void
    {
        $nee = 315;
        $vaaf = 5000.0;
        $peso = 1.2;
        $incremento = $peso - 1.0;
        $base = round($nee * $vaaf, 2);
        $adicional = round($nee * $vaaf * $incremento, 2);

        $this->assertEqualsWithDelta(1_575_000.0, $base, 0.01);
        $this->assertEqualsWithDelta(315_000.0, $adicional, 0.01);
    }

    public function test_parcela_vaar_proporcional_ao_incremento_nee(): void
    {
        $complement = 1_000_000.0;
        $nee = 100;
        $totalMat = 1000;
        $incremento = 0.2;
        $parcela = round($complement * ($nee * $incremento) / $totalMat, 2);

        $this->assertEqualsWithDelta(20_000.0, $parcela, 0.01);
    }
}
