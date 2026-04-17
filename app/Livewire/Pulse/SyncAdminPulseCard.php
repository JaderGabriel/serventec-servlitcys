<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Métricas Pulse para Admin → Sincronizações (geo e pedagógico): volume próprio + pedidos lentos agregados.
 */
#[Lazy]
class SyncAdminPulseCard extends Card
{
    public function render(): Renderable
    {
        [$payload, $time, $runAt] = $this->remember(function () {
            $hits = [
                'geo-sync' => 0,
                'pedagogical-sync' => 0,
            ];
            foreach ($this->aggregate('sync_admin_endpoint', 'count', 'count', 'desc', 10) as $r) {
                $k = (string) ($r->key ?? '');
                if (array_key_exists($k, $hits)) {
                    $hits[$k] = (int) ($r->count ?? 0);
                }
            }

            $slow = [
                'geo-sync' => ['count' => 0, 'max' => null],
                'pedagogical-sync' => ['count' => 0, 'max' => null],
            ];

            foreach ($this->aggregate('slow_request', ['max', 'count'], 'count', 'desc', 250) as $row) {
                try {
                    [$method, $uri] = json_decode((string) $row->key, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    continue;
                }
                $u = strtolower((string) $uri);
                $bucket = null;
                if (str_contains($u, 'geo-sync')) {
                    $bucket = 'geo-sync';
                } elseif (str_contains($u, 'pedagogical-sync')) {
                    $bucket = 'pedagogical-sync';
                }
                if ($bucket === null) {
                    continue;
                }
                $slow[$bucket]['count'] += (int) ($row->count ?? 0);
                $m = isset($row->max) ? (int) $row->max : null;
                if ($m !== null) {
                    $prev = $slow[$bucket]['max'];
                    $slow[$bucket]['max'] = $prev === null ? $m : max($prev, $m);
                }
            }

            return compact('hits', 'slow');
        }, 'v1');

        return View::make('livewire.pulse.sync-admin-pulse-card', [
            'geoHits' => $payload['hits']['geo-sync'],
            'pedHits' => $payload['hits']['pedagogical-sync'],
            'geoSlow' => $payload['slow']['geo-sync'],
            'pedSlow' => $payload['slow']['pedagogical-sync'],
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
