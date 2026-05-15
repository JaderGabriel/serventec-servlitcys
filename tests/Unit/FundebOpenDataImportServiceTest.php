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
    public function years_for_new_city_sync_usa_vigente_e_anterior(): void
    {
        $years = FundebOpenDataImportService::yearsForNewCitySync();
        $vigente = FundebOpenDataImportService::suggestedImportYear();

        $this->assertSame([$vigente, $vigente - 1], $years);
    }

    #[Test]
    public function configured_sync_years_usa_intervalo_quando_lista_vazia(): void
    {
        config([
            'ieducar.fundeb.open_data.sync_years' => [],
            'ieducar.fundeb.open_data.sync_from_year' => 2022,
            'ieducar.fundeb.open_data.sync_to_year' => 2024,
        ]);
        $years = FundebOpenDataImportService::configuredSyncYears();

        $this->assertSame([2024, 2023, 2022], $years);
    }

    #[Test]
    public function configured_sync_years_respeita_lista_explicita(): void
    {
        config(['ieducar.fundeb.open_data.sync_years' => [2020, 2024, 2025]]);
        $years = FundebOpenDataImportService::configuredSyncYears();

        $this->assertSame([2025, 2024, 2020], $years);
    }

    #[Test]
    public function years_in_range_gera_lista_descendente(): void
    {
        $years = FundebOpenDataImportService::yearsInRange(2019, 2021);

        $this->assertSame([2021, 2020, 2019], $years);
    }

    #[Test]
    public function resolve_sync_years_unifica_config_e_cache(): void
    {
        $ibge = '2999999';
        $cacheDir = storage_path('app/fundeb/api/'.$ibge);
        @mkdir($cacheDir, 0755, true);
        file_put_contents($cacheDir.'/2018.json', '[]');

        config([
            'ieducar.fundeb.open_data.sync_years' => [2024],
            'ieducar.fundeb.open_data.sync_include_cached_years' => true,
            'ieducar.fundeb.open_data.sync_include_database_years' => false,
            'ieducar.fundeb.open_data.national_floor.enabled' => false,
            'ieducar.fundeb.open_data.json_url' => 'storage://app/fundeb/api/{ibge}/{ano}.json',
        ]);

        $repo = Mockery::mock(FundebMunicipioReferenceRepository::class);
        $service = new FundebOpenDataImportService($repo);
        $years = $service->resolveSyncYears();

        $this->assertContains(2024, $years);
        $this->assertContains(2018, $years);

        @unlink($cacheDir.'/2018.json');
        @rmdir($cacheDir);
    }

    #[Test]
    public function usa_piso_nacional_quando_api_indisponivel(): void
    {
        config([
            'ieducar.fundeb.open_data.json_url' => '',
            'ieducar.fundeb.open_data.cache_path' => '',
            'ieducar.fundeb.open_data.resource_id' => '',
            'ieducar.fundeb.open_data.national_floor.enabled' => true,
            'ieducar.discrepancies.vaa_referencia_anual' => 5559.73,
        ]);

        $city = new City(['id' => 4, 'name' => 'Novo', 'ibge_municipio' => '3550308']);
        $saved = new FundebMunicipioReference([
            'ibge_municipio' => '3550308',
            'ano' => 2024,
            'vaaf' => 5559.73,
            'fonte' => 'referencia_nacional_config',
        ]);
        $saved->id = 103;

        $repo = Mockery::mock(FundebMunicipioReferenceRepository::class);
        $repo->shouldReceive('upsert')
            ->once()
            ->with($city, 2024, Mockery::on(static fn (array $d) => $d['vaaf'] === 5559.73 && $d['fonte'] === 'referencia_nacional_config'))
            ->andReturn($saved);

        $service = new FundebOpenDataImportService($repo);
        $result = $service->importForCityYear($city, 2024, false);

        $this->assertTrue($result['success']);
    }

    #[Test]
    public function import_for_city_regista_logs_de_andamento(): void
    {
        config([
            'ieducar.fundeb.open_data.json_url' => 'https://api.example/fundeb/{ibge}/{ano}',
            'ieducar.fundeb.open_data.resource_id' => '',
        ]);

        Http::fake(['api.example/*' => Http::response([
            ['codigo_ibge' => '2910800', 'ano' => 2024, 'vaaf' => 5100.0],
        ], 200)]);

        $city = new City(['id' => 1, 'name' => 'Teste', 'ibge_municipio' => '2910800']);
        $saved = new FundebMunicipioReference(['ibge_municipio' => '2910800', 'ano' => 2024, 'vaaf' => 5100.0, 'fonte' => 'api']);
        $saved->id = 1;

        $repo = Mockery::mock(FundebMunicipioReferenceRepository::class);
        $repo->shouldReceive('upsert')->once()->andReturn($saved);

        $progress = new \App\Services\Fundeb\FundebImportProgress();
        $service = new FundebOpenDataImportService($repo);
        $service->importForCityYear($city, 2024, false, $progress);

        $this->assertGreaterThanOrEqual(2, count($progress->entries()));
        $messages = implode(' ', array_column($progress->entries(), 'message'));
        $this->assertStringContainsString('Teste', $messages);
        $this->assertStringContainsString('✓', $messages);
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
