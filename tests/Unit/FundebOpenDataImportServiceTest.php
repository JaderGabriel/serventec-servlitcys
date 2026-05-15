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
