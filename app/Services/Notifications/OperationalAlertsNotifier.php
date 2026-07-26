<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\OperationalNotificationChannel;
use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Enums\NotificationPriority;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Models\User;
use App\Support\Notifications\NotificationKinds;
use App\Support\Notifications\NotificationQueuePresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OperationalAlertsNotifier
{
    public function __construct(
        private readonly OperationalNotificationChannel $dispatcher,
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
                array_merge([
                    'title' => __('Falhas de sincronização nas últimas 24 h'),
                    'body' => __(':count tarefa(s) falharam. Revise a fila de processamento e os logs.', ['count' => $syncFailed]),
                    'icon' => 'error',
                    'priority' => NotificationPriority::Critical->value,
                    'kind' => NotificationKinds::OPERATIONS,
                    'action_url' => route('admin.sync-queue.index'),
                    'dedupe_key' => 'ops:sync_failed_24h',
                ], NotificationQueuePresentation::forOperations('sync_failed')),
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
                array_merge([
                    'title' => __('PDFs presos na fila'),
                    'body' => __(':count relatório(s) pendente(s) há mais de :h hora(s). Verifique o worker da fila.', [
                        'count' => $pdfStale,
                        'h' => $staleHours,
                    ]),
                    'icon' => 'warning',
                    'priority' => NotificationPriority::Critical->value,
                    'kind' => NotificationKinds::OPERATIONS,
                    'action_url' => route('admin.sync-queue.index').'#fila-pdf',
                    'dedupe_key' => 'ops:pdf_stale',
                ], NotificationQueuePresentation::forOperations('pdf_stale')),
            );
        }

        if (Schema::hasTable('jobs')) {
            $pendingJobs = (int) DB::table('jobs')->count();
            $threshold = max(10, (int) ($cfg['queue_pending_threshold'] ?? 25));

            if ($pendingJobs >= $threshold) {
                $this->dispatcher->notifyOperational(
                    $recipients,
                    array_merge([
                        'title' => __('Fila de jobs sobrecarregada'),
                        'body' => __(':count job(s) aguardam processamento (limiar :limit). Confirme que o worker está ativo.', [
                            'count' => $pendingJobs,
                            'limit' => $threshold,
                        ]),
                        'icon' => 'warning',
                        'priority' => NotificationPriority::High->value,
                        'kind' => NotificationKinds::OPERATIONS,
                        'action_url' => route('admin.sync-queue.index'),
                        'dedupe_key' => 'ops:queue_backlog',
                    ], NotificationQueuePresentation::forOperations('queue_backlog')),
                );
            }
        }

        if ((string) config('queue.default') === 'sync' && app()->environment('production')) {
            $this->dispatcher->notifyOperational(
                $recipients,
                array_merge([
                    'title' => __('Fila em modo síncrono (produção)'),
                    'body' => __('QUEUE_CONNECTION=sync — PDFs e sincronizações não persistem na tabela jobs. Configure database ou redis.'),
                    'icon' => 'error',
                    'priority' => NotificationPriority::Critical->value,
                    'kind' => NotificationKinds::OPERATIONS,
                    'action_url' => route('admin.sync-queue.index'),
                    'dedupe_key' => 'ops:queue_sync_mode',
                ], NotificationQueuePresentation::forOperations('generic')),
            );
        }

        $this->notifyStaleAdminSync($recipients, $cfg, $now);
        $this->notifyStuckPipeline(
            $recipients,
            'ops:horizonte_pipeline_stuck',
            \App\Support\Horizonte\HorizonteFortnightlyFeedPipeline::get(),
            max(2, (int) ($cfg['pipeline_stuck_hours'] ?? 6)),
            __('Horizonte — pipeline parado'),
            route('admin.sync-queue.index').'#fila-horizonte',
            'horizonte_stuck',
        );
        $this->notifyStuckPipeline(
            $recipients,
            'ops:cadunico_escolarizacao_pipeline_stuck',
            \App\Support\Cadunico\CadunicoEscolarizacaoFeedPipeline::get(),
            max(2, (int) ($cfg['pipeline_stuck_hours'] ?? 6)),
            __('CadÚnico — pipeline Escolarização parado'),
            route('admin.sync-queue.index').'#agendamentos',
            'cadunico_stuck',
        );

        app(ModuleMonitorOperationalNotifier::class)->notifyIfSnapshotStale();
        app(ModuleMonitorOperationalNotifier::class)->notifyDailySummaryIfDue();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $recipients
     * @param  array<string, mixed>  $cfg
     */
    private function notifyStaleAdminSync($recipients, array $cfg, \Illuminate\Support\Carbon $now): void
    {
        $staleHours = max(1, (int) ($cfg['sync_stale_hours'] ?? 4));
        $stale = AdminSyncTask::query()
            ->where('status', AdminSyncTaskStatus::Processing->value)
            ->where(function ($q) use ($now, $staleHours): void {
                $q->where(function ($inner) use ($now, $staleHours): void {
                    $inner->whereNotNull('started_at')
                        ->where('started_at', '<=', $now->copy()->subHours($staleHours));
                })->orWhere(function ($inner) use ($now, $staleHours): void {
                    $inner->whereNull('started_at')
                        ->where('created_at', '<=', $now->copy()->subHours($staleHours));
                });
            })
            ->count();

        if ($stale <= 0) {
            return;
        }

        $this->dispatcher->notifyOperational(
            $recipients,
            array_merge([
                'title' => __('Sincronizações presas em processamento'),
                'body' => __(':count tarefa(s) em «processando» há mais de :h hora(s). Verifique o worker admin-sync.', [
                    'count' => $stale,
                    'h' => $staleHours,
                ]),
                'icon' => 'warning',
                'priority' => NotificationPriority::Critical->value,
                'kind' => NotificationKinds::OPERATIONS,
                'action_url' => route('admin.sync-queue.index'),
                'dedupe_key' => 'ops:sync_stale',
            ], NotificationQueuePresentation::forOperations('sync_stale')),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $recipients
     * @param  array<string, mixed>|null  $state
     */
    private function notifyStuckPipeline(
        $recipients,
        string $dedupeKey,
        ?array $state,
        int $stuckHours,
        string $title,
        string $actionUrl,
        string $opsVariant,
    ): void {
        if (! is_array($state) || ($state['status'] ?? '') !== 'running') {
            return;
        }

        $updatedRaw = $state['updated_at'] ?? $state['started_at'] ?? null;
        if (! is_string($updatedRaw) || $updatedRaw === '') {
            return;
        }

        try {
            $updated = \Illuminate\Support\Carbon::parse($updatedRaw);
        } catch (\Throwable) {
            return;
        }

        if ($updated->greaterThan(now()->subHours($stuckHours))) {
            return;
        }

        $phase = (string) ($state['current_phase'] ?? '—');
        $this->dispatcher->notifyOperational(
            $recipients,
            array_merge([
                'title' => $title,
                'body' => __('Sem progresso há :h h (fase :phase). Retome com --continue ou reinicie com --reset.', [
                    'h' => (string) $stuckHours,
                    'phase' => $phase,
                ]),
                'icon' => 'warning',
                'priority' => NotificationPriority::Critical->value,
                'kind' => NotificationKinds::OPERATIONS,
                'action_url' => $actionUrl,
                'dedupe_key' => $dedupeKey,
            ], NotificationQueuePresentation::forOperations($opsVariant)),
        );
    }
}
