<?php

namespace App\Support\Admin;

use App\Enums\AdminSyncDomain;

/**
 * Catálogo de módulos monitorizados (saúde operacional admin).
 */
final class ModuleMonitorCatalog
{
    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     icon: string,
     *     accent: string,
     *     group: string,
     *     admin_route: ?string,
     *     sync_queue_anchor: ?string,
     *     pulse_prefixes: list<string>,
     *     sync_domains: list<string>
     * }>
     */
    public static function modules(): array
    {
        return [
            [
                'id' => 'analytics',
                'label' => __('Painel analítico'),
                'description' => __('Abas lazy, filtros i-Educar e impacto FUNDEB/Censo.'),
                'icon' => 'chart-bar',
                'accent' => 'teal',
                'group' => 'consultoria',
                'admin_route' => 'dashboard.analytics',
                'sync_queue_anchor' => null,
                'pulse_prefixes' => ['analytics:tab:', 'http:route:dashboard.analytics'],
                'sync_domains' => [],
            ],
            [
                'id' => 'rx',
                'label' => __('RX — cadastro e Censo'),
                'description' => __('Visão rede: volume digitado e ritmo Censo.'),
                'icon' => 'clipboard-document-list',
                'accent' => 'violet',
                'group' => 'consultoria',
                'admin_route' => 'dashboard.rx',
                'sync_queue_anchor' => null,
                'pulse_prefixes' => ['rx:', 'map:'],
                'sync_domains' => [],
            ],
            [
                'id' => 'pdf',
                'label' => __('Relatórios PDF (Serventec)'),
                'description' => __('Exportação diagnóstica na aba Serventec.'),
                'icon' => 'document-text',
                'accent' => 'rose',
                'group' => 'consultoria',
                'admin_route' => null,
                'sync_queue_anchor' => 'fila-pdf',
                'pulse_prefixes' => ['pdf:'],
                'sync_domains' => [],
            ],
            [
                'id' => 'geo',
                'label' => __('Sincronização geográfica'),
                'description' => __('Mapa INEP, coordenadas e microdados.'),
                'icon' => 'map-pin',
                'accent' => 'sky',
                'group' => 'sincronizacao',
                'admin_route' => 'admin.geo-sync.index',
                'sync_queue_anchor' => 'fila-geo',
                'pulse_prefixes' => ['sync:geo', 'http:route:admin.geo-sync'],
                'sync_domains' => [AdminSyncDomain::Geo->value],
            ],
            [
                'id' => 'pedagogical',
                'label' => __('Sincronização pedagógica (SAEB)'),
                'description' => __('Planilhas INEP, API e microdados SAEB.'),
                'icon' => 'academic-cap',
                'accent' => 'violet',
                'group' => 'sincronizacao',
                'admin_route' => 'admin.pedagogical-sync.index',
                'sync_queue_anchor' => 'fila-pedagogical',
                'pulse_prefixes' => ['sync:pedagogical', 'http:route:admin.pedagogical-sync'],
                'sync_domains' => [AdminSyncDomain::Pedagogical->value],
            ],
            [
                'id' => 'cadunico',
                'label' => __('CadÚnico / Cecad'),
                'description' => __('Snapshots agregados, sync automática e fila cadastro.'),
                'icon' => 'users',
                'accent' => 'fuchsia',
                'group' => 'sincronizacao',
                'admin_route' => 'admin.cadunico-sync.index',
                'sync_queue_anchor' => 'fila-cadastro',
                'pulse_prefixes' => ['sync:cadastro', 'cadunico:', 'http:route:admin.cadunico-sync'],
                'sync_domains' => [AdminSyncDomain::Cadastro->value],
            ],
            [
                'id' => 'fundeb',
                'label' => __('admin_ieducar_compatibility.page.nav_label'),
                'description' => __('admin_ieducar_compatibility.page.nav_tooltip'),
                'icon' => 'banknotes',
                'accent' => 'amber',
                'group' => 'sincronizacao',
                'admin_route' => 'admin.ieducar-compatibility.index',
                'sync_queue_anchor' => 'fila-fundeb',
                'pulse_prefixes' => ['sync:fundeb', 'ieducar:fundeb', 'http:route:admin.ieducar-compatibility'],
                'sync_domains' => [AdminSyncDomain::Fundeb->value],
            ],
            [
                'id' => 'finance_realtime',
                'label' => __('Repasses / Tempo Real'),
                'description' => __('CKAN, SISWEB e BB — municipal_transfer_snapshots e aba Finanças → Tempo Real.'),
                'icon' => 'banknotes',
                'accent' => 'emerald',
                'group' => 'sincronizacao',
                'admin_route' => 'admin.public-data.index',
                'sync_queue_anchor' => 'fila-funding',
                'pulse_prefixes' => ['sync:funding', 'funding:rebuild-finance-realtime'],
                'sync_domains' => [AdminSyncDomain::Funding->value],
            ],
            [
                'id' => 'ieducar',
                'label' => __('i-Educar / exportações'),
                'description' => __('Exportação NEE, schema e tarefas do painel.'),
                'icon' => 'circle-stack',
                'accent' => 'indigo',
                'group' => 'sincronizacao',
                'admin_route' => null,
                'sync_queue_anchor' => 'fila-ieducar',
                'pulse_prefixes' => ['sync:ieducar', 'export:inclusion', 'ieducar:'],
                'sync_domains' => [AdminSyncDomain::Ieducar->value],
            ],
            [
                'id' => 'system',
                'label' => __('Sincronização massiva'),
                'description' => __('Sync semanal com checkpoint retomável.'),
                'icon' => 'command-line',
                'accent' => 'slate',
                'group' => 'sincronizacao',
                'admin_route' => null,
                'sync_queue_anchor' => 'fila-system',
                'pulse_prefixes' => ['sync:system'],
                'sync_domains' => [AdminSyncDomain::System->value],
            ],
            [
                'id' => 'public_data',
                'label' => __('Hub dados públicos'),
                'description' => __('Orquestração de importações fora do i-Educar.'),
                'icon' => 'globe-alt',
                'accent' => 'emerald',
                'group' => 'sincronizacao',
                'admin_route' => 'admin.public-data.index',
                'sync_queue_anchor' => null,
                'pulse_prefixes' => ['sync:public', 'http:route:admin.public-data'],
                'sync_domains' => [],
            ],
            [
                'id' => 'connections',
                'label' => __('Conexões i-Educar'),
                'description' => __('Probe PDO por município.'),
                'icon' => 'circle-stack',
                'accent' => 'sky',
                'group' => 'infra',
                'admin_route' => 'admin.connections.index',
                'sync_queue_anchor' => null,
                'pulse_prefixes' => ['http:route:admin.connections', 'http:route:cities.db-status'],
                'sync_domains' => [],
            ],
            [
                'id' => 'database',
                'label' => __('SQL e bases municipais'),
                'description' => __('Consultas lentas e blocos CityDataConnection.'),
                'icon' => 'circle-stack',
                'accent' => 'amber',
                'group' => 'infra',
                'admin_route' => null,
                'sync_queue_anchor' => null,
                'pulse_prefixes' => ['db_slow_scope:', 'db_muni_run', 'db_slow_fp:'],
                'sync_domains' => [],
            ],
            [
                'id' => 'queue',
                'label' => __('Filas e workers'),
                'description' => __('Jobs Laravel, failed_jobs e workers admin-sync/PDF.'),
                'icon' => 'queue-list',
                'accent' => 'indigo',
                'group' => 'infra',
                'admin_route' => 'admin.sync-queue.index',
                'sync_queue_anchor' => null,
                'pulse_prefixes' => [],
                'sync_domains' => [],
            ],
        ];
    }

    public static function find(string $id): ?array
    {
        foreach (self::modules() as $module) {
            if ($module['id'] === $id) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function moduleIdsForSyncDomain(string $domain): array
    {
        $ids = [];
        foreach (self::modules() as $module) {
            if (in_array($domain, $module['sync_domains'], true)) {
                $ids[] = $module['id'];
            }
        }

        return $ids !== [] ? $ids : ['ieducar'];
    }

    public static function moduleIdForPulseKey(string $key): ?string
    {
        foreach (self::modules() as $module) {
            foreach ($module['pulse_prefixes'] as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    return $module['id'];
                }
            }
        }

        if (str_starts_with($key, 'http:route:')) {
            return 'analytics';
        }

        return null;
    }
}
