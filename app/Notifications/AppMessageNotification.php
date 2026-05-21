<?php

namespace App\Notifications;

use App\Support\Notifications\NotificationPayload;
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
 *   priority?: string,
 *   action_url?: ?string,
 *   kind?: ?string,
 *   dedupe_key?: ?string
 * }  $payload
 */
class AppMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
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
        return NotificationPayload::normalize($this->payload);
    }
}
