<?php

namespace App\Support\Admin;

/**
 * Navegação e tons das telas admin fora do hub de importação (municípios, LGPD, etc.).
 */
final class AdminScreenCatalog
{
    public const GROUP_MUNICIPALITIES = 'municipalities';

    public const GROUP_ADMINISTRATION = 'administration';

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
    public static function navItems(string $group): array
    {
        return match ($group) {
            self::GROUP_MUNICIPALITIES => [
                [
                    'key' => 'cities',
                    'route' => 'cities.index',
                    'query' => null,
                    'fragment' => null,
                    'label' => __('Cidades'),
                    'hint' => __('Cadastro, IBGE e activação'),
                    'accent' => 'violet',
                    'icon' => 'map-pin',
                ],
                [
                    'key' => 'connections',
                    'route' => 'admin.connections.index',
                    'query' => null,
                    'fragment' => null,
                    'label' => __('Conexões i-Educar'),
                    'hint' => __('Testar banco i-Educar'),
                    'accent' => 'indigo',
                    'icon' => 'circle-stack',
                ],
                [
                    'key' => 'fundeb',
                    'route' => 'admin.ieducar-compatibility.index',
                    'query' => null,
                    'fragment' => null,
                    'label' => __('admin_ieducar_compatibility.hub.tab_label'),
                    'hint' => __('admin_ieducar_compatibility.hub.tab_hint'),
                    'accent' => 'amber',
                    'icon' => 'banknotes',
                ],
            ],
            self::GROUP_ADMINISTRATION => [
                [
                    'key' => 'legal-documents',
                    'route' => 'admin.legal-documents.index',
                    'query' => null,
                    'fragment' => null,
                    'label' => __('Documentos'),
                    'hint' => __('Política de privacidade e cookies'),
                    'accent' => 'rose',
                    'icon' => 'document-text',
                ],
                [
                    'key' => 'legal-consents',
                    'route' => 'admin.legal-consents.index',
                    'query' => null,
                    'fragment' => null,
                    'label' => __('Consentimentos'),
                    'hint' => __('Aceites LGPD e auditoria'),
                    'accent' => 'rose',
                    'icon' => 'shield-check',
                ],
            ],
            default => [],
        };
    }

    public static function shellAccentForScreen(string $group, string $key): string
    {
        foreach (self::navItems($group) as $item) {
            if (($item['key'] ?? '') === $key) {
                return (string) ($item['accent'] ?? 'slate');
            }
        }

        return match ($group) {
            self::GROUP_MUNICIPALITIES => 'violet',
            self::GROUP_ADMINISTRATION => 'rose',
            default => 'slate',
        };
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

    public static function navAriaLabel(string $group): string
    {
        return match ($group) {
            self::GROUP_MUNICIPALITIES => __('Municípios e i-Educar'),
            self::GROUP_ADMINISTRATION => __('Administração e conformidade'),
            default => __('Navegação admin'),
        };
    }
}
