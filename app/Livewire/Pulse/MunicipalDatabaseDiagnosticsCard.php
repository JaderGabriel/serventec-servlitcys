<?php

namespace App\Livewire\Pulse;

use App\Models\City;
use App\Support\Pulse\PulseDatabaseMetricsAggregator;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Lentidão SQL por município (bases i-Educar) no período Pulse.
 */
#[Lazy]
class MunicipalDatabaseDiagnosticsCard extends Card
{
    public function render(): Renderable
    {
        [$rows, $time, $runAt] = $this->remember(function () {
            $metrics = PulseDatabaseMetricsAggregator::summarize(
                fn (string $type, array|string $aggregate, ?string $orderBy, string $direction, int $limit) => $this->aggregate($type, $aggregate, $orderBy, $direction, $limit)
            );

            $cityIds = array_keys($metrics['municipal_by_city']);
            $names = $cityIds !== []
                ? City::query()->whereIn('id', $cityIds)->pluck('name', 'id')
                : collect();

            $slowRunMs = (int) config('pulse_diagnostics.slow_municipal_run_ms', 1500);

            return collect($metrics['municipal_by_city'])
                ->map(function (array $row) use ($names, $slowRunMs) {
                    $cityId = (int) $row['city_id'];
                    $score = max(
                        (int) ($row['slow_max_ms'] ?? 0),
                        (int) ($row['run_max_ms'] ?? 0),
                        (int) ($row['request_max_ms'] ?? 0),
                    );

                    return [
                        'city_id' => $cityId,
                        'name' => (string) ($names[$cityId] ?? '#'.$cityId),
                        'run_count' => (int) ($row['run_count'] ?? 0),
                        'run_max_ms' => $row['run_max_ms'],
                        'run_slow_count' => (int) ($row['run_slow_count'] ?? 0),
                        'slow_count' => (int) ($row['slow_count'] ?? 0),
                        'slow_max_ms' => $row['slow_max_ms'],
                        'request_max_ms' => $row['request_max_ms'],
                        'attention' => $score >= $slowRunMs || ($row['run_slow_count'] ?? 0) > 0 || ($row['slow_count'] ?? 0) > 0,
                        'score' => $score,
                    ];
                })
                ->filter(fn (array $r): bool => $r['run_count'] > 0 || $r['slow_count'] > 0 || $r['run_slow_count'] > 0)
                ->sortByDesc('score')
                ->values();
        }, 'muni-db-v1');

        return View::make('livewire.pulse.municipal-database-diagnostics-card', [
            'rows' => $rows,
            'time' => $time,
            'runAt' => $runAt,
            'slowRunMs' => (int) config('pulse_diagnostics.slow_municipal_run_ms', 1500),
            'slowQueryMs' => (int) config('pulse_diagnostics.slow_query_ms', 300),
        ]);
    }
}
