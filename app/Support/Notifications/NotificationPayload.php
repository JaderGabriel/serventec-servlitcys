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
     *   dedupe_key?: ?string,
     *   sync_task_id?: int,
     *   pdf_export_id?: int,
     *   queue_domain?: ?string,
     *   queue_icon?: ?string,
     *   queue_accent?: ?string,
     *   queue_label?: ?string,
     *   queue_icon_box_class?: ?string
     * }  $payload
     * @return array<string, mixed>
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

        $normalized = [
            'title' => (string) ($payload['title'] ?? ''),
            'body' => (string) ($payload['body'] ?? ''),
            'icon' => $icon,
            'priority' => $priority->value,
            'action_url' => filled($payload['action_url'] ?? null) ? (string) $payload['action_url'] : null,
            'kind' => $kind,
            'kind_label' => NotificationKinds::label($kind),
            'dedupe_key' => filled($payload['dedupe_key'] ?? null) ? (string) $payload['dedupe_key'] : null,
        ];

        foreach (['sync_task_id', 'pdf_export_id'] as $intKey) {
            if (isset($payload[$intKey])) {
                $normalized[$intKey] = (int) $payload[$intKey];
            }
        }

        foreach (['queue_domain', 'queue_icon', 'queue_accent', 'queue_label', 'queue_icon_box_class'] as $key) {
            if (filled($payload[$key] ?? null)) {
                $normalized[$key] = (string) $payload[$key];
            }
        }

        return NotificationQueuePresentation::enrichStoredData($normalized);
    }
}
