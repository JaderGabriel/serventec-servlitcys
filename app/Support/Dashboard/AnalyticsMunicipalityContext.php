<?php

namespace App\Support\Dashboard;

use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\IeducarAnalyticsMetricsScope;

/**
 * Contexto municipal leve (filtros aplicados) para faixas de impacto nas abas do painel.
 */
final class AnalyticsMunicipalityContext
{
    /**
     * Reutiliza o payload já montado pelo Diagnóstico (evita segunda passagem em Discrepâncias).
     *
     * @param  array<string, mixed>  $healthData
     * @return array<string, mixed>|null
     */
    public static function fromHealthSnapshot(array $healthData): ?array
    {
        if ($healthData === []) {
            return null;
        }

        $summary = is_array($healthData['summary'] ?? null) ? $healthData['summary'] : [];
        $perda = (float) ($summary['perda_estimada_anual'] ?? 0);
        $ganho = (float) ($summary['ganho_potencial_anual'] ?? 0);
        $pendencias = (int) ($summary['pendencias_cadastro'] ?? 0);
        $corrigiveis = (int) ($summary['corrigiveis'] ?? 0);
        $comProblema = (int) ($summary['com_problema'] ?? 0);
        $escolas = (int) ($summary['escolas_afetadas'] ?? 0);
        $matriculas = $summary['total_matriculas'] ?? null;
        $matriculas = is_numeric($matriculas) ? (int) $matriculas : null;

        $score = (int) ($healthData['compliance_score'] ?? 0);
        if ($score <= 0) {
            $score = self::estimateComplianceScore($pendencias, $corrigiveis, $perda, $ganho);
        }

        $status = (string) ($healthData['compliance_status'] ?? '');
        if ($status === '' || $status === 'neutral') {
            $status = self::statusFromScore($score);
        }

        $label = (string) ($healthData['compliance_label'] ?? '');
        if ($label === '') {
            $label = self::labelFromScore($score);
        }

        return [
            'perda_estimada_anual' => $perda,
            'ganho_potencial_anual' => $ganho,
            'saldo_liquido' => round($ganho - $perda, 2),
            'pendencias_cadastro' => $pendencias,
            'corrigiveis' => $corrigiveis,
            'com_problema' => $comProblema,
            'escolas_afetadas' => $escolas,
            'total_matriculas' => $matriculas !== null && $matriculas > 0 ? $matriculas : null,
            'compliance_score' => $score,
            'compliance_status' => $status,
            'compliance_label' => $label,
            'funding_reference' => is_array($healthData['funding_reference'] ?? null)
                ? $healthData['funding_reference']
                : null,
        ];
    }

    /**
     * Contexto leve para a aba Censo (ritmo de cadastro / exportação Educacenso).
     *
     * @param  array<string, mixed>  $workDone
     * @param  array<string, mixed>  $overviewData
     * @return array<string, mixed>|null
     */
    public static function fromWorkDoneSnapshot(array $workDone, array $overviewData = []): ?array
    {
        if ($workDone === []) {
            return null;
        }

        $censo = is_array($workDone['censo'] ?? null) ? $workDone['censo'] : [];
        $censoSum = is_array($censo['summary'] ?? null) ? $censo['summary'] : [];
        $pendentes = (int) ($censoSum['pendentes'] ?? 0);
        $periods = is_array($workDone['periods'] ?? null) ? $workDone['periods'] : [];
        $fortnight = (int) ($periods['fortnight'] ?? 0);
        $kpis = is_array($overviewData['kpis'] ?? null) ? $overviewData['kpis'] : [];
        $mat = (int) ($workDone['total_matriculas'] ?? $kpis['matriculas'] ?? 0);

        $score = 92;
        if ($pendentes > 0) {
            $score = max(30, 100 - min(55, $pendentes * 4));
        } elseif (! ($workDone['activity_available'] ?? false)) {
            $score = 75;
        } elseif ($fortnight <= 0) {
            $score = 80;
        }

        $status = self::statusFromScore($score);

        return [
            'perda_estimada_anual' => 0.0,
            'ganho_potencial_anual' => 0.0,
            'saldo_liquido' => 0.0,
            'pendencias_cadastro' => $pendentes,
            'corrigiveis' => 0,
            'com_problema' => $pendentes,
            'escolas_afetadas' => 0,
            'total_matriculas' => $mat > 0 ? $mat : null,
            'compliance_score' => $score,
            'compliance_status' => $status,
            'compliance_label' => self::labelFromScore($score),
            'funding_reference' => null,
        ];
    }

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
        $metricExtras = IeducarAnalyticsMetricsScope::resolve()?->toMunicipalityContextExtras() ?? [];
        $matriculas = (int) ($metricExtras['total_matriculas'] ?? $kpis['matriculas'] ?? $overviewData['total_matriculas'] ?? 0);
        $distorcaoPct = isset($metricExtras['distorcao_pct']) ? (float) $metricExtras['distorcao_pct'] : null;

        $score = self::estimateComplianceScore($pendencias, $corrigiveis, $perda, $ganho, $distorcaoPct);
        $status = self::statusFromScore($score);

        return array_merge([
            'perda_estimada_anual' => $perda,
            'ganho_potencial_anual' => $ganho,
            'saldo_liquido' => round($ganho - $perda, 2),
            'pendencias_cadastro' => $pendencias,
            'corrigiveis' => $corrigiveis,
            'escolas_afetadas' => $escolas,
            'total_matriculas' => $matriculas > 0 ? $matriculas : null,
            'year_label' => filled($fundingSnapshot['year_label'] ?? null)
                ? (string) $fundingSnapshot['year_label']
                : null,
            'compliance_score' => $score,
            'compliance_status' => $status,
            'compliance_label' => self::labelFromScore($score),
            'funding_reference' => is_array($fundingSnapshot['funding_reference'] ?? null)
                ? $fundingSnapshot['funding_reference']
                : null,
        ], $metricExtras);
    }

    public static function estimateComplianceScore(
        int $pendencias,
        int $corrigiveis,
        float $perda,
        float $ganho,
        ?float $distorcaoPct = null,
    ): int {
        $score = 100.0;
        $score -= min(45.0, $pendencias * 2.5);
        $score -= min(20.0, $corrigiveis * 0.8);
        if ($perda > 0) {
            $score -= min(25.0, log10(max(10.0, $perda)) * 4.0);
        }
        if ($distorcaoPct !== null && $distorcaoPct > 0) {
            $score -= min(12.0, $distorcaoPct * 0.45);
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
