<?php

namespace App\Livewire\Pulse;

use App\Support\Pulse\PulseDatabaseMetricsAggregator;
use App\Support\Pulse\PulseDatabaseScope;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Diagnóstico SQL estruturado: base Laravel vs bases i-Educar municipais.
 */
#[Lazy]
class DatabaseDiagnosticsCard extends Card
{
    public function render(): Renderable
    {
        [$payload, $time, $runAt] = $this->remember(function () {
            $metrics = PulseDatabaseMetricsAggregator::summarize(
                fn (string $type, array|string $aggregate, ?string $orderBy, string $direction, int $limit) => $this->aggregate($type, $aggregate, $orderBy, $direction, $limit)
            );

            $slowMs = (int) config('pulse_diagnostics.slow_query_ms', 300);
            $slowRunMs = (int) config('pulse_diagnostics.slow_municipal_run_ms', 1500);

            $systemBuiltin = $metrics['builtin_slow_by_scope'][$metrics['system']['scope_key']] ?? ['count' => 0, 'max_ms' => null];

            return [
                'slow_ms' => $slowMs,
                'slow_run_ms' => $slowRunMs,
                'system' => $metrics['system'],
                'system_builtin_slow' => $systemBuiltin,
                'slow_fingerprints' => array_slice($metrics['slow_fingerprints'], 0, 12),
                'municipal_hot' => collect($metrics['municipal_by_city'])
                    ->filter(fn (array $r): bool => ($r['slow_count'] ?? 0) > 0
                        || ($r['run_slow_count'] ?? 0) > 0
                        || ($r['run_max_ms'] ?? 0) >= $slowRunMs)
                    ->sortByDesc(fn (array $r): int => max(
                        (int) ($r['slow_max_ms'] ?? 0),
                        (int) ($r['run_max_ms'] ?? 0),
                        (int) ($r['request_max_ms'] ?? 0),
                    ))
                    ->take(15)
                    ->values()
                    ->all(),
            ];
        }, 'db-diag-v1');

        return View::make('livewire.pulse.database-diagnostics-card', [
            'payload' => $payload,
            'time' => $time,
            'runAt' => $runAt,
            'scopeLabel' => static fn (string $scopeKey): string => PulseDatabaseScope::label([
                'kind' => str_starts_with($scopeKey, 'municipal:') ? 'municipal' : (str_starts_with($scopeKey, 'system:') ? 'system' : 'other'),
                'scope_key' => $scopeKey,
                'city_id' => preg_match('/municipal:cid:(\d+):/', $scopeKey, $m) ? (int) $m[1] : null,
                'driver' => preg_match('/:(mysql|pgsql|mariadb|ieducar)$/i', $scopeKey, $m) ? $m[1] : '',
                'connection' => $scopeKey,
            ]),
        ]);
    }
}
