<?php

namespace App\Support\Pulse;

use Laravel\Pulse\Facades\Pulse;

/**
 * Acumula tempo SQL por âmbito (system / municipal) durante um pedido HTTP.
 */
final class RequestDbTimingAccumulator
{
    /** @var array<string, float> */
    private static array $totalsMs = [];

    public static function add(string $scopeKey, float $durationMs): void
    {
        self::$totalsMs[$scopeKey] = (self::$totalsMs[$scopeKey] ?? 0.0) + $durationMs;
    }

    public static function flush(): void
    {
        if (self::$totalsMs === []) {
            return;
        }

        if (! config('pulse.enabled', true) || ! config('pulse_diagnostics.enabled', true)) {
            self::$totalsMs = [];

            return;
        }

        if (! config('pulse_diagnostics.accumulate_request_totals', true)) {
            self::$totalsMs = [];

            return;
        }

        try {
            foreach (self::$totalsMs as $scopeKey => $ms) {
                $rounded = (int) round($ms);
                if ($rounded <= 0) {
                    continue;
                }
                Pulse::record('db_request_total', $scopeKey, $rounded)->count()->max();
            }
        } catch (\Throwable) {
            // Pulse não deve afectar o pedido.
        }

        self::$totalsMs = [];
    }

    public static function reset(): void
    {
        self::$totalsMs = [];
    }
}
