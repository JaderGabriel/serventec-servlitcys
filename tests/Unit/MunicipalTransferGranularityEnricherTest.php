<?php

namespace Tests\Unit;

use App\Services\Funding\TesouroTransferenciasCsvService;
use App\Support\Funding\MunicipalTransferGranularityEnricher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalTransferGranularityEnricherTest extends TestCase
{
    #[Test]
    public function tesouro_csv_recebe_mensal_e_repasses_antes_de_gravar(): void
    {
        $enricher = new MunicipalTransferGranularityEnricher(new TesouroTransferenciasCsvService);
        $rows = $enricher->enrichRows([
            [
                'fonte' => 'tesouro_csv',
                'ano' => 2026,
                'meta' => [
                    'mensal' => ['1' => 100.0, '2' => 200.0],
                ],
            ],
        ], 2026);

        $meta = $rows[0]['meta'];
        $this->assertSame([1 => 100.0, 2 => 200.0], $meta['mensal'] ?? null);
        $this->assertSame(2, $meta['meses_somados'] ?? null);
        $this->assertCount(2, $meta['repasses'] ?? []);
        $this->assertSame('month', $meta['granularity'] ?? null);
        $this->assertSame(1, $meta['repasses'][0]['mes'] ?? null);
        $this->assertSame('month', $meta['repasses'][0]['granularity'] ?? null);
    }

    #[Test]
    public function bb_extrato_marca_granularidade_diaria(): void
    {
        $enricher = new MunicipalTransferGranularityEnricher(new TesouroTransferenciasCsvService);
        $row = $enricher->enrichRow([
            'fonte' => 'bb_extrato',
            'ano' => 2025,
            'meta' => [
                'lancamentos' => [
                    ['data' => '10/03/2025', 'valor' => 50.0],
                ],
            ],
        ], 2025);

        $this->assertSame('day', $row['meta']['granularity'] ?? null);
    }

    #[Test]
    public function portal_transparencia_respeita_granularidade_por_repasse(): void
    {
        $enricher = new MunicipalTransferGranularityEnricher(new TesouroTransferenciasCsvService);
        $row = $enricher->enrichRow([
            'fonte' => 'portal_transparencia',
            'ano' => 2026,
            'meta' => [
                'repasses' => [
                    ['mes' => 3, 'ano' => 2026, 'valor' => 100.0, 'granularity' => 'month'],
                ],
            ],
        ], 2026);

        $this->assertSame('month', $row['meta']['granularity'] ?? null);
    }
}
