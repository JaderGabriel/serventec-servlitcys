<?php

namespace App\Services\Admin;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Support\Admin\ModuleMonitorCatalog;
use App\Support\Admin\ModuleMonitorHorizonteProbe;
use App\Support\Admin\ModuleMonitorSnapshotCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Recolhe sinais estruturais de saúde por módulo (independente do uso Pulse no período).
 */
final class ModuleMonitorProbeService
{
    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $syncCompleted = $this->lastCompletedByDomain();
        $syncFailed = $this->lastFailedByDomain();
        $syncActive = $this->activeCountByDomain();
        $system = $this->systemProbe();

        $modules = [];
        foreach (ModuleMonitorCatalog::modules() as $def) {
            $modules[$def['id']] = $this->probeModule($def, $syncCompleted, $syncFailed, $syncActive, $system);
        }

        $snapshot = [
            'collected_at' => now()->toIso8601String(),
            'system' => $system,
            'modules' => $modules,
        ];

        ModuleMonitorSnapshotCache::put($snapshot);

        return $snapshot;
    }

    /**
     * @return array<string, Carbon|null>
     */
    private function lastCompletedByDomain(): array
    {
        $rows = AdminSyncTask::query()
            ->selectRaw('domain, max(completed_at) as last_at')
            ->where('status', AdminSyncTaskStatus::Completed->value)
            ->whereNotNull('completed_at')
            ->groupBy('domain')
            ->pluck('last_at', 'domain');

        $map = [];
        foreach ($rows as $domain => $at) {
            $map[(string) $domain] = $at ? Carbon::parse($at) : null;
        }

        return $map;
    }

    /**
     * @return array<string, Carbon|null>
     */
    private function lastFailedByDomain(): array
    {
        $rows = AdminSyncTask::query()
            ->selectRaw('domain, max(created_at) as last_at')
            ->where('status', AdminSyncTaskStatus::Failed->value)
            ->groupBy('domain')
            ->pluck('last_at', 'domain');

        $map = [];
        foreach ($rows as $domain => $at) {
            $map[(string) $domain] = $at ? Carbon::parse($at) : null;
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function activeCountByDomain(): array
    {
        return AdminSyncTask::query()
            ->selectRaw('domain, count(*) as aggregate')
            ->whereIn('status', [AdminSyncTaskStatus::Pending->value, AdminSyncTaskStatus::Processing->value])
            ->groupBy('domain')
            ->pluck('aggregate', 'domain')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function systemProbe(): array
    {
        $activeCities = City::query()->active()->get();
        $citiesReady = $activeCities->filter(fn (City $city): bool => $city->hasDataSetup())->count();

        $queueConnection = (string) config('queue.default', 'sync');
        $pendingJobs = null;
        try {
            if ($queueConnection !== 'sync' && $queueConnection !== 'null') {
                $pendingJobs = Queue::connection($queueConnection)->size();
            } else {
                $pendingJobs = 0;
            }
        } catch (Throwable) {
            $pendingJobs = null;
        }

        $failedJobs7d = null;
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs7d = (int) DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDays(7))
                ->count();
        }

        return [
            'cities_active' => $activeCities->count(),
            'cities_ready' => $citiesReady,
            'queue_connection' => $queueConnection,
            'pending_jobs' => $pendingJobs,
            'failed_jobs_7d' => $failedJobs7d,
            'pulse_enabled' => (bool) config('pulse.enabled', true),
        ];
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, Carbon|null>  $syncCompleted
     * @param  array<string, Carbon|null>  $syncFailed
     * @param  array<string, int>  $syncActive
     * @param  array<string, mixed>  $system
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private function probeModule(array $def, array $syncCompleted, array $syncFailed, array $syncActive, array $system): array
    {
        $moduleId = (string) $def['id'];

        return match ($moduleId) {
            'analytics', 'rx', 'educacenso' => $this->probeConsultoriaModule($moduleId, $system),
            'pdf' => $this->probePdfModule(),
            'public_data' => $this->probePublicDataModule(),
            'horizonte' => $this->probeHorizonteModule(),
            'connections' => $this->probeConnectionsModule($system),
            'database' => $this->probeDatabaseModule($system),
            'queue' => $this->probeQueueModule($system),
            default => $this->probeSyncModule($def, $syncCompleted, $syncFailed, $syncActive),
        };
    }

    /**
     * @param  array<string, mixed>  $system
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private function probeConsultoriaModule(string $moduleId, array $system): array
    {
        $ready = (int) ($system['cities_ready'] ?? 0);
        $active = (int) ($system['cities_active'] ?? 0);

        if ($active === 0) {
            return $this->probeResult('unknown', __('Nenhum município activo no catálogo.'));
        }

        if ($ready === 0) {
            return $this->probeResult('degraded', __('Municípios activos sem base i-Educar configurada.'));
        }

        $label = match ($moduleId) {
            'rx' => __('RX disponível'),
            'educacenso' => __('Conferência Educacenso disponível'),
            default => __('Consultoria disponível'),
        };

        return $this->probeResult(
            'idle',
            __(':label — :ready/:active município(s) prontos.', ['label' => $label, 'ready' => $ready, 'active' => $active]),
            tags: [__(':n prontos', ['n' => $ready])],
        );
    }

    /**
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private function probePdfModule(): array
    {
        $lastSuccess = AnalyticsReportExport::query()
            ->where('status', AnalyticsReportExportStatus::Completed->value)
            ->orderByDesc('completed_at')
            ->value('completed_at');

        $lastFailure = AnalyticsReportExport::query()
            ->where('status', AnalyticsReportExportStatus::Failed->value)
            ->orderByDesc('created_at')
            ->value('created_at');

        $pending = (int) AnalyticsReportExport::query()
            ->whereIn('status', [
                AnalyticsReportExportStatus::Pending->value,
                AnalyticsReportExportStatus::Processing->value,
            ])
            ->count();

        $recentFailures = (int) AnalyticsReportExport::query()
            ->where('status', AnalyticsReportExportStatus::Failed->value)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $successAt = $lastSuccess ? Carbon::parse($lastSuccess) : null;
        $failureAt = $lastFailure ? Carbon::parse($lastFailure) : null;

        if ($recentFailures > 0 && ($successAt === null || ($failureAt !== null && $failureAt->gt($successAt)))) {
            return $this->probeResult(
                'failed',
                __(':n exportação(ões) PDF falharam nos últimos 7 dias.', ['n' => $recentFailures]),
                $successAt?->toIso8601String(),
                $failureAt?->toIso8601String(),
                [__(':n falhas (7d)', ['n' => $recentFailures])],
            );
        }

        if ($pending > 0) {
            return $this->probeResult(
                'degraded',
                __(':n PDF(s) aguardando processamento.', ['n' => $pending]),
                $successAt?->toIso8601String(),
                $failureAt?->toIso8601String(),
                [__(':n em fila', ['n' => $pending])],
            );
        }

        if ($successAt !== null) {
            return $this->probeResult(
                'operational',
                __('Último PDF concluído :when.', ['when' => $successAt->diffForHumans()]),
                $successAt->toIso8601String(),
                $failureAt?->toIso8601String(),
            );
        }

        return $this->probeResult('idle', __('Sem exportações PDF registadas — módulo pronto.'));
    }

    /**
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private function probePublicDataModule(): array
    {
        $report = PublicDataOfficialCheckCache::get();
        if ($report === null) {
            return $this->probeResult(
                'degraded',
                __('Verificação de fontes oficiais ainda não executada — agende public-data:check-official.'),
            );
        }

        $checkedAt = isset($report['checked_at'])
            ? Carbon::parse((string) $report['checked_at'])
            : null;

        $staleHours = max(1, (int) config('module_monitor.probe.public_data_cache_stale_hours', 48));
        if ($checkedAt === null || $checkedAt->lt(now()->subHours($staleHours))) {
            return $this->probeResult(
                'degraded',
                __('Verificação de fontes desactualizada — executar public-data:check-official.'),
                tags: $checkedAt ? [__('há :when', ['when' => $checkedAt->diffForHumans()])] : [],
            );
        }

        $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
        $errors = count(array_filter(
            $findings,
            static fn (array $f): bool => in_array((string) ($f['status'] ?? ''), ['error', 'unreachable'], true),
        ));

        if ($errors > 0) {
            return $this->probeResult(
                'degraded',
                __(':n fonte(s) oficial(is) com erro na última verificação.', ['n' => $errors]),
                tags: [__('verificado :when', ['when' => $checkedAt->diffForHumans()])],
            );
        }

        return $this->probeResult(
            'operational',
            __('Fontes oficiais verificadas :when — :n fonte(s) OK.', [
                'when' => $checkedAt->diffForHumans(),
                'n' => count($findings),
            ]),
            tags: [__(':n fontes', ['n' => count($findings)])],
        );
    }

    /**
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private function probeHorizonteModule(): array
    {
        try {
            $status = app(HorizonteImportHubStatusService::class)->build();
        } catch (Throwable) {
            return $this->probeResult('unknown', __('Não foi possível avaliar cobertura Horizonte.'));
        }

        $probe = ModuleMonitorHorizonteProbe::probe($status);

        return $this->probeResult(
            $probe['signal'],
            $probe['detail'],
            $probe['last_success_at'],
            $probe['last_failure_at'],
            $probe['tags'],
        );
    }

    /**
     * @param  array<string, mixed>  $system
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private function probeConnectionsModule(array $system): array
    {
        $ready = (int) ($system['cities_ready'] ?? 0);
        $active = (int) ($system['cities_active'] ?? 0);

        if ($active === 0) {
            return $this->probeResult('unknown', __('Sem municípios activos para avaliar conexões.'));
        }

        $ratio = $active > 0 ? $ready / $active : 0.0;

        if ($ratio < 0.5) {
            return $this->probeResult(
                'degraded',
                __('Apenas :ready de :active municípios com conexão i-Educar configurada.', ['ready' => $ready, 'active' => $active]),
                tags: [__(':pct%', ['pct' => (int) round($ratio * 100)])],
            );
        }

        if ($ready < $active) {
            return $this->probeResult(
                'idle',
                __(':ready/:active municípios com base configurada.', ['ready' => $ready, 'active' => $active]),
                tags: [__(':n prontos', ['n' => $ready])],
            );
        }

        return $this->probeResult(
            'operational',
            __('Todas as conexões municipais configuradas (:n).', ['n' => $ready]),
            tags: [__('100% prontos')],
        );
    }

    /**
     * @param  array<string, mixed>  $system
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private function probeDatabaseModule(array $system): array
    {
        $ready = (int) ($system['cities_ready'] ?? 0);

        if ($ready === 0) {
            return $this->probeResult('degraded', __('Sem bases municipais configuradas para consulta SQL.'));
        }

        return $this->probeResult(
            'idle',
            __('Infra SQL municipal disponível para :n município(s).', ['n' => $ready]),
            tags: [__(':n bases', ['n' => $ready])],
        );
    }

    /**
     * @param  array<string, mixed>  $system
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private function probeQueueModule(array $system): array
    {
        $pending = $system['pending_jobs'] ?? null;
        $failed7d = (int) ($system['failed_jobs_7d'] ?? 0);
        $connection = (string) ($system['queue_connection'] ?? 'sync');

        if ($connection === 'sync') {
            return $this->probeResult(
                'degraded',
                __('Fila em modo sync — jobs executam na requisição HTTP.'),
                tags: ['sync'],
            );
        }

        if ($failed7d > 0) {
            return $this->probeResult(
                'failed',
                __(':n job(s) falharam nos últimos 7 dias.', ['n' => $failed7d]),
                tags: [__(':n failed_jobs', ['n' => $failed7d])],
            );
        }

        if ($pending !== null && $pending >= 10) {
            return $this->probeResult(
                'degraded',
                __(':n job(s) pendentes na fila :conn.', ['n' => $pending, 'conn' => $connection]),
                tags: [__(':n pendentes', ['n' => $pending])],
            );
        }

        return $this->probeResult(
            'operational',
            __('Fila :conn sem falhas recentes.', ['conn' => $connection]),
            tags: $pending !== null ? [__(':n pendentes', ['n' => $pending])] : [],
        );
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, Carbon|null>  $syncCompleted
     * @param  array<string, Carbon|null>  $syncFailed
     * @param  array<string, int>  $syncActive
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private function probeSyncModule(array $def, array $syncCompleted, array $syncFailed, array $syncActive): array
    {
        $domains = $def['sync_domains'] ?? [];
        if ($domains === []) {
            return $this->probeResult('unknown', __('Módulo sem domínio de sincronização mapeado.'));
        }

        $staleDays = max(1, (int) config('module_monitor.probe.sync_stale_days', 14));
        $failureWindowDays = max(1, (int) config('module_monitor.probe.sync_failure_window_days', 7));

        $lastSuccess = null;
        $lastFailure = null;
        $activeTotal = 0;

        foreach ($domains as $domain) {
            $domain = (string) $domain;
            $success = $syncCompleted[$domain] ?? null;
            $failure = $syncFailed[$domain] ?? null;
            $activeTotal += $syncActive[$domain] ?? 0;

            if ($success !== null && ($lastSuccess === null || $success->gt($lastSuccess))) {
                $lastSuccess = $success;
            }
            if ($failure !== null && ($lastFailure === null || $failure->gt($lastFailure))) {
                $lastFailure = $failure;
            }
        }

        $successIso = $lastSuccess?->toIso8601String();
        $failureIso = $lastFailure?->toIso8601String();

        if ($lastFailure !== null
            && $lastFailure->gte(now()->subDays($failureWindowDays))
            && ($lastSuccess === null || $lastFailure->gt($lastSuccess))) {
            return $this->probeResult(
                'failed',
                __('Última sincronização falhou :when.', ['when' => $lastFailure->diffForHumans()]),
                $successIso,
                $failureIso,
                [__('falha recente')],
            );
        }

        if ($activeTotal > 0) {
            return $this->probeResult(
                'degraded',
                __(':n tarefa(s) de sync em processamento.', ['n' => $activeTotal]),
                $successIso,
                $failureIso,
                [__(':n activas', ['n' => $activeTotal])],
            );
        }

        if ($lastSuccess === null) {
            return $this->probeResult(
                'idle',
                __('Sem histórico de sincronização — importação ainda não executada.'),
                tags: [__('sem histórico')],
            );
        }

        if ($lastSuccess->lt(now()->subDays($staleDays))) {
            return $this->probeResult(
                'degraded',
                __('Último sync concluído há :when (limiar :days dias).', [
                    'when' => $lastSuccess->diffForHumans(),
                    'days' => $staleDays,
                ]),
                $successIso,
                $failureIso,
                [__('sync antigo')],
            );
        }

        return $this->probeResult(
            'operational',
            __('Último sync concluído :when.', ['when' => $lastSuccess->diffForHumans()]),
            $successIso,
            $failureIso,
            [__('sync recente')],
        );
    }

    /**
     * @param  list<string>  $tags
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private function probeResult(
        string $signal,
        string $detail,
        ?string $lastSuccessAt = null,
        ?string $lastFailureAt = null,
        array $tags = [],
    ): array {
        return [
            'signal' => $signal,
            'detail' => $detail,
            'last_success_at' => $lastSuccessAt,
            'last_failure_at' => $lastFailureAt,
            'tags' => $tags,
        ];
    }
}
