<?php

namespace App\Support\Scheduling;

use App\Services\Notifications\ScheduleFailureNotifier;
use Illuminate\Console\Scheduling\Event;
use Throwable;

/** Anexa onFailure → sino admin nos jobs críticos do schedule. */
final class ScheduleFailureHooks
{
    public static function attach(Event $event, string $jobName): Event
    {
        if (! filter_var(config('notifications.schedule_failures.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return $event;
        }

        return $event->onFailure(static function () use ($jobName): void {
            try {
                app(ScheduleFailureNotifier::class)->notify($jobName);
            } catch (Throwable) {
                // Nunca interromper o scheduler.
            }
        });
    }
}
