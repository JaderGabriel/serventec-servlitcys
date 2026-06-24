<?php

namespace Tests\Unit;

use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Services\Horizonte\HorizonteTesouroRepassesSyncService;
use App\Support\Horizonte\HorizonteTesouroRepassesSyncProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteTesouroRepassesSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function comando_dedicado_importa_apenas_ano_vigente_por_predefinicao(): void
    {
        Cache::flush();
        HorizonteTesouroRepassesSyncProgress::reset([2026]);

        $this->mock(MunicipalTransferSnapshotRepository::class, function ($mock): void {
            $mock->shouldReceive('upsertBatch')
                ->once()
                ->with(null, Mockery::on(static fn (array $rows): bool => ($rows[0]['ano'] ?? 0) === 2026))
                ->andReturn(1);
        });

        config([
            'horizonte.reference_year' => 2025,
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_enabled' => true,
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_resources' => [
                'fundeb' => [
                    'resource_id' => 'test-repasses-cmd',
                    'programa_id' => 'fundeb',
                    'name' => 'FUNDEB cmd',
                    'url' => 'https://example.test/fundeb-cmd.csv',
                ],
            ],
        ]);

        $csv = (string) file_get_contents(base_path('tests/Fixtures/tesouro-fundeb-dual-year-snippet.csv'));

        Http::fake([
            'example.test/fundeb-cmd.csv' => Http::response($csv, 200, ['Content-Type' => 'text/csv']),
            'servicodados.ibge.gov.br/api/v1/localidades/estados/*/municipios' => Http::response([
                ['id' => 2911105, 'nome' => 'Formosa do Rio Preto'],
            ], 200),
        ]);

        $this->travelTo('2026-06-15');

        $result = app(HorizonteTesouroRepassesSyncService::class)->run([
            'uf' => 'BA',
            'ufs_per_step' => 1,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame([2026], $result['years']);
        $this->assertSame(1, $result['imported']);
    }
}
