<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\MunicipalEmendaSnapshot;
use App\Repositories\MunicipalEmendaSnapshotRepository;
use App\Services\Funding\MunicipalEmendaCatalogService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalEmendaCatalogServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function empty_state_quando_sem_snapshots(): void
    {
        $city = new City([
            'id' => 1,
            'name' => 'Itamari',
            'uf' => 'BA',
            'ibge_municipio' => '2915700',
        ]);

        $repo = Mockery::mock(MunicipalEmendaSnapshotRepository::class);
        $repo->shouldReceive('forCityYear')->once()->andReturn([]);

        $catalog = (new MunicipalEmendaCatalogService($repo))->build($city, 2025);

        $this->assertTrue($catalog['available']);
        $this->assertSame(0, $catalog['count']);
        $this->assertNotEmpty($catalog['empty_message']);
        $this->assertStringContainsString('funding:enrich-consultoria-emendas', (string) $catalog['enrich_hint']);
        $this->assertSame([], $catalog['rows']);
    }

    #[Test]
    public function cataloga_emendas_com_totais_e_documentos(): void
    {
        $city = new City([
            'id' => 1,
            'name' => 'Campestre',
            'uf' => 'MG',
            'ibge_municipio' => '3111005',
        ]);

        $snap = new MunicipalEmendaSnapshot([
            'codigo_emenda' => '202540290014',
            'numero_emenda' => '0014',
            'tipo_emenda' => 'Emenda Individual',
            'autor' => 'AUTOR',
            'localidade_do_gasto' => 'CAMPESTRE - MG',
            'funcao' => 'Educação',
            'subfuncao' => 'Educação básica',
            'valor_empenhado' => 60000,
            'valor_liquidado' => 0,
            'valor_pago' => 10000,
            'documentos' => [
                ['fase' => 'Empenho', 'data' => '28/11/2025', 'codigoDocumentoResumido' => '2025NE1'],
            ],
        ]);

        $repo = Mockery::mock(MunicipalEmendaSnapshotRepository::class);
        $repo->shouldReceive('forCityYear')->once()->andReturn([$snap]);

        $catalog = (new MunicipalEmendaCatalogService($repo))->build($city, 2025);

        $this->assertTrue($catalog['available']);
        $this->assertNull($catalog['empty_message']);
        $this->assertSame(1, $catalog['count']);
        $this->assertSame(60000.0, $catalog['total_empenhado']);
        $this->assertSame(10000.0, $catalog['total_pago']);
        $this->assertSame('AUTOR', $catalog['rows'][0]['autor']);
        $this->assertSame(1, $catalog['rows'][0]['documentos_count']);
        $this->assertSame('Empenho', $catalog['rows'][0]['documentos'][0]['fase']);
    }
}
