<?php

namespace Tests\Unit;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Services\Funding\BbExtratoCsvFetcher;
use App\Services\Funding\BbFundebExtratoService;
use App\Services\Funding\MunicipalTransferImportService;
use App\Services\Funding\SiswebFundebRepassesService;
use App\Services\Funding\TesouroFundebPublicacaoService;
use App\Services\Funding\TesouroTransferenciasCsvService;
use App\Support\Funding\MunicipalTransferGranularityEnricher;
use App\Support\Funding\PortalTransparenciaApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PortalTransparenciaApiClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function parseia_ano_mes_do_campo_anoMes(): void
    {
        $this->assertSame(2025, PortalTransparenciaApiClient::yearFromAnoMes(202506));
        $this->assertSame(6, PortalTransparenciaApiClient::monthFromAnoMes(202506));
        $this->assertSame(2025, PortalTransparenciaApiClient::yearFromAnoMes('2025/06'));
        $this->assertNull(PortalTransparenciaApiClient::monthFromAnoMes('2025'));
    }

    #[Test]
    public function recursos_recebidos_usa_endpoint_e_header_corretos(): void
    {
        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/despesas/recursos-recebidos*' => Http::response([
                [
                    'anoMes' => 202503,
                    'nomeOrgao' => 'FNDE - PNAE Alimentação',
                    'nomeUG' => 'FNDE',
                    'valor' => 15000.5,
                ],
            ], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $rows = $client->recursosRecebidos('2915700', 2025, 'token-teste', 10, 1);

        $this->assertCount(1, $rows);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api-de-dados/despesas/recursos-recebidos')
                && $request->hasHeader('chave-api-dados', 'token-teste')
                && ($request['codigoIBGE'] ?? null) === '2915700'
                && ($request['mesAnoInicio'] ?? null) === '01/2025'
                && ($request['mesAnoFim'] ?? null) === '12/2025'
                && ($request['pagina'] ?? null) === 1;
        });
    }

    #[Test]
    public function recursos_para_municipio_usa_cnpj_favorecido(): void
    {
        Cache::flush();

        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/convenios*' => Http::response([
                [
                    'convenente' => [
                        'nome' => 'MUNICIPIO DE ITAMARI',
                        'cnpjFormatado' => '13.753.959/0001-40',
                    ],
                ],
            ], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/despesas/recursos-recebidos*' => Http::response([
                [
                    'anoMes' => 202503,
                    'nomeOrgao' => 'Fundo Nacional de Desenvolvimento da Educação',
                    'nomeUG' => 'FUNDO NACIONAL DE DESENVOLVIMENTO DA EDUCACAO',
                    'codigoOrgao' => '26298',
                    'valor' => 15000.5,
                ],
            ], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $rows = $client->recursosRecebidosParaMunicipio('2915700', 2025, 'token-teste', 10, 1);

        $this->assertCount(1, $rows);
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/despesas/recursos-recebidos')) {
                return false;
            }

            return ($request['codigoFavorecido'] ?? null) === '13753959000140'
                && ! isset($request['codigoIBGE']);
        });
    }

    #[Test]
    public function convenios_filtra_ano_pela_ultima_liberacao(): void
    {
        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/convenios*' => Http::response([], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $client->convenios('2907608', 'token-teste', 10, 2026, 1);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/convenios')
                && ($request['dataUltimaLiberacaoInicial'] ?? null) === '01/01/2026'
                && ($request['dataUltimaLiberacaoFinal'] ?? null) === '31/12/2026'
                && ! isset($request['dataInicial']);
        });
    }

    #[Test]
    public function emendas_consulta_ano_e_funcao_educacao(): void
    {
        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/emendas*' => Http::response([
                [
                    'codigoEmenda' => '202540290014',
                    'ano' => 2025,
                    'localidadeDoGasto' => 'CAMPESTRE - MG',
                    'funcao' => 'Educação',
                    'valorEmpenhado' => '60.000,00',
                    'autor' => 'LAFAYETTE DE ANDRADA',
                ],
            ], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $rows = $client->emendas(2025, 'token-teste', 10, 1);

        $this->assertCount(1, $rows);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api-de-dados/emendas')
                && ! str_contains($request->url(), '/documentos/')
                && $request->hasHeader('chave-api-dados', 'token-teste')
                && (int) ($request['ano'] ?? 0) === 2025
                && ($request['codigoFuncao'] ?? null) === PortalTransparenciaApiClient::FUNCAO_EDUCACAO
                && (int) ($request['pagina'] ?? 0) === 1;
        });
    }

    #[Test]
    public function emendas_para_municipio_filtra_localidade_e_ignora_uf_agregada(): void
    {
        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/emendas*' => Http::response([
                [
                    'codigoEmenda' => '1',
                    'localidadeDoGasto' => 'CAMPESTRE - MG',
                    'valorPago' => '10.000,00',
                ],
                [
                    'codigoEmenda' => '2',
                    'localidadeDoGasto' => 'MINAS GERAIS (UF)',
                    'valorPago' => '99.000,00',
                ],
                [
                    'codigoEmenda' => '3',
                    'localidadeDoGasto' => 'ALFENAS - MG',
                    'valorPago' => '5.000,00',
                ],
            ], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $rows = $client->emendasParaMunicipio('Campestre', 'MG', 2025, 'token-teste', 10, 1);

        $this->assertCount(1, $rows);
        $this->assertSame('1', $rows[0]['codigoEmenda']);
    }

    #[Test]
    public function emendas_documentos_usa_codigo_no_path(): void
    {
        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/emendas/documentos/*' => Http::response([
                [
                    'id' => 1,
                    'fase' => 'Empenho',
                    'codigoDocumento' => '2025NE654165',
                ],
            ], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $docs = $client->emendasDocumentos('202540290014', 'token-teste', 10, 1);

        $this->assertCount(1, $docs);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api-de-dados/emendas/documentos/202540290014')
                && (int) ($request['pagina'] ?? 0) === 1;
        });
    }

    #[Test]
    public function parse_valor_brl_e_match_localidade(): void
    {
        $this->assertSame(60000.0, PortalTransparenciaApiClient::parseValorBrl('60.000,00'));
        $this->assertSame(0.0, PortalTransparenciaApiClient::parseValorBrl('0,00'));
        $this->assertSame(1500.5, PortalTransparenciaApiClient::parseValorBrl(1500.5));

        $this->assertTrue(PortalTransparenciaApiClient::localidadeMatchesMunicipio('CAMPESTRE - MG', 'Campestre', 'MG'));
        $this->assertTrue(PortalTransparenciaApiClient::localidadeMatchesMunicipio('Tarauacá - AC', 'Tarauacá', 'AC'));
        $this->assertFalse(PortalTransparenciaApiClient::localidadeMatchesMunicipio('ACRE (UF)', 'Tarauacá', 'AC'));
        $this->assertFalse(PortalTransparenciaApiClient::localidadeMatchesMunicipio('CAMPESTRE - MG', 'Campestre', 'BA'));
    }

    #[Test]
    public function contratos_exige_codigo_orgao_e_datas(): void
    {
        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/contratos*' => Http::response([
                [
                    'id' => 99,
                    'numero' => '1/2025',
                    'objeto' => 'Sistema de gestão',
                    'valorInicialCompra' => 1000.5,
                    'fornecedor' => [
                        'cnpjFormatado' => '12.345.678/0001-99',
                        'nome' => 'Vendor X',
                    ],
                ],
            ], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $rows = $client->contratos('26298', 'token-teste', '01/01/2025', '31/12/2025', 10, 1);

        $this->assertCount(1, $rows);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api-de-dados/contratos')
                && ($request['codigoOrgao'] ?? null) === '26298'
                && ($request['dataInicial'] ?? null) === '01/01/2025'
                && ($request['dataFinal'] ?? null) === '31/12/2025'
                && (int) ($request['pagina'] ?? 0) === 1;
        });
    }

    #[Test]
    public function licitacoes_ano_varre_meses(): void
    {
        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/licitacoes*' => Http::response([
                ['id' => 1, 'valor' => 10],
            ], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $rows = $client->licitacoesAno('26000', 2025, 'token-teste', 10, 1, 2);

        $this->assertCount(2, $rows);
        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api-de-dados/licitacoes')
                && ($request['codigoOrgao'] ?? null) === '26000'
                && ($request['dataInicial'] ?? null) === '01/01/2025'
                && ($request['dataFinal'] ?? null) === '31/01/2025';
        });
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api-de-dados/licitacoes')
                && ($request['dataInicial'] ?? null) === '01/02/2025'
                && ($request['dataFinal'] ?? null) === '28/02/2025';
        });
    }

    #[Test]
    public function contratos_por_cnpj_e_itens_contratados(): void
    {
        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/contratos/cpf-cnpj*' => Http::response([
                ['id' => 10, 'numero' => '1'],
            ], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/contratos/itens-contratados*' => Http::response([
                ['numero' => '1', 'descricao' => 'Sistema'],
            ], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $contratos = $client->contratosPorCnpj('12.345.678/0001-99', 'token-teste', 10, 1);
        $itens = $client->itensContratados(10, 'token-teste', 10, 1);

        $this->assertCount(1, $contratos);
        $this->assertCount(1, $itens);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/contratos/cpf-cnpj')
                && ($request['cpfCnpj'] ?? null) === '12345678000199';
        });
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/contratos/itens-contratados')
                && (string) ($request['id'] ?? '') === '10';
        });
    }

    #[Test]
    public function ceis_cnep_cepim_consultam_por_cnpj(): void
    {
        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/ceis*' => Http::response([
                ['id' => 1, 'sancionado' => ['nome' => 'Vendor A', 'codigoFormatado' => '12.345.678/0001-99']],
            ], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/cnep*' => Http::response([], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/cepim*' => Http::response([
                ['id' => 2, 'motivo' => 'Impedida', 'pessoaJuridica' => ['nome' => 'Vendor A']],
            ], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $ceis = $client->ceis('12.345.678/0001-99', 'token-teste', 10, 1);
        $cnep = $client->cnep('12345678000199', 'token-teste', 10, 1);
        $cepim = $client->cepim('12345678000199', 'token-teste', 10, 1);

        $this->assertCount(1, $ceis);
        $this->assertSame([], $cnep);
        $this->assertCount(1, $cepim);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/ceis')
            && ($request['codigoSancionado'] ?? null) === '12345678000199');
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/cepim')
            && ($request['cnpjSancionado'] ?? null) === '12345678000199');
    }

    #[Test]
    public function import_complementar_grava_pnae_do_portal_e_omite_fundeb(): void
    {
        Cache::flush();

        config([
            'ieducar.funding.transfers.enabled' => true,
            'ieducar.other_funding.public_queries.portal_transparencia.enabled' => true,
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => 'token-teste',
            'ieducar.other_funding.public_queries.tesouro_ckan.enabled' => false,
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_resources' => [],
        ]);

        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/convenios*' => Http::response([
                [
                    'convenente' => [
                        'nome' => 'MUNICIPIO DE ITAMARI',
                        'cnpjFormatado' => '13.753.959/0001-40',
                    ],
                ],
            ], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/despesas/recursos-recebidos*' => Http::response([
                [
                    'anoMes' => 202504,
                    'nomeOrgao' => 'FNDE - Programa Nacional de Alimentação Escolar PNAE',
                    'valor' => 22000,
                ],
                [
                    'anoMes' => 202504,
                    'nomeOrgao' => 'Tesouro - FUNDEB',
                    'valor' => 999999,
                ],
            ], 200),
        ]);

        $city = new City([
            'id' => 1,
            'name' => 'Itamari',
            'uf' => 'BA',
            'ibge_municipio' => '2915700',
        ]);

        $snapshots = Mockery::mock(MunicipalTransferSnapshotRepository::class);
        $snapshots->shouldReceive('upsertBatch')
            ->once()
            ->withArgs(function (?City $c, array $rows) use ($city): bool {
                $this->assertSame($city->ibge_municipio, $c?->ibge_municipio);
                $this->assertNotEmpty($rows);
                $programas = array_column($rows, 'programa_id');
                $this->assertContains('pnae', $programas);
                $this->assertNotContains('fundeb', $programas);

                return true;
            })
            ->andReturn(1);

        $tesouroCsv = new TesouroTransferenciasCsvService;
        $service = new MunicipalTransferImportService(
            $snapshots,
            $tesouroCsv,
            new TesouroFundebPublicacaoService,
            new SiswebFundebRepassesService,
            new BbFundebExtratoService(new BbExtratoCsvFetcher),
            new MunicipalTransferGranularityEnricher($tesouroCsv),
            new PortalTransparenciaApiClient,
        );

        $result = $service->importComplementaryForCityYear($city, 2025);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['rows']);
        $this->assertGreaterThanOrEqual(1, $result['skipped_fundeb']);
        $this->assertArrayHasKey('pnae', $result['by_programa']);
    }
}
