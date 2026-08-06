<?php

namespace App\Services\Horizonte;

use App\Contracts\Notifications\OperationalNotificationChannel;
use App\Enums\NotificationPriority;
use App\Support\Horizonte\HorizonteFortnightlyFeedPhaseCatalog;
use App\Support\Notifications\NotificationKinds;
use App\Support\Notifications\NotificationQueuePresentation;

/** Notificações administrativas por fase do abastecimento Horizonte. */
final class HorizonteFortnightlyFeedNotifier
{
    /** Fases cuja falha é Critical (impacto directo no mapa / mercado / financeiro). */
    private const CRITICAL_FAILURE_PHASES = [
        'fundeb_receita',
        'censo_matriculas',
        'educacenso',
        'cadunico_sync',
        'procurement_sync',
        'obras_sync',
        'siconfi_sync',
        'saeb_planilhas',
        'ideb_divulgacao',
        'transparency_sync',
    ];

    public function __construct(
        private readonly OperationalNotificationChannel $dispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $phaseResult
     */
    public function phaseFinished(string $runId, array $phaseResult, int $step, int $total): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        $key = (string) ($phaseResult['key'] ?? '');
        $success = (bool) ($phaseResult['success'] ?? false);
        $skipped = (bool) ($phaseResult['skipped'] ?? false);

        if ($success && ! $skipped && ! $this->notifyPhaseSuccess()) {
            return;
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $label = HorizonteFortnightlyFeedPhaseCatalog::label($key);
        $message = trim((string) ($phaseResult['message'] ?? ''));

        $title = match (true) {
            $skipped => __('Horizonte — fase ignorada'),
            $success => __('Horizonte — fase concluída'),
            default => __('Horizonte — fase com falha'),
        };

        $body = __(':label (:step/:total). :msg', [
            'label' => $label,
            'step' => (string) $step,
            'total' => (string) $total,
            'msg' => $message !== '' ? $message : ($success ? __('OK') : __('Rever logs.')),
        ]);

        $critical = ! $success && ! $skipped && in_array($key, self::CRITICAL_FAILURE_PHASES, true);

        $this->dispatcher->notifyOperational($recipients, array_merge([
            'title' => $title,
            'body' => $body,
            'icon' => $success ? 'success' : ($skipped ? 'info' : 'error'),
            'priority' => $success || $skipped
                ? NotificationPriority::Normal->value
                : ($critical ? NotificationPriority::Critical->value : NotificationPriority::High->value),
            'kind' => NotificationKinds::PUBLIC_DATA,
            'dedupe_key' => 'horizonte:phase:'.$runId.':'.$key,
        ], NotificationQueuePresentation::forHorizonte()));
    }

    /**
     * @param  array<string, mixed>  $pipeline
     */
    public function cycleFinished(array $pipeline): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        $success = (bool) ($pipeline['success'] ?? false);
        if ($success && ! $this->notifyPhaseSuccess()) {
            // Ciclo OK ainda notifica se notify_cycle_success (default true) — resumo útil.
            if (! filter_var(config('horizonte.fortnightly_feed.notify_cycle_success', true), FILTER_VALIDATE_BOOLEAN)) {
                return;
            }
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $runId = (string) ($pipeline['run_id'] ?? '');
        $total = count(is_array($pipeline['phase_queue'] ?? null) ? $pipeline['phase_queue'] : []);
        $message = trim((string) ($pipeline['message'] ?? ''));

        $this->dispatcher->notifyOperational($recipients, array_merge([
            'title' => $success
                ? __('Horizonte — abastecimento concluído')
                : __('Horizonte — abastecimento com avisos'),
            'body' => $message !== ''
                ? $message
                : __(':n fase(s) executadas em etapas.', ['n' => (string) $total]),
            'icon' => $success ? 'success' : 'warning',
            'priority' => $success ? NotificationPriority::Normal->value : NotificationPriority::High->value,
            'kind' => NotificationKinds::PUBLIC_DATA,
            'dedupe_key' => 'horizonte:cycle:'.$runId,
        ], NotificationQueuePresentation::forHorizonte()));
    }

    private function shouldNotify(): bool
    {
        return $this->dispatcher->isEnabled()
            && filter_var(config('horizonte.fortnightly_feed.notify_phases', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function notifyPhaseSuccess(): bool
    {
        return filter_var(config('horizonte.fortnightly_feed.notify_phase_success', false), FILTER_VALIDATE_BOOLEAN);
    }
}
