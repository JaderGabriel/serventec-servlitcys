<?php

namespace Tests\Unit\Support\Horizonte;

use App\Support\Horizonte\ObrasgovWorkFieldExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ObrasgovWorkFieldExtractorTest extends TestCase
{
    #[Test]
    public function valor_previsto_sums_investimentos(): void
    {
        $this->assertSame(1500.5, ObrasgovWorkFieldExtractor::valorPrevisto([
            'investimentos_previstos' => [
                ['vl_investimento_previsto' => 1000],
                ['vl_investimento_previsto' => 500.5],
            ],
        ]));
    }

    #[Test]
    public function valor_previsto_ignora_placeholder_001(): void
    {
        $this->assertNull(ObrasgovWorkFieldExtractor::valorPrevisto([
            'investimentos_previstos' => [['vl_investimento_previsto' => 0.01]],
        ]));

        $this->assertSame(5000.0, ObrasgovWorkFieldExtractor::valorPrevisto([
            'investimentos_previstos' => [
                ['vl_investimento_previsto' => 0.01],
                ['vl_investimento_previsto' => 5000],
            ],
        ]));
    }

    #[Test]
    public function data_inicio_prefers_efetiva(): void
    {
        $this->assertSame('2022-03-15', ObrasgovWorkFieldExtractor::dataInicio([
            'dt_inicial_prevista' => '2022-01-01',
            'dt_inicial_efetiva' => '2022-03-15',
        ]));
    }

    #[Test]
    public function data_inicio_usa_dt_inicial_execucao_antes_de_cadastro(): void
    {
        $this->assertSame('2014-03-20', ObrasgovWorkFieldExtractor::dataInicio(
            ['dt_cadastro' => '2025-07-30'],
            ['dt_inicial_execucao' => '2014-03-20T00:00:00'],
        ));
    }

    #[Test]
    public function data_inicio_usa_nomes_reais_do_projeto(): void
    {
        $this->assertSame('2021-11-09', ObrasgovWorkFieldExtractor::dataInicio([
            'dt_inicial_prevista' => '2021-11-09',
            'dt_cadastro' => '2025-07-28',
            'dt_inicial_efetiva' => null,
        ]));
    }

    #[Test]
    public function data_paralisacao_picks_latest(): void
    {
        $this->assertSame('2024-06-01', ObrasgovWorkFieldExtractor::dataParalisacao([
            ['dt_paralisacao' => '2023-01-10'],
            ['data_paralisacao' => '01/06/2024'],
        ]));
    }

    #[Test]
    public function data_paralisacao_usa_campo_obrasgov_historico(): void
    {
        $this->assertSame('2026-05-25', ObrasgovWorkFieldExtractor::dataParalisacao([
            [
                'data_historico_situacao_investimento' => '2026-05-25',
                'justificativa_cancelada_paralisada' => 'Paralisada',
            ],
        ]));
    }

    #[Test]
    public function data_ultima_afericao_from_execucao(): void
    {
        $this->assertSame('2025-11-20', ObrasgovWorkFieldExtractor::dataUltimaAfericao([
            'dt_ultima_afericao' => '2025-11-20T12:00:00',
            'percentual_execucao_fisica' => 42,
        ]));
    }

    #[Test]
    public function data_ultima_afericao_usa_dt_atualizacao_execucao(): void
    {
        $this->assertSame('2026-05-27', ObrasgovWorkFieldExtractor::dataUltimaAfericao([
            'dt_atualizacao_execucao' => '2026-05-27',
        ]));
    }

    #[Test]
    public function totais_empenho_usa_campos_reais_da_api(): void
    {
        $totais = ObrasgovWorkFieldExtractor::totaisEmpenho([
            [
                'valor_empenho' => 916818.15,
                'pago' => 100000,
                'liquidado' => 200000,
                'fonte' => '1000000000',
            ],
            [
                'valor_empenho' => 1000,
                'pago' => null,
                'liquidado' => null,
            ],
        ]);

        $this->assertSame(917818.15, $totais['valor_empenhado']);
        $this->assertSame(100000.0, $totais['valor_pago']);
        $this->assertSame(200000.0, $totais['valor_liquidado']);
        $this->assertSame('1000000000', $totais['fonte']);
    }

    #[Test]
    public function percentual_execucao_from_api_field(): void
    {
        $this->assertSame(83.41, ObrasgovWorkFieldExtractor::percentualExecucao([
            'percentual_execucao_fisica' => 83.41,
        ]));
    }

    #[Test]
    public function porte_extrai_escola_e_salas_da_meta(): void
    {
        $porte = ObrasgovWorkFieldExtractor::porte([
            'desc_meta_global' => 'Escola 6 Salas',
            'desc_nome' => 'Comunidade do Gregóreo - Tarauacá - AC',
            'populacao_beneficiada' => null,
        ]);

        $this->assertSame('escola', $porte['tipology']);
        $this->assertSame(6, $porte['salas']);
        $this->assertSame('Escola 6 Salas', $porte['porte_resumo']);
        $this->assertNull($porte['populacao_beneficiada']);
    }

    #[Test]
    public function porte_identifica_creche_e_populacao_api(): void
    {
        $porte = ObrasgovWorkFieldExtractor::porte([
            'desc_meta_global' => 'Creche Pré-Escola - Tipo 1',
            'desc_nome' => 'Creche Municipal de Xapuri',
            'populacao_beneficiada' => 188,
        ]);

        $this->assertSame('creche', $porte['tipology']);
        $this->assertSame(188, $porte['populacao_beneficiada']);
        $this->assertSame('populacao_beneficiada', $porte['populacao_fonte']);
    }
}
