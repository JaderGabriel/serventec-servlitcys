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
