<?php

namespace Tests\Unit;

use App\Support\Ieducar\FundebResourceProjection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebResourceProjectionVaarTest extends TestCase
{
    #[Test]
    public function usa_complementacao_vaar_importada_quando_config_ativa(): void
    {
        config([
            'ieducar.fundeb.use_imported_vaar' => true,
            'ieducar.fundeb.complementacao_vaar_pct_base' => 10,
            'ieducar.discrepancies.aviso_financeiro' => 'aviso teste',
        ]);

        $ref = [
            'vaaf' => 5000.0,
            'fonte' => 'oficial_db',
            'fonte_label' => 'FNDE',
            'ano' => 2024,
            'municipal' => ['vaaf' => 5000.0],
            'previa' => null,
            'divergencia' => null,
            'vaat' => null,
            'complementacao_vaar' => 250000.0,
        ];

        $proj = FundebResourceProjection::build(
            100,
            '2024',
            ['kpis' => ['matriculas' => 100]],
            ['summary' => ['perda_estimada_anual' => 0, 'ganho_potencial_anual' => 0]],
            null,
            null,
            $ref,
        );

        $this->assertSame(250000.0, $proj['totais']['complementacao_vaar'] ?? null);
        $this->assertSame(750000.0, $proj['totais']['total_com_complemento'] ?? null);
    }
}
