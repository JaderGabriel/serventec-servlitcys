<?php

namespace App\Support\Scheduling;

use Illuminate\Console\Scheduling\Event;

/**
 * Aplica cadências ao scheduler Laravel (cron do servidor invoca schedule:run).
 */
final class ScheduleIntervals
{
    public static function everyMinutes(Event $event, int $minutes): Event
    {
        $minutes = max(1, min(59, $minutes));

        return match ($minutes) {
            1 => $event->everyMinute(),
            2 => $event->everyTwoMinutes(),
            3 => $event->everyThreeMinutes(),
            4 => $event->everyFourMinutes(),
            5 => $event->everyFiveMinutes(),
            10 => $event->everyTenMinutes(),
            15 => $event->everyFifteenMinutes(),
            30 => $event->everyThirtyMinutes(),
            default => $event->cron('*/'.$minutes.' * * * *'),
        };
    }

    public static function everyHours(Event $event, int $hours): Event
    {
        $hours = max(1, min(24, $hours));

        if ($hours === 1) {
            return $event->hourly();
        }

        return $event->cron('0 */'.$hours.' * * *');
    }
}
