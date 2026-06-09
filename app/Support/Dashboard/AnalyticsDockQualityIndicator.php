<?php

namespace App\Support\Dashboard;

use App\Models\City;

/**
 * Indicador compacto de qualidade no rodapé da consultoria (mesma base do Diagnóstico).
 */
final class AnalyticsDockQualityIndicator
{
    /**
     * @return array{
     *   available: bool,
     *   partial: bool,
     *   estimated: bool,
     *   score: ?int,
     *   status: string,
     *   status_label: string,
     *   title: string,
     *   hint: string
     * }
     */
    public static function build(
        ?array $healthData,
        ?array $municipalityContext,
        ?array $fundingSnapshot,
        bool $yearFilterReady,
    ): array {
        if (! $yearFilterReady) {
            return self::empty();
        }

        if (is_array($healthData) && is_numeric($healthData['compliance_score'] ?? null)) {
            return self::pack(
                (int) $healthData['compliance_score'],
                (string) ($healthData['compliance_status'] ?? 'neutral'),
                estimated: false,
            );
        }

        if (is_array($municipalityContext) && is_numeric($municipalityContext['compliance_score'] ?? null)) {
            $score = (int) $municipalityContext['compliance_score'];
            if ($score > 0) {
                return self::pack(
                    $score,
                    (string) ($municipalityContext['compliance_status'] ?? 'neutral'),
                    estimated: false,
                );
            }
        }

        if (is_array($fundingSnapshot)) {
            $ctx = AnalyticsMunicipalityContext::fromFundingSnapshot($fundingSnapshot, []);
            if (is_array($ctx) && ($ctx['compliance_score'] ?? 0) > 0) {
                return self::pack(
                    (int) $ctx['compliance_score'],
                    (string) ($ctx['compliance_status'] ?? 'neutral'),
                    estimated: true,
                );
            }
        }

        return [
            ...self::empty(),
            'partial' => true,
        ];
    }

    /**
     * @return array{
     *   available: bool,
     *   partial: bool,
     *   estimated: bool,
     *   score: ?int,
     *   status: string,
     *   status_label: string,
     *   title: string,
     *   hint: string
     * }
     */
    public static function empty(): array
    {
        return [
            'available' => false,
            'partial' => false,
            'estimated' => false,
            'score' => null,
            'status' => 'neutral',
            'status_label' => self::executiveStatusLabel('neutral'),
            'title' => __('Qualidade'),
            'hint' => __('Índice geral de qualidade do recorte ativo (0–100). Mesma base do Painel de decisão no Diagnóstico.'),
        ];
    }

    public static function diagnosisUrl(City $city, IeducarFilterState $filters): string
    {
        return route('dashboard.analytics', array_merge(
            $filters->toQueryParams(),
            ['city_id' => $city->id, 'tab' => 'municipality_health'],
        )).'#diag-qualidade-sistema';
    }

    /**
     * @return array{
     *   available: bool,
     *   partial: bool,
     *   estimated: bool,
     *   score: ?int,
     *   status: string,
     *   status_label: string,
     *   title: string,
     *   hint: string
     * }
     */
    private static function pack(int $score, string $status, bool $estimated): array
    {
        $score = max(0, min(100, $score));
        $status = in_array($status, ['success', 'warning', 'danger'], true) ? $status : AnalyticsMunicipalityContext::statusFromScore($score);

        return [
            'available' => true,
            'partial' => false,
            'estimated' => $estimated,
            'score' => $score,
            'status' => $status,
            'status_label' => self::executiveStatusLabel($status),
            'title' => __('Qualidade'),
            'hint' => $estimated
                ? __('Estimativa a partir do resumo financeiro. Abra Diagnóstico para o índice consolidado.')
                : __('Índice geral de qualidade do recorte ativo (0–100). Mesma base do Painel de decisão no Diagnóstico.'),
        ];
    }

    public static function executiveStatusLabel(string $status): string
    {
        return match ($status) {
            'success' => __('Adequado no filtro'),
            'warning' => __('Atenção — corrigir antes do Censo'),
            'danger' => __('Crítico — ação imediata'),
            default => __('Sem índice'),
        };
    }
}
