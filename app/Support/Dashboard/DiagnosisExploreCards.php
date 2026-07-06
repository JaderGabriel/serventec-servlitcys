<?php

namespace App\Support\Dashboard;

/**
 * Cartões «Navegação rápida» do Diagnóstico — métrica e status por módulo (roteiro gerencial).
 */
final class DiagnosisExploreCards
{
    /** @var list<string> */
    private const EXCLUDED_TABS = ['municipality_health', 'discrepancies', 'overview'];

    /**
     * Fases do roteiro gerencial (ordem de leitura na UI).
     *
     * @return list<array{id: string, step: string, label: string}>
     */
    public static function managerialPhases(): array
    {
        return [
            ['id' => 'finance', 'step' => '1', 'label' => __('Decisão de repasse')],
            ['id' => 'cadastro', 'step' => '2', 'label' => __('Base cadastral')],
            ['id' => 'pedagogico', 'step' => '3', 'label' => __('Condicionalidades VAAR')],
            ['id' => 'censo', 'step' => '4', 'label' => __('Fecho Censo')],
        ];
    }

    /**
     * @return list<array{
     *   tab: string,
     *   label: string,
     *   group: string,
     *   phase: string,
     *   phase_step: string,
     *   phase_label: string,
     *   focus: string,
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
        $tabToGroup = AnalyticsTabCatalog::tabToGroupMap();

        $cards = [];
        $order = 0;

        foreach (self::exploreTabOrder() as $tab) {
            $order++;
            $phase = self::managerialPhase($tab);
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
                group: (string) ($tabToGroup[$tab] ?? ''),
                phase: $phase['phase'],
                phaseStep: $phase['phase_step'],
                phaseLabel: $phase['phase_label'],
                focus: $phase['focus'],
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
     * Agrupa cartões por fase gerencial (para seções na UI).
     *
     * @return list<array{id: string, step: string, label: string, cards: list<array<string, mixed>>}>
     */
    public static function buildGroupedByPhase(array $health): array
    {
        $cards = self::build($health);
        $byPhase = [];
        foreach ($cards as $card) {
            $byPhase[$card['phase']][] = $card;
        }

        $grouped = [];
        foreach (self::managerialPhases() as $phase) {
            $id = $phase['id'];
            if (empty($byPhase[$id])) {
                continue;
            }
            $grouped[] = [
                'id' => $id,
                'step' => $phase['step'],
                'label' => $phase['label'],
                'cards' => $byPhase[$id],
            ];
        }

        return $grouped;
    }

    /**
     * @return list<string>
     */
    private static function exploreTabOrder(): array
    {
        return [
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
    }

    /**
     * @return array{phase: string, phase_step: string, phase_label: string, focus: string}
     */
    private static function managerialPhase(string $tab): array
    {
        return match ($tab) {
            'fundeb', 'finance_realtime', 'comparativo', 'other_funding' => [
                'phase' => 'finance',
                'phase_step' => '1',
                'phase_label' => __('Decisão de repasse'),
                'focus' => self::managerialFocus($tab),
            ],
            'enrollment', 'cadunico_previsao', 'network', 'school_units' => [
                'phase' => 'cadastro',
                'phase_step' => '2',
                'phase_label' => __('Base cadastral'),
                'focus' => self::managerialFocus($tab),
            ],
            'inclusion', 'performance', 'attendance' => [
                'phase' => 'pedagogico',
                'phase_step' => '3',
                'phase_label' => __('Condicionalidades VAAR'),
                'focus' => self::managerialFocus($tab),
            ],
            'work_done' => [
                'phase' => 'censo',
                'phase_step' => '4',
                'phase_label' => __('Fecho Censo'),
                'focus' => self::managerialFocus($tab),
            ],
            default => [
                'phase' => 'cadastro',
                'phase_step' => '2',
                'phase_label' => __('Base cadastral'),
                'focus' => '',
            ],
        };
    }

    private static function managerialFocus(string $tab): string
    {
        return match ($tab) {
            'fundeb' => __('Previsão VAAF/VAAR e condicionalidades'),
            'finance_realtime' => __('Repasses observados × expectativa'),
            'comparativo' => __('Evolução anual e projeção'),
            'other_funding' => __('Programas complementares (PNAE, PDDE…)'),
            'enrollment' => __('Volume, ocupação e distorção idade-série'),
            'cadunico_previsao' => __('Lacuna territorial 4–17 anos'),
            'network' => __('Capacidade e vagas ociosas'),
            'school_units' => __('Georreferência e lista de espera'),
            'inclusion' => __('NEE, AEE e recurso de prova'),
            'performance' => __('Aprovação, evasão e SAEB'),
            'attendance' => __('Frequência por período'),
            'work_done' => __('Ritmo Educacenso e meta de fecho'),
            default => '',
        };
    }

    /**
     * @return array{tone: string, icon: string}
     */
    private static function tabMeta(string $tab): array
    {
        return match ($tab) {
            'enrollment' => ['tone' => 'indigo', 'icon' => 'users'],
            'cadunico_previsao' => ['tone' => 'sky', 'icon' => 'user-group'],
            'network' => ['tone' => 'blue', 'icon' => 'share'],
            'school_units' => ['tone' => 'indigo', 'icon' => 'map-pin'],
            'inclusion' => ['tone' => 'violet', 'icon' => 'users'],
            'performance' => ['tone' => 'indigo', 'icon' => 'chart-bar'],
            'attendance' => ['tone' => 'amber', 'icon' => 'calendar-days'],
            'work_done' => ['tone' => 'sky', 'icon' => 'document-chart'],
            'fundeb' => ['tone' => 'blue', 'icon' => 'banknotes'],
            'finance_realtime' => ['tone' => 'amber', 'icon' => 'signal'],
            'comparativo' => ['tone' => 'blue', 'icon' => 'arrows-right-left'],
            'other_funding' => ['tone' => 'amber', 'icon' => 'building-library'],
            default => ['tone' => 'blue', 'icon' => 'clipboard-check'],
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
            return __('Em linha');
        }

        $summary = is_array($health['summary'] ?? null) ? $health['summary'] : [];

        return match ($tab) {
            'enrollment' => ($summary['total_matriculas'] ?? null) !== null
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
            'enrollment' => __('matrículas ativas'),
            'fundeb' => __('módulos VAAR em alerta'),
            'other_funding' => __('programas em alerta'),
            'work_done' => __('pendências Censo'),
            'inclusion' => __('recurso prova s/ NEE'),
            'cadunico_previsao' => __('lacuna CadÚnico'),
            'finance_realtime' => __('diferença repasse'),
            'comparativo' => __('ano base'),
            'network' => __('ociosidade'),
            'school_units' => __('cobertura geo'),
            'performance' => __('indicador pedagógico'),
            'attendance' => __('registo faltas'),
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
        string $phase,
        string $phaseStep,
        string $phaseLabel,
        string $focus,
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
            'phase' => $phase,
            'phase_step' => $phaseStep,
            'phase_label' => $phaseLabel,
            'focus' => $focus,
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
            'success' => __('Em linha'),
            'warning' => __('Revisar'),
            'danger' => __('Priorizar'),
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
