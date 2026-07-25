<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\Funding\TesouroTransferenciasCsvService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TesouroTransferenciasCsvServiceTest extends TestCase
{
    #[Test]
    public function parseia_csv_tesouro_e_soma_meses_do_ano(): void
    {
        $fixture = base_path('tests/Fixtures/tesouro-fundeb-snippet.csv');
        $csv = (string) file_get_contents($fixture);
        $service = new TesouroTransferenciasCsvService();
        $index = $service->parseCsvBody($csv);

        $key = 'formosa do rio preto|BA';
        $this->assertArrayHasKey($key, $index['by_nome_uf']);
        $this->assertEqualsWithDelta(78000.0, $index['by_nome_uf'][$key]['annual'][2025], 0.01);
        $this->assertSame(12, $index['by_nome_uf'][$key]['months_counted'][2025]);

        $fromFile = $service->parseCsvFile($fixture);
        $this->assertSame($index['by_nome_uf'][$key]['annual'][2025], $fromFile['by_nome_uf'][$key]['annual'][2025]);
    }

    #[Test]
    public function usa_csv_local_quando_configurado(): void
    {
        $fixture = base_path('tests/Fixtures/tesouro-fundeb-snippet.csv');
        $localDir = storage_path('app/funding/tesouro-csv');
        if (! is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }
        $localCopy = $localDir.'/fundeb-por-municipio.csv';
        copy($fixture, $localCopy);

        $resourceId = 'test-fundeb-local-only';
        @unlink(storage_path('app/funding/tesouro-csv/'.$resourceId.'.json'));

        config([
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_resources' => [
                'fundeb' => [
                    'resource_id' => $resourceId,
                    'programa_id' => 'fundeb',
                    'name' => 'FUNDEB local',
                    'url' => 'https://blocked.example.test/fundeb.csv',
                    'local_path' => 'funding/tesouro-csv/fundeb-por-municipio.csv',
                ],
            ],
        ]);

        Http::fake([
            'blocked.example.test/*' => Http::response('', 503),
        ]);

        $city = new City([
            'name' => 'Formosa do Rio Preto',
            'uf' => 'BA',
            'ibge_municipio' => '2911105',
        ]);

        $service = new TesouroTransferenciasCsvService();
        $rows = $service->fetchRowsForCityYear($city, 2025, 10);

        $this->assertCount(1, $rows);
        $this->assertSame('local', $service->lastIndexLoadMeta()['source'] ?? null);

        @unlink($localCopy);
        @unlink(storage_path('app/funding/tesouro-csv/'.$resourceId.'.json'));
    }

    #[Test]
    public function fetch_rows_para_cidade_por_nome_e_uf(): void
    {
        config([
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
                ['Content-Type' => 'text/csv'],
            ),
        ]);

        $city = new City([
            'name' => 'Formosa do Rio Preto',
            'uf' => 'BA',
            'ibge_municipio' => '2911105',
        ]);

        $service = new TesouroTransferenciasCsvService();
        $rows = $service->fetchRowsForCityYear($city, 2025, 10);

        $this->assertCount(1, $rows);
        $this->assertSame('2911105', $rows[0]['ibge_municipio']);
        $this->assertSame('fundeb', $rows[0]['programa_id']);
        $this->assertSame('tesouro_csv', $rows[0]['fonte']);
        $this->assertEqualsWithDelta(78000.0, $rows[0]['valor'], 0.01);
        $this->assertSame('3521', $rows[0]['meta']['cod_mun']);
    }

    #[Test]
    public function resolve_mensal_prefere_indice_vivo_sobre_meta_desactualizado(): void
    {
        $fixture = base_path('tests/Fixtures/tesouro-fundeb-snippet.csv');
        $localDir = storage_path('app/funding/tesouro-csv');
        if (! is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }
        $localCopy = $localDir.'/fundeb-merge-test.csv';
        copy($fixture, $localCopy);

        $resourceId = 'test-fundeb-merge';
        @unlink(storage_path('app/funding/tesouro-csv/'.$resourceId.'.json'));

        config([
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_resources' => [
                'fundeb' => [
                    'resource_id' => $resourceId,
                    'programa_id' => 'fundeb',
                    'name' => 'FUNDEB merge',
                    'url' => 'https://blocked.example.test/fundeb.csv',
                    'local_path' => 'funding/tesouro-csv/fundeb-merge-test.csv',
                ],
            ],
        ]);

        Http::fake([
            'blocked.example.test/*' => Http::response('', 503),
        ]);

        $service = new TesouroTransferenciasCsvService();
        $mensal = $service->resolveMensalForSnapshotMeta([
            'resource_id' => $resourceId,
            'cod_mun' => '3521',
            'municipio' => 'Formosa do Rio Preto',
            'uf' => 'BA',
            // Meta antigo sem junho — o índice CKAN da fixture tem 12 meses.
            'mensal' => [1 => 1000.0, 2 => 1000.0, 3 => 1000.0, 4 => 1000.0, 5 => 1000.0],
        ], 2025, 10);

        $this->assertArrayHasKey(6, $mensal);
        $this->assertGreaterThan(5, count($mensal));

        @unlink($localCopy);
        @unlink(storage_path('app/funding/tesouro-csv/'.$resourceId.'.json'));
    }
}
