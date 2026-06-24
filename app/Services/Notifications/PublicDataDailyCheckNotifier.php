<?php

namespace App\Services\Notifications;

use App\Enums\NotificationPriority;
use App\Services\Admin\PublicDataOfficialAvailabilityService;
use App\Services\Admin\PublicDataOfficialCheckCache;
use App\Support\Admin\PublicDataAvailabilityPresenter;
use App\Support\Notifications\NotificationKinds;

final class PublicDataDailyCheckNotifier
{
    public function __construct(
        private readonly PublicDataOfficialAvailabilityService $availability,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * Executa verificação read-only, regista cache para o hub admin e opcionalmente notifica.
     *
     * @return array<string, mixed>
     */
    public function run(bool $notify = true): array
    {
        if (! (bool) config('public_data_availability.enabled', true)) {
            return ['skipped' => true, 'reason' => 'disabled'];
        }

        $report = $this->availability->scan();
        PublicDataOfficialCheckCache::put($report);

        $notified = false;
        if ($notify) {
            $notified = $this->dispatchNotification($report);
        }

        $counts = PublicDataAvailabilityPresenter::counts($report);

        return [
            'skipped' => false,
            'has_news' => (bool) $report['has_news'],
            'news_count' => (int) $report['news_count'],
            'attention_count' => $counts['attention'],
            'aligned_count' => $counts['aligned'],
            'action_count' => $counts['action'],
            'findings' => count($report['findings']),
            'notified' => $notified,
            'report' => $report,
        ];
    }

    /** @deprecated Use run() — mantido para compatibilidade com agendador. */
    public function notifyAdminsDaily(): array
    {
        return $this->run(true);
    }

    /**
     * @param  array{has_news: bool, news_count: int, attention_count?: int, findings: list<array<string, mixed>>}  $report
     */
    private function dispatchNotification(array $report): bool
    {
        if (! $this->dispatcher->isEnabled()) {
            return false;
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return false;
        }

        $counts = PublicDataAvailabilityPresenter::counts($report);
        $hasAction = $counts['action'] > 0;

        $this->dispatcher->notifyOperational($recipients, [
            'title' => PublicDataAvailabilityPresenter::notificationTitle($report),
            'body' => PublicDataAvailabilityPresenter::notificationBody($report),
            'icon' => $hasAction ? ($counts['new'] > 0 ? 'info' : 'warning') : 'success',
            'priority' => $counts['new'] > 0
                ? NotificationPriority::High->value
                : ($hasAction ? NotificationPriority::High->value : NotificationPriority::Normal->value),
            'kind' => NotificationKinds::PUBLIC_DATA,
            'action_url' => route('admin.public-data.index').'#verificacao-oficial',
            'dedupe_key' => 'public-data:daily:'.now()->format('Y-m-d'),
        ]);

        return true;
    }
}
