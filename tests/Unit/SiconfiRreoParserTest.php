<?php

namespace Tests\Unit;

use App\Support\Horizonte\SiconfiRreoParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SiconfiRreoParserTest extends TestCase
{
    #[Test]
    public function parse_extracts_education_and_liquidity_indicators(): void
    {
        $annex01 = [
            [
                'conta' => 'RECEITAS CORRENTES (I)',
                'coluna' => 'Até o Bimestre',
                'valor' => 10_000_000,
            ],
            [
                'conta' => 'RECEITAS (EXCETO INTRA-ORÇAMENTÁRIAS) (I)',
                'coluna' => 'Até o Bimestre',
                'valor' => 12_000_000,
            ],
            [
                'conta' => 'TRANSFERÊNCIAS CORRENTES',
                'coluna' => 'Até o Bimestre',
                'valor' => 4_000_000,
            ],
        ];
        $annex02 = [
            [
                'conta' => 'Educação',
                'coluna' => 'DESPESAS LIQUIDADAS ATÉ O BIMESTRE (d)',
                'valor' => 2_500_000,
            ],
        ];
        $annex06 = [
            [
                'conta' => 'DÍVIDA CONSOLIDADA (XXVIII)',
                'coluna' => 'Até o Bimestre',
                'valor' => 5_000_000,
            ],
            [
                'conta' => 'Disponibilidade de Caixa',
                'coluna' => 'Até o Bimestre',
                'valor' => 1_000_000,
            ],
            [
                'conta' => '(-) Restos a Pagar Processados (XXX)',
                'coluna' => 'Até o Bimestre',
                'valor' => 300_000,
            ],
        ];
        $annex14 = [
            [
                'conta' => 'Mínimo Anual de <18% / 25%> das Receitas de Impostos na Manutenção e Desenvolvimento do Ensino',
                'coluna' => '% (d/total d)',
                'valor' => 22.5,
            ],
        ];

        $parsed = SiconfiRreoParser::parse($annex01, $annex02, $annex06, $annex14);

        $this->assertSame(10_000_000.0, $parsed['receita_corrente_liquida']);
        $this->assertSame(2_500_000.0, $parsed['despesa_educacao_liquidada']);
        $this->assertSame(25.0, $parsed['pct_educacao_receita_corrente']);
        $this->assertSame(22.5, $parsed['pct_minimo_constitucional']);
        $this->assertSame(66.667, $parsed['pct_receita_propria']);
        $this->assertSame(0.2, $parsed['liquidity_ratio']);
        $this->assertGreaterThan(0, $parsed['fiscal_capacity_score']);
    }
}
