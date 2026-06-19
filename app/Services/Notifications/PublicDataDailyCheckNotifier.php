<?php

namespace App\Services\Notifications;

use App\Enums\NotificationPriority;
use App\Services\Admin\PublicDataOfficialAvailabilityService;
use App\Support\Notifications\NotificationKinds;

final class PublicDataDailyCheckNotifier
{
    public function __construct(
        private readonly PublicDataOfficialAvailabilityService $availability,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function notifyAdminsDaily(): array
    {
        if (! (bool) config('public_data_availability.enabled', true)) {
            return ['skipped' => true, 'reason' => 'disabled'];
        }

        if (! $this->dispatcher->isEnabled()) {
            return ['skipped' => true, 'reason' => 'notifications_disabled'];
        }

        $recipients = $this->dispatcher->operationalRecipients();
        if ($recipients->isEmpty()) {
            return ['skipped' => true, 'reason' => 'no_recipients'];
        }

        $report = $this->availability->scan();
        $body = $this->buildBody($report);

        $this->dispatcher->notifyOperational($recipients, [
            'title' => $report['has_news']
                ? __('Dados públicos: :n novidade(s) nas fontes oficiais', ['n' => (int) $report['news_count']])
                : __('Dados públicos: verificação diária — sem novidades'),
            'body' => $body,
            'icon' => $report['has_news'] ? 'info' : 'success',
            'priority' => $report['has_news']
                ? NotificationPriority::High->value
                : NotificationPriority::Normal->value,
            'kind' => NotificationKinds::PUBLIC_DATA,
            'action_url' => route('admin.public-data.index'),
            'dedupe_key' => 'public-data:daily:'.now()->format('Y-m-d'),
        ]);

        return [
            'skipped' => false,
            'has_news' => (bool) $report['has_news'],
            'news_count' => (int) $report['news_count'],
            'findings' => count($report['findings']),
        ];
    }

    /**
     * @param  array{has_news: bool, news_count: int, findings: list<array<string, mixed>>}  $report
     */
    private function buildBody(array $report): string
    {
        $lines = [];
        $lines[] = $report['has_news']
            ? __('Foram detectadas publicações ou lacunas que podem exigir importação:')
            : __('Nenhuma novidade nas fontes verificadas hoje. Resumo por área:');

        foreach ($report['findings'] as $finding) {
            $status = (string) ($finding['status'] ?? '');
            $prefix = match ($status) {
                'new_available' => '●',
                'attention' => '◦',
                'unreachable' => '✕',
                'not_configured' => '—',
                default => '○',
            };

            $lines[] = '';
            $lines[] = $prefix.' '.($finding['source_title'] ?? $finding['source_id'] ?? '');
            $lines[] = (string) ($finding['headline'] ?? '');
            if (filled($finding['detail'] ?? null)) {
                $lines[] = (string) $finding['detail'];
            }

            $routineCli = $finding['routine_cli'] ?? null;
            if (is_string($routineCli) && $routineCli !== '' && in_array($status, ['new_available', 'attention', 'unreachable', 'not_configured'], true)) {
                $lines[] = __('Rotina: :cmd', ['cmd' => $routineCli]);
            } elseif (filled($finding['routine_label'] ?? null) && in_array($status, ['new_available', 'attention'], true)) {
                $lines[] = __('Rotina: :label (hub Dados públicos)', ['label' => (string) $finding['routine_label']]);
            }
        }

        $lines[] = '';
        $lines[] = __('Hub: :url', ['url' => route('admin.public-data.index')]);

        return mb_substr(implode("\n", $lines), 0, 3500);
    }
}
