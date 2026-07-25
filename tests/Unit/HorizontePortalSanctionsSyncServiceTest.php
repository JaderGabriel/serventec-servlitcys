<?php

namespace Tests\Unit;

use App\Models\PortalVendorSanctionSnapshot;
use App\Repositories\PortalVendorSanctionSnapshotRepository;
use App\Services\Horizonte\HorizontePortalSanctionsSyncService;
use App\Support\Funding\PortalTransparenciaApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizontePortalSanctionsSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sync_persists_ceis_and_cepim_for_curated_cnpj(): void
    {
        config([
            'horizonte.transparency.procurement.enabled' => true,
            'horizonte.transparency.procurement.software_vendors_raw' => '12345678000199|Vendor A',
            'horizonte.transparency.procurement.sanctions_max_pages' => 1,
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => 'token-teste',
        ]);

        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/ceis*' => Http::response([
                [
                    'id' => 11,
                    'dataInicioSancao' => '2024-01-01',
                    'tipoSancao' => ['descricaoResumida' => 'Suspensão'],
                    'orgaoSancionador' => ['nome' => 'CGU'],
                    'sancionado' => ['nome' => 'Vendor A Ltda'],
                ],
            ], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/cnep*' => Http::response([], 200),
            'api.portaldatransparencia.gov.br/api-de-dados/cepim*' => Http::response([
                [
                    'id' => 22,
                    'motivo' => 'Impedimento',
                    'orgaoSuperior' => ['nome' => 'MEC'],
                    'pessoaJuridica' => ['nome' => 'Vendor A'],
                ],
            ], 200),
        ]);

        $service = new HorizontePortalSanctionsSyncService(
            new PortalTransparenciaApiClient,
            new PortalVendorSanctionSnapshotRepository,
        );

        $result = $service->sync();

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['cnpjs_checked']);
        $this->assertSame(1, $result['sanctioned_cnpjs']);
        $this->assertSame(1, $result['by_fonte']['ceis']);
        $this->assertSame(1, $result['by_fonte']['cepim']);
        $this->assertSame(2, PortalVendorSanctionSnapshot::query()->count());

        $summary = (new PortalVendorSanctionSnapshotRepository)->summaryForCnpjs(['12345678000199']);
        $this->assertSame(1, $summary['sanctioned_cnpjs']);
        $this->assertSame(2, $summary['records']);
    }

    #[Test]
    public function sync_skips_without_api_key(): void
    {
        config([
            'horizonte.transparency.procurement.enabled' => true,
            'horizonte.transparency.procurement.software_vendors_raw' => '12345678000199|Vendor A',
            'ieducar.other_funding.public_queries.portal_transparencia.api_key' => '',
        ]);

        $service = new HorizontePortalSanctionsSyncService(
            new PortalTransparenciaApiClient,
            new PortalVendorSanctionSnapshotRepository,
        );

        $result = $service->sync();
        $this->assertTrue($result['skipped'] ?? false);
    }
}
