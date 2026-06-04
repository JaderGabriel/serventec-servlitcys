<?php

namespace Tests\Unit;

use App\Models\City;
use App\Repositories\CadunicoTerritorioSnapshotRepository;
use App\Services\Cadunico\CadunicoTerritorioCsvImportService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoTerritorioCsvImportServiceTest extends TestCase
{
    #[Test]
    public function importa_linhas_territoriais_do_csv(): void
    {
        $path = storage_path('app/test-cadunico-territorio.csv');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, implode("\n", [
            'territorio_codigo;territorio_nome;criancas_4_17;latitude;longitude;indice_vulnerabilidade',
            '001;Centro;100;-12.97;-38.50;30',
            '002;Suburbio;200;-12.99;-38.45;60',
        ]));

        $city = new City(['name' => 'Teste', 'ibge_municipio' => '2910800']);

        $repository = Mockery::mock(CadunicoTerritorioSnapshotRepository::class);
        $repository->shouldReceive('upsert')
            ->twice()
            ->with(
                '2910800',
                2024,
                Mockery::type('string'),
                Mockery::type('array'),
            );

        $result = (new CadunicoTerritorioCsvImportService($repository))->importFile($path, 2024, $city);

        @unlink($path);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['imported']);
    }
}
