<?php

namespace Tests\Unit;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\InepCensoMunicipioMatricula;
use App\Services\Cadunico\CadunicoEscolarizacaoDecisionCardBuilder;
use App\Services\Cadunico\CadunicoFaixaEtariaMetodo;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoEscolarizacaoDecisionCardBuilderTest extends TestCase
{
    #[Test]
    public function monta_linhas_por_faixa_com_censo_e_eja(): void
    {
        $gap = [
            'available' => true,
            'faixa_metodo' => CadunicoFaixaEtariaMetodo::IDADE,
            'gap_total_fmt' => '300',
            'censo_ajuste_aplicado' => true,
            'censo_nao_municipal' => 200,
            'por_faixa' => [
                [
                    'key' => 'criancas_4_5',
                    'faixa' => 'Pré-escola (4-5 anos)',
                    'cadunico' => 500,
                    'ieducar_estimado' => 480,
                    'ieducar' => 480,
                    'gap' => 20,
                    'gap_fmt' => '20',
                    'fundeb_gap_label' => 'R$ 100.000',
                ],
                [
                    'key' => 'criancas_6_10',
                    'faixa' => 'Fundamental (6-10)',
                    'cadunico' => 1200,
                    'ieducar_estimado' => 900,
                    'ieducar' => 900,
                    'gap' => 300,
                    'gap_fmt' => '300',
                    'fundeb_gap_label' => 'R$ 1,5 mi',
                ],
            ],
        ];

        $censo = new InepCensoMunicipioMatricula([
            'matriculas_infantil' => 520,
            'matriculas_infantil_municipal' => 400,
            'matriculas_fundamental_1' => 1000,
            'matriculas_fundamental_1_municipal' => 850,
            'matriculas_eja' => 450,
            'matriculas_eja_municipal' => 120,
            'matriculas_eja_nao_municipal' => 330,
        ]);

        $card = (new CadunicoEscolarizacaoDecisionCardBuilder)->build($gap, $censo, 115);

        $this->assertTrue($card['available']);
        $this->assertCount(2, $card['linhas']);
        $this->assertSame(300, $card['linhas'][0]['fora_rede_municipal']);
        $this->assertSame(200, $card['linhas'][0]['possivel_fora_escola']);
        $this->assertTrue($card['eja']['available']);
        $this->assertSame(450, $card['eja']['censo_total']);
        $this->assertSame(115, $card['eja']['ieducar_municipal']);
        $this->assertNotEmpty($card['prioridades_acao']);
    }

    #[Test]
    public function indisponivel_sem_gap(): void
    {
        $card = (new CadunicoEscolarizacaoDecisionCardBuilder)->build(['available' => false], null, null);

        $this->assertFalse($card['available']);
    }
}
