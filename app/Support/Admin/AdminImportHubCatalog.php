<?php

namespace App\Support\Admin;

/**
 * Metadados do hub admin de importação (navegação e documentação).
 */
final class AdminImportHubCatalog
{
    /**
     * @return list<array{key: string, route: string, label: string, hint: string, accent: string, icon: string}>
     */
    public static function navItems(): array
    {
        return [
            [
                'key' => 'hub',
                'route' => 'admin.public-data.index',
                'label' => __('Hub'),
                'hint' => __('Visão geral e lacunas PDF'),
                'accent' => ImportHubThemeCatalog::shellAccentForHubKey('hub'),
                'icon' => 'squares-2x2',
            ],
            [
                'key' => 'fundeb',
                'route' => 'admin.ieducar-compatibility.index',
                'label' => __('FUNDEB'),
                'hint' => __('VAAF / VAAT / VAAR'),
                'accent' => ImportHubThemeCatalog::shellAccentForHubKey('fundeb'),
                'icon' => 'banknotes',
            ],
            [
                'key' => 'cadastro',
                'route' => 'admin.cadunico-sync.index',
                'label' => __('CadÚnico'),
                'hint' => __('Cecad / Misocial'),
                'accent' => ImportHubThemeCatalog::shellAccentForHubKey('cadastro'),
                'icon' => 'users',
            ],
            [
                'key' => 'geo',
                'route' => 'admin.geo-sync.index',
                'label' => __('Geo'),
                'hint' => __('Mapa e coordenadas'),
                'accent' => ImportHubThemeCatalog::shellAccentForHubKey('geo'),
                'icon' => 'map-pin',
            ],
            [
                'key' => 'pedagogical',
                'route' => 'admin.pedagogical-sync.index',
                'label' => __('SAEB'),
                'hint' => __('Desempenho INEP'),
                'accent' => ImportHubThemeCatalog::shellAccentForHubKey('pedagogical'),
                'icon' => 'academic-cap',
            ],
            [
                'key' => 'queue',
                'route' => 'admin.sync-queue.index',
                'label' => __('Fila'),
                'hint' => __('Tarefas e automação'),
                'accent' => ImportHubThemeCatalog::shellAccentForHubKey('queue'),
                'icon' => 'queue-list',
            ],
        ];
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
