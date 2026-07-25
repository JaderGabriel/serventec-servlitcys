<?php

namespace Tests\Unit;

use App\Models\City;
use App\Repositories\MunicipalEmendaSnapshotRepository;
use App\Services\Funding\MunicipalEmendaImportService;
use App\Support\Funding\PortalTransparenciaApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalEmendaImportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function importa_emendas_filtradas_por_localidade_e_grava_snapshot(): void
    {
        Cache::flush();

        config([
            'ieducar.other_funding.public_queries.portal_transparencia.enabled' => true,
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => 'token-teste',
            'ieducar.other_funding.public_queries.portal_transparencia.emendas_max_pages' => 1,
            'ieducar.other_funding.public_queries.portal_transparencia.emendas_fetch_documentos' => true,
            'ieducar.other_funding.public_queries.portal_transparencia.emendas_documentos_max_pages' => 1,
        ]);

        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/emendas/documentos/*' => Http::response([
                [
                    'id' => 9,
                    'fase' => 'Empenho',
                    'codigoDocumento' => '2025NE1',
                ],
            ], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/emendas*' => Http::response([
                [
                    'codigoEmenda' => '202540290014',
                    'ano' => 2025,
                    'tipoEmenda' => 'Emenda Individual',
                    'autor' => 'AUTOR TESTE',
                    'numeroEmenda' => '0014',
                    'localidadeDoGasto' => 'CAMPESTRE - MG',
                    'funcao' => 'Educação',
                    'subfuncao' => 'Educação básica',
                    'valorEmpenhado' => '60.000,00',
                    'valorLiquidado' => '0,00',
                    'valorPago' => '0,00',
                    'valorRestoInscrito' => '60.000,00',
                    'valorRestoCancelado' => '0,00',
                    'valorRestoPago' => '0,00',
                ],
                [
                    'codigoEmenda' => '202599999999',
                    'ano' => 2025,
                    'localidadeDoGasto' => 'MINAS GERAIS (UF)',
                    'funcao' => 'Educação',
                    'valorEmpenhado' => '1.000,00',
                ],
            ], 200),
        ]);

        $city = new City([
            'id' => 7,
            'name' => 'Campestre',
            'uf' => 'MG',
            'ibge_municipio' => '3111005',
        ]);

        $snapshots = Mockery::mock(MunicipalEmendaSnapshotRepository::class);
        $snapshots->shouldReceive('upsertBatch')
            ->once()
            ->withArgs(function (?City $c, array $rows) use ($city): bool {
                $this->assertSame($city->id, $c?->id);
                $this->assertCount(1, $rows);
                $this->assertSame('202540290014', $rows[0]['codigo_emenda']);
                $this->assertSame(60000.0, $rows[0]['valor_empenhado']);
                $this->assertSame('Empenho', $rows[0]['documentos'][0]['fase'] ?? null);

                return true;
            })
            ->andReturn(1);

        $service = new MunicipalEmendaImportService(
            new PortalTransparenciaApiClient,
            $snapshots,
        );

        $result = $service->importForCities(collect([$city]), 2025, false);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['matched_rows']);
        $this->assertSame(1, $result['upserted']);
        $this->assertSame(1, $result['documentos_fetched']);
    }

    #[Test]
    public function dry_run_nao_grava_nem_pede_documentos(): void
    {
        Cache::flush();

        config([
            'ieducar.other_funding.public_queries.portal_transparencia.enabled' => true,
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => 'token-teste',
            'ieducar.other_funding.public_queries.portal_transparencia.emendas_max_pages' => 1,
            'ieducar.other_funding.public_queries.portal_transparencia.emendas_fetch_documentos' => true,
        ]);

        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/emendas*' => Http::response([
                [
                    'codigoEmenda' => '1',
                    'ano' => 2025,
                    'localidadeDoGasto' => 'CAMPESTRE - MG',
                    'valorEmpenhado' => '10,00',
                ],
            ], 200),
        ]);

        $city = new City([
            'id' => 99,
            'name' => 'Campestre',
            'uf' => 'MG',
            'ibge_municipio' => '3111005',
        ]);

        $snapshots = Mockery::mock(MunicipalEmendaSnapshotRepository::class);
        $snapshots->shouldNotReceive('upsertBatch');

        $service = new MunicipalEmendaImportService(
            new PortalTransparenciaApiClient,
            $snapshots,
        );

        $result = $service->importForCities(collect([$city]), 2025, true);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['matched_rows']);
        $this->assertSame(0, $result['upserted']);
        $this->assertSame(0, $result['documentos_fetched']);
        Http::assertSentCount(1);
    }
}
