<?php

namespace App\Services\Horizonte;

use App\Enums\NotificationPriority;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\Horizonte\HorizonteFortnightlyFeedPhaseCatalog;
use App\Support\Notifications\NotificationKinds;
use App\Support\Notifications\NotificationQueuePresentation;

/** Notificações administrativas por fase do abastecimento Horizonte. */
final class HorizonteFortnightlyFeedNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $phaseResult
     */
    public function phaseFinished(string $runId, array $phaseResult, int $step, int $total): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $key = (string) ($phaseResult['key'] ?? '');
        $label = HorizonteFortnightlyFeedPhaseCatalog::label($key);
        $success = (bool) ($phaseResult['success'] ?? false);
        $skipped = (bool) ($phaseResult['skipped'] ?? false);
        $message = trim((string) ($phaseResult['message'] ?? ''));

        $title = match (true) {
            $skipped => __('Horizonte — fase ignorada'),
            $success => __('Horizonte — fase concluída'),
            default => __('Horizonte — fase com aviso'),
        };

        $body = __(':label (:step/:total). :msg', [
            'label' => $label,
            'step' => (string) $step,
            'total' => (string) $total,
            'msg' => $message !== '' ? $message : ($success ? __('OK') : __('Rever logs.')),
        ]);

        $this->dispatcher->notifyOperational($recipients, array_merge([
            'title' => $title,
            'body' => $body,
            'icon' => $success ? 'success' : ($skipped ? 'info' : 'warning'),
            'priority' => $success ? NotificationPriority::Normal->value : NotificationPriority::High->value,
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

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return;
        }

        $runId = (string) ($pipeline['run_id'] ?? '');
        $success = (bool) ($pipeline['success'] ?? false);
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
}
