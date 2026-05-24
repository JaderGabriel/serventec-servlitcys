<?php

namespace App\Support\Pulse;

use App\Models\City;
use Laravel\Pulse\Facades\Pulse;

final class PulseDatabaseRecorder
{
    public static function enabled(): bool
    {
        return (bool) config('pulse.enabled', true)
            && (bool) config('pulse_diagnostics.enabled', true);
    }

    public static function recordQuery(string $connectionName, string $sql, float $durationMs): void
    {
        if (! self::enabled()) {
            return;
        }

        if (self::shouldIgnoreSql($sql)) {
            return;
        }

        $scope = PulseDatabaseScope::fromConnectionName($connectionName);
        $scopeKey = (string) $scope['scope_key'];

        RequestDbTimingAccumulator::add($scopeKey, $durationMs);

        $slowMs = (int) config('pulse_diagnostics.slow_query_ms', 300);
        if ($durationMs < $slowMs) {
            return;
        }

        $ms = (int) round($durationMs);

        try {
            Pulse::record('db_slow_scope', $scopeKey, $ms)->count()->max();

            $fp = PulseDatabaseFingerprint::fromSql($sql);
            $fpKey = json_encode([
                $scopeKey,
                $fp['fingerprint'],
                mb_substr($fp['label'], 0, 120),
            ], JSON_THROW_ON_ERROR);

            Pulse::record('db_slow_fp', $fpKey, $ms)->count()->max();
        } catch (\Throwable) {
            // Ignorar falhas de ingest Pulse.
        }
    }

    public static function recordMunicipalRun(City $city, float $durationMs): void
    {
        if (! self::enabled()) {
            return;
        }

        $ms = (int) round($durationMs);
        if ($ms <= 0) {
            return;
        }

        $driver = $city->effectiveIeducarDriver();
        $scopeKey = PulseDatabaseScope::municipalScopeKey((int) $city->id, $driver);
        RequestDbTimingAccumulator::add($scopeKey, $durationMs);

        try {
            Pulse::record('db_muni_run', 'cid:'.(int) $city->id, $ms)->count()->max();

            $slowRunMs = (int) config('pulse_diagnostics.slow_municipal_run_ms', 1500);
            if ($ms >= $slowRunMs) {
                Pulse::record('db_muni_run_slow', 'cid:'.(int) $city->id, $ms)->count()->max();
            }
        } catch (\Throwable) {
            //
        }
    }

    private static function shouldIgnoreSql(string $sql): bool
    {
        $trim = ltrim($sql);

        return preg_match('/^(insert\s+into\s+`?pulse_|select\s+.*\sfrom\s+`?pulse_)/i', $trim) === 1;
    }
}
