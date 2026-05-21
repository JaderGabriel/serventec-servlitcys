<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notificação persistida em `notifications` (canal database).
 *
 * @param  array{
 *   title: string,
 *   body: string,
 *   icon?: string,
 *   action_url?: ?string,
 *   kind?: ?string
 * }  $payload
 */
class AppMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{title: string, body: string, icon?: string, action_url?: ?string, kind?: ?string}  $payload
     */
    public function __construct(
        public array $payload,
    ) {
        $this->onQueue((string) config('notifications.queue', 'default'));
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => (string) ($this->payload['title'] ?? ''),
            'body' => (string) ($this->payload['body'] ?? ''),
            'icon' => (string) ($this->payload['icon'] ?? 'info'),
            'action_url' => filled($this->payload['action_url'] ?? null) ? (string) $this->payload['action_url'] : null,
            'kind' => filled($this->payload['kind'] ?? null) ? (string) $this->payload['kind'] : null,
        ];
    }
}
