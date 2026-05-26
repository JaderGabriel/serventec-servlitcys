<?php

namespace App\Support\Pulse;

use DateInterval;
use Illuminate\Support\Collection;
use Laravel\Pulse\Facades\Pulse;

/**
 * Agrega métricas Pulse fora dos cartões Livewire (ex.: monitor de módulos).
 */
final class PulseAggregateBridge
{
    public static function isAvailable(): bool
    {
        return (bool) config('pulse.enabled', true)
            && (bool) config('pulse_diagnostics.enabled', true);
    }

    public static function periodInterval(string $period): DateInterval
    {
        $hours = (int) (config('module_monitor.periods.'.$period.'.hours')
            ?? config('module_monitor.periods.24h.hours', 24));

        return now()->subHours(max(1, $hours))->diffAsDateInterval();
    }

    public static function periodLabel(string $period): string
    {
        $label = config('module_monitor.periods.'.$period.'.label');

        return is_string($label) ? __($label) : __('Últimas 24 horas');
    }

    /**
     * @param  'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'>  $aggregates
     * @return Collection<int, object>
     */
    public static function aggregate(
        string $type,
        string|array $aggregates,
        string $period,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 100,
    ): Collection {
        if (! self::isAvailable()) {
            return collect();
        }

        try {
            return Pulse::aggregate($type, $aggregates, self::periodInterval($period), $orderBy, $direction, $limit);
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * @return callable(string, array|string, ?string, string, int): Collection<int, object>
     */
    public static function aggregateFn(string $period): callable
    {
        return static fn (
            string $type,
            array|string $aggregates,
            ?string $orderBy,
            string $direction,
            int $limit,
        ): Collection => self::aggregate($type, $aggregates, $period, $orderBy, $direction, $limit);
    }
}
