<?php

namespace App\Support\Dashboard;

use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Contexto municipal leve (filtros aplicados) para faixas de impacto nas abas do painel.
 */
final class AnalyticsMunicipalityContext
{
    /**
     * @param  array{summary?: array<string, mixed>, funding_reference?: ?array<string, mixed>}|null  $fundingSnapshot
     * @param  array<string, mixed>  $overviewData
     * @return array<string, mixed>|null
     */
    public static function fromFundingSnapshot(?array $fundingSnapshot, array $overviewData = []): ?array
    {
        if ($fundingSnapshot === null) {
            return null;
        }

        $summary = is_array($fundingSnapshot['summary'] ?? null) ? $fundingSnapshot['summary'] : [];
        $perda = (float) ($summary['perda_estimada_anual'] ?? 0);
        $ganho = (float) ($summary['ganho_potencial_anual'] ?? 0);
        $pendencias = (int) ($summary['com_problema'] ?? 0);
        $corrigiveis = (int) ($summary['corrigiveis'] ?? 0);
        $escolas = (int) ($summary['escolas_afetadas'] ?? 0);

        $kpis = is_array($overviewData['kpis'] ?? null) ? $overviewData['kpis'] : [];
        $matriculas = (int) ($kpis['matriculas'] ?? $overviewData['total_matriculas'] ?? 0);

        $score = self::estimateComplianceScore($pendencias, $corrigiveis, $perda, $ganho);
        $status = self::statusFromScore($score);

        return [
            'perda_estimada_anual' => $perda,
            'ganho_potencial_anual' => $ganho,
            'saldo_liquido' => round($ganho - $perda, 2),
            'pendencias_cadastro' => $pendencias,
            'corrigiveis' => $corrigiveis,
            'escolas_afetadas' => $escolas,
            'total_matriculas' => $matriculas > 0 ? $matriculas : null,
            'compliance_score' => $score,
            'compliance_status' => $status,
            'compliance_label' => self::labelFromScore($score),
            'funding_reference' => is_array($fundingSnapshot['funding_reference'] ?? null)
                ? $fundingSnapshot['funding_reference']
                : null,
        ];
    }

    public static function estimateComplianceScore(int $pendencias, int $corrigiveis, float $perda, float $ganho): int
    {
        $score = 100.0;
        $score -= min(45.0, $pendencias * 2.5);
        $score -= min(20.0, $corrigiveis * 0.8);
        if ($perda > 0) {
            $score -= min(25.0, log10(max(10.0, $perda)) * 4.0);
        }
        if ($ganho > 0 && $pendencias > 0) {
            $score += min(8.0, $corrigiveis * 0.4);
        }

        return (int) max(0, min(100, round($score)));
    }

    public static function statusFromScore(int $score): string
    {
        return match (true) {
            $score >= 75 => 'success',
            $score >= 50 => 'warning',
            default => 'danger',
        };
    }

    public static function labelFromScore(int $score): string
    {
        return match (true) {
            $score >= 85 => __('Cadastro consistente'),
            $score >= 75 => __('Situação favorável'),
            $score >= 50 => __('Atenção — pendências no filtro'),
            $score >= 30 => __('Risco moderado'),
            default => __('Risco elevado'),
        };
    }

    public static function formatSaldo(float $value): string
    {
        return DiscrepanciesFundingImpact::formatBrl($value);
    }
}
