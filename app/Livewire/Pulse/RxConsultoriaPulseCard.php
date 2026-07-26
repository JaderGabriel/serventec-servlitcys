<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/** Métricas Pulse para RX e abas da consultoria (Analytics). */
#[Lazy]
class RxConsultoriaPulseCard extends Card
{
    public function render(): Renderable
    {
        [$payload, $time, $runAt] = $this->remember(function () {
            $rx = ['count' => 0, 'max_ms' => 0, 'slow' => 0, 'errors' => 0];
            $map = ['count' => 0, 'max_ms' => 0, 'slow' => 0, 'errors' => 0];
            $analytics = ['count' => 0, 'max_ms' => 0, 'slow' => 0, 'errors' => 0];
            $topTabs = [];

            foreach ($this->aggregate('app_operation', ['max', 'count'], 'count', 'desc', 200) as $row) {
                $key = (string) ($row->key ?? '');
                $count = (int) ($row->count ?? 0);
                $maxMs = (int) ($row->max ?? 0);

                if (str_starts_with($key, 'rx:')) {
                    $rx['count'] += $count;
                    $rx['max_ms'] = max($rx['max_ms'], $maxMs);
                } elseif (str_starts_with($key, 'map:')) {
                    $map['count'] += $count;
                    $map['max_ms'] = max($map['max_ms'], $maxMs);
                } elseif (str_starts_with($key, 'analytics:tab:')) {
                    $analytics['count'] += $count;
                    $analytics['max_ms'] = max($analytics['max_ms'], $maxMs);
                    $tab = substr($key, strlen('analytics:tab:'));
                    $tab = explode('|', $tab, 2)[0];
                    if (! isset($topTabs[$tab])) {
                        $topTabs[$tab] = ['count' => 0, 'max_ms' => 0];
                    }
                    $topTabs[$tab]['count'] += $count;
                    $topTabs[$tab]['max_ms'] = max($topTabs[$tab]['max_ms'], $maxMs);
                }
            }

            foreach ($this->aggregate('app_operation_slow', ['max', 'count'], 'count', 'desc', 120) as $row) {
                $key = (string) ($row->key ?? '');
                $n = (int) ($row->count ?? 0);
                if (str_starts_with($key, 'rx:')) {
                    $rx['slow'] += $n;
                } elseif (str_starts_with($key, 'map:')) {
                    $map['slow'] += $n;
                } elseif (str_starts_with($key, 'analytics:tab:')) {
                    $analytics['slow'] += $n;
                }
            }

            foreach ($this->aggregate('app_operation_error', 'count', 'count', 'desc', 40) as $row) {
                $key = (string) ($row->key ?? '');
                $n = (int) ($row->count ?? 0);
                if (str_starts_with($key, 'rx:')) {
                    $rx['errors'] += $n;
                } elseif (str_starts_with($key, 'map:')) {
                    $map['errors'] += $n;
                } elseif (str_starts_with($key, 'analytics:tab:')) {
                    $analytics['errors'] += $n;
                }
            }

            uasort($topTabs, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
            $topTabs = array_slice($topTabs, 0, 6, true);

            return compact('rx', 'map', 'analytics', 'topTabs');
        }, 'rx-consultoria-v1');

        return View::make('livewire.pulse.rx-consultoria-pulse-card', [
            'rx' => $payload['rx'],
            'map' => $payload['map'],
            'analytics' => $payload['analytics'],
            'topTabs' => $payload['topTabs'],
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
