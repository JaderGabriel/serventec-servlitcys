<?php

namespace App\Support\Notifications;

use App\Enums\NotificationPriority;

final class NotificationPayload
{
    /**
     * @param  array{
     *   title: string,
     *   body: string,
     *   icon?: string,
     *   priority?: string,
     *   action_url?: ?string,
     *   kind?: ?string,
     *   dedupe_key?: ?string
     * }  $payload
     * @return array{
     *   title: string,
     *   body: string,
     *   icon: string,
     *   priority: string,
     *   action_url: ?string,
     *   kind: ?string,
     *   kind_label: ?string,
     *   dedupe_key: ?string
     * }
     */
    public static function normalize(array $payload): array
    {
        $priority = NotificationPriority::tryFrom((string) ($payload['priority'] ?? ''))
            ?? NotificationPriority::Normal;

        $icon = (string) ($payload['icon'] ?? '');
        if ($icon === '') {
            $icon = $priority->icon();
        }

        $kind = filled($payload['kind'] ?? null) ? (string) $payload['kind'] : null;

        return [
            'title' => (string) ($payload['title'] ?? ''),
            'body' => (string) ($payload['body'] ?? ''),
            'icon' => $icon,
            'priority' => $priority->value,
            'action_url' => filled($payload['action_url'] ?? null) ? (string) $payload['action_url'] : null,
            'kind' => $kind,
            'kind_label' => NotificationKinds::label($kind),
            'dedupe_key' => filled($payload['dedupe_key'] ?? null) ? (string) $payload['dedupe_key'] : null,
        ];
    }
}
