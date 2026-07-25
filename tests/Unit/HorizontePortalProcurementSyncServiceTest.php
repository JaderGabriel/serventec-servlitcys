<?php

namespace Tests\Unit;

use App\Repositories\PortalProcurementSnapshotRepository;
use App\Services\Horizonte\HorizontePortalProcurementSyncService;
use App\Support\Funding\PortalTransparenciaApiClient;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizontePortalProcurementSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function sync_persiste_contratos_e_marca_vendor_curado(): void
    {
        config([
            'ieducar.other_funding.public_queries.portal_transparencia.enabled' => true,
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => 'token-teste',
            'horizonte.transparency.procurement.enabled' => true,
            'horizonte.transparency.procurement.orgaos_siafi' => [
                ['codigo' => '26298', 'sigla' => 'FNDE', 'nome' => 'FNDE'],
            ],
            'horizonte.transparency.procurement.software_vendors_raw' => '12345678000199|Vendor A',
            'horizonte.transparency.procurement.max_pages_per_org' => 1,
            'horizonte.transparency.procurement.licitacoes_max_months' => 1,
        ]);

        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/contratos*' => Http::response([
                [
                    'id' => 501,
                    'numero' => '12/2025',
                    'objeto' => 'Licença de software educacional',
                    'situacaoContrato' => 'Vigente',
                    'modalidadeCompra' => 'Pregão',
                    'valorInicialCompra' => 150000.0,
                    'valorFinalCompra' => 160000.0,
                    'dataAssinatura' => '10/03/2025',
                    'fornecedor' => [
                        'cnpjFormatado' => '12.345.678/0001-99',
                        'nome' => 'VENDOR A LTDA',
                    ],
                    'unidadeGestora' => ['codigo' => '153173', 'nome' => 'FNDE'],
                ],
            ], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/licitacoes*' => Http::response([
                [
                    'id' => 77,
                    'valor' => 5000,
                    'situacaoCompra' => 'Publicada',
                    'modalidadeLicitacao' => 'Pregão Eletrônico',
                    'dataPublicacao' => '15/01/2025',
                    'licitacao' => ['numero' => '1/2025', 'objeto' => 'Aquisição TI'],
                    'municipio' => [
                        'codigoIBGE' => '2927408',
                        'nomeIBGE' => 'Salvador',
                        'uf' => ['sigla' => 'BA', 'nome' => 'Bahia'],
                    ],
                ],
            ], 200),
        ]);

        $snapshots = Mockery::mock(PortalProcurementSnapshotRepository::class);
        $snapshots->shouldReceive('upsertBatch')
            ->once()
            ->withArgs(function (array $rows): bool {
                $this->assertCount(2, $rows);
                $contrato = collect($rows)->firstWhere('tipo', 'contrato');
                $licitacao = collect($rows)->firstWhere('tipo', 'licitacao');
                $this->assertNotNull($contrato);
                $this->assertNotNull($licitacao);
                $this->assertSame('26298', $contrato['codigo_orgao']);
                $this->assertSame('12345678000199', $contrato['fornecedor_cnpj']);
                $this->assertTrue($contrato['vendor_matched']);
                $this->assertSame('Vendor A', $contrato['vendor_label']);
                $this->assertSame('2927408', $licitacao['ibge_municipio']);
                $this->assertSame('BA', $licitacao['uf']);

                return true;
            })
            ->andReturn(2);

        $service = new HorizontePortalProcurementSyncService(
            new PortalTransparenciaApiClient,
            $snapshots,
        );

        $result = $service->sync(['year' => 2025, 'tipos' => 'contratos,licitacoes', 'skip_vendors' => true]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['contratos_fetched']);
        $this->assertSame(1, $result['licitacoes_fetched']);
        $this->assertSame(2, $result['upserted']);
        $this->assertSame(1, $result['vendor_matched']);
    }

    #[Test]
    public function sync_enrich_por_cnpj_curado_e_itens_software(): void
    {
        config([
            'ieducar.other_funding.public_queries.portal_transparencia.enabled' => true,
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => 'token-teste',
            'horizonte.transparency.procurement.enabled' => true,
            'horizonte.transparency.procurement.orgaos_siafi' => [],
            'horizonte.transparency.procurement.software_vendors_raw' => '12345678000199|Vendor A',
            'horizonte.transparency.procurement.item_software_keywords' => ['software', 'sistema'],
            'horizonte.transparency.procurement.vendor_max_pages' => 1,
            'horizonte.transparency.procurement.itens_max_pages' => 1,
            'horizonte.transparency.procurement.itens_per_vendor_contract' => 5,
        ]);

        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/contratos/cpf-cnpj*' => Http::response([
                [
                    'id' => 900,
                    'numero' => '9/2025',
                    'objeto' => 'Serviços diversos',
                    'valorInicialCompra' => 10,
                    'fornecedor' => [
                        'cnpjFormatado' => '12.345.678/0001-99',
                        'nome' => 'Vendor A',
                    ],
                    'unidadeGestora' => [
                        'codigo' => '1',
                        'nome' => 'UG',
                        'orgaoVinculado' => [
                            'codigoSIAFI' => '26298',
                            'sigla' => 'FNDE',
                            'nome' => 'FNDE',
                        ],
                    ],
                ],
            ], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/contratos/itens-contratados*' => Http::response([
                [
                    'numero' => '1',
                    'descricao' => 'Licença de software educacional',
                    'quantidade' => 1,
                    'valor' => '10,00',
                ],
            ], 200),
        ]);

        $snapshots = Mockery::mock(PortalProcurementSnapshotRepository::class);
        $snapshots->shouldReceive('upsertBatch')
            ->once()
            ->withArgs(function (array $rows): bool {
                $this->assertCount(1, $rows);
                $this->assertTrue($rows[0]['vendor_matched']);
                $this->assertTrue($rows[0]['itens_software']);
                $this->assertSame('26298', $rows[0]['codigo_orgao']);
                $this->assertNotEmpty($rows[0]['itens']);

                return true;
            })
            ->andReturn(1);

        $service = new HorizontePortalProcurementSyncService(
            new PortalTransparenciaApiClient,
            $snapshots,
        );

        $result = $service->sync(['year' => 2025, 'skip_orgaos' => true]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['vendor_cnpj_fetched']);
        $this->assertSame(1, $result['itens_software']);
        $this->assertSame(1, $result['upserted']);
    }

    #[Test]
    public function sync_dry_run_nao_grava(): void
    {
        config([
            'ieducar.other_funding.public_queries.portal_transparencia.enabled' => true,
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => 'token-teste',
            'horizonte.transparency.procurement.enabled' => true,
            'horizonte.transparency.procurement.orgaos_siafi' => [
                ['codigo' => '26000', 'sigla' => 'MEC', 'nome' => 'MEC'],
            ],
            'horizonte.transparency.procurement.software_vendors_raw' => '',
            'horizonte.transparency.procurement.max_pages_per_org' => 1,
            'horizonte.transparency.procurement.licitacoes_max_months' => 1,
        ]);

        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/contratos*' => Http::response([
                ['id' => 1, 'numero' => '1', 'objeto' => 'x', 'valorInicialCompra' => 1],
            ], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/licitacoes*' => Http::response([], 200),
        ]);

        $snapshots = Mockery::mock(PortalProcurementSnapshotRepository::class);
        $snapshots->shouldNotReceive('upsertBatch');

        $service = new HorizontePortalProcurementSyncService(
            new PortalTransparenciaApiClient,
            $snapshots,
        );

        $result = $service->sync([
            'year' => 2025,
            'tipos' => 'contratos',
            'dry_run' => true,
            'skip_vendors' => true,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['upserted']);
        $this->assertSame(1, $result['contratos_fetched']);
        $this->assertStringContainsString('Dry-run', $result['message']);
    }

    #[Test]
    public function sync_skip_sem_api_key(): void
    {
        config([
            'ieducar.other_funding.public_queries.portal_transparencia.enabled' => true,
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => '',
            'horizonte.transparency.procurement.enabled' => true,
        ]);

        $snapshots = Mockery::mock(PortalProcurementSnapshotRepository::class);
        $snapshots->shouldNotReceive('upsertBatch');

        $service = new HorizontePortalProcurementSyncService(
            new PortalTransparenciaApiClient,
            $snapshots,
        );

        $result = $service->sync(['year' => 2025]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['skipped'] ?? false);
    }
}
