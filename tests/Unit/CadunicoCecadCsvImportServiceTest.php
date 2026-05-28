<?php

namespace Tests\Unit;

use App\Repositories\CadunicoMunicipioSnapshotRepository;
use App\Services\Cadunico\CadunicoCecadCsvImportService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoCecadCsvImportServiceTest extends TestCase
{
    #[Test]
    public function importa_csv_com_colunas_padrao(): void
    {
        $path = storage_path('app/test-cadunico-import.csv');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $csv = "codigo_ibge;ano;criancas_4_5;criancas_6_10;criancas_11_14;criancas_15_17\n";
        $csv .= "2910800;2024;100;200;150;80\n";
        file_put_contents($path, $csv);

        $repository = Mockery::mock(CadunicoMunicipioSnapshotRepository::class);
        $repository->shouldReceive('upsert')
            ->once()
            ->with(
                '2910800',
                2024,
                Mockery::on(static fn (array $data): bool => ($data['criancas_6_10'] ?? null) === 200
                    && ($data['populacao_escolar_estimada'] ?? null) === 530),
            );

        $result = (new CadunicoCecadCsvImportService($repository))->importFile($path, 2024);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);

        @unlink($path);
    }
}
