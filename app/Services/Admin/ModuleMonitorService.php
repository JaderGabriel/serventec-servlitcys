<?php

namespace App\Services\Admin;

use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Support\Admin\ModuleMonitorCatalog;
use App\Support\Pulse\PulseAggregateBridge;
use App\Support\Pulse\PulseOperationMetricsAggregator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ModuleMonitorService
{
    /**
     * @return array{
     *     period: string,
     *     period_label: string,
     *     generated_at: string,
     *     pulse_available: bool,
     *     system: array<string, mixed>,
     *     modules: list<array<string, mixed>>,
     *     incidents: list<array<string, mixed>>
     * }
     */
    public function build(string $period = '24h'): array
    {
        $since = $this->sinceForPeriod($period);
        $pulseAvailable = PulseAggregateBridge::isAvailable();
        $aggregateFn = PulseAggregateBridge::aggregateFn($period);
        $pulseOps = $pulseAvailable
            ? PulseOperationMetricsAggregator::summarize($aggregateFn)
            : ['operations' => [], 'slow_operations' => [], 'errors' => []];

        $syncByDomain = $this->syncStatsByDomain($since);
        $modulePulse = $this->pulseMetricsByModule($pulseOps);
        $incidents = $this->collectIncidents($since, $pulseOps);

        $system = $this->systemOverview($since);

        $modules = [];
        foreach (ModuleMonitorCatalog::modules() as $def) {
            $modules[] = $this->buildModuleRow(
                $def,
                $syncByDomain,
                $modulePulse[$def['id']] ?? self::emptyPulseModuleMetrics(),
                $incidents,
                $system,
            );
        }

        usort($modules, static function (array $a, array $b): int {
            $order = ['critical' => 0, 'warning' => 1, 'unknown' => 2, 'healthy' => 3];

            return ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9);
        });

        return [
            'period' => $period,
            'period_label' => PulseAggregateBridge::periodLabel($period),
            'generated_at' => now()->toIso8601String(),
            'pulse_available' => $pulseAvailable,
            'system' => $system,
            'modules' => $modules,
            'incidents' => array_slice($incidents, 0, (int) config('module_monitor.incidents_limit', 50)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function systemOverview(Carbon $since): array
    {
        $queueConnection = (string) config('queue.default', 'sync');
        $pendingJobs = null;
        $failedJobs = null;

        try {
            if ($queueConnection !== 'sync' && $queueConnection !== 'null') {
                $pendingJobs = Queue::connection($queueConnection)->size();
            } else {
                $pendingJobs = 0;
            }
        } catch (Throwable) {
            $pendingJobs = null;
        }

        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = (int) DB::table('failed_jobs')
                ->where('failed_at', '>=', $since)
                ->count();
        }

        $syncFailed = (int) AdminSyncTask::query()
            ->where('status', AdminSyncTaskStatus::Failed->value)
            ->where('created_at', '>=', $since)
            ->count();

        $pdfFailed = (int) AnalyticsReportExport::query()
            ->where('status', AnalyticsReportExportStatus::Failed->value)
            ->where('created_at', '>=', $since)
            ->count();

        $status = 'healthy';
        if ($syncFailed > 0 || ($failedJobs ?? 0) > 0 || $pdfFailed > 0) {
            $status = 'critical';
        } elseif (($pendingJobs ?? 0) >= max(10, (int) config('notifications.operational_alerts.queue_pending_threshold', 25))) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'queue_connection' => $queueConnection,
            'queue_is_sync' => $queueConnection === 'sync',
            'pending_jobs' => $pendingJobs,
            'failed_jobs_period' => $failedJobs,
            'sync_failures' => $syncFailed,
            'pdf_failures' => $pdfFailed,
            'pulse_enabled' => (bool) config('pulse.enabled', true),
        ];
    }

    /**
     * @param  array{operations: list, slow_operations: list, errors: list}  $pulseOps
     * @return array<string, array{error_count: int, slow_count: int, max_ms: int, op_count: int}>
     */
    private function pulseMetricsByModule(array $pulseOps): array
    {
        $byModule = [];

        foreach ($pulseOps['errors'] as $row) {
            $key = (string) ($row['key'] ?? '');
            $moduleId = ModuleMonitorCatalog::moduleIdForPulseKey($key) ?? 'analytics';
            $byModule[$moduleId] ??= self::emptyPulseModuleMetrics();
            $byModule[$moduleId]['error_count'] += (int) ($row['count'] ?? 0);
        }

        foreach ($pulseOps['slow_operations'] as $row) {
            $key = (string) ($row['key'] ?? '');
            $moduleId = ModuleMonitorCatalog::moduleIdForPulseKey($key) ?? 'analytics';
            $byModule[$moduleId] ??= self::emptyPulseModuleMetrics();
            $byModule[$moduleId]['slow_count'] += (int) ($row['count'] ?? 0);
            $byModule[$moduleId]['max_ms'] = max($byModule[$moduleId]['max_ms'], (int) ($row['max_ms'] ?? 0));
        }

        foreach ($pulseOps['operations'] as $row) {
            $key = (string) ($row['key'] ?? '');
            $moduleId = ModuleMonitorCatalog::moduleIdForPulseKey($key) ?? 'analytics';
            $byModule[$moduleId] ??= self::emptyPulseModuleMetrics();
            $byModule[$moduleId]['op_count'] += (int) ($row['count'] ?? 0);
            $byModule[$moduleId]['max_ms'] = max($byModule[$moduleId]['max_ms'], (int) ($row['max_ms'] ?? 0));
        }

        return $byModule;
    }

    /**
     * @return array<string, array{failed: int, active: int, completed: int, last_failed_at: ?string}>
     */
    private function syncStatsByDomain(Carbon $since): array
    {
        $stats = [];

        $rows = AdminSyncTask::query()
            ->selectRaw('domain, status, count(*) as aggregate')
            ->where('created_at', '>=', $since)
            ->groupBy('domain', 'status')
            ->get();

        foreach ($rows as $row) {
            $domain = (string) $row->domain;
            $stats[$domain] ??= ['failed' => 0, 'active' => 0, 'completed' => 0, 'last_failed_at' => null];
            $n = (int) $row->aggregate;
            $status = (string) $row->status;

            if ($status === AdminSyncTaskStatus::Failed->value) {
                $stats[$domain]['failed'] += $n;
            } elseif (in_array($status, [AdminSyncTaskStatus::Pending->value, AdminSyncTaskStatus::Processing->value], true)) {
                $stats[$domain]['active'] += $n;
            } elseif ($status === AdminSyncTaskStatus::Completed->value) {
                $stats[$domain]['completed'] += $n;
            }
        }

        $lastFailed = AdminSyncTask::query()
            ->selectRaw('domain, max(created_at) as last_at')
            ->where('status', AdminSyncTaskStatus::Failed->value)
            ->where('created_at', '>=', $since)
            ->groupBy('domain')
            ->pluck('last_at', 'domain');

        foreach ($lastFailed as $domain => $at) {
            $stats[(string) $domain] ??= ['failed' => 0, 'active' => 0, 'completed' => 0, 'last_failed_at' => null];
            $stats[(string) $domain]['last_failed_at'] = $at ? Carbon::parse($at)->toIso8601String() : null;
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, array{failed: int, active: int, completed: int, last_failed_at: ?string}>  $syncByDomain
     * @param  array{error_count: int, slow_count: int, max_ms: int, op_count: int}  $pulse
     * @param  list<array<string, mixed>>  $incidents
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $system
     */
    private function buildModuleRow(array $def, array $syncByDomain, array $pulse, array $incidents, array $system): array
    {
        $syncFailed = 0;
        $syncActive = 0;
        $syncCompleted = 0;
        $lastFailedAt = null;

        foreach ($def['sync_domains'] as $domain) {
            $row = $syncByDomain[$domain] ?? null;
            if ($row === null) {
                continue;
            }
            $syncFailed += $row['failed'];
            $syncActive += $row['active'];
            $syncCompleted += $row['completed'];
            if ($row['last_failed_at'] !== null) {
                $lastFailedAt = $lastFailedAt === null || $row['last_failed_at'] > $lastFailedAt
                    ? $row['last_failed_at']
                    : $lastFailedAt;
            }
        }

        $moduleIncidents = array_values(array_filter(
            $incidents,
            static fn (array $i): bool => ($i['module_id'] ?? '') === $def['id'],
        ));

        $failureCount = $syncFailed + $pulse['error_count']
            + count(array_filter($moduleIncidents, static fn (array $i): bool => $i['type'] === 'failure'));

        $slowCount = $pulse['slow_count']
            + count(array_filter($moduleIncidents, static fn (array $i): bool => $i['type'] === 'slowness'));

        $hasActivity = $syncFailed + $syncActive + $syncCompleted + $pulse['op_count'] > 0;

        $status = 'healthy';
        if ($failureCount > 0) {
            $status = 'critical';
        } elseif ($slowCount > 0 || $syncActive > 5) {
            $status = 'warning';
        } elseif (! $hasActivity && $def['id'] !== 'queue') {
            $status = 'unknown';
        }

        if ($def['id'] === 'queue') {
            if (($system['failed_jobs_period'] ?? 0) > 0 || ($system['sync_failures'] ?? 0) > 0) {
                $status = 'critical';
            } elseif (($system['pending_jobs'] ?? 0) >= 10) {
                $status = 'warning';
            } elseif (($system['pending_jobs'] ?? 0) === 0) {
                $status = 'healthy';
            }
        }

        return [
            'id' => $def['id'],
            'label' => $def['label'],
            'description' => $def['description'],
            'icon' => $def['icon'],
            'accent' => $def['accent'],
            'group' => $def['group'],
            'status' => $status,
            'operating_label' => $this->operatingLabelForStatus($status),
            'status_detail' => $this->statusDetailForModule(
                $status,
                $syncFailed,
                $pulse['error_count'],
                $slowCount,
                $syncActive,
                $pulse['op_count'],
                $hasActivity,
                $def['id'] === 'queue',
                $system,
            ),
            'sync_failed' => $syncFailed,
            'sync_active' => $syncActive,
            'sync_completed' => $syncCompleted,
            'last_failed_at' => $lastFailedAt,
            'pulse_errors' => $pulse['error_count'],
            'pulse_slow' => $pulse['slow_count'],
            'pulse_max_ms' => $pulse['max_ms'],
            'pulse_ops' => $pulse['op_count'],
            'incident_count' => count($moduleIncidents),
        ];
    }

    private function operatingLabelForStatus(string $status): string
    {
        return match ($status) {
            'healthy' => __('Em funcionamento'),
            'warning' => __('Degradado'),
            'critical' => __('Com falhas'),
            default => __('Indeterminado'),
        };
    }

    /**
     * @param  array<string, mixed>  $system
     */
    private function statusDetailForModule(
        string $status,
        int $syncFailed,
        int $pulseErrors,
        int $slowCount,
        int $syncActive,
        int $pulseOps,
        bool $hasActivity,
        bool $isQueueModule,
        array $system,
    ): string {
        if ($isQueueModule) {
            if ($status === 'critical') {
                return __('Jobs ou sincronizações falharam no período.');
            }
            if ($status === 'warning') {
                return __('Fila com volume elevado de jobs pendentes.');
            }

            return __('Workers e fila sem alertas no período.');
        }

        if ($status === 'critical') {
            if ($syncFailed > 0 && $pulseErrors > 0) {
                return __(':sync falha(s) na fila e :pulse erro(s) Pulse.', [
                    'sync' => $syncFailed,
                    'pulse' => $pulseErrors,
                ]);
            }
            if ($syncFailed > 0) {
                return __(':n falha(s) registada(s) na fila de sincronização.', ['n' => $syncFailed]);
            }
            if ($pulseErrors > 0) {
                return __(':n erro(s) de operação no Pulse.', ['n' => $pulseErrors]);
            }

            return __('Falha operacional detectada no período.');
        }

        if ($status === 'warning') {
            if ($slowCount > 0 && $syncActive > 0) {
                return __('Lentidão em :slow operação(ões) e :active tarefa(s) activa(s).', [
                    'slow' => $slowCount,
                    'active' => $syncActive,
                ]);
            }
            if ($slowCount > 0) {
                return __(':n operação(ões) acima do limiar de lentidão.', ['n' => $slowCount]);
            }
            if ($syncActive > 5) {
                return __(':n tarefas ainda em processamento na fila.', ['n' => $syncActive]);
            }

            return __('Funcionamento degradado — rever métricas abaixo.');
        }

        if ($status === 'healthy') {
            if ($hasActivity) {
                return __('Operações normais no período (:ops registos Pulse).', ['ops' => $pulseOps]);
            }

            return __('Sem alertas; sem actividade medida no período.');
        }

        return __('Sem telemetria suficiente para avaliar o período.');
    }

    /**
     * @param  array{operations: list, slow_operations: list, errors: list}  $pulseOps
     * @return list<array<string, mixed>>
     */
    private function collectIncidents(Carbon $since, array $pulseOps): array
    {
        $incidents = [];
        $slowMs = (int) config('module_monitor.slow_operation_ms', 750);

        AdminSyncTask::query()
            ->with(['city:id,name,uf'])
            ->where('status', AdminSyncTaskStatus::Failed->value)
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->each(function (AdminSyncTask $task) use (&$incidents): void {
                $moduleIds = ModuleMonitorCatalog::moduleIdsForSyncDomain((string) $task->domain);
                foreach ($moduleIds as $moduleId) {
                    $incidents[] = [
                        'id' => 'sync-'.$task->id.'-'.$moduleId,
                        'module_id' => $moduleId,
                        'type' => 'failure',
                        'title' => $task->label,
                        'detail' => \Illuminate\Support\Str::limit((string) ($task->error_message ?? __('Sem mensagem')), 200),
                        'occurred_at' => $task->created_at?->toIso8601String(),
                        'duration_ms' => $task->durationSeconds() !== null ? $task->durationSeconds() * 1000 : null,
                        'url' => route('admin.sync-queue.show', $task),
                    ];
                }
            });

        AnalyticsReportExport::query()
            ->with(['city:id,name', 'user:id,name'])
            ->where('status', AnalyticsReportExportStatus::Failed->value)
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->each(function (AnalyticsReportExport $export) use (&$incidents): void {
                $incidents[] = [
                    'id' => 'pdf-'.$export->id,
                    'module_id' => 'pdf',
                    'type' => 'failure',
                    'title' => __('PDF falhou — :city', ['city' => $export->city?->name ?? '#'.$export->city_id]),
                    'detail' => \Illuminate\Support\Str::limit((string) ($export->error_message ?? ''), 200),
                    'occurred_at' => $export->created_at?->toIso8601String(),
                    'duration_ms' => null,
                    'url' => route('admin.sync-queue.index', ['pdf_status' => 'failed']).'#fila-pdf',
                ];
            });

        foreach ($pulseOps['errors'] as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $incidents[] = [
                'id' => 'pulse-err-'.md5($key),
                'module_id' => ModuleMonitorCatalog::moduleIdForPulseKey($key) ?? 'analytics',
                'type' => 'failure',
                'title' => PulseOperationMetricsAggregator::labelForKey($key),
                'detail' => __(':count erro(s) registado(s) no Pulse.', ['count' => (int) ($row['count'] ?? 0)]),
                'occurred_at' => now()->toIso8601String(),
                'duration_ms' => null,
                'url' => route('pulse'),
            ];
        }

        foreach ($pulseOps['slow_operations'] as $row) {
            $key = (string) ($row['key'] ?? '');
            $maxMs = (int) ($row['max_ms'] ?? 0);
            if ($key === '' || $maxMs < $slowMs) {
                continue;
            }
            $incidents[] = [
                'id' => 'pulse-slow-'.md5($key),
                'module_id' => ModuleMonitorCatalog::moduleIdForPulseKey($key) ?? 'analytics',
                'type' => 'slowness',
                'title' => PulseOperationMetricsAggregator::labelForKey($key),
                'detail' => __('Pico :ms ms · :count ocorrência(s) lentas (limiar :limit ms).', [
                    'ms' => number_format($maxMs, 0, ',', '.'),
                    'count' => (int) ($row['count'] ?? 0),
                    'limit' => $slowMs,
                ]),
                'occurred_at' => now()->toIso8601String(),
                'duration_ms' => $maxMs,
                'url' => route('pulse'),
            ];
        }

        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')
                ->where('failed_at', '>=', $since)
                ->orderByDesc('failed_at')
                ->limit(10)
                ->get()
                ->each(function (object $job) use (&$incidents): void {
                    $jobName = 'job';
                    $decoded = json_decode((string) ($job->payload ?? ''), true);
                    if (is_array($decoded) && isset($decoded['displayName'])) {
                        $jobName = (string) $decoded['displayName'];
                    }

                    $incidents[] = [
                        'id' => 'failed-job-'.$job->id,
                        'module_id' => 'queue',
                        'type' => 'failure',
                        'title' => __('Job falhou: :name', ['name' => class_basename($jobName)]),
                        'detail' => \Illuminate\Support\Str::limit((string) ($job->exception ?? ''), 180),
                        'occurred_at' => Carbon::parse($job->failed_at)->toIso8601String(),
                        'duration_ms' => null,
                        'url' => route('admin.sync-queue.index'),
                    ];
                });
        }

        usort($incidents, static function (array $a, array $b): int {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });

        return $incidents;
    }

 
    private function sinceForPeriod(string $period): Carbon
    {
        $hours = (int) (config('module_monitor.periods.'.$period.'.hours')
            ?? config('module_monitor.periods.24h.hours', 24));

        return now()->subHours(max(1, $hours));
    }

    /**
     * @return array{error_count: int, slow_count: int, max_ms: int, op_count: int}
     */
    private static function emptyPulseModuleMetrics(): array
    {
        return [
            'error_count' => 0,
            'slow_count' => 0,
            'max_ms' => 0,
            'op_count' => 0,
        ];
    }
}
