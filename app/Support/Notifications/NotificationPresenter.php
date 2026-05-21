<?php

namespace App\Support\Notifications;

use Illuminate\Notifications\DatabaseNotification;

final class NotificationPresenter
{
    /**
     * @return array{
     *   id: string,
     *   read: bool,
     *   title: string,
     *   body: string,
     *   icon: string,
     *   kind: ?string,
     *   action_url: ?string,
     *   created_at: string,
     *   created_label: string
     * }
     */
    public static function fromDatabaseNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => (string) $notification->id,
            'read' => $notification->read_at !== null,
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'icon' => (string) ($data['icon'] ?? 'info'),
            'kind' => filled($data['kind'] ?? null) ? (string) $data['kind'] : null,
            'action_url' => filled($data['action_url'] ?? null) ? (string) $data['action_url'] : null,
            'created_at' => $notification->created_at?->toIso8601String() ?? '',
            'created_label' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }
}
