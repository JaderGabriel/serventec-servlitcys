<?php

namespace Tests\Unit;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Services\Funding\MunicipalTransferImportService;
use App\Services\Funding\TesouroTransferenciasCsvService;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalTransferImportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function importa_repasses_fundeb_via_csv_tesouro(): void
    {
        config([
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => '',
            'ieducar.funding.transfers.historical_years' => 1,
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_resources' => [
                'fundeb' => [
                    'resource_id' => 'test-fundeb',
                    'programa_id' => 'fundeb',
                    'name' => 'FUNDEB test',
                    'url' => 'https://example.test/fundeb.csv',
                ],
            ],
        ]);

        Http::fake([
            'example.test/fundeb.csv' => Http::response(
                (string) file_get_contents(base_path('tests/Fixtures/tesouro-fundeb-snippet.csv')),
                200,
            ),
            'tesourotransparente.gov.br/*' => Http::response(['success' => false], 400),
        ]);

        $city = new City([
            'id' => 99,
            'name' => 'Formosa do Rio Preto',
            'uf' => 'BA',
            'ibge_municipio' => '2911105',
        ]);

        $snapshots = Mockery::mock(MunicipalTransferSnapshotRepository::class);
        $snapshots->shouldReceive('upsertBatch')
            ->once()
            ->withArgs(function (?City $c, array $rows) use ($city): bool {
                $this->assertSame($city->ibge_municipio, $c?->ibge_municipio);
                $this->assertCount(1, $rows);
                $this->assertSame('fundeb', $rows[0]['programa_id']);
                $this->assertSame('tesouro_csv', $rows[0]['fonte']);

                return true;
            })
            ->andReturn(1);

        $service = new MunicipalTransferImportService(
            $snapshots,
            new TesouroTransferenciasCsvService(),
        );

        $result = $service->importForCityYear($city, 2025);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['rows']);
        $this->assertArrayHasKey('tesouro_csv', $result['by_fonte']);
    }
}
