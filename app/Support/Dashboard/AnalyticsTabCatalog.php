<?php

namespace App\Support\Dashboard;

use App\Models\User;

/**
 * Ordem e agrupamento das abas do painel — cadastro → pedagógico → finanças.
 */
final class AnalyticsTabCatalog
{
    /**
     * @return list<array{id: string, label: string, tabs: list<string>}>
     */
    public static function groups(): array
    {
        return [
            [
                'id' => 'cadastro',
                'label' => __('Cadastro e rede'),
                'tabs' => [
                    'overview',
                    'enrollment',
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
                'id' => 'consultoria',
                'label' => __('Finanças e repasses'),
                'tabs' => [
                    'municipality_health',
                    'discrepancies',
                    'fundeb',
                    'other_funding',
                    'work_done',
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
            'cadastro' => [
                'step' => '1',
                'short' => __('Cadastro'),
                'hint' => __('Visão da rede, matrículas e unidades'),
                'tone' => 'indigo',
            ],
            'pedagogico' => [
                'step' => '2',
                'short' => __('Pedagógico'),
                'hint' => __('Inclusão, desempenho e frequência'),
                'tone' => 'violet',
            ],
            'consultoria' => [
                'step' => '3',
                'short' => __('Finanças'),
                'hint' => __('Diagnóstico, discrepâncias, FUNDEB e Censo'),
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
            'discrepancies' => __('Erros de cadastro com impacto financeiro'),
            'fundeb' => __('VAAF, VAAR e previsão de repasse'),
            'other_funding' => __('PNAE, PNATE, PDDE e fontes públicas'),
            'work_done' => __('Ritmo de cadastro e exportação Censo'),
            'overview' => __('Totais de escolas, turmas e matrículas'),
            'enrollment' => __('Matrículas, distorção e ocupação'),
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
            'discrepancies' => __('Discrepâncias'),
            'fundeb' => __('FUNDEB'),
            'other_funding' => __('Financiamentos'),
            'work_done' => __('Censo'),
            'overview' => __('Visão geral'),
            'enrollment' => __('Matrículas'),
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

        return 'overview';
    }

    /**
     * Abas com faixa de impacto no topo (até Censo).
     *
     * @return list<string>
     */
    public static function tabsWithImpactStrip(): array
    {
        return [
            'overview',
            'enrollment',
            'network',
            'school_units',
            'inclusion',
            'performance',
            'attendance',
            'fundeb',
            'other_funding',
            'work_done',
        ];
    }
}
