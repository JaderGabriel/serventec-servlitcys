<?php

namespace App\Support\Admin;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Models\AdminSyncTask;
use App\Services\AdminSync\AdminSyncTaskExplainer;
use Illuminate\Support\Collection;

/** Metadados e contagens para a página admin/sync-queue. */
final class AdminSyncQueueIndexPresenter
{
    /**
     * @return list<array{
     *     id: string,
     *     domain: ?AdminSyncDomain,
     *     label: string,
     *     description: string,
     *     icon: string,
     *     accent: string,
     *     anchor: string,
     *     queue_label: string,
     *     counts: array<string, int>,
     *     total: int,
     *     active: int,
     *     failed: int
     * }>
     */
    public static function syncThemeCards(
        Collection $countsByDomainStatus,
        string $syncQueueName,
    ): array {
        $cards = [];

        foreach (self::syncThemeDefinitions($syncQueueName) as $def) {
            /** @var AdminSyncDomain $domain */
            $domain = $def['domain'];
            $domainKey = $domain->value;
            $statusCounts = $countsByDomainStatus->get($domainKey, collect());
            $counts = [];
            $total = 0;
            foreach (AdminSyncTaskStatus::cases() as $status) {
                $n = (int) ($statusCounts->firstWhere('status', $status->value)?->aggregate ?? 0);
                $counts[$status->value] = $n;
                $total += $n;
            }

            $cards[] = [
                'id' => $def['id'],
                'domain' => $domain,
                'label' => $def['label'],
                'description' => $def['description'],
                'icon' => $def['icon'],
                'accent' => $def['accent'],
                'anchor' => $def['anchor'],
                'admin_route' => $def['admin_route'] ?? null,
                'queue_label' => $syncQueueName,
                'counts' => $counts,
                'total' => $total,
                'active' => ($counts[AdminSyncTaskStatus::Pending->value] ?? 0)
                    + ($counts[AdminSyncTaskStatus::Processing->value] ?? 0),
                'failed' => $counts[AdminSyncTaskStatus::Failed->value] ?? 0,
            ];
        }

        return $cards;
    }

    /**
     * @return array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     icon: string,
     *     accent: string,
     *     anchor: string,
     *     queue_label: string,
     *     counts: array<string, int>,
     *     total: int,
     *     active: int,
     *     failed: int,
     *     ready: int
     * }
     */
    public static function pdfThemeCard(Collection $pdfCounts, string $pdfQueueName): array
    {
        $counts = [];
        $total = 0;
        foreach (AnalyticsReportExportStatus::cases() as $status) {
            $n = (int) ($pdfCounts[$status->value] ?? 0);
            $counts[$status->value] = $n;
            $total += $n;
        }

        return [
            'id' => 'pdf',
            'label' => __('Relatórios PDF (Diagnóstico)'),
            'description' => __('Exportações geradas na aba Diagnóstico do painel analítico — worker dedicado.'),
            'icon' => 'document-text',
            'accent' => 'rose',
            'anchor' => 'fila-pdf',
            'queue_label' => $pdfQueueName,
            'counts' => $counts,
            'total' => $total,
            'active' => ($counts[AnalyticsReportExportStatus::Pending->value] ?? 0)
                + ($counts[AnalyticsReportExportStatus::Processing->value] ?? 0),
            'failed' => $counts[AnalyticsReportExportStatus::Failed->value] ?? 0,
            'ready' => $counts[AnalyticsReportExportStatus::Completed->value] ?? 0,
        ];
    }

    /**
     * @return list<array{
     *     theme: array,
     *     tasks: \Illuminate\Support\Collection<int, AdminSyncTask>,
     *     total: int
     * }>
     */
    public static function syncThemeSections(
        Collection $countsByDomainStatus,
        string $syncQueueName,
        int $previewLimit = 8,
    ): array {
        $sections = [];

        foreach (self::syncThemeDefinitions($syncQueueName) as $def) {
            /** @var AdminSyncDomain $domain */
            $domain = $def['domain'];
            $domainKey = $domain->value;
            $total = (int) AdminSyncTask::query()->where('domain', $domainKey)->count();

            if ($total === 0) {
                continue;
            }

            $statusCounts = $countsByDomainStatus->get($domainKey, collect());
            $counts = [];
            foreach (AdminSyncTaskStatus::cases() as $status) {
                $counts[$status->value] = (int) ($statusCounts->firstWhere('status', $status->value)?->aggregate ?? 0);
            }

            $sections[] = [
                'theme' => array_merge($def, [
                    'domain' => $domain,
                    'counts' => $counts,
                    'total' => $total,
                    'active' => ($counts[AdminSyncTaskStatus::Pending->value] ?? 0)
                        + ($counts[AdminSyncTaskStatus::Processing->value] ?? 0),
                    'failed' => $counts[AdminSyncTaskStatus::Failed->value] ?? 0,
                ]),
                'tasks' => AdminSyncTask::query()
                    ->with(['city:id,name,uf', 'queuedBy:id,name'])
                    ->where('domain', $domainKey)
                    ->orderByDesc('id')
                    ->limit($previewLimit)
                    ->get(),
                'total' => $total,
            ];
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    public static function taskContextLines(AdminSyncTask $task, int $max = 4): array
    {
        $lines = [];
        $summary = AdminSyncTaskExplainer::summary($task);
        if ($summary !== '') {
            $lines[] = $summary;
        }

        foreach (AdminSyncTaskExplainer::payloadHints($task) as $hint) {
            $lines[] = $hint;
            if (count($lines) >= $max) {
                break;
            }
        }

        return array_slice($lines, 0, $max);
    }

    /**
     * @return list<array{
     *     id: string,
     *     domain: AdminSyncDomain,
     *     label: string,
     *     description: string,
     *     icon: string,
     *     accent: string,
     *     anchor: string
     * }>
     */
    private static function syncThemeDefinitions(string $syncQueueName): array
    {
        unset($syncQueueName);

        return [
            [
                'id' => 'fundeb',
                'domain' => AdminSyncDomain::Fundeb,
                'label' => AdminSyncDomain::Fundeb->label(),
                'description' => __('VAAF, VAAT, VAAR e importações por município/ano.'),
                'icon' => 'banknotes',
                'accent' => 'amber',
                'anchor' => 'fila-fundeb',
            ],
            [
                'id' => 'funding',
                'domain' => AdminSyncDomain::Funding,
                'label' => AdminSyncDomain::Funding->label(),
                'description' => __('Repasses e financiamentos complementares.'),
                'icon' => 'chart-bar',
                'accent' => 'emerald',
                'anchor' => 'fila-funding',
            ],
            [
                'id' => 'geo',
                'domain' => AdminSyncDomain::Geo,
                'label' => AdminSyncDomain::Geo->label(),
                'description' => __('Coordenadas i-Educar, INEP e microdados para o mapa.'),
                'icon' => 'map-pin',
                'accent' => 'sky',
                'anchor' => 'fila-geo',
            ],
            [
                'id' => 'pedagogical',
                'domain' => AdminSyncDomain::Pedagogical,
                'label' => AdminSyncDomain::Pedagogical->label(),
                'description' => __('Indicadores SAEB (API, CSV, microdados INEP).'),
                'icon' => 'academic-cap',
                'accent' => 'violet',
                'anchor' => 'fila-pedagogical',
            ],
            [
                'id' => 'cadastro',
                'domain' => AdminSyncDomain::Cadastro,
                'label' => AdminSyncDomain::Cadastro->label(),
                'description' => __('Cecad/CadÚnico: sync automática (URL nacional), CSV e snapshots por município.'),
                'icon' => 'users',
                'accent' => 'violet',
                'anchor' => 'fila-cadastro',
                'admin_route' => 'admin.cadunico-sync.index',
            ],
            [
                'id' => 'ieducar',
                'domain' => AdminSyncDomain::Ieducar,
                'label' => AdminSyncDomain::Ieducar->label(),
                'description' => __('Schema, exportações NEE e tarefas ligadas ao painel.'),
                'icon' => 'circle-stack',
                'accent' => 'indigo',
                'anchor' => 'fila-ieducar',
            ],
            [
                'id' => 'system',
                'domain' => AdminSyncDomain::System,
                'label' => AdminSyncDomain::System->label(),
                'description' => __('Sincronização massiva semanal com checkpoint retomável.'),
                'icon' => 'command-line',
                'accent' => 'slate',
                'anchor' => 'fila-system',
            ],
        ];
    }

    public static function themeForDomain(AdminSyncDomain $domain, string $syncQueueName): ?array
    {
        foreach (self::syncThemeDefinitions($syncQueueName) as $def) {
            if ($def['domain'] === $domain) {
                return array_merge($def, ['domain' => $domain]);
            }
        }

        return null;
    }
}
