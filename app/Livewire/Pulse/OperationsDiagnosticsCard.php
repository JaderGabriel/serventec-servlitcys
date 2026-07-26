<?php

namespace App\Livewire\Pulse;

use App\Support\Pulse\PulseOperationMetricsAggregator;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Etapas pesadas da aplicação: abas Analytics, RX, Clio, CadÚnico, Horizonte, sync, PDF.
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
                'by_prefix' => array_slice($prefixRows, 0, 12),
            ];
        }, 'ops-diag-v2');

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
                'clio' => __('Clio'),
                'cadunico' => __('CadÚnico'),
                'horizonte' => __('Horizonte'),
                default => $prefix,
            },
        ]);
    }

    public static function prefixForKey(string $key): string
    {
        if (str_starts_with($key, 'http:route:')) {
            return 'http';
        }

        if (str_starts_with($key, 'analytics:tab:')) {
            return 'analytics';
        }

        if (str_starts_with($key, 'admin:home:')) {
            return 'admin';
        }

        foreach (['horizonte:', 'cadunico:', 'clio:', 'sync:', 'pdf:', 'map:', 'export:', 'rx:', 'ieducar:'] as $prefix) {
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
