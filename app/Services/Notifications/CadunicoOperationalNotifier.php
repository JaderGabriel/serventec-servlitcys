<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\OperationalNotificationChannel;
use App\Enums\NotificationPriority;
use App\Support\Notifications\NotificationKinds;
use App\Support\Notifications\NotificationQueuePresentation;

/** Notificações de rotinas CadÚnico (CUN-04, Escolarização) para o sino admin. */
final class CadunicoOperationalNotifier
{
    public function __construct(
        private readonly OperationalNotificationChannel $dispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $result  Saída de CadunicoPortalBeneficiosSyncService::sync
     */
    public function beneficiosPortalFinished(array $result): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        if ($result['dry_run'] ?? false) {
            return;
        }

        if ($result['skipped'] ?? false) {
            return;
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $success = (bool) ($result['success'] ?? false);
        $message = trim((string) ($result['message'] ?? ''));
        $cities = (int) ($result['cities'] ?? 0);
        $month = now()->format('Y-m');

        if ($success && ! $this->notifySuccess()) {
            return;
        }

        $this->dispatcher->notifyOperational($recipients, array_merge([
            'title' => $success
                ? __('CadÚnico — benefícios Portal (CUN-04) OK')
                : __('CadÚnico — benefícios Portal (CUN-04) falhou'),
            'body' => $message !== ''
                ? $message
                : ($success
                    ? __(':n município(s) sincronizado(s).', ['n' => (string) $cities])
                    : __('Rever logs e PORTAL_TRANSPARENCIA_API_KEY.')),
            'icon' => $success ? 'success' : 'error',
            'priority' => $success ? NotificationPriority::Normal->value : NotificationPriority::Critical->value,
            'kind' => NotificationKinds::PUBLIC_DATA,
            'dedupe_key' => 'cadunico:beneficios:'.$month.':'.($success ? 'ok' : 'fail'),
        ], NotificationQueuePresentation::forCadunico(route('admin.cadunico-sync.index'))));
    }

    /**
     * @param  array<string, mixed>  $phaseResult
     * @param  array<string, mixed>  $pipeline
     */
    public function escolarizacaoPhaseFinished(array $phaseResult, array $pipeline, int $step, int $total): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        $success = (bool) ($phaseResult['success'] ?? false);
        if ($success && ! $this->notifySuccess()) {
            return;
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $runId = (string) ($pipeline['run_id'] ?? 'manual');
        $key = (string) ($phaseResult['key'] ?? '');
        $message = trim((string) ($phaseResult['message'] ?? ''));

        $this->dispatcher->notifyOperational($recipients, array_merge([
            'title' => $success
                ? __('CadÚnico — fase Escolarização OK')
                : __('CadÚnico — fase Escolarização com falha'),
            'body' => __(':phase (:step/:total). :msg', [
                'phase' => $key !== '' ? $key : '—',
                'step' => (string) $step,
                'total' => (string) $total,
                'msg' => $message !== '' ? $message : ($success ? __('OK') : __('Rever logs.')),
            ]),
            'icon' => $success ? 'success' : 'error',
            'priority' => $success ? NotificationPriority::Normal->value : NotificationPriority::Critical->value,
            'kind' => NotificationKinds::PUBLIC_DATA,
            'dedupe_key' => 'cadunico:escolarizacao:'.$runId.':'.$key,
        ], NotificationQueuePresentation::forCadunico()));
    }

    /**
     * @param  array<string, mixed>  $pipeline
     */
    public function escolarizacaoCycleFinished(array $pipeline): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        $success = (bool) ($pipeline['success'] ?? false);
        if ($success && ! $this->notifySuccess()) {
            return;
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $runId = (string) ($pipeline['run_id'] ?? 'manual');
        $message = trim((string) ($pipeline['message'] ?? ''));

        $this->dispatcher->notifyOperational($recipients, array_merge([
            'title' => $success
                ? __('CadÚnico — Escolarização concluída')
                : __('CadÚnico — Escolarização com avisos'),
            'body' => $message !== '' ? $message : __('Pipeline Escolarização terminou.'),
            'icon' => $success ? 'success' : 'warning',
            'priority' => $success ? NotificationPriority::Normal->value : NotificationPriority::High->value,
            'kind' => NotificationKinds::PUBLIC_DATA,
            'dedupe_key' => 'cadunico:escolarizacao:cycle:'.$runId,
        ], NotificationQueuePresentation::forCadunico()));
    }

    private function shouldNotify(): bool
    {
        return $this->dispatcher->isEnabled()
            && filter_var(config('ieducar.cadunico.notify_operational', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function notifySuccess(): bool
    {
        return filter_var(config('ieducar.cadunico.notify_success', false), FILTER_VALIDATE_BOOLEAN);
    }
}
