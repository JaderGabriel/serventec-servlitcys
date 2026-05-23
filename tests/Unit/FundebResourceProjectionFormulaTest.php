<?php

namespace Tests\Unit;

use App\Support\Ieducar\FundebResourceProjection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebResourceProjectionFormulaTest extends TestCase
{
    #[Test]
    public function previsao_base_usa_vaaf_municipal_na_formula_e_no_total(): void
    {
        $ref = [
            'vaaf' => 4500.0,
            'fonte' => 'oficial_db',
            'fonte_label' => 'Portaria FNDE',
            'ano' => 2024,
            'municipal' => [
                'vaaf' => 5123.45,
                'fonte_label' => 'Importação municipal',
                'ano' => 2024,
            ],
            'previa' => ['vaaf' => 4500.0],
            'divergencia' => null,
            'vaat' => null,
            'complementacao_vaar' => null,
        ];

        $mat = 200;
        $proj = FundebResourceProjection::build(
            $mat,
            '2024',
            ['kpis' => ['matriculas' => $mat]],
            ['summary' => ['perda_estimada_anual' => 0, 'ganho_potencial_anual' => 0]],
            null,
            null,
            $ref,
        );

        $expectedBase = round($mat * 5123.45, 2);
        $this->assertSame(5123.45, $proj['vaa_referencia']);
        $this->assertSame($expectedBase, $proj['totais']['fundeb_base_anual']);
        $this->assertStringContainsString('5.123,45', $proj['formula_base']);
        $this->assertStringContainsString('1.024.690,00', $proj['formula_base']);
        $this->assertStringContainsString('VAAF municipal', $proj['formula_base']);
        $this->assertStringNotContainsString('4.500,00', $proj['formula_base']);
    }
}
