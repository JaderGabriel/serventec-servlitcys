<?php

namespace Tests\Unit;

use App\Models\MunicipalBenefitSnapshot;
use App\Repositories\MunicipalBenefitSnapshotRepository;
use App\Services\Cadunico\CadunicoBeneficiosEscolarizacaoCalloutBuilder;
use App\Services\Cadunico\CadunicoPortalBeneficiosSyncService;
use App\Support\Funding\PortalTransparenciaApiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoPortalBeneficiosCun04Test extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function aggregate_beneficio_por_municipio_soma_quantidade_e_valor(): void
    {
        $agg = PortalTransparenciaApiClient::aggregateBeneficioPorMunicipio([
            [
                'quantidadeBeneficiados' => 100,
                'valor' => '1.500,50',
                'dataReferencia' => '01/06/2025',
                'tipo' => ['descricao' => 'Novo Bolsa Família'],
            ],
            [
                'quantidadeBeneficiados' => 20,
                'valor' => 200.5,
            ],
        ]);

        $this->assertSame(120, $agg['quantidade']);
        $this->assertSame(1701.0, $agg['valor']);
        $this->assertSame('01/06/2025', $agg['data_referencia']);
        $this->assertSame('Novo Bolsa Família', $agg['tipo_descricao']);
    }

    #[Test]
    public function mes_ano_window_retorna_competencias_anteriores(): void
    {
        $window = PortalTransparenciaApiClient::mesAnoWindow(3, new \DateTimeImmutable('2025-07-15'));

        $this->assertSame([202507, 202506, 202505], $window);
    }

    #[Test]
    public function client_consulta_novo_bolsa_familia_por_ibge(): void
    {
        Http::fake([
            'api.portaldatransparencia.gov.br/api-de-dados/novo-bolsa-familia-por-municipio*' => Http::response([
                [
                    'id' => 1,
                    'quantidadeBeneficiados' => 850,
                    'valor' => 412345.67,
                    'dataReferencia' => '01/05/2025',
                    'tipo' => ['descricao' => 'Novo Bolsa Família'],
                ],
            ], 200),
        ]);

        $client = new PortalTransparenciaApiClient;
        $items = $client->novoBolsaFamiliaPorMunicipio('2910800', 202505, 'token-teste', 10, 1);

        $this->assertCount(1, $items);
        $this->assertSame(850, $items[0]['quantidadeBeneficiados']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'novo-bolsa-familia-por-municipio')
                && ($request['codigoIbge'] ?? null) === '2910800'
                && (int) ($request['mesAno'] ?? 0) === 202505;
        });
    }

    #[Test]
    public function callouts_montam_pressao_social_com_fora_escola(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('municipal_benefit_snapshots')
            ->andReturn(true);

        $nbf = new MunicipalBenefitSnapshot([
            'programa' => MunicipalBenefitSnapshot::PROGRAMA_NBF,
            'mes_ano' => 202505,
            'quantidade_beneficiados' => 900,
            'valor' => 450000.0,
        ]);
        $bpc = new MunicipalBenefitSnapshot([
            'programa' => MunicipalBenefitSnapshot::PROGRAMA_BPC,
            'mes_ano' => 202505,
            'quantidade_beneficiados' => 120,
            'valor' => 80000.0,
        ]);

        $repo = Mockery::mock(MunicipalBenefitSnapshotRepository::class);
        $repo->shouldReceive('latestByPrograma')
            ->once()
            ->with('2910800')
            ->andReturn([
                MunicipalBenefitSnapshot::PROGRAMA_NBF => $nbf,
                MunicipalBenefitSnapshot::PROGRAMA_BPC => $bpc,
            ]);

        $block = (new CadunicoBeneficiosEscolarizacaoCalloutBuilder($repo))->build(
            '2910800',
            ['possivel_fora_escola' => 80],
        );

        $this->assertTrue($block['available']);
        $this->assertArrayHasKey('bolsa', $block['metrics']);
        $this->assertArrayHasKey('bpc', $block['metrics']);
        $texts = array_map(static fn (array $c): string => (string) ($c['text'] ?? ''), $block['callouts']);
        $this->assertTrue(collect($texts)->contains(fn (string $t): bool => str_contains($t, 'Pressão social')));
        $this->assertTrue(collect($texts)->contains(fn (string $t): bool => str_contains($t, 'não prova')));
    }

    #[Test]
    public function sync_dry_run_respeita_portal_desactivado(): void
    {
        config([
            'ieducar.other_funding.public_queries.portal_transparencia.enabled' => false,
            'ieducar.cadunico.beneficios_portal.enabled' => true,
        ]);

        $service = new CadunicoPortalBeneficiosSyncService(
            new PortalTransparenciaApiClient,
            Mockery::mock(MunicipalBenefitSnapshotRepository::class),
        );

        $result = $service->sync(null, null, 1, true);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['skipped'] ?? false);
    }
}
