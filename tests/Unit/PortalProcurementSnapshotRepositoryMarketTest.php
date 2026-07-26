<?php

namespace Tests\Unit;

use App\Models\PortalProcurementSnapshot;
use App\Repositories\PortalProcurementSnapshotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PortalProcurementSnapshotRepositoryMarketTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function licitacoes_market_by_ibge_aggregates_and_samples(): void
    {
        $repo = new PortalProcurementSnapshotRepository;
        $now = now();

        $repo->upsertBatch([
            [
                'tipo' => PortalProcurementSnapshot::TIPO_LICITACAO,
                'ano' => 2025,
                'codigo_orgao' => '26298',
                'orgao_sigla' => 'FNDE',
                'orgao_nome' => 'FNDE',
                'external_id' => 'L1',
                'objeto' => 'Aquisição de sistema de gestão escolar',
                'valor' => 1000,
                'data_publicacao' => '2025-03-01',
                'ibge_municipio' => '3550308',
                'itens_software' => true,
            ],
            [
                'tipo' => PortalProcurementSnapshot::TIPO_LICITACAO,
                'ano' => 2025,
                'codigo_orgao' => '26298',
                'orgao_sigla' => 'FNDE',
                'orgao_nome' => 'FNDE',
                'external_id' => 'L2',
                'objeto' => 'Material de expediente',
                'valor' => 500,
                'data_publicacao' => '2025-02-01',
                'ibge_municipio' => '3550308',
                'itens_software' => false,
            ],
            [
                'tipo' => PortalProcurementSnapshot::TIPO_CONTRATO,
                'ano' => 2025,
                'codigo_orgao' => '26298',
                'orgao_sigla' => 'FNDE',
                'orgao_nome' => 'FNDE',
                'external_id' => 'C1',
                'objeto' => 'Software',
                'valor' => 9000,
                'vendor_matched' => true,
                'vendor_label' => 'Vendor A',
                'itens_software' => true,
                'ibge_municipio' => null,
            ],
            [
                'tipo' => PortalProcurementSnapshot::TIPO_CONTRATO,
                'ano' => 2025,
                'codigo_orgao' => '26298',
                'orgao_sigla' => 'FNDE',
                'orgao_nome' => 'FNDE',
                'external_id' => 'C2',
                'objeto' => 'Sistema municipal',
                'valor' => 7000,
                'fornecedor_cnpj' => '33324175000103',
                'fornecedor_nome' => 'PROESC LTDA',
                'vendor_matched' => true,
                'vendor_label' => 'Proesc',
                'itens_software' => true,
                'ibge_municipio' => '3550308',
                'data_inicio_vigencia' => '2025-01-01',
                'data_fim_vigencia' => '2025-12-31',
            ],
        ], $now);

        $byIbge = $repo->licitacoesMarketByIbge(2025);
        $this->assertArrayHasKey('3550308', $byIbge);
        $this->assertSame(2, $byIbge['3550308']['licitacoes']);
        $this->assertSame(2, $byIbge['3550308']['licitacoes_software']); // 1 licitacao soft + 1 contrato soft
        $this->assertSame(8500.0, $byIbge['3550308']['valor_total']);
        $this->assertGreaterThanOrEqual(2, count($byIbge['3550308']['samples']));
        $this->assertSame('Proesc', $byIbge['3550308']['samples'][0]['vendor_label'] ?? null);

        $national = $repo->nationalVendorMarketSummary(2025);
        $this->assertSame(2, $national['vendor_matched']);
        $this->assertSame(2, $national['itens_software']);
    }
}
