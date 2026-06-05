<?php

namespace App\Support\Dashboard;

use App\Models\User;

/**
 * Ordem e agrupamento das abas do painel — resumo → cadastro → pedagógico → censo → finanças.
 */
final class AnalyticsTabCatalog
{
    public const GROUP_RESUMO = 'resumo';

    public const GROUP_CENSO = 'censo';

    public const GROUP_FINANCE = 'consultoria';

    /**
     * @return list<array{id: string, label: string, tabs: list<string>}>
     */
    public static function groups(): array
    {
        return [
            [
                'id' => self::GROUP_RESUMO,
                'label' => __('Resumo executivo'),
                'tabs' => [
                    'municipality_health',
                ],
            ],
            [
                'id' => 'cadastro',
                'label' => __('Cadastro e rede'),
                'tabs' => [
                    'overview',
                    'enrollment',
                    'cadunico_previsao',
                    'network',
                    'school_units',
                ],
            ],
            [
                'id' => 'pedagogico',
                'label' => __('Indicadores pedagógicos'),
                'tabs' => [
                    'inclusion',
                    'performance',
                    'attendance',
                ],
            ],
            [
                'id' => self::GROUP_CENSO,
                'label' => __('Censo e cadastro'),
                'tabs' => [
                    'work_done',
                ],
            ],
            [
                'id' => self::GROUP_FINANCE,
                'label' => __('Finanças e repasses'),
                'tabs' => [
                    'discrepancies',
                    'fundeb',
                    'finance_realtime',
                    'comparativo',
                    'other_funding',
                ],
            ],
        ];
    }

    /**
     * Metadados para navegação em dois níveis (área temática → sub-aba).
     *
     * @return array<string, array{step: string, short: string, hint: string, tone: string}>
     */
    public static function groupPresentation(): array
    {
        return [
            'resumo' => [
                'step' => '1',
                'short' => __('Resumo'),
                'hint' => __('Diagnóstico executivo, prioridades e explorar em detalhe'),
                'tone' => 'teal',
            ],
            'cadastro' => [
                'step' => '2',
                'short' => __('Cadastro'),
                'hint' => __('Visão da rede, matrículas, CadÚnico (previsão) e unidades'),
                'tone' => 'indigo',
            ],
            'pedagogico' => [
                'step' => '3',
                'short' => __('Pedagógico'),
                'hint' => __('Inclusão, desempenho e frequência'),
                'tone' => 'violet',
            ],
            'censo' => [
                'step' => '4',
                'short' => __('Censo'),
                'hint' => __('Ritmo de cadastro e exportação Educacenso'),
                'tone' => 'sky',
            ],
            'consultoria' => [
                'step' => '5',
                'short' => __('Finanças'),
                'hint' => __('Discrepâncias, FUNDEB, repasses, comparativo e programas'),
                'tone' => 'teal',
            ],
        ];
    }

    /**
     * Frase curta por sub-aba (tooltip / legenda sob o menu).
     *
     * @return array<string, string>
     */
    public static function tabHints(): array
    {
        return [
            'municipality_health' => __('Resumo executivo e prioridades'),
            'comparativo' => __('Evolução anual e projeção FUNDEB'),
            'discrepancies' => __('Erros de cadastro com impacto financeiro'),
            'fundeb' => __('VAAF, VAAR e previsão de repasse'),
            'finance_realtime' => __('Repasses observados × expectativa (Tesouro/BB)'),
            'other_funding' => __('PNAE, PNATE, PDDE e fontes públicas'),
            'work_done' => __('Educacenso, ritmo de cadastro e fecho do ano'),
            'overview' => __('Totais de escolas, turmas e matrículas'),
            'enrollment' => __('Matrículas, distorção e ocupação'),
            'cadunico_previsao' => __('CadÚnico: previsão fora da rede e FUNDEB'),
            'network' => __('Vagas, turnos e oferta da rede'),
            'school_units' => __('Mapa, unidades e lista de espera'),
            'inclusion' => __('NEE, equidade e recurso de prova'),
            'performance' => __('Aprovação, evasão e SAEB'),
            'attendance' => __('Frequência por período'),
        ];
    }

    /**
     * @return array<string, string> tab_id => group_id
     */
    public static function tabToGroupMap(): array
    {
        $map = [];
        foreach (self::groups() as $group) {
            $gid = (string) ($group['id'] ?? '');
            foreach ($group['tabs'] ?? [] as $tabId) {
                $map[(string) $tabId] = $gid;
            }
        }

        return $map;
    }

    public static function groupIdForTab(string $tab): ?string
    {
        return self::tabToGroupMap()[$tab] ?? null;
    }

    /**
     * Payload para Alpine (navegação do painel).
     *
     * @return array{
     *   groups: list<array{id: string, label: string, short: string, step: string, hint: string, tone: string, tabs: list<string>}>,
     *   tabLabels: array<string, string>,
     *   tabHints: array<string, string>,
     *   tabToGroup: array<string, string>
     * }
     */
    public static function navigationPayload(): array
    {
        $presentation = self::groupPresentation();
        $labels = self::labels();
        $hints = self::tabHints();
        $groups = [];

        foreach (self::groups() as $group) {
            $id = (string) ($group['id'] ?? '');
            $pres = $presentation[$id] ?? [];
            $groups[] = [
                'id' => $id,
                'label' => (string) ($group['label'] ?? ''),
                'short' => (string) ($pres['short'] ?? $group['label'] ?? ''),
                'step' => (string) ($pres['step'] ?? ''),
                'hint' => (string) ($pres['hint'] ?? ''),
                'tone' => (string) ($pres['tone'] ?? 'teal'),
                'tabs' => array_values(array_filter(
                    $group['tabs'] ?? [],
                    static fn (string $k): bool => isset($labels[$k]),
                )),
            ];
        }

        return [
            'groups' => $groups,
            'tabLabels' => $labels,
            'tabHints' => $hints,
            'tabToGroup' => self::tabToGroupMap(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'municipality_health' => __('Diagnóstico'),
            'comparativo' => __('Comparativo'),
            'discrepancies' => __('Discrepâncias'),
            'fundeb' => __('FUNDEB'),
            'finance_realtime' => __('Tempo Real'),
            'other_funding' => __('Financiamentos'),
            'work_done' => __('Censo'),
            'overview' => __('Visão geral'),
            'enrollment' => __('Matrículas'),
            'cadunico_previsao' => __('CadÚnico'),
            'network' => __('Rede & oferta'),
            'school_units' => __('Unidades'),
            'inclusion' => __('Inclusão'),
            'performance' => __('Desempenho'),
            'attendance' => __('Frequência'),
        ];
    }

    /**
     * @return array<string, string> Chave => rótulo na ordem de navegação
     */
    public static function tabsOrdered(): array
    {
        $labels = self::labels();
        $out = [];
        foreach (self::groups() as $group) {
            foreach ($group['tabs'] as $key) {
                if (isset($labels[$key])) {
                    $out[$key] = $labels[$key];
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function tabKeys(): array
    {
        return array_keys(self::tabsOrdered());
    }

    public static function isValidTab(string $tab): bool
    {
        return in_array($tab, self::tabKeys(), true);
    }

    public static function resolveInitialTab(string $requestedTab, User $user, bool $yearFilterReady): string
    {
        if (self::isValidTab($requestedTab)) {
            return $requestedTab;
        }

        if ($yearFilterReady) {
            return 'municipality_health';
        }

        return 'overview';
    }

    /**
     * Abas com faixa de impacto no topo.
     *
     * @return list<string>
     */
    public static function tabsWithImpactStrip(): array
    {
        return [
            'overview',
            'enrollment',
            'cadunico_previsao',
            'network',
            'school_units',
            'inclusion',
            'performance',
            'attendance',
            'work_done',
            'municipality_health',
            'discrepancies',
            'fundeb',
            'other_funding',
            'comparativo',
        ];
    }

    /**
     * @return list<string>
     */
    public static function financeGroupTabKeys(): array
    {
        $tabs = [];
        foreach (self::groups() as $group) {
            if (($group['id'] ?? '') === self::GROUP_FINANCE) {
                $tabs = $group['tabs'] ?? [];
                break;
            }
        }

        return array_values($tabs);
    }

    public static function isFinanceGroupTab(string $tab): bool
    {
        return in_array($tab, self::financeGroupTabKeys(), true);
    }

    public static function isCensoGroupTab(string $tab): bool
    {
        return self::groupIdForTab($tab) === self::GROUP_CENSO;
    }

    public static function isResumoGroupTab(string $tab): bool
    {
        return self::groupIdForTab($tab) === self::GROUP_RESUMO;
    }
}
