<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Services\Funding\MunicipalFundingPublicSnapshotService;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalFundingPublicSnapshotServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function build_inclui_fundeb_local_e_marca_portal_sem_chave(): void
    {
        Cache::flush();

        config([
            'ieducar.other_funding.public_queries.enabled' => true,
            'ieducar.other_funding.public_queries.cache_ttl_seconds' => 60,
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => '',
            'ieducar.fundeb.open_data.resource_id' => '',
        ]);

        $city = new City(['id' => 1, 'name' => 'Itamarí', 'uf' => 'BA', 'ibge_municipio' => '2911403']);

        $ref = new FundebMunicipioReference([
            'ibge_municipio' => '2911403',
            'ano' => 2025,
            'vaaf' => 5100.0,
            'fonte' => 'teste',
        ]);
        $ref->imported_at = now();

        $repo = Mockery::mock(FundebMunicipioReferenceRepository::class);
        $repo->shouldReceive('findForCityYear')->with($city, 2025)->andReturn($ref);

        Http::fake([
            '*' => Http::response(['success' => true, 'result' => ['records' => []]], 200),
        ]);

        $service = new MunicipalFundingPublicSnapshotService($repo, app(FundebOpenDataImportService::class));
        $payload = $service->build($city, new IeducarFilterState('2025', null, null, null));

        $this->assertTrue($payload['available']);
        $this->assertSame('2911403', $payload['ibge']);

        $ids = array_column($payload['queries'], 'id');
        $this->assertContains('fundeb_referencia', $ids);
        $this->assertContains('portal_transparencia', $ids);

        $portal = collect($payload['queries'])->firstWhere('id', 'portal_transparencia');
        $this->assertSame('skipped', $portal['status']);
    }

    #[Test]
    public function build_avisa_quando_falta_ibge(): void
    {
        $city = new City(['ibge_municipio' => null]);
        $service = new MunicipalFundingPublicSnapshotService(
            Mockery::mock(FundebMunicipioReferenceRepository::class),
            app(FundebOpenDataImportService::class),
        );

        $payload = $service->build($city, new IeducarFilterState('2025', null, null, null));

        $this->assertFalse($payload['available']);
        $this->assertStringContainsString('IBGE', (string) $payload['intro']);
    }
}
