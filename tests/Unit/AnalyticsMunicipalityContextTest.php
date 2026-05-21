<?php

namespace Tests\Unit;

use App\Support\Dashboard\AnalyticsMunicipalityContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Contexto leve do município para faixas de impacto (saldo, score) sem reexecutar todas as queries.
 */
final class AnalyticsMunicipalityContextTest extends TestCase
{
    /**
     * Cenário: snapshot de discrepâncias já calculou perda/ganho e overview trouxe matrículas.
     * Esperado: saldo líquido = ganho - perda; score derivado de pendências.
     */
    #[Test]
    public function from_funding_snapshot_monta_saldo_e_conformidade(): void
    {
        $ctx = AnalyticsMunicipalityContext::fromFundingSnapshot([
            'summary' => [
                'perda_estimada_anual' => 10_000.0,
                'ganho_potencial_anual' => 10_000.0,
                'com_problema' => 50,
                'corrigiveis' => 40,
                'escolas_afetadas' => 3,
            ],
        ], ['kpis' => ['matriculas' => 1200]]);

        $this->assertNotNull($ctx);
        $this->assertSame(10_000.0, $ctx['perda_estimada_anual']);
        $this->assertSame(0.0, $ctx['saldo_liquido']);
        $this->assertSame(1200, $ctx['total_matriculas']);
        $this->assertGreaterThan(0, $ctx['compliance_score']);
        $this->assertContains($ctx['compliance_status'], ['success', 'warning', 'danger']);
    }

    /**
     * Cenário: chamada sem snapshot (ano não aplicado ou erro upstream).
     */
    #[Test]
    public function from_funding_snapshot_retorna_null_sem_dados(): void
    {
        $this->assertNull(AnalyticsMunicipalityContext::fromFundingSnapshot(null));
    }

    /**
     * Cenário: município sem pendências e sem perda financeira.
     * Esperado: score alto (success).
     */
    #[Test]
    public function score_alto_quando_sem_pendencias(): void
    {
        $score = AnalyticsMunicipalityContext::estimateComplianceScore(0, 0, 0.0, 0.0);

        $this->assertGreaterThanOrEqual(75, $score);
        $this->assertSame('success', AnalyticsMunicipalityContext::statusFromScore($score));
    }

    /**
     * Cenário: muitas pendências e perda elevada (ordem de grandeza logarítmica).
     * Esperado: score baixo (danger) — usado no speedometer do Diagnóstico.
     */
    #[Test]
    public function score_baixo_com_muitas_pendencias_e_perda(): void
    {
        $score = AnalyticsMunicipalityContext::estimateComplianceScore(80, 10, 500_000.0, 0.0);

        $this->assertLessThan(50, $score);
        $this->assertSame('danger', AnalyticsMunicipalityContext::statusFromScore($score));
    }

    /**
     * Cenário: faixas de rótulo exibidas ao gestor municipal no painel.
     */
    #[Test]
    public function labels_por_faixa_de_score(): void
    {
        $this->assertNotSame('', AnalyticsMunicipalityContext::labelFromScore(90));
        $this->assertNotSame('', AnalyticsMunicipalityContext::labelFromScore(40));
        $this->assertNotSame('', AnalyticsMunicipalityContext::labelFromScore(10));
    }

    /**
     * Cenário: saldo negativo formatado em BRL na UI (consistência com Discrepâncias).
     */
    #[Test]
    public function format_saldo_usa_format_brl(): void
    {
        $formatted = AnalyticsMunicipalityContext::formatSaldo(-1234.56);

        $this->assertStringContainsString('R$', $formatted);
        $this->assertStringContainsString('1.234', $formatted);
    }
}
