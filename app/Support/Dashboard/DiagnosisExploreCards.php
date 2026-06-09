<?php

namespace App\Support\Dashboard;

/**
 * Cartões «Explorar em detalhe» do Diagnóstico — métrica e status por módulo do painel (não o índice global).
 */
final class DiagnosisExploreCards
{
    /** @var list<string> */
    private const EXCLUDED_TABS = ['municipality_health', 'discrepancies'];

    /**
     * @return list<array{
     *   tab: string,
     *   label: string,
     *   group: string,
     *   tone: string,
     *   icon: string,
     *   metric_value: string,
     *   metric_label: string,
     *   metric_detail: string,
     *   status: string,
     *   status_label: string,
     *   legend: string,
     *   hint: string,
     *   order: int
     * }>
     */
    public static function build(array $health): array
    {
        $blocks = is_array($health['thematic_blocks'] ?? null) ? $health['thematic_blocks'] : [];
        $dimensions = is_array($health['cadastro_dimensions'] ?? null) ? $health['cadastro_dimensions'] : [];
        $labels = AnalyticsTabCatalog::labels();
        $hints = AnalyticsTabCatalog::tabHints();
        $groupPresentation = AnalyticsTabCatalog::groupPresentation();
        $tabToGroup = AnalyticsTabCatalog::tabToGroupMap();

        $cards = [];
        $order = 0;

        foreach (self::exploreTabOrder() as $tab) {
            $order++;
            $groupId = $tabToGroup[$tab] ?? '';
            $groupLabel = (string) ($groupPresentation[$groupId]['short'] ?? $groupId);
            $meta = self::tabMeta($tab);
            $statusRow = AnalyticsTabImpactBuilder::exploreStatusForHealth($tab, $health);

            if ($tab === 'school_units') {
                $statusRow = self::mergeSchoolUnitsFromDimensions($statusRow, $dimensions);
            }

            $status = self::mergeStatus(
                self::worstBlockStatus($blocks, $tab),
                (string) ($statusRow['status'] ?? 'neutral'),
            );

            $metricValue = filled($statusRow['share_value'] ?? null)
                ? (string) $statusRow['share_value']
                : self::metricValueFallback($statusRow, $health, $tab);
            $metricLabel = filled($statusRow['share_label'] ?? null)
                ? (string) $statusRow['share_label']
                : self::metricLabelFallback($tab);

            $cards[] = self::baseCard(
                tab: $tab,
                order: $order,
                group: $groupLabel,
                tone: $meta['tone'],
                icon: $meta['icon'],
                label: $labels[$tab] ?? $tab,
                hint: $hints[$tab] ?? '',
                metricValue: $metricValue,
                metricLabel: $metricLabel,
                metricDetail: (string) ($statusRow['label'] ?? ''),
                status: $status,
                legend: AnalyticsTabImpactBuilder::exploreTabPurpose($tab),
            );
        }

        return $cards;
    }

    /**
     * @return list<string>
     */
    private static function exploreTabOrder(): array
    {
        $tabs = [];
        foreach (AnalyticsTabCatalog::groups() as $group) {
            foreach ($group['tabs'] ?? [] as $tabId) {
                $tab = (string) $tabId;
                if (in_array($tab, self::EXCLUDED_TABS, true)) {
                    continue;
                }
                $tabs[] = $tab;
            }
        }

        return $tabs;
    }

    /**
     * @return array{tone: string, icon: string}
     */
    private static function tabMeta(string $tab): array
    {
        return match ($tab) {
            'overview' => ['tone' => 'indigo', 'icon' => 'layout-grid'],
            'enrollment' => ['tone' => 'indigo', 'icon' => 'users'],
            'cadunico_previsao' => ['tone' => 'sky', 'icon' => 'user-group'],
            'network' => ['tone' => 'teal', 'icon' => 'share'],
            'school_units' => ['tone' => 'indigo', 'icon' => 'map-pin'],
            'inclusion' => ['tone' => 'violet', 'icon' => 'users'],
            'performance' => ['tone' => 'indigo', 'icon' => 'chart-bar'],
            'attendance' => ['tone' => 'amber', 'icon' => 'calendar-days'],
            'work_done' => ['tone' => 'sky', 'icon' => 'document-chart'],
            'fundeb' => ['tone' => 'teal', 'icon' => 'banknotes'],
            'finance_realtime' => ['tone' => 'amber', 'icon' => 'signal'],
            'comparativo' => ['tone' => 'teal', 'icon' => 'arrows-right-left'],
            'other_funding' => ['tone' => 'amber', 'icon' => 'building-library'],
            default => ['tone' => 'teal', 'icon' => 'clipboard-check'],
        };
    }

    /**
     * @param  array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}  $statusRow
     * @param  list<array<string, mixed>>  $dimensions
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function mergeSchoolUnitsFromDimensions(array $statusRow, array $dimensions): array
    {
        if (($statusRow['share_value'] ?? null) !== null || ($statusRow['status'] ?? 'neutral') !== 'neutral') {
            return $statusRow;
        }

        $geo = self::findDimension($dimensions, 'escola_sem_geo');
        if ($geo === null) {
            return $statusRow;
        }

        $escolas = (int) ($geo['occurrences_total'] ?? $geo['total'] ?? 0);
        if ($escolas <= 0 || ! ($geo['has_issue'] ?? false)) {
            return $statusRow;
        }

        return [
            'status' => (string) ($geo['status'] ?? 'warning'),
            'label' => __(':n escola(s) sem coordenadas no filtro', ['n' => number_format($escolas)]),
            'score' => $statusRow['score'],
            'share_label' => __('Sem georreferência'),
            'share_value' => number_format($escolas),
        ];
    }

    /**
     * @param  array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}  $statusRow
     */
    private static function metricValueFallback(array $statusRow, array $health, string $tab): string
    {
        $status = (string) ($statusRow['status'] ?? 'neutral');
        if ($status === 'success') {
            return __('OK');
        }

        $summary = is_array($health['summary'] ?? null) ? $health['summary'] : [];

        return match ($tab) {
            'overview' => ($summary['total_matriculas'] ?? null) !== null
                ? number_format((int) $summary['total_matriculas'])
                : '—',
            'fundeb' => number_format((int) ($summary['modulos_fundeb_alerta'] ?? 0)),
            'other_funding' => number_format((int) ($health['programas_alerta'] ?? 0)),
            'work_done' => number_format((int) ($summary['censo_pendentes'] ?? $summary['cadastros_quinzena'] ?? 0)),
            'inclusion' => number_format((int) ($summary['recurso_prova_sem_nee'] ?? 0)),
            default => '—',
        };
    }

    private static function metricLabelFallback(string $tab): string
    {
        return match ($tab) {
            'overview' => __('matrículas'),
            'fundeb' => __('módulos VAAR em alerta'),
            'other_funding' => __('programas em alerta'),
            'work_done' => __('indicador Censo'),
            'inclusion' => __('recurso prova s/ NEE'),
            'cadunico_previsao' => __('lacuna CadÚnico'),
            'finance_realtime' => __('repasses'),
            'comparativo' => __('ano base'),
            default => __('indicador'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function baseCard(
        string $tab,
        int $order,
        string $group,
        string $tone,
        string $icon,
        string $label,
        string $hint,
        string $metricValue,
        string $metricLabel,
        string $metricDetail,
        string $status,
        string $legend,
    ): array {
        return [
            'tab' => $tab,
            'order' => $order,
            'group' => $group,
            'tone' => $tone,
            'icon' => $icon,
            'label' => $label,
            'hint' => $hint,
            'metric_value' => $metricValue,
            'metric_label' => $metricLabel,
            'metric_detail' => $metricDetail,
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'legend' => $legend,
        ];
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'success' => __('Adequado'),
            'warning' => __('Atenção'),
            'danger' => __('Crítico'),
            default => __('Consultar'),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private static function worstBlockStatus(array $blocks, string $tab): string
    {
        $worst = 'neutral';
        foreach ($blocks as $block) {
            if (! is_array($block) || ($block['tab_link'] ?? '') !== $tab) {
                continue;
            }
            $worst = self::mergeStatus($worst, (string) ($block['status'] ?? 'neutral'));
        }

        return $worst;
    }

    /**
     * @param  list<array<string, mixed>>  $dimensions
     * @return array<string, mixed>|null
     */
    private static function findDimension(array $dimensions, string $id): ?array
    {
        foreach ($dimensions as $dimension) {
            if (is_array($dimension) && ($dimension['id'] ?? '') === $id) {
                return $dimension;
            }
        }

        return null;
    }

    private static function mergeStatus(string $a, string $b): string
    {
        $rank = ['neutral' => 0, 'success' => 1, 'warning' => 2, 'danger' => 3];

        return ($rank[$b] ?? 0) >= ($rank[$a] ?? 0) ? $b : $a;
    }
}
