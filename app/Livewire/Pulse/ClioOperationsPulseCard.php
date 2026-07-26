<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/** Métricas Pulse para Clio (ingest, análise, cruzamento, BI, exports). */
#[Lazy]
class ClioOperationsPulseCard extends Card
{
    public function render(): Renderable
    {
        [$payload, $time, $runAt] = $this->remember(function () {
            $buckets = [
                'ingest' => ['count' => 0, 'max_ms' => 0, 'slow' => 0, 'errors' => 0],
                'analyze' => ['count' => 0, 'max_ms' => 0, 'slow' => 0, 'errors' => 0],
                'cross_check' => ['count' => 0, 'max_ms' => 0, 'slow' => 0, 'errors' => 0],
                'bi' => ['count' => 0, 'max_ms' => 0, 'slow' => 0, 'errors' => 0],
                'export' => ['count' => 0, 'max_ms' => 0, 'slow' => 0, 'errors' => 0],
            ];

            foreach ($this->aggregate('app_operation', ['max', 'count'], 'count', 'desc', 200) as $row) {
                $key = (string) ($row->key ?? '');
                if (! str_starts_with($key, 'clio:')) {
                    continue;
                }
                $bucket = self::bucketForKey($key);
                if ($bucket === null) {
                    continue;
                }
                $buckets[$bucket]['count'] += (int) ($row->count ?? 0);
                $buckets[$bucket]['max_ms'] = max($buckets[$bucket]['max_ms'], (int) ($row->max ?? 0));
            }

            foreach ($this->aggregate('app_operation_slow', ['max', 'count'], 'count', 'desc', 120) as $row) {
                $key = (string) ($row->key ?? '');
                $bucket = self::bucketForKey($key);
                if ($bucket === null) {
                    continue;
                }
                $buckets[$bucket]['slow'] += (int) ($row->count ?? 0);
            }

            foreach ($this->aggregate('app_operation_error', 'count', 'count', 'desc', 40) as $row) {
                $key = (string) ($row->key ?? '');
                $bucket = self::bucketForKey($key);
                if ($bucket === null) {
                    continue;
                }
                $buckets[$bucket]['errors'] += (int) ($row->count ?? 0);
            }

            $httpSlow = ['count' => 0, 'max' => null];
            foreach ($this->aggregate('slow_request', ['max', 'count'], 'count', 'desc', 250) as $row) {
                try {
                    [, $uri] = json_decode((string) $row->key, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    continue;
                }
                $u = strtolower((string) $uri);
                if (! str_contains($u, '/clio')) {
                    continue;
                }
                $httpSlow['count'] += (int) ($row->count ?? 0);
                $m = isset($row->max) ? (int) $row->max : null;
                if ($m !== null) {
                    $httpSlow['max'] = $httpSlow['max'] === null ? $m : max($httpSlow['max'], $m);
                }
            }

            $totalOps = array_sum(array_column($buckets, 'count'));
            $totalErrors = array_sum(array_column($buckets, 'errors'));

            return compact('buckets', 'httpSlow', 'totalOps', 'totalErrors');
        }, 'clio-ops-v1');

        return View::make('livewire.pulse.clio-operations-pulse-card', [
            'buckets' => $payload['buckets'],
            'httpSlow' => $payload['httpSlow'],
            'totalOps' => $payload['totalOps'],
            'totalErrors' => $payload['totalErrors'],
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }

    private static function bucketForKey(string $key): ?string
    {
        return match (true) {
            str_starts_with($key, 'clio:campaign:ingest') => 'ingest',
            str_starts_with($key, 'clio:campaign:analyze') => 'analyze',
            str_starts_with($key, 'clio:campaign:cross-check') => 'cross_check',
            str_starts_with($key, 'clio:bi:') => 'bi',
            str_starts_with($key, 'clio:export:') => 'export',
            default => str_starts_with($key, 'clio:') ? 'analyze' : null,
        };
    }
}
