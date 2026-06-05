<?php

namespace App\Support\Admin;

/**
 * Metadados do hub admin de importação (navegação e documentação).
 */
final class AdminImportHubCatalog
{
    /**
     * @return list<array{
     *     key: string,
     *     route: string,
     *     label: string,
     *     hint: string,
     *     accent: string,
     *     icon: string,
     *     fragment: ?string,
     *     query: ?array<string, string>
     * }>
     */
    public static function navItems(): array
    {
        return [
            [
                'key' => 'hub',
                'route' => 'admin.public-data.index',
                'query' => ['hub' => 'hub'],
                'fragment' => null,
                'label' => __('Hub'),
                'hint' => __('Visão geral e lacunas PDF'),
                'accent' => AdminVisualCatalog::shellAccentForHubKey('hub'),
                'icon' => 'squares-2x2',
            ],
            [
                'key' => 'repasses',
                'route' => 'admin.public-data.index',
                'query' => ['hub' => 'repasses'],
                'fragment' => 'source-repasses_tesouro',
                'label' => __('Repasses'),
                'hint' => __('Tempo Real — CKAN, SISWEB, BB'),
                'accent' => AdminVisualCatalog::shellAccentForHubKey('repasses'),
                'icon' => 'banknotes',
            ],
            [
                'key' => 'fundeb',
                'route' => 'admin.ieducar-compatibility.index',
                'query' => null,
                'fragment' => null,
                'label' => __('VAAF'),
                'hint' => __('VAAF / VAAT / VAAR — FNDE'),
                'accent' => AdminVisualCatalog::shellAccentForHubKey('fundeb'),
                'icon' => 'banknotes',
            ],
            [
                'key' => 'geo',
                'route' => 'admin.geo-sync.index',
                'query' => null,
                'fragment' => null,
                'label' => __('Geo'),
                'hint' => __('Mapa e coordenadas'),
                'accent' => AdminVisualCatalog::shellAccentForHubKey('geo'),
                'icon' => 'map-pin',
            ],
            [
                'key' => 'pedagogical',
                'route' => 'admin.pedagogical-sync.index',
                'query' => null,
                'fragment' => null,
                'label' => __('SAEB'),
                'hint' => __('Desempenho INEP'),
                'accent' => AdminVisualCatalog::shellAccentForHubKey('pedagogical'),
                'icon' => 'academic-cap',
            ],
            [
                'key' => 'cadastro',
                'route' => 'admin.cadunico-sync.index',
                'query' => null,
                'fragment' => null,
                'label' => __('CadÚnico'),
                'hint' => __('Cecad / Misocial'),
                'accent' => AdminVisualCatalog::shellAccentForHubKey('cadastro'),
                'icon' => 'users',
            ],
            [
                'key' => 'queue',
                'route' => 'admin.sync-queue.index',
                'query' => null,
                'fragment' => null,
                'label' => __('Fila'),
                'hint' => __('Tarefas e automação'),
                'accent' => AdminVisualCatalog::shellAccentForHubKey('queue'),
                'icon' => 'queue-list',
            ],
        ];
    }

    public static function navHref(array $item): string
    {
        $params = $item['query'] ?? [];
        $url = $params === []
            ? route($item['route'])
            : route($item['route'], $params);

        if (filled($item['fragment'] ?? null)) {
            $url .= '#'.($item['fragment']);
        }

        return $url;
    }

    public static function resolveHubActive(?string $hub): string
    {
        $hub = (string) ($hub ?? 'hub');
        $keys = array_column(self::navItems(), 'key');

        return in_array($hub, $keys, true) ? $hub : 'hub';
    }

    /**
     * @return array<string, string>
     */
    public static function statusBadgeClasses(): array
    {
        return [
            'ok' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200',
            'partial' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
            'warn' => 'bg-rose-100 text-rose-900 dark:bg-rose-950/50 dark:text-rose-200',
            'info' => 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200',
            'neutral' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        ];
    }
}
