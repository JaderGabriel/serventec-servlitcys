<?php

namespace App\Support\Notifications;

use App\Enums\NotificationPriority;
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
     *   priority: string,
     *   priority_label: string,
     *   is_critical: bool,
     *   kind: ?string,
     *   kind_label: ?string,
     *   action_url: ?string,
     *   created_at: string,
     *   created_label: string
     * }
     */
    public static function fromDatabaseNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $priority = NotificationPriority::tryFrom((string) ($data['priority'] ?? ''))
            ?? NotificationPriority::Normal;

        return [
            'id' => (string) $notification->id,
            'read' => $notification->read_at !== null,
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'icon' => (string) ($data['icon'] ?? 'info'),
            'priority' => $priority->value,
            'priority_label' => $priority->label(),
            'is_critical' => $priority->isCritical(),
            'kind' => filled($data['kind'] ?? null) ? (string) $data['kind'] : null,
            'kind_label' => filled($data['kind_label'] ?? null)
                ? (string) $data['kind_label']
                : NotificationKinds::label(filled($data['kind'] ?? null) ? (string) $data['kind'] : null),
            'action_url' => filled($data['action_url'] ?? null) ? (string) $data['action_url'] : null,
            'created_at' => $notification->created_at?->toIso8601String() ?? '',
            'created_label' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }
}
