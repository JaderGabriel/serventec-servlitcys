<?php

namespace App\Support\Pulse;

use Laravel\Pulse\Facades\Pulse;

/**
 * Métricas Pulse estruturadas para etapas pesadas (HTTP, jobs, imports, RX).
 */
final class PulseOperationRecorder
{
    public static function enabled(): bool
    {
        return (bool) config('pulse.enabled', true)
            && (bool) config('pulse_diagnostics.operations_enabled', true);
    }

    public static function record(string $key, float $durationMs): void
    {
        if (! self::enabled()) {
            return;
        }

        $key = trim($key);
        if ($key === '') {
            return;
        }

        $ms = max(0, (int) round($durationMs));
        if ($ms <= 0) {
            return;
        }

        try {
            Pulse::record('app_operation', $key, $ms)->count()->max();

            $slowMs = (int) config('pulse_diagnostics.slow_operation_ms', 750);
            if ($ms >= $slowMs) {
                Pulse::record('app_operation_slow', $key, $ms)->count()->max();
            }
        } catch (\Throwable) {
            //
        }
    }

    public static function recordFailure(string $key): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            Pulse::record('app_operation_error', $key, 1)->count();
        } catch (\Throwable) {
            //
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function measure(string $key, callable $callback): mixed
    {
        $t0 = microtime(true);
        try {
            return $callback();
        } finally {
            self::record($key, (microtime(true) - $t0) * 1000);
        }
    }

    public static function analyticsTabKey(string $tab, ?int $cityId = null): string
    {
        $key = 'analytics:tab:'.$tab;
        if ($cityId !== null && $cityId > 0) {
            $key .= '|cid:'.$cityId;
        }

        return $key;
    }

    public static function syncJobKey(string $domain, string $taskKey, ?int $cityId = null): string
    {
        $key = 'sync:'.$domain.':'.$taskKey;
        if ($cityId !== null && $cityId > 0) {
            $key .= '|cid:'.$cityId;
        }

        return $key;
    }

    public static function horizonteMapKey(string $scope, ?string $uf = null, bool $cacheHit = false): string
    {
        $key = 'horizonte:map:'.$scope;
        if ($uf !== null && $uf !== '') {
            $key .= '|uf:'.$uf;
        }

        return $key.($cacheHit ? '|cache:hit' : '|cache:miss');
    }

    public static function horizonteFeedPhaseKey(string $phaseKey): string
    {
        return 'horizonte:feed:phase:'.$phaseKey;
    }
}
