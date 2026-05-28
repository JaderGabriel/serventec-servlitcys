<?php

namespace Tests\Unit;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Repositories\CadunicoMunicipioSnapshotRepository;
use App\Services\Cadunico\CadunicoCecadCsvImportService;
use App\Services\Cadunico\CadunicoOpenDataImportService;
use App\Support\Cadunico\CadunicoStoragePaths;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoOpenDataImportServiceTest extends TestCase
{
    #[Test]
    public function importa_a_partir_de_cache_json(): void
    {
        $ibge = '2910800';
        $ano = 2024;
        $path = CadunicoStoragePaths::apiCacheFile($ibge, $ano);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode([
            'codigo_ibge' => $ibge,
            'ano' => $ano,
            'criancas_4_5' => 100,
            'criancas_6_10' => 200,
            'criancas_11_14' => 150,
            'criancas_15_17' => 80,
        ], JSON_THROW_ON_ERROR));

        $repo = Mockery::mock(CadunicoMunicipioSnapshotRepository::class);
        $repo->shouldReceive('upsert')
            ->once()
            ->with($ibge, $ano, Mockery::type('array'))
            ->andReturn(new CadunicoMunicipioSnapshot);

        $service = new CadunicoOpenDataImportService($repo, new CadunicoCecadCsvImportService($repo));
        $result = $service->importForIbge($ibge, $ano);

        $this->assertTrue($result['success']);
        $this->assertSame('api_cache', $result['source']);

        @unlink($path);
    }

    #[Test]
    public function city_sem_ibge_falha(): void
    {
        $city = new City(['name' => 'Teste', 'ibge_municipio' => null]);
        $repo = Mockery::mock(CadunicoMunicipioSnapshotRepository::class);
        $service = new CadunicoOpenDataImportService($repo, new CadunicoCecadCsvImportService($repo));

        $result = $service->importForCity($city, 2024);

        $this->assertFalse($result['success']);
    }
}
