<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Métricas Pulse para mapa Horizonte e fases do abastecimento bimestral.
 */
#[Lazy]
class HorizonteOperationsPulseCard extends Card
{
    public function render(): Renderable
    {
        [$payload, $time, $runAt] = $this->remember(function () {
            $map = [
                'overview' => ['count' => 0, 'max_ms' => 0, 'slow' => 0],
                'regional' => ['count' => 0, 'max_ms' => 0, 'slow' => 0],
                'build' => ['count' => 0, 'max_ms' => 0, 'slow' => 0],
            ];
            $feedPhases = [];
            $feedErrors = 0;

            foreach ($this->aggregate('app_operation', ['max', 'count'], 'count', 'desc', 200) as $row) {
                $key = (string) ($row->key ?? '');
                if (! str_starts_with($key, 'horizonte:')) {
                    continue;
                }

                $count = (int) ($row->count ?? 0);
                $maxMs = (int) ($row->max ?? 0);

                if (str_starts_with($key, 'horizonte:map:overview')) {
                    $map['overview']['count'] += $count;
                    $map['overview']['max_ms'] = max($map['overview']['max_ms'], $maxMs);
                } elseif (str_starts_with($key, 'horizonte:map:regional')) {
                    $map['regional']['count'] += $count;
                    $map['regional']['max_ms'] = max($map['regional']['max_ms'], $maxMs);
                } elseif (str_starts_with($key, 'horizonte:map:build')) {
                    $map['build']['count'] += $count;
                    $map['build']['max_ms'] = max($map['build']['max_ms'], $maxMs);
                } elseif (str_starts_with($key, 'horizonte:feed:phase:')) {
                    $phase = substr($key, strlen('horizonte:feed:phase:'));
                    $feedPhases[$phase] = [
                        'count' => $count,
                        'max_ms' => $maxMs,
                    ];
                }
            }

            foreach ($this->aggregate('app_operation_slow', ['max', 'count'], 'count', 'desc', 120) as $row) {
                $key = (string) ($row->key ?? '');
                if (! str_starts_with($key, 'horizonte:')) {
                    continue;
                }
                $slowCount = (int) ($row->count ?? 0);
                if (str_starts_with($key, 'horizonte:map:overview')) {
                    $map['overview']['slow'] += $slowCount;
                } elseif (str_starts_with($key, 'horizonte:map:regional')) {
                    $map['regional']['slow'] += $slowCount;
                } elseif (str_starts_with($key, 'horizonte:map:build')) {
                    $map['build']['slow'] += $slowCount;
                }
            }

            foreach ($this->aggregate('app_operation_error', 'count', 'count', 'desc', 40) as $row) {
                $key = (string) ($row->key ?? '');
                if (str_starts_with($key, 'horizonte:feed:phase:')) {
                    $feedErrors += (int) ($row->count ?? 0);
                }
            }

            $httpSlow = ['count' => 0, 'max' => null];
            foreach ($this->aggregate('slow_request', ['max', 'count'], 'count', 'desc', 250) as $row) {
                try {
                    [, $uri] = json_decode((string) $row->key, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    continue;
                }
                $u = strtolower((string) $uri);
                if (! str_contains($u, 'horizonte')) {
                    continue;
                }
                $httpSlow['count'] += (int) ($row->count ?? 0);
                $m = isset($row->max) ? (int) $row->max : null;
                if ($m !== null) {
                    $httpSlow['max'] = $httpSlow['max'] === null ? $m : max($httpSlow['max'], $m);
                }
            }

            uasort($feedPhases, static fn (array $a, array $b): int => ($b['max_ms'] ?? 0) <=> ($a['max_ms'] ?? 0));
            $feedPhases = array_slice($feedPhases, 0, 6, true);

            return compact('map', 'feedPhases', 'feedErrors', 'httpSlow');
        }, 'v1');

        return View::make('livewire.pulse.horizonte-operations-pulse-card', [
            'map' => $payload['map'],
            'feedPhases' => $payload['feedPhases'],
            'feedErrors' => $payload['feedErrors'],
            'httpSlow' => $payload['httpSlow'],
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
