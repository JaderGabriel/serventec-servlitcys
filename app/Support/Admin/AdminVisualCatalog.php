<?php

namespace App\Support\Admin;

/**
 * Padrão visual admin: cores, ícones e variantes por domínio/ação.
 */
final class AdminVisualCatalog
{
    /**
     * @return array{accent: string, icon: string, label: string}
     */
    public static function domainTone(string $domain): array
    {
        return match ($domain) {
            'fundeb' => ['accent' => 'amber', 'icon' => 'banknotes', 'label' => __('admin_ieducar_compatibility.hub.tab_label')],
            'funding' => ['accent' => 'emerald', 'icon' => 'banknotes', 'label' => __('Repasses / Tempo Real')],
            'geo' => ['accent' => 'sky', 'icon' => 'map-pin', 'label' => __('Geográfica')],
            'pedagogical' => ['accent' => 'violet', 'icon' => 'academic-cap', 'label' => __('SAEB / INEP')],
            'cadastro' => ['accent' => 'fuchsia', 'icon' => 'users', 'label' => __('CadÚnico / Cecad')],
            'ieducar' => ['accent' => 'indigo', 'icon' => 'circle-stack', 'label' => __('i-Educar')],
            'system' => ['accent' => 'slate', 'icon' => 'command-line', 'label' => __('Sistema')],
            default => ['accent' => 'slate', 'icon' => 'queue-list', 'label' => ucfirst($domain)],
        };
    }

    public static function shellAccentForHubKey(string $hubKey): string
    {
        return match ($hubKey) {
            'hub' => 'emerald',
            'repasses' => 'emerald',
            'fundeb' => 'amber',
            'cadastro' => 'fuchsia',
            'geo' => 'sky',
            'pedagogical' => 'violet',
            'queue' => 'slate',
            default => 'indigo',
        };
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    public static function enrichAction(array $action): array
    {
        $key = (string) ($action['key'] ?? '');
        $flow = self::actionFlowMeta($key);

        return array_merge($action, array_filter([
            'variant' => $action['variant'] ?? $flow['variant'],
            'tags' => $action['tags'] ?? $flow['tags'],
            'step' => $action['step'] ?? $flow['step'],
            'submit_accent' => $action['submit_accent'] ?? $flow['submit_accent'],
            'icon' => $action['icon'] ?? $flow['icon'],
        ], static fn ($v) => $v !== null && $v !== []));
    }

    /**
     * @return array{variant: string, tags: list<string>, step: ?string, submit_accent: ?string, icon: ?string}
     */
    public static function actionFlowMeta(string $key): array
    {
        if (str_starts_with($key, 'rebuild_')) {
            return [
                'variant' => 'warning',
                'tags' => [__('Rebuild'), __('Destrutivo')],
                'step' => __('Purgar + reimportar'),
                'submit_accent' => 'amber',
                'icon' => 'arrow-path',
            ];
        }

        if (str_contains($key, 'import_transfers') || str_contains($key, 'transfers')) {
            return [
                'variant' => 'primary',
                'tags' => [__('Importar'), __('Municipal')],
                'step' => __('Enfileirar repasses'),
                'submit_accent' => 'emerald',
                'icon' => 'banknotes',
            ];
        }

        if (in_array($key, ['auto_sync', 'weekly_mass_sync'], true)) {
            return [
                'variant' => 'primary',
                'tags' => [__('Automático')],
                'step' => null,
                'submit_accent' => 'emerald',
                'icon' => null,
            ];
        }

        if (str_starts_with($key, 'import_')) {
            return [
                'variant' => 'default',
                'tags' => [__('Importar')],
                'step' => null,
                'submit_accent' => null,
                'icon' => null,
            ];
        }

        return [
            'variant' => 'default',
            'tags' => [],
            'step' => null,
            'submit_accent' => null,
            'icon' => null,
        ];
    }

    public static function submitButtonClasses(string $accent = 'indigo'): string
    {
        $base = 'inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-gray-900';

        return match ($accent) {
            'emerald' => $base.' bg-emerald-600 hover:bg-emerald-500 focus:ring-emerald-500',
            'amber' => $base.' bg-amber-600 hover:bg-amber-500 focus:ring-amber-500',
            'sky' => $base.' bg-sky-600 hover:bg-sky-500 focus:ring-sky-500',
            'violet' => $base.' bg-violet-600 hover:bg-violet-500 focus:ring-violet-500',
            'fuchsia' => $base.' bg-fuchsia-600 hover:bg-fuchsia-500 focus:ring-fuchsia-500',
            'rose' => $base.' bg-rose-600 hover:bg-rose-500 focus:ring-rose-500',
            'slate' => $base.' bg-slate-600 hover:bg-slate-500 focus:ring-slate-500',
            default => $base.' bg-indigo-600 hover:bg-indigo-500 focus:ring-indigo-500',
        };
    }

    public static function linkClasses(string $accent = 'indigo'): string
    {
        return match ($accent) {
            'emerald' => 'text-emerald-700 dark:text-emerald-300 hover:underline',
            'amber' => 'text-amber-700 dark:text-amber-300 hover:underline',
            'sky' => 'text-sky-700 dark:text-sky-300 hover:underline',
            'violet' => 'text-violet-700 dark:text-violet-300 hover:underline',
            'fuchsia' => 'text-fuchsia-700 dark:text-fuchsia-300 hover:underline',
            'rose' => 'text-rose-700 dark:text-rose-300 hover:underline',
            'slate' => 'text-slate-700 dark:text-slate-300 hover:underline',
            default => 'text-indigo-700 dark:text-indigo-300 hover:underline',
        };
    }

    public static function chipClasses(string $accent = 'indigo'): string
    {
        return match ($accent) {
            'emerald' => 'rounded-lg border border-emerald-300/80 px-3 py-1.5 font-medium text-emerald-900 dark:text-emerald-100 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/40',
            'amber' => 'rounded-lg border border-amber-300/80 px-3 py-1.5 font-medium text-amber-900 dark:text-amber-100 hover:bg-amber-50/60 dark:hover:bg-amber-950/40',
            'sky' => 'rounded-lg border border-sky-300/80 px-3 py-1.5 font-medium text-sky-900 dark:text-sky-100 hover:bg-sky-50/60 dark:hover:bg-sky-950/40',
            'violet' => 'rounded-lg border border-violet-300/80 px-3 py-1.5 font-medium text-violet-900 dark:text-violet-100 hover:bg-violet-50/60 dark:hover:bg-violet-950/40',
            'fuchsia' => 'rounded-lg border border-fuchsia-300/80 px-3 py-1.5 font-medium text-fuchsia-900 dark:text-fuchsia-100 hover:bg-fuchsia-50/60 dark:hover:bg-fuchsia-950/40',
            'slate' => 'rounded-lg border border-slate-300/80 px-3 py-1.5 font-medium text-slate-800 dark:text-slate-200 hover:bg-slate-50/60 dark:hover:bg-slate-900/40',
            default => 'rounded-lg border border-indigo-300/80 px-3 py-1.5 font-medium text-indigo-900 dark:text-indigo-100 hover:bg-white/60 dark:hover:bg-indigo-950/40',
        };
    }

    public static function categoryAccent(string $categoryId): string
    {
        return match ($categoryId) {
            'geo' => 'sky',
            'fundeb' => 'amber',
            'funding_repasses' => 'emerald',
            'cadunico' => 'fuchsia',
            'pedagogical', 'saeb' => 'violet',
            default => 'indigo',
        };
    }
}
