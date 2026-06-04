<?php

namespace App\Support\Admin;

use App\Enums\AdminSyncDomain;

/** Ícones, cores e agrupamento temático (filas, hub dados públicos, notificações). */
final class ImportHubThemeCatalog
{
    /** @var list<string> */
    private const DOMAIN_ORDER = [
        'fundeb',
        'funding',
        'geo',
        'pedagogical',
        'cadastro',
        'system',
    ];

    /**
     * @return array{
     *     id: string,
     *     domain: string,
     *     label: string,
     *     description: string,
     *     icon: string,
     *     accent: string,
     *     anchor: string,
     *     hub_anchor: string
     * }
     */
    public static function themeForDomainValue(string $domain): array
    {
        $enum = AdminSyncDomain::tryFrom($domain);
        if ($enum !== null) {
            $syncQueueName = (string) config('ieducar.admin_sync.queue', 'admin-sync');
            $theme = AdminSyncQueueIndexPresenter::themeForDomain($enum, $syncQueueName);
            if ($theme !== null) {
                return self::normalizeTheme($theme, $domain);
            }
        }

        return self::fallbackTheme($domain);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public static function enrichSource(array $source): array
    {
        $domain = (string) ($source['domain'] ?? 'system');
        $theme = self::themeForDomainValue($domain);

        return array_merge($source, [
            'theme_icon' => $theme['icon'],
            'theme_accent' => $theme['accent'],
            'theme_label' => $theme['label'],
            'theme_anchor' => $theme['hub_anchor'],
            'theme_queue_anchor' => $theme['anchor'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @return list<array{theme: array, sources: list<array<string, mixed>>}>
     */
    public static function sectionsForSources(array $sources): array
    {
        $byDomain = [];
        foreach ($sources as $source) {
            $enriched = self::enrichSource($source);
            $domain = (string) ($enriched['domain'] ?? 'system');
            $byDomain[$domain][] = $enriched;
        }

        $sections = [];
        foreach (self::DOMAIN_ORDER as $domain) {
            if (($byDomain[$domain] ?? []) === []) {
                continue;
            }

            $sections[] = [
                'theme' => self::themeForDomainValue($domain),
                'sources' => $byDomain[$domain],
            ];
        }

        return $sections;
    }

    /**
     * @param  list<array{theme: array, sources: list<array<string, mixed>>}>  $sections
     * @return list<array{
     *     id: string,
     *     anchor: string,
     *     label: string,
     *     description: string,
     *     icon: string,
     *     accent: string,
     *     source_count: int,
     *     status_ok: int,
     *     status_alert: int
     * }>
     */
    public static function overviewCardsForSections(array $sections): array
    {
        $cards = [];

        foreach ($sections as $section) {
            $theme = $section['theme'];
            $sources = $section['sources'];
            $statusOk = 0;
            $statusAlert = 0;

            foreach ($sources as $source) {
                $level = (string) (($source['status']['level'] ?? '') ?: 'neutral');
                if ($level === 'ok') {
                    $statusOk++;
                }
                if (in_array($level, ['warn', 'partial'], true)) {
                    $statusAlert++;
                }
            }

            $cards[] = [
                'id' => $theme['id'],
                'anchor' => $theme['hub_anchor'],
                'label' => $theme['label'],
                'description' => $theme['description'],
                'icon' => $theme['icon'],
                'accent' => $theme['accent'],
                'source_count' => count($sources),
                'status_ok' => $statusOk,
                'status_alert' => $statusAlert,
            ];
        }

        return $cards;
    }

    public static function shellAccentForHubKey(string $hubKey): string
    {
        return match ($hubKey) {
            'hub' => 'emerald',
            'fundeb' => 'amber',
            'cadastro' => 'violet',
            'geo' => 'sky',
            'pedagogical' => 'violet',
            'queue' => 'slate',
            default => 'indigo',
        };
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, mixed>
     */
    private static function normalizeTheme(array $theme, string $domain): array
    {
        $id = (string) ($theme['id'] ?? $domain);

        $normalized = [
            'id' => $id,
            'domain' => $domain,
            'label' => (string) ($theme['label'] ?? $domain),
            'description' => (string) ($theme['description'] ?? ''),
            'icon' => (string) ($theme['icon'] ?? 'queue-list'),
            'accent' => (string) ($theme['accent'] ?? 'slate'),
            'anchor' => (string) ($theme['anchor'] ?? 'fila-'.$id),
            'hub_anchor' => 'hub-'.$id,
        ];

        $adminRoute = $theme['admin_route'] ?? self::adminRouteForDomain($domain);
        if (filled($adminRoute)) {
            $normalized['admin_route'] = (string) $adminRoute;
        }

        return $normalized;
    }

    private static function adminRouteForDomain(string $domain): ?string
    {
        return match ($domain) {
            'fundeb' => 'admin.ieducar-compatibility.index',
            'cadastro' => 'admin.cadunico-sync.index',
            'geo' => 'admin.geo-sync.index',
            'pedagogical' => 'admin.pedagogical-sync.index',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallbackTheme(string $domain): array
    {
        return [
            'id' => $domain,
            'domain' => $domain,
            'label' => ucfirst($domain),
            'description' => '',
            'icon' => 'queue-list',
            'accent' => 'slate',
            'anchor' => 'fila-'.$domain,
            'hub_anchor' => 'hub-'.$domain,
        ];
    }
}
