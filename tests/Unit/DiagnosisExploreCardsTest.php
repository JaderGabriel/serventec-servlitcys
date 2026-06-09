<?php

namespace Tests\Unit;

use App\Support\Dashboard\DiagnosisExploreCards;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DiagnosisExploreCardsTest extends TestCase
{
    #[Test]
    public function cards_seguem_roteiro_gerencial_sem_visao_geral_nem_discrepancias(): void
    {
        $health = $this->sampleHealth();

        $cards = DiagnosisExploreCards::build($health);
        $tabs = array_column($cards, 'tab');

        $expected = [
            'fundeb',
            'finance_realtime',
            'comparativo',
            'other_funding',
            'enrollment',
            'cadunico_previsao',
            'network',
            'school_units',
            'inclusion',
            'performance',
            'attendance',
            'work_done',
        ];

        $this->assertSame($expected, $tabs);
        $this->assertNotContains('overview', $tabs);
        $this->assertNotContains('discrepancies', $tabs);
        $this->assertNotContains('municipality_health', $tabs);
    }

    #[Test]
    public function cards_agrupam_por_fase_gerencial(): void
    {
        $phases = DiagnosisExploreCards::buildGroupedByPhase($this->sampleHealth());

        $this->assertSame(['finance', 'cadastro', 'pedagogico', 'censo'], array_column($phases, 'id'));
        $this->assertSame('1', $phases[0]['step']);
        $this->assertSame('fundeb', $phases[0]['cards'][0]['tab']);
        $this->assertSame('enrollment', $phases[1]['cards'][0]['tab']);
        $this->assertSame('inclusion', $phases[2]['cards'][0]['tab']);
        $this->assertSame('work_done', $phases[3]['cards'][0]['tab']);
    }

    #[Test]
    public function cards_usam_metricas_por_area_nao_indice_global(): void
    {
        $health = [
            'compliance_score' => 41,
            'summary' => [
                'total_matriculas' => 500,
                'modulos_fundeb_alerta' => 2,
                'recurso_prova_sem_nee' => 7,
                'censo_pendentes' => 3,
            ],
            'programas_alerta' => 1,
            'complementary_programs' => [['status' => 'warning']],
            'fundeb_modules' => [['status' => 'danger'], ['status' => 'warning']],
            'thematic_blocks' => [],
            'explore_tab_payload' => [
                'fundeb' => [
                    'modules' => [['status' => 'danger'], ['status' => 'warning']],
                    'resource_projection' => ['available' => true, 'totais' => ['fundeb_base_anual' => 900000]],
                ],
                'inclusion' => ['recurso_prova' => ['sem_nee' => 7]],
                'work_done' => ['censo' => ['summary' => ['pendentes' => 3]]],
            ],
        ];

        $cards = DiagnosisExploreCards::build($health);
        $byTab = [];
        foreach ($cards as $card) {
            $byTab[$card['tab']] = $card;
        }

        $this->assertStringContainsString('900', $byTab['fundeb']['metric_value']);
        $this->assertSame('1', $byTab['other_funding']['metric_value']);
        $this->assertSame(__('Alto'), $byTab['inclusion']['metric_value']);
        $this->assertSame(__('Priorizar'), $byTab['inclusion']['status_label']);
        $this->assertSame('fundeb', $cards[0]['tab']);
        $this->assertNotSame('41', $byTab['fundeb']['metric_value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleHealth(): array
    {
        return [
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
                'total_matriculas' => 842,
            ],
            'programas_alerta' => 1,
            'other_funding_programs' => 4,
            'work_done_available' => true,
            'fundeb_modules' => [
                ['status' => 'warning'],
                ['status' => 'danger'],
            ],
            'complementary_programs' => [
                ['id' => 'pnae', 'status' => 'warning'],
            ],
            'thematic_blocks' => [],
            'explore_tab_payload' => [
                'enrollment' => ['kpis' => ['matriculas' => 842, 'turmas' => 48]],
                'network' => ['kpis' => ['taxa_ociosidade_pct' => 12.5, 'vagas_ociosas' => 30]],
                'inclusion' => ['recurso_prova' => ['sem_nee' => 7], 'total_matriculas' => 842],
                'performance' => ['kpis' => []],
                'attendance' => ['unavailable' => true],
                'fundeb' => [
                    'modules' => [
                        ['status' => 'warning'],
                        ['status' => 'danger'],
                    ],
                    'resource_projection' => [
                        'available' => true,
                        'totais' => ['fundeb_base_anual' => 1200000],
                    ],
                ],
                'work_done' => [
                    'activity_available' => true,
                    'censo' => ['summary' => ['pendentes' => 3]],
                ],
                'comparativo' => [
                    'available' => true,
                    'base_year' => 2024,
                    'base_year_detail' => ['matriculas' => 842],
                ],
            ],
        ];
    }
}
