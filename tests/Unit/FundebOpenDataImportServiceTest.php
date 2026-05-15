<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebOpenDataImportService;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebOpenDataImportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function importa_via_json_url_e_chama_upsert(): void
    {
        config([
            'ieducar.fundeb.open_data.json_url' => 'https://api.example/fundeb/{ibge}/{ano}',
            'ieducar.fundeb.open_data.resource_id' => '',
        ]);

        Http::fake([
            'api.example/*' => Http::response([
                ['codigo_ibge' => '2910800', 'ano' => 2024, 'vaaf' => 5120.50, 'vaat' => 4800],
            ], 200),
        ]);

        $city = new City(['id' => 1, 'name' => 'Teste', 'ibge_municipio' => '2910800']);
        $saved = new FundebMunicipioReference([
            'ibge_municipio' => '2910800',
            'ano' => 2024,
            'vaaf' => 5120.50,
            'vaat' => 4800,
            'fonte' => 'api_ckan_fnde',
        ]);
        $saved->id = 99;

        $repo = Mockery::mock(FundebMunicipioReferenceRepository::class);
        $repo->shouldReceive('upsert')
            ->once()
            ->with($city, 2024, Mockery::on(static fn (array $d) => $d['vaaf'] === 5120.50 && $d['vaat'] === 4800.0))
            ->andReturn($saved);

        $service = new FundebOpenDataImportService($repo);
        $result = $service->importForCityYear($city, 2024);

        $this->assertTrue($result['success']);
    }

    #[Test]
    public function importa_ano_anterior_quando_vigente_nao_existe_na_api(): void
    {
        config([
            'ieducar.fundeb.open_data.json_url' => 'https://api.example/fundeb/{ibge}/{ano}',
            'ieducar.fundeb.open_data.resource_id' => '',
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/2024')) {
                return Http::response([], 404);
            }
            if (str_contains($request->url(), '/2023')) {
                return Http::response([
                    ['codigo_ibge' => '2910800', 'ano' => 2023, 'vaaf' => 5050.0],
                ], 200);
            }

            return Http::response([], 404);
        });

        $city = new City(['id' => 1, 'name' => 'Teste', 'ibge_municipio' => '2910800']);
        $saved = new FundebMunicipioReference([
            'ibge_municipio' => '2910800',
            'ano' => 2023,
            'vaaf' => 5050.0,
            'fonte' => 'api_ckan_fnde',
        ]);
        $saved->id = 100;

        $repo = Mockery::mock(FundebMunicipioReferenceRepository::class);
        $repo->shouldReceive('upsert')
            ->once()
            ->with($city, 2023, Mockery::on(static fn (array $d) => $d['vaaf'] === 5050.0))
            ->andReturn($saved);

        $service = new FundebOpenDataImportService($repo);
        $result = $service->importForCityYear($city, 2024);

        $this->assertTrue($result['success']);
        $this->assertSame(2023, $result['imported_ano']);
    }

    #[Test]
    public function busca_ckan_e_grava_cache_quando_json_local_ausente(): void
    {
        $ibge = '2913309';
        $cacheRel = 'fundeb/api/'.$ibge.'/2024.json';
        $cacheAbs = storage_path('app/'.$cacheRel);
        if (is_file($cacheAbs)) {
            unlink($cacheAbs);
        }
        @mkdir(dirname($cacheAbs), 0755, true);

        config([
            'ieducar.fundeb.open_data.json_url' => 'storage://app/fundeb/api/{ibge}/{ano}.json',
            'ieducar.fundeb.open_data.cache_path' => '',
            'ieducar.fundeb.open_data.resource_id' => 'test-resource',
            'ieducar.fundeb.open_data.ckan_base_url' => 'https://ckan.test',
        ]);

        Http::fake([
            'ckan.test/*' => Http::response([
                'success' => true,
                'result' => [
                    'records' => [
                        ['codigo_ibge' => $ibge, 'ano' => 2024, 'vaaf' => 5200.0, 'vaat' => 8100.0],
                    ],
                ],
            ], 200),
        ]);

        $city = new City(['id' => 3, 'name' => 'Cidade Modelo', 'ibge_municipio' => $ibge]);
        $saved = new FundebMunicipioReference([
            'ibge_municipio' => $ibge,
            'ano' => 2024,
            'vaaf' => 5200.0,
            'fonte' => 'api_ckan_fnde',
        ]);
        $saved->id = 101;

        $repo = Mockery::mock(FundebMunicipioReferenceRepository::class);
        $repo->shouldReceive('upsert')->once()->andReturn($saved);

        $service = new FundebOpenDataImportService($repo);
        $result = $service->importForCityYear($city, 2024);

        $this->assertTrue($result['success']);
        $this->assertFileExists($cacheAbs);
        $cached = json_decode((string) file_get_contents($cacheAbs), true);
        $this->assertIsArray($cached);
        $this->assertSame($ibge, (string) ($cached[0]['codigo_ibge'] ?? ''));

        if (is_file($cacheAbs)) {
            unlink($cacheAbs);
        }
    }

    #[Test]
    public function falha_sem_ibge_na_cidade(): void
    {
        $city = new City(['id' => 2, 'name' => 'Sem IBGE', 'ibge_municipio' => null]);
        $repo = Mockery::mock(FundebMunicipioReferenceRepository::class);
        $repo->shouldNotReceive('upsert');

        $service = new FundebOpenDataImportService($repo);
        $result = $service->importForCityYear($city, 2024);

        $this->assertFalse($result['success']);
    }
}
