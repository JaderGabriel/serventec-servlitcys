<?php

namespace App\Livewire\Pulse;

use App\Models\AdminSyncTask;
use App\Models\City;
use App\Support\Pulse\PulseDatabaseMetricsAggregator;
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

            $dbMetrics = PulseDatabaseMetricsAggregator::summarize(
                fn (string $type, array|string $aggregate, ?string $orderBy, string $direction, int $limit) => $this->aggregate($type, $aggregate, $orderBy, $direction, $limit)
            );
            $muniDb = $dbMetrics['municipal_by_city'];

            $cities = City::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'uf', 'ibge_municipio', 'db_driver', 'db_host', 'db_database']);

            return $cities->map(function (City $city) use ($traffic, $muniDb) {
                $setup = $city->hasDataSetup();
                $db = $muniDb[(int) $city->id] ?? null;

                return [
                    'id' => (int) $city->id,
                    'name' => (string) $city->name,
                    'uf' => (string) ($city->uf ?? ''),
                    'ibge' => (string) ($city->ibge_municipio ?? ''),
                    'driver' => $city->effectiveIeducarDriver(),
                    'setup' => $setup,
                    'requests' => $traffic[$city->id] ?? 0,
                    'db_run_max_ms' => $db['run_max_ms'] ?? null,
                    'db_slow_count' => (int) ($db['slow_count'] ?? 0),
                ];
            })->sortByDesc(fn (array $c): int => max(
                (int) ($c['requests'] ?? 0),
                (int) ($c['db_run_max_ms'] ?? 0),
                (int) ($c['db_slow_count'] ?? 0) * 100,
            ))->values();
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
