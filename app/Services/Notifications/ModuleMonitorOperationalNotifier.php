<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\OperationalNotificationChannel;
use App\Enums\NotificationPriority;
use App\Support\Admin\ModuleMonitorCatalog;
use App\Support\Admin\ModuleMonitorSnapshotCache;
use App\Support\Notifications\NotificationKinds;
use App\Support\Notifications\NotificationQueuePresentation;

/**
 * Liga o Module Monitor ao sino admin: pós-recolha, snapshot stale e resumo diário.
 */
final class ModuleMonitorOperationalNotifier
{
    public function __construct(
        private readonly OperationalNotificationChannel $dispatcher,
    ) {}

    /**
     * Avalia o snapshot acabado de recolher e notifica falhas / degradações.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function afterCollect(array $snapshot): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        $modules = is_array($snapshot['modules'] ?? null) ? $snapshot['modules'] : [];
        $failed = [];
        $degraded = [];

        foreach ($modules as $moduleId => $probe) {
            if (! is_array($probe)) {
                continue;
            }
            $signal = (string) ($probe['signal'] ?? 'unknown');
            $label = ModuleMonitorCatalog::find((string) $moduleId)['label']
                ?? (string) $moduleId;

            if ($signal === 'failed') {
                $failed[] = $label;
            } elseif ($signal === 'degraded') {
                $degraded[] = $label;
            }
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        if ($failed !== [] && $this->notifyCritical()) {
            $this->dispatcher->notifyOperational($recipients, array_merge([
                'title' => __('Monitor de módulos — falhas'),
                'body' => __(':n módulo(s) com sinal «failed»: :list', [
                    'n' => (string) count($failed),
                    'list' => implode(', ', array_slice($failed, 0, 8)),
                ]),
                'icon' => 'error',
                'priority' => NotificationPriority::Critical->value,
                'kind' => NotificationKinds::OPERATIONS,
                'action_url' => route('admin.module-monitor.index'),
                'dedupe_key' => 'ops:module_monitor:failed',
            ], NotificationQueuePresentation::forOperations('module_monitor')));
        }

        if ($degraded !== [] && $this->notifyDegraded()) {
            $this->dispatcher->notifyOperational($recipients, array_merge([
                'title' => __('Monitor de módulos — degradado'),
                'body' => __(':n módulo(s) com sinal «degraded»: :list', [
                    'n' => (string) count($degraded),
                    'list' => implode(', ', array_slice($degraded, 0, 8)),
                ]),
                'icon' => 'warning',
                'priority' => NotificationPriority::High->value,
                'kind' => NotificationKinds::OPERATIONS,
                'action_url' => route('admin.module-monitor.index'),
                'dedupe_key' => 'ops:module_monitor:degraded',
            ], NotificationQueuePresentation::forOperations('module_monitor')));
        }
    }

    /** Snapshot ausente ou demasiado antigo (recolha agendada falhou / parou). */
    public function notifyIfSnapshotStale(): void
    {
        if (! $this->shouldNotify() || ! $this->notifySnapshotStale()) {
            return;
        }

        if (! filter_var(config('module_monitor.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $snapshot = ModuleMonitorSnapshotCache::get();
        if (ModuleMonitorSnapshotCache::isFresh($snapshot)) {
            return;
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $staleHours = max(1, (int) config('module_monitor.snapshot.stale_hours', 36));
        $collected = is_array($snapshot) ? (string) ($snapshot['collected_at'] ?? '') : '';

        $this->dispatcher->notifyOperational($recipients, array_merge([
            'title' => __('Monitor de módulos — recolha desactualizada'),
            'body' => $collected !== ''
                ? __('Última sonda :at (limiar :h h). Verifique o agendamento module-monitor:collect.', [
                    'at' => $collected,
                    'h' => (string) $staleHours,
                ])
                : __('Nenhuma sonda em cache. Execute module-monitor:collect ou aguarde o agendamento.'),
            'icon' => 'warning',
            'priority' => NotificationPriority::Critical->value,
            'kind' => NotificationKinds::OPERATIONS,
            'action_url' => route('admin.module-monitor.index'),
            'dedupe_key' => 'ops:module_monitor:snapshot_stale',
        ], NotificationQueuePresentation::forOperations('module_monitor')));
    }

    /**
     * Resumo diário de saúde (uma vez por dia, via alertas operacionais).
     */
    public function notifyDailySummaryIfDue(): void
    {
        if (! $this->shouldNotify() || ! $this->notifyDailySummary()) {
            return;
        }

        if (! filter_var(config('module_monitor.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $time = trim((string) config('module_monitor.notify.daily_summary_time', '08:00')) ?: '08:00';
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time, 2)), 2, 0);
        $now = now();
        // Janela de ~ops interval após a hora configurada (ex.: 08:00–08:20).
        if ((int) $now->format('G') !== $hour || (int) $now->format('i') > max(20, $minute + 15)) {
            return;
        }

        $snapshot = ModuleMonitorSnapshotCache::get();
        if (! is_array($snapshot) || ! is_array($snapshot['modules'] ?? null)) {
            return;
        }

        $counts = ['failed' => 0, 'degraded' => 0, 'operational' => 0, 'idle' => 0, 'unknown' => 0];
        foreach ($snapshot['modules'] as $probe) {
            if (! is_array($probe)) {
                continue;
            }
            $signal = (string) ($probe['signal'] ?? 'unknown');
            if (! array_key_exists($signal, $counts)) {
                $signal = 'unknown';
            }
            $counts[$signal]++;
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $this->dispatcher->notifyOperational($recipients, array_merge([
            'title' => __('Monitor de módulos — resumo diário'),
            'body' => __('Falhas :f · Degradados :d · OK/repouso :ok · Indeterminados :u', [
                'f' => (string) $counts['failed'],
                'd' => (string) $counts['degraded'],
                'ok' => (string) ($counts['operational'] + $counts['idle']),
                'u' => (string) $counts['unknown'],
            ]),
            'icon' => $counts['failed'] > 0 ? 'error' : ($counts['degraded'] > 0 ? 'warning' : 'success'),
            'priority' => $counts['failed'] > 0
                ? NotificationPriority::Critical->value
                : NotificationPriority::Normal->value,
            'kind' => NotificationKinds::OPERATIONS,
            'action_url' => route('admin.module-monitor.index'),
            'dedupe_key' => 'ops:module_monitor:summary:'.$now->format('Y-m-d'),
        ], NotificationQueuePresentation::forOperations('module_monitor')));
    }

    private function shouldNotify(): bool
    {
        return $this->dispatcher->isEnabled()
            && filter_var(config('module_monitor.notify.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function notifyCritical(): bool
    {
        return filter_var(config('module_monitor.notify.on_critical', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function notifyDegraded(): bool
    {
        return filter_var(config('module_monitor.notify.on_degraded', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function notifySnapshotStale(): bool
    {
        return filter_var(config('module_monitor.notify.snapshot_stale', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function notifyDailySummary(): bool
    {
        return filter_var(config('module_monitor.notify.daily_summary', true), FILTER_VALIDATE_BOOLEAN);
    }
}
