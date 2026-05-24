<?php

namespace App\Support\Pulse;

use Illuminate\Support\Collection;

/**
 * Agrega métricas Pulse de diagnóstico SQL (sistema + municípios).
 */
final class PulseDatabaseMetricsAggregator
{
    /**
     * @param  callable(string, array<int, string>, ?string, string, int): Collection  $aggregate
     * @return array{
     *     system: array{scope_key: string, slow_count: int, slow_max_ms: ?int, request_max_ms: ?int, muni_run_max_ms: ?int},
     *     municipal_by_city: array<int, array{
     *         city_id: int,
     *         scope_key: ?string,
     *         slow_count: int,
     *         slow_max_ms: ?int,
     *         run_count: int,
     *         run_max_ms: ?int,
     *         run_slow_count: int,
     *         request_max_ms: ?int
     *     }>,
     *     slow_fingerprints: list<array{scope_key: string, label: string, count: int, max_ms: int}>,
     *     builtin_slow_by_scope: array<string, array{count: int, max_ms: ?int}>
     * }
     */
    public static function summarize(callable $aggregate): array
    {
        $systemKey = PulseDatabaseScope::systemScopeKey(
            (string) (config('database.connections.'.config('database.default').'.driver') ?? 'mysql')
        );

        $system = [
            'scope_key' => $systemKey,
            'slow_count' => 0,
            'slow_max_ms' => null,
            'request_max_ms' => null,
            'muni_run_max_ms' => null,
        ];

        foreach ($aggregate('db_slow_scope', ['max', 'count'], 'count', 'desc', 80) as $row) {
            $key = (string) ($row->key ?? '');
            if ($key !== $systemKey) {
                continue;
            }
            $system['slow_count'] += (int) ($row->count ?? 0);
            $m = isset($row->max) ? (int) $row->max : null;
            if ($m !== null) {
                $system['slow_max_ms'] = $system['slow_max_ms'] === null ? $m : max($system['slow_max_ms'], $m);
            }
        }

        foreach ($aggregate('db_request_total', 'max', 'max', 'desc', 40) as $row) {
            if ((string) ($row->key ?? '') !== $systemKey) {
                continue;
            }
            $m = isset($row->max) ? (int) $row->max : null;
            if ($m !== null) {
                $system['request_max_ms'] = $system['request_max_ms'] === null ? $m : max($system['request_max_ms'], $m);
            }
        }

        $municipalByCity = [];

        foreach ($aggregate('db_muni_run', ['max', 'count'], 'count', 'desc', 120) as $row) {
            $k = (string) ($row->key ?? '');
            if (! str_starts_with($k, 'cid:')) {
                continue;
            }
            $cityId = (int) substr($k, 4);
            if ($cityId <= 0) {
                continue;
            }
            $municipalByCity[$cityId] ??= self::emptyMunicipalRow($cityId);
            $municipalByCity[$cityId]['run_count'] += (int) ($row->count ?? 0);
            $m = isset($row->max) ? (int) $row->max : null;
            if ($m !== null) {
                $prev = $municipalByCity[$cityId]['run_max_ms'];
                $municipalByCity[$cityId]['run_max_ms'] = $prev === null ? $m : max($prev, $m);
            }
        }

        foreach ($aggregate('db_muni_run_slow', ['max', 'count'], 'count', 'desc', 80) as $row) {
            $k = (string) ($row->key ?? '');
            if (! str_starts_with($k, 'cid:')) {
                continue;
            }
            $cityId = (int) substr($k, 4);
            if ($cityId <= 0) {
                continue;
            }
            $municipalByCity[$cityId] ??= self::emptyMunicipalRow($cityId);
            $municipalByCity[$cityId]['run_slow_count'] += (int) ($row->count ?? 0);
        }

        foreach ($aggregate('db_slow_scope', ['max', 'count'], 'count', 'desc', 200) as $row) {
            $key = (string) ($row->key ?? '');
            if (! str_starts_with($key, 'municipal:cid:')) {
                continue;
            }
            if (! preg_match('/^municipal:cid:(\d+):/', $key, $m)) {
                continue;
            }
            $cityId = (int) $m[1];
            $municipalByCity[$cityId] ??= self::emptyMunicipalRow($cityId);
            $municipalByCity[$cityId]['scope_key'] = $key;
            $municipalByCity[$cityId]['slow_count'] += (int) ($row->count ?? 0);
            $max = isset($row->max) ? (int) $row->max : null;
            if ($max !== null) {
                $prev = $municipalByCity[$cityId]['slow_max_ms'];
                $municipalByCity[$cityId]['slow_max_ms'] = $prev === null ? $max : max($prev, $max);
            }
        }

        foreach ($aggregate('db_request_total', ['max', 'count'], 'max', 'desc', 200) as $row) {
            $key = (string) ($row->key ?? '');
            if (! str_starts_with($key, 'municipal:cid:')) {
                continue;
            }
            if (! preg_match('/^municipal:cid:(\d+):/', $key, $m)) {
                continue;
            }
            $cityId = (int) $m[1];
            $municipalByCity[$cityId] ??= self::emptyMunicipalRow($cityId);
            $max = isset($row->max) ? (int) $row->max : null;
            if ($max !== null) {
                $prev = $municipalByCity[$cityId]['request_max_ms'];
                $municipalByCity[$cityId]['request_max_ms'] = $prev === null ? $max : max($prev, $max);
            }
        }

        $slowFingerprints = [];
        foreach ($aggregate('db_slow_fp', ['max', 'count'], 'count', 'desc', 40) as $row) {
            try {
                $decoded = json_decode((string) ($row->key ?? ''), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                continue;
            }
            if (! is_array($decoded) || count($decoded) < 2) {
                continue;
            }
            $slowFingerprints[] = [
                'scope_key' => (string) ($decoded[0] ?? ''),
                'label' => (string) ($decoded[2] ?? $decoded[1] ?? ''),
                'count' => (int) ($row->count ?? 0),
                'max_ms' => (int) ($row->max ?? 0),
            ];
        }

        $builtinSlow = [];
        foreach ($aggregate('slow_query', ['max', 'count'], 'count', 'desc', 120) as $row) {
            try {
                $decoded = json_decode((string) ($row->key ?? ''), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                continue;
            }
            $connection = is_array($decoded) ? (string) ($decoded[0] ?? '') : '';
            if ($connection === '') {
                continue;
            }
            $scope = PulseDatabaseScope::fromConnectionName($connection);
            $scopeKey = (string) $scope['scope_key'];
            $builtinSlow[$scopeKey] ??= ['count' => 0, 'max_ms' => null];
            $builtinSlow[$scopeKey]['count'] += (int) ($row->count ?? 0);
            $m = isset($row->max) ? (int) $row->max : null;
            if ($m !== null) {
                $prev = $builtinSlow[$scopeKey]['max_ms'];
                $builtinSlow[$scopeKey]['max_ms'] = $prev === null ? $m : max($prev, $m);
            }
        }

        return [
            'system' => $system,
            'municipal_by_city' => $municipalByCity,
            'slow_fingerprints' => $slowFingerprints,
            'builtin_slow_by_scope' => $builtinSlow,
        ];
    }

    /**
     * @return array{
     *     city_id: int,
     *     scope_key: ?string,
     *     slow_count: int,
     *     slow_max_ms: ?int,
     *     run_count: int,
     *     run_max_ms: ?int,
     *     run_slow_count: int,
     *     request_max_ms: ?int
     * }
     */
    private static function emptyMunicipalRow(int $cityId): array
    {
        return [
            'city_id' => $cityId,
            'scope_key' => null,
            'slow_count' => 0,
            'slow_max_ms' => null,
            'run_count' => 0,
            'run_max_ms' => null,
            'run_slow_count' => 0,
            'request_max_ms' => null,
        ];
    }
}
