<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\OperationalNotificationChannel;
use App\Enums\NotificationPriority;
use App\Support\Notifications\NotificationKinds;
use App\Support\Notifications\NotificationQueuePresentation;
use App\Support\Scheduling\ScheduledJobsCatalog;
use Illuminate\Support\Facades\Log;

/** Notifica o sino quando um job agendado crítico falha (onFailure). */
final class ScheduleFailureNotifier
{
    public function __construct(
        private readonly OperationalNotificationChannel $dispatcher,
    ) {}

    public function notify(string $jobName): void
    {
        if (! $this->dispatcher->isEnabled()) {
            return;
        }

        if (! filter_var(config('notifications.schedule_failures.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $meta = ScheduledJobsCatalog::metaFor($jobName);
        $label = is_array($meta) ? (string) ($meta['label'] ?? $jobName) : $jobName;
        $command = is_array($meta) ? (string) ($meta['command'] ?? $jobName) : $jobName;

        Log::warning('schedule.job_failed', [
            'job' => $jobName,
            'label' => $label,
        ]);

        $this->dispatcher->notifyOperational($recipients, array_merge([
            'title' => __('Agendamento falhou — :label', ['label' => $label]),
            'body' => __('O job «:job» terminou com erro. Comando: :cmd', [
                'job' => $jobName,
                'cmd' => $command,
            ]),
            'icon' => 'error',
            'priority' => NotificationPriority::Critical->value,
            'kind' => NotificationKinds::OPERATIONS,
            'action_url' => route('admin.sync-queue.index').'#agendamentos',
            'dedupe_key' => 'ops:schedule_failed:'.$jobName,
        ], NotificationQueuePresentation::forOperations('schedule_failed')));
    }
}
