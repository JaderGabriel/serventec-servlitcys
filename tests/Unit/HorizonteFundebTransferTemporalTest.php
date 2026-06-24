<?php

namespace Tests\Unit;

use App\Models\MunicipalTransferSnapshot;
use App\Support\Horizonte\HorizonteFundebTransferTemporal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteFundebTransferTemporalTest extends TestCase
{
    #[Test]
    public function resolve_ultimo_mes_com_repasse_no_meta_mensal(): void
    {
        $row = MunicipalTransferSnapshot::make([
            'ibge_municipio' => '2921500',
            'ano' => 2026,
            'fonte' => 'tesouro_csv',
            'programa_id' => 'fundeb',
            'valor' => 25000.0,
            'meta' => [
                'mensal' => [
                    2 => 10000.0,
                    4 => 15000.0,
                ],
            ],
            'imported_at' => now()->setDate(2026, 6, 10),
        ]);

        $temporal = HorizonteFundebTransferTemporal::lastRecorded([$row], 2026);

        $this->assertNotNull($temporal);
        $this->assertSame(4, $temporal['month']);
        $this->assertSame('abr/2026', $temporal['label']);
        $this->assertSame('mensal', $temporal['source']);
        $this->assertNotNull($temporal['recorded_at']);
    }

    #[Test]
    public function usa_imported_at_quando_nao_ha_mensal(): void
    {
        $row = MunicipalTransferSnapshot::make([
            'ibge_municipio' => '2921500',
            'ano' => 2026,
            'fonte' => 'tesouro_csv',
            'programa_id' => 'fundeb',
            'valor' => 25000.0,
            'meta' => [],
            'imported_at' => now()->setDate(2026, 5, 3),
        ]);

        $temporal = HorizonteFundebTransferTemporal::lastRecorded([$row], 2026);

        $this->assertNotNull($temporal);
        $this->assertSame('03/05/2026', $temporal['label']);
        $this->assertSame('imported_at', $temporal['source']);
    }
}
