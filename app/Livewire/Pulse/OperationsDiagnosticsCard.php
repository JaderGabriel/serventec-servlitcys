<?php

namespace App\Livewire\Pulse;

use App\Support\Pulse\PulseOperationMetricsAggregator;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Etapas pesadas da aplicação: abas Analytics, RX, sync, PDF, mapa, exports.
 */
#[Lazy]
class OperationsDiagnosticsCard extends Card
{
    public function render(): Renderable
    {
        [$payload, $time, $runAt] = $this->remember(function () {
            $metrics = PulseOperationMetricsAggregator::summarize(
                fn (string $type, array|string $aggregate, ?string $orderBy, string $direction, int $limit) => $this->aggregate($type, $aggregate, $orderBy, $direction, $limit)
            );

            $slowMs = (int) config('pulse_diagnostics.slow_operation_ms', 750);

            $byPrefix = [];
            foreach ($metrics['operations'] as $row) {
                $prefix = self::prefixForKey($row['key']);
                if (! isset($byPrefix[$prefix])) {
                    $byPrefix[$prefix] = ['prefix' => $prefix, 'count' => 0, 'max_ms' => 0, 'slow_count' => 0];
                }
                $byPrefix[$prefix]['count'] += (int) $row['count'];
                $byPrefix[$prefix]['max_ms'] = max($byPrefix[$prefix]['max_ms'], (int) $row['max_ms']);
                $byPrefix[$prefix]['slow_count'] += (int) ($row['slow_count'] ?? 0);
            }

            $prefixRows = array_values($byPrefix);
            usort($prefixRows, static fn (array $a, array $b): int => $b['max_ms'] <=> $a['max_ms']);

            return [
                'slow_ms' => $slowMs,
                'operations' => array_slice($metrics['operations'], 0, 20),
                'slow_operations' => array_slice($metrics['slow_operations'], 0, 15),
                'errors' => $metrics['errors'],
                'by_prefix' => array_slice($prefixRows, 0, 8),
            ];
        }, 'ops-diag-v1');

        return View::make('livewire.pulse.operations-diagnostics-card', [
            'payload' => $payload,
            'time' => $time,
            'runAt' => $runAt,
            'prefixLabel' => static fn (string $prefix): string => match ($prefix) {
                'http' => __('HTTP (rotas)'),
                'analytics' => __('Analytics (abas)'),
                'sync' => __('Sincronização'),
                'pdf' => __('PDF'),
                'map' => __('Mapa RX'),
                'export' => __('Exportações'),
                'admin' => __('Início admin'),
                'rx' => __('Painel RX'),
                'ieducar' => __('i-Educar'),
                default => $prefix,
            },
        ]);
    }

    private static function prefixForKey(string $key): string
    {
        if (str_starts_with($key, 'http:route:')) {
            return 'http';
        }

        foreach (['analytics:tab:', 'sync:', 'pdf:', 'map:', 'export:', 'admin:home:', 'rx:', 'ieducar:'] as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return rtrim($prefix, ':');
            }
        }

        $pipe = strpos($key, '|');
        if ($pipe !== false) {
            return substr($key, 0, $pipe);
        }

        return $key;
    }
}
