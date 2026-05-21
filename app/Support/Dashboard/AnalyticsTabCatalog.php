<?php

namespace App\Support\Dashboard;

use App\Models\User;

/**
 * Ordem e agrupamento das abas do painel — fluxo consultoria municipal (finanças → cadastro → pedagógico).
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
                'id' => 'consultoria',
                'label' => __('Consultoria & finanças'),
                'tabs' => [
                    'municipality_health',
                    'discrepancies',
                    'fundeb',
                    'other_funding',
                    'work_done',
                ],
            ],
            [
                'id' => 'cadastro',
                'label' => __('Cadastro & rede'),
                'tabs' => [
                    'overview',
                    'enrollment',
                    'network',
                    'school_units',
                ],
            ],
            [
                'id' => 'pedagogico',
                'label' => __('Pedagógico'),
                'tabs' => [
                    'inclusion',
                    'performance',
                    'attendance',
                ],
            ],
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

        if ($yearFilterReady && ! $user->isAdmin()) {
            return 'municipality_health';
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
