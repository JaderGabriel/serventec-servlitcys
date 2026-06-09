<?php

namespace Tests\Unit;

use App\Support\Dashboard\AnalyticsDockQualityIndicator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsDockQualityIndicatorTest extends TestCase
{
    #[Test]
    public function build_usa_score_do_diagnostico_quando_disponivel(): void
    {
        $indicator = AnalyticsDockQualityIndicator::build(
            ['compliance_score' => 72, 'compliance_status' => 'warning'],
            null,
            null,
            true,
        );

        $this->assertTrue($indicator['available']);
        $this->assertSame(72, $indicator['score']);
        $this->assertSame('warning', $indicator['status']);
        $this->assertFalse($indicator['estimated']);
        $this->assertSame(__('Atenção — corrigir antes do Censo'), $indicator['status_label']);
    }

    #[Test]
    public function build_estima_a_partir_do_funding_quando_sem_health(): void
    {
        $indicator = AnalyticsDockQualityIndicator::build(
            null,
            null,
            [
                'summary' => [
                    'com_problema' => 20,
                    'corrigiveis' => 5,
                    'perda_estimada_anual' => 50_000.0,
                    'ganho_potencial_anual' => 10_000.0,
                ],
            ],
            true,
        );

        $this->assertTrue($indicator['available']);
        $this->assertIsInt($indicator['score']);
        $this->assertTrue($indicator['estimated']);
    }

    #[Test]
    public function build_vazio_sem_ano_letivo(): void
    {
        $indicator = AnalyticsDockQualityIndicator::build(null, null, null, false);

        $this->assertFalse($indicator['available']);
        $this->assertNull($indicator['score']);
    }
}
