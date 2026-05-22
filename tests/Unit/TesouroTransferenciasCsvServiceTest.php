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
        $csv = (string) file_get_contents(base_path('tests/Fixtures/tesouro-fundeb-snippet.csv'));
        $service = new TesouroTransferenciasCsvService();
        $index = $service->parseCsvBody($csv);

        $key = 'formosa do rio preto|BA';
        $this->assertArrayHasKey($key, $index['by_nome_uf']);
        $this->assertEqualsWithDelta(78000.0, $index['by_nome_uf'][$key]['annual'][2025], 0.01);
        $this->assertSame(12, $index['by_nome_uf'][$key]['months_counted'][2025]);
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
}
