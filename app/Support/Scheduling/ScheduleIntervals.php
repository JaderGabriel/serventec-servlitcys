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

    /**
     * @param  list<string>  $times  Horários «H:i» no fuso da aplicação (ex. 06:00, 18:00).
     */
    public static function dailyAtTimes(Event $event, array $times): Event
    {
        $parsed = self::normalizeDailyTimes($times);

        if ($parsed === []) {
            return $event->hourly();
        }

        if (count($parsed) === 1) {
            return $event->dailyAt($parsed[0]);
        }

        if (count($parsed) === 2) {
            [$h1, $h2] = array_map(
                static fn (string $t): int => (int) substr($t, 0, 2),
                $parsed,
            );

            return $event->twiceDaily($h1, $h2);
        }

        $cronMinutes = implode(',', array_map(
            static fn (string $t): string => (string) (int) substr($t, 3, 2),
            $parsed,
        ));
        $cronHours = implode(',', array_map(
            static fn (string $t): string => (string) (int) substr($t, 0, 2),
            $parsed,
        ));

        return $event->cron($cronMinutes.' '.$cronHours.' * * *');
    }

    /**
     * @param  list<string>  $times
     * @return list<string>
     */
    public static function normalizeDailyTimes(array $times): array
    {
        $out = [];
        foreach ($times as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $m)) {
                $h = max(0, min(23, (int) $m[1]));
                $min = max(0, min(59, (int) $m[2]));
                $out[] = sprintf('%02d:%02d', $h, $min);
            } elseif (ctype_digit($raw)) {
                $h = max(0, min(23, (int) $raw));
                $out[] = sprintf('%02d:00', $h);
            }
        }

        return array_values(array_unique($out));
    }
}
