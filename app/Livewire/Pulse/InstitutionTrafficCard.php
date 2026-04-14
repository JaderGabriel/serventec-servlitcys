<?php

namespace App\Livewire\Pulse;

use App\Models\City;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Tráfego agregado por cidade (instituição) e total global, para o painel Pulse.
 */
#[Lazy]
class InstitutionTrafficCard extends Card
{
    public function render(): Renderable
    {
        [$globalTotal, $time, $runAt] = $this->remember(function () {
            $rows = $this->aggregate('trafego_app', 'count', null, 'desc', 5);
            $sum = 0;
            foreach ($rows as $r) {
                $sum += (int) ($r->count ?? 0);
            }

            return $sum;
        }, 'global');

        [$cityRows, $time2, $runAt2] = $this->remember(function () {
            $agg = $this->aggregate('instituicao_request', 'count', 'count', 'desc', 40);
            if ($agg->isEmpty()) {
                return collect();
            }

            $ids = $agg->map(function ($r) {
                $k = (string) ($r->key ?? '');

                return str_starts_with($k, 'cid:')
                    ? (int) substr($k, 4)
                    : 0;
            })->filter(fn (int $id) => $id > 0)->unique()->values();

            $names = City::query()->whereIn('id', $ids)->pluck('name', 'id');

            return $agg->map(function ($r) use ($names) {
                $k = (string) ($r->key ?? '');
                $id = str_starts_with($k, 'cid:') ? (int) substr($k, 4) : 0;

                return (object) [
                    'city_id' => $id,
                    'city_name' => $id > 0 ? ($names[$id] ?? ('#'.$id)) : $k,
                    'count' => (int) ($r->count ?? 0),
                ];
            });
        }, 'cities');

        return View::make('livewire.pulse.institution-traffic-card', [
            'globalTotal' => $globalTotal,
            'cityRows' => $cityRows,
            'time' => $time2,
            'runAt' => $runAt2,
            'timeGlobal' => $time,
            'runAtGlobal' => $runAt,
        ]);
    }
}
