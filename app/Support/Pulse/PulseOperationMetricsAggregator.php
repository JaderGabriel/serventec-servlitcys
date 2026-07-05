<?php

namespace App\Support\Pulse;

/**
 * Agrega métricas app_operation / app_operation_slow para o painel Pulse.
 */
final class PulseOperationMetricsAggregator
{
    /**
     * @param  callable(string, array<int, string>|string, ?string, string, int): \Illuminate\Support\Collection  $aggregate
     * @return array{
     *     operations: list<array{key: string, label: string, count: int, max_ms: int, slow_count: int}>,
     *     slow_operations: list<array{key: string, label: string, count: int, max_ms: int}>,
     *     errors: list<array{key: string, count: int}>
     * }
     */
    public static function summarize(callable $aggregate): array
    {
        $ops = [];
        foreach ($aggregate('app_operation', ['max', 'count'], 'count', 'desc', 120) as $row) {
            $key = (string) ($row->key ?? '');
            if ($key === '') {
                continue;
            }
            $ops[$key] = [
                'key' => $key,
                'label' => self::labelForKey($key),
                'count' => (int) ($row->count ?? 0),
                'max_ms' => (int) ($row->max ?? 0),
                'slow_count' => 0,
            ];
        }

        $slowOps = [];
        foreach ($aggregate('app_operation_slow', ['max', 'count'], 'count', 'desc', 80) as $row) {
            $key = (string) ($row->key ?? '');
            if ($key === '') {
                continue;
            }
            $slowOps[] = [
                'key' => $key,
                'label' => self::labelForKey($key),
                'count' => (int) ($row->count ?? 0),
                'max_ms' => (int) ($row->max ?? 0),
            ];
            if (isset($ops[$key])) {
                $ops[$key]['slow_count'] = (int) ($row->count ?? 0);
            }
        }

        $errors = [];
        foreach ($aggregate('app_operation_error', 'count', 'count', 'desc', 40) as $row) {
            $errors[] = [
                'key' => (string) ($row->key ?? ''),
                'count' => (int) ($row->count ?? 0),
            ];
        }

        $operations = array_values($ops);
        usort($operations, static fn (array $a, array $b): int => $b['max_ms'] <=> $a['max_ms']);

        return [
            'operations' => array_slice($operations, 0, 25),
            'slow_operations' => $slowOps,
            'errors' => $errors,
        ];
    }

    public static function labelForKey(string $key): string
    {
        if (str_starts_with($key, 'http:route:')) {
            $rest = substr($key, strlen('http:route:'));

            return __('HTTP :route', ['route' => $rest]);
        }

        if (str_starts_with($key, 'analytics:tab:')) {
            return __('Analytics :tab', ['tab' => substr($key, strlen('analytics:tab:'))]);
        }

        if (str_starts_with($key, 'sync:')) {
            return __('Sync :task', ['task' => substr($key, strlen('sync:'))]);
        }

        if (str_starts_with($key, 'pdf:')) {
            return __('PDF :ctx', ['ctx' => substr($key, strlen('pdf:'))]);
        }

        if (str_starts_with($key, 'map:')) {
            return __('Mapa :ctx', ['ctx' => substr($key, strlen('map:'))]);
        }

        if (str_starts_with($key, 'export:')) {
            return __('Export :ctx', ['ctx' => substr($key, strlen('export:'))]);
        }

        if (str_starts_with($key, 'admin:home:')) {
            return __('Início :ctx', ['ctx' => substr($key, strlen('admin:home:'))]);
        }

        if (str_starts_with($key, 'rx:')) {
            return __('RX :ctx', ['ctx' => substr($key, strlen('rx:'))]);
        }

        if (str_starts_with($key, 'ieducar:')) {
            return __('i-Educar :ctx', ['ctx' => substr($key, strlen('ieducar:'))]);
        }

        if (str_starts_with($key, 'horizonte:')) {
            return __('Horizonte :ctx', ['ctx' => substr($key, strlen('horizonte:'))]);
        }

        return $key;
    }
}
