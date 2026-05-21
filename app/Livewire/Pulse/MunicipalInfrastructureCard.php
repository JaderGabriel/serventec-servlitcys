<?php

namespace App\Livewire\Pulse;

use App\Models\AdminSyncTask;
use App\Models\City;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Inventário operacional de municípios (bases i-Educar) para decisão de infraestrutura.
 */
#[Lazy]
class MunicipalInfrastructureCard extends Card
{
    public function render(): Renderable
    {
        [$rows, $time, $runAt] = $this->remember(function () {
            $traffic = [];
            foreach ($this->aggregate('instituicao_request', 'count', 'count', 'desc', 80) as $r) {
                $k = (string) ($r->key ?? '');
                $id = str_starts_with($k, 'cid:') ? (int) substr($k, 4) : 0;
                if ($id > 0) {
                    $traffic[$id] = (int) ($r->count ?? 0);
                }
            }

            $cities = City::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'uf', 'ibge_municipio', 'db_driver', 'db_host', 'db_database']);

            return $cities->map(function (City $city) use ($traffic) {
                $setup = $city->hasDataSetup();

                return [
                    'id' => (int) $city->id,
                    'name' => (string) $city->name,
                    'uf' => (string) ($city->uf ?? ''),
                    'ibge' => (string) ($city->ibge_municipio ?? ''),
                    'driver' => $city->effectiveIeducarDriver(),
                    'setup' => $setup,
                    'requests' => $traffic[$city->id] ?? 0,
                ];
            })->sortByDesc('requests')->values();
        }, 'municipal');

        [$syncRecent, $t2, $r2] = $this->remember(function () {
            return AdminSyncTask::query()
                ->with('city:id,name')
                ->orderByDesc('id')
                ->limit(6)
                ->get()
                ->map(fn (AdminSyncTask $t) => [
                    'id' => (int) $t->id,
                    'label' => (string) ($t->label ?? $t->domain),
                    'status' => (string) $t->status,
                    'city' => $t->city?->name,
                    'created' => $t->created_at?->diffForHumans() ?? '',
                ])
                ->all();
        }, 'sync-recent');

        $maxRequests = max(1, ...$rows->pluck('requests')->all() ?: [1]);

        return View::make('livewire.pulse.municipal-infrastructure-card', [
            'cities' => $rows,
            'maxRequests' => $maxRequests,
            'syncRecent' => $syncRecent,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
