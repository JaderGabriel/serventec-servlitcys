<?php

namespace Tests\Unit;

use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Services\Horizonte\HorizonteTesouroTransferSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteTesouroTransferSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function repasses_importa_fundeb_quando_ano_tem_linhas_no_csv(): void
    {
        Cache::flush();

        $this->mock(MunicipalTransferSnapshotRepository::class, function ($mock): void {
            $mock->shouldReceive('upsertBatch')
                ->once()
                ->with(null, Mockery::type('array'))
                ->andReturn(1);
        });

        config([
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_enabled' => true,
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_resources' => [
                'fundeb' => [
                    'resource_id' => 'test-fundeb-snippet',
                    'programa_id' => 'fundeb',
                    'name' => 'FUNDEB test',
                    'url' => 'https://example.test/fundeb-snippet.csv',
                ],
            ],
        ]);

        $csv = (string) file_get_contents(base_path('tests/Fixtures/tesouro-fundeb-snippet.csv'));

        Http::fake([
            'example.test/fundeb-snippet.csv' => Http::response($csv, 200, ['Content-Type' => 'text/csv']),
            'servicodados.ibge.gov.br/api/v1/localidades/estados/*/municipios' => Http::response([
                ['id' => 2911105, 'nome' => 'Formosa do Rio Preto'],
            ], 200),
        ]);

        $result = app(HorizonteTesouroTransferSyncService::class)->syncNationalFundeb(2025);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['imported']);
        $this->assertStringContainsString('2025', (string) $result['message']);
    }

    #[Test]
    public function repasses_importa_com_uf_vazia_como_no_feed_nacional(): void
    {
        Cache::flush();

        $this->mock(MunicipalTransferSnapshotRepository::class, function ($mock): void {
            $mock->shouldReceive('upsertBatch')
                ->once()
                ->with(null, Mockery::type('array'))
                ->andReturn(1);
        });

        config([
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_enabled' => true,
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_resources' => [
                'fundeb' => [
                    'resource_id' => 'test-fundeb-empty-uf',
                    'programa_id' => 'fundeb',
                    'name' => 'FUNDEB test',
                    'url' => 'https://example.test/fundeb-empty-uf.csv',
                ],
            ],
        ]);

        $csv = (string) file_get_contents(base_path('tests/Fixtures/tesouro-fundeb-snippet.csv'));

        Http::fake([
            'example.test/fundeb-empty-uf.csv' => Http::response($csv, 200, ['Content-Type' => 'text/csv']),
            'servicodados.ibge.gov.br/api/v1/localidades/estados/*/municipios' => Http::response([
                ['id' => 2911105, 'nome' => 'Formosa do Rio Preto'],
            ], 200),
        ]);

        $result = app(HorizonteTesouroTransferSyncService::class)->syncNationalFundeb(2025, ['uf' => '']);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['imported']);
    }

    #[Test]
    public function repasses_falha_quando_nenhum_ano_tem_linhas(): void
    {
        Cache::flush();

        @unlink(storage_path('app/funding/tesouro-csv/test-fundeb-no-ibge.json'));
        @unlink(storage_path('app/funding/tesouro-csv/cod_mun_to_ibge.json'));

        $this->mock(MunicipalTransferSnapshotRepository::class, function ($mock): void {
            $mock->shouldNotReceive('upsertBatch');
        });

        config([
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_enabled' => true,
            'ieducar.other_funding.public_queries.tesouro_ckan.csv_resources' => [
                'fundeb' => [
                    'resource_id' => 'test-fundeb-no-cross',
                    'programa_id' => 'fundeb',
                    'name' => 'FUNDEB sem cruzamento',
                    'url' => 'https://example.test/fundeb-no-cross.csv',
                ],
            ],
        ]);

        $csv = "COD_MUN;Município;UF;;Mês;2025\n99999;Municipio Inexistente;ZZ;;1;5000\n";

        Http::fake([
            'example.test/fundeb-no-cross.csv' => Http::response($csv, 200, ['Content-Type' => 'text/csv']),
        ]);

        $result = app(HorizonteTesouroTransferSyncService::class)->syncNationalFundeb(2025);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('2025', (string) $result['message']);
        $this->assertStringContainsString('2024', (string) $result['message']);
    }
}
