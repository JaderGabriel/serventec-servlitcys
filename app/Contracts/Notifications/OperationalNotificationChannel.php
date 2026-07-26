<?php

namespace App\Contracts\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Canal operacional do sino (admins).
 * Extraiído de NotificationDispatcher para permitir doubles em testes.
 */
interface OperationalNotificationChannel
{
    public function isEnabled(): bool;

    /**
     * @return Collection<int, User>
     */
    public function operationalRecipients(): Collection;

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array{title: string, body: string, icon?: string, priority?: string, action_url?: ?string, kind?: ?string, dedupe_key?: ?string}  $payload
     */
    public function notifyOperational(Collection $recipients, array $payload): void;
}
