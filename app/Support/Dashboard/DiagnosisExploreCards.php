<?php

namespace App\Support\Dashboard;

/**
 * Cartões «Explorar em detalhe» do Diagnóstico — métrica e status por área (não o índice global).
 */
final class DiagnosisExploreCards
{
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
        $summary = is_array($health['summary'] ?? null) ? $health['summary'] : [];
        $blocks = is_array($health['thematic_blocks'] ?? null) ? $health['thematic_blocks'] : [];
        $labels = AnalyticsTabCatalog::labels();
        $hints = AnalyticsTabCatalog::tabHints();

        $pendencias = (int) ($summary['pendencias_cadastro'] ?? 0);
        $ocorrencias = (int) ($summary['com_problema'] ?? 0);
        $corrigiveis = (int) ($summary['corrigiveis'] ?? 0);
        $modulosAlerta = (int) ($summary['modulos_fundeb_alerta'] ?? 0);
        $programasAlerta = (int) ($health['programas_alerta'] ?? 0);
        $censoPendentes = (int) ($summary['censo_pendentes'] ?? 0);
        $cadastrosQuinzena = (int) ($summary['cadastros_quinzena'] ?? 0);
        $recursoSemNee = (int) ($summary['recurso_prova_sem_nee'] ?? 0);
        $workDoneAvailable = (bool) ($health['work_done_available'] ?? false);

        $cards = [
            self::cardDiscrepancies($pendencias, $ocorrencias, $corrigiveis, $blocks, $labels, $hints),
            self::cardFundeb($modulosAlerta, is_array($health['fundeb_modules'] ?? null) ? $health['fundeb_modules'] : [], $blocks, $labels, $hints),
            self::cardOtherFunding($programasAlerta, (int) ($health['other_funding_programs'] ?? 0), $blocks, $labels, $hints),
            self::cardWorkDone($censoPendentes, $cadastrosQuinzena, $workDoneAvailable, $blocks, $labels, $hints),
            self::cardInclusion($recursoSemNee, $pendencias, $blocks, $labels, $hints),
            self::cardPerformance($blocks, $labels, $hints),
        ];

        usort($cards, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $cards;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private static function cardDiscrepancies(
        int $pendencias,
        int $ocorrencias,
        int $corrigiveis,
        array $blocks,
        array $labels,
        array $hints,
    ): array {
        $blockStatus = self::worstBlockStatus($blocks, 'discrepancies');
        $status = self::mergeStatus(
            $blockStatus,
            match (true) {
                $pendencias >= 3 || $ocorrencias >= 100 => 'danger',
                $pendencias > 0 || $ocorrencias > 0 => 'warning',
                default => 'success',
            }
        );

        $metricValue = $ocorrencias > 0
            ? number_format($ocorrencias)
            : ($pendencias > 0 ? number_format($pendencias) : '0');
        $metricLabel = $ocorrencias > 0
            ? __('ocorrências')
            : __('rotinas c/ pendência');

        $detail = $pendencias > 0
            ? __(':n tipo(s) de rotina · :c corrigível(is)', ['n' => number_format($pendencias), 'c' => number_format($corrigiveis)])
            : __('Nenhuma pendência detectada no filtro.');

        return self::baseCard(
            tab: 'discrepancies',
            order: 1,
            group: __('Finanças'),
            tone: 'rose',
            icon: 'clipboard-check',
            label: $labels['discrepancies'] ?? __('Discrepâncias'),
            hint: $hints['discrepancies'] ?? '',
            metricValue: $metricValue,
            metricLabel: $metricLabel,
            metricDetail: $detail,
            status: $status,
            legend: __('Soma de ocorrências nas rotinas de cadastro com impacto em repasses.'),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $modules
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private static function cardFundeb(
        int $modulosAlerta,
        array $modules,
        array $blocks,
        array $labels,
        array $hints,
    ): array {
        $hasDanger = count(array_filter($modules, static fn (array $m): bool => ($m['status'] ?? '') === 'danger')) > 0;
        $blockStatus = self::worstBlockStatus($blocks, 'fundeb');
        $status = self::mergeStatus(
            $blockStatus,
            match (true) {
                $hasDanger => 'danger',
                $modulosAlerta > 0 => 'warning',
                default => 'success',
            }
        );

        return self::baseCard(
            tab: 'fundeb',
            order: 2,
            group: __('Finanças'),
            tone: 'teal',
            icon: 'banknotes',
            label: $labels['fundeb'] ?? __('FUNDEB'),
            hint: $hints['fundeb'] ?? '',
            metricValue: number_format($modulosAlerta),
            metricLabel: __('módulos VAAR em alerta'),
            metricDetail: $modulosAlerta > 0
                ? __('Revise condicionalidades antes do repasse.')
                : __('Roteiro VAAR sem alertas no filtro.'),
            status: $status,
            legend: __('Condicionalidades FUNDEB (VAAR) derivadas do cadastro e documentação.'),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private static function cardOtherFunding(
        int $programasAlerta,
        int $totalProgramas,
        array $blocks,
        array $labels,
        array $hints,
    ): array {
        $blockStatus = self::worstBlockStatus($blocks, 'other_funding');
        $status = self::mergeStatus(
            $blockStatus,
            match (true) {
                $programasAlerta >= 2 => 'danger',
                $programasAlerta > 0 => 'warning',
                default => 'success',
            }
        );

        return self::baseCard(
            tab: 'other_funding',
            order: 3,
            group: __('Finanças'),
            tone: 'amber',
            icon: 'building-library',
            label: $labels['other_funding'] ?? __('Financiamentos'),
            hint: $hints['other_funding'] ?? '',
            metricValue: number_format($programasAlerta),
            metricLabel: __('programas em alerta'),
            metricDetail: $totalProgramas > 0
                ? __(':a de :t monitorados (PNAE, PNATE, PDDE…)', ['a' => number_format($programasAlerta), 't' => number_format($totalProgramas)])
                : __('Abra a aba para cobertura de campos.'),
            status: $status,
            legend: __('Cobertura de campos no i-Educar exigida pelos programas complementares.'),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private static function cardWorkDone(
        int $censoPendentes,
        int $cadastrosQuinzena,
        bool $available,
        array $blocks,
        array $labels,
        array $hints,
    ): array {
        $blockStatus = self::worstBlockStatus($blocks, 'work_done');
        $status = self::mergeStatus(
            $blockStatus,
            match (true) {
                $censoPendentes > 0 => 'warning',
                $cadastrosQuinzena > 0 => 'success',
                ! $available => 'neutral',
                default => 'neutral',
            }
        );

        if ($censoPendentes > 0) {
            $metricValue = number_format($censoPendentes);
            $metricLabel = __('escolas pendentes Censo');
            $detail = __('Exportação Educacenso incompleta no filtro.');
        } elseif ($cadastrosQuinzena > 0) {
            $metricValue = number_format($cadastrosQuinzena);
            $metricLabel = __('cadastros (quinzena)');
            $detail = __('Ritmo recente de cadastro no i-Educar.');
        } elseif ($available) {
            $metricValue = '0';
            $metricLabel = __('pendências Censo');
            $detail = __('Sem alertas de exportação no filtro.');
        } else {
            $metricValue = '—';
            $metricLabel = __('ritmo de cadastro');
            $detail = __('Dados de Censo indisponíveis — aplique filtros ou abra a área Censo.');
        }

        return self::baseCard(
            tab: 'work_done',
            order: 4,
            group: __('Censo'),
            tone: 'sky',
            icon: 'document-chart',
            label: $labels['work_done'] ?? __('Censo'),
            hint: $hints['work_done'] ?? '',
            metricValue: $metricValue,
            metricLabel: $metricLabel,
            metricDetail: $detail,
            status: $status,
            legend: __('Ritmo de cadastro e situação da exportação Educacenso.'),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private static function cardInclusion(
        int $recursoSemNee,
        int $pendenciasCadastro,
        array $blocks,
        array $labels,
        array $hints,
    ): array {
        $blockStatus = self::worstBlockStatus($blocks, 'inclusion');
        $status = self::mergeStatus(
            $blockStatus,
            match (true) {
                $recursoSemNee >= 10 => 'danger',
                $recursoSemNee > 0 => 'warning',
                default => 'success',
            }
        );

        $metricValue = $recursoSemNee > 0
            ? number_format($recursoSemNee)
            : '0';
        $detail = $recursoSemNee > 0
            ? __('Recurso INEP sem cadastro NEE — ver Discrepâncias.')
            : __('Sem inconsistências NEE críticas no resumo.');

        return self::baseCard(
            tab: 'inclusion',
            order: 5,
            group: __('Pedagógico'),
            tone: 'violet',
            icon: 'users',
            label: $labels['inclusion'] ?? __('Inclusão'),
            hint: $hints['inclusion'] ?? '',
            metricValue: $metricValue,
            metricLabel: __('recurso prova s/ NEE'),
            metricDetail: $detail,
            status: $status,
            legend: __('Equidade e educação especial — cadastro NEE e turmas AEE.'),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private static function cardPerformance(array $blocks, array $labels, array $hints): array
    {
        $block = self::findBlock($blocks, 'performance');
        $blockStatus = is_array($block) ? (string) ($block['status'] ?? 'neutral') : 'neutral';
        $hasSaeb = is_array($block) && count($block['items'] ?? []) > 0
            && $blockStatus === 'success';

        $status = $hasSaeb ? 'success' : ($blockStatus !== 'neutral' ? $blockStatus : 'neutral');

        return self::baseCard(
            tab: 'performance',
            order: 6,
            group: __('Pedagógico'),
            tone: 'indigo',
            icon: 'chart-bar',
            label: $labels['performance'] ?? __('Desempenho'),
            hint: $hints['performance'] ?? '',
            metricValue: $hasSaeb ? __('OK') : '—',
            metricLabel: __('SAEB / aprendizagem'),
            metricDetail: $hasSaeb
                ? __('Série pedagógica disponível no filtro.')
                : __('Importe SAEB ou consulte a aba Desempenho.'),
            status: $status,
            legend: __('Indicadores externos INEP quando sincronizados no painel.'),
        );
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
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    private static function findBlock(array $blocks, string $tab): ?array
    {
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['tab_link'] ?? '') === $tab) {
                return $block;
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
