<?php

namespace Tests\Unit;

use App\Support\Dashboard\DiagnosisExploreCards;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DiagnosisExploreCardsTest extends TestCase
{
    #[Test]
    public function cards_usam_metricas_por_area_nao_indice_global(): void
    {
        $health = [
            'compliance_score' => 41,
            'compliance_status' => 'danger',
            'summary' => [
                'pendencias_cadastro' => 5,
                'com_problema' => 120,
                'corrigiveis' => 80,
                'modulos_fundeb_alerta' => 2,
                'censo_pendentes' => 3,
                'cadastros_quinzena' => 15,
                'recurso_prova_sem_nee' => 7,
            ],
            'programas_alerta' => 1,
            'other_funding_programs' => 4,
            'work_done_available' => true,
            'fundeb_modules' => [
                ['status' => 'warning'],
                ['status' => 'danger'],
            ],
            'thematic_blocks' => [],
        ];

        $cards = DiagnosisExploreCards::build($health);
        $byTab = [];
        foreach ($cards as $card) {
            $byTab[$card['tab']] = $card;
        }

        $this->assertSame('120', $byTab['discrepancies']['metric_value']);
        $this->assertSame('2', $byTab['fundeb']['metric_value']);
        $this->assertSame('1', $byTab['other_funding']['metric_value']);
        $this->assertSame('3', $byTab['work_done']['metric_value']);
        $this->assertSame('7', $byTab['inclusion']['metric_value']);
        $this->assertNotSame('41', $byTab['discrepancies']['metric_value']);
        $this->assertSame('discrepancies', $cards[0]['tab']);
        $this->assertSame('performance', $cards[5]['tab']);
    }
}
