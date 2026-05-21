<?php

namespace App\Services\Notifications;

use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Enums\NotificationPriority;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Models\User;
use App\Support\Notifications\NotificationKinds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OperationalAlertsNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * Avalia o ambiente operacional e notifica administradores (com deduplicação).
     * Também é invocado por `php artisan notifications:operational-alerts` no agendador.
     */
    public function notifyAdminsIfNeeded(?User $triggeredBy = null): void
    {
        if (! $this->dispatcher->isEnabled() || ! (bool) config('notifications.operational_alerts.enabled', true)) {
            return;
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $cfg = (array) config('notifications.operational_alerts', []);
        $now = now();

        $syncFailed = AdminSyncTask::query()
            ->where('status', AdminSyncTaskStatus::Failed->value)
            ->where('created_at', '>=', $now->copy()->subDay())
            ->count();

        if ($syncFailed >= max(1, (int) ($cfg['sync_failures_threshold'] ?? 1))) {
            $this->dispatcher->notifyOperational(
                $recipients,
                [
                    'title' => __('Falhas de sincronização nas últimas 24 h'),
                    'body' => __(':count tarefa(s) falharam. Revise a fila de processamento e os logs.', ['count' => $syncFailed]),
                    'icon' => 'error',
                    'priority' => NotificationPriority::Critical->value,
                    'kind' => NotificationKinds::OPERATIONS,
                    'action_url' => route('admin.sync-queue.index'),
                    'dedupe_key' => 'ops:sync_failed_24h',
                ],
            );
        }

        $staleHours = max(1, (int) ($cfg['pdf_stale_hours'] ?? 2));
        $pdfStale = AnalyticsReportExport::query()
            ->whereIn('status', [
                AnalyticsReportExportStatus::Pending->value,
                AnalyticsReportExportStatus::Processing->value,
            ])
            ->where('created_at', '<=', $now->copy()->subHours($staleHours))
            ->count();

        if ($pdfStale > 0) {
            $this->dispatcher->notifyOperational(
                $recipients,
                [
                    'title' => __('PDFs presos na fila'),
                    'body' => __(':count relatório(s) pendente(s) há mais de :h hora(s). Verifique o worker da fila.', [
                        'count' => $pdfStale,
                        'h' => $staleHours,
                    ]),
                    'icon' => 'warning',
                    'priority' => NotificationPriority::Critical->value,
                    'kind' => NotificationKinds::OPERATIONS,
                    'action_url' => route('admin.sync-queue.index'),
                    'dedupe_key' => 'ops:pdf_stale',
                ],
            );
        }

        if (Schema::hasTable('jobs')) {
            $pendingJobs = (int) DB::table('jobs')->count();
            $threshold = max(10, (int) ($cfg['queue_pending_threshold'] ?? 25));

            if ($pendingJobs >= $threshold) {
                $this->dispatcher->notifyOperational(
                    $recipients,
                    [
                        'title' => __('Fila de jobs sobrecarregada'),
                        'body' => __(':count job(s) aguardam processamento (limiar :limit). Confirme que o worker está activo.', [
                            'count' => $pendingJobs,
                            'limit' => $threshold,
                        ]),
                        'icon' => 'warning',
                        'priority' => NotificationPriority::High->value,
                        'kind' => NotificationKinds::OPERATIONS,
                        'action_url' => route('admin.sync-queue.index'),
                        'dedupe_key' => 'ops:queue_backlog',
                    ],
                );
            }
        }

        if ((string) config('queue.default') === 'sync' && app()->environment('production')) {
            $this->dispatcher->notifyOperational(
                $recipients,
                [
                    'title' => __('Fila em modo síncrono (produção)'),
                    'body' => __('QUEUE_CONNECTION=sync — PDFs e sincronizações não persistem na tabela jobs. Configure database ou redis.'),
                    'icon' => 'error',
                    'priority' => NotificationPriority::Critical->value,
                    'kind' => NotificationKinds::OPERATIONS,
                    'action_url' => route('admin.sync-queue.index'),
                    'dedupe_key' => 'ops:queue_sync_mode',
                ],
            );
        }
    }
}
