<?php

namespace App\Services\Dashboard;

use App\Models\City;
use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Dashboard\MunicipalityMapCadastroPresenter;
use App\Support\Pulse\PulseOperationRecorder;
use App\Support\Rx\RxCityMetricsCollector;

/**
 * Snapshot RX (cadastro ano vigente) para cores e cartões do mapa no Início.
 */
final class AdminHomeMapCadastroSnapshot
{
    public function __construct(
        private readonly RxCityMetricsCollector $collector,
    ) {}

    /**
     * @return array{
     *     vigente_ano: int,
     *     by_city_id: array<int, array<string, mixed>>,
     *     generated_at: string
     * }
     */
    public function forMap(): array
    {
        if ((bool) config('performance.home_defer_map_rx_snapshot', true)) {
            return $this->emptyPayload();
        }

        return $this->loadOrBuild();
    }

    /**
     * Endpoint AJAX do mapa — sempre tenta cache/build (ignora defer da página Início).
     */
    public function forMapAjax(): array
    {
        return $this->loadOrBuild();
    }

    /**
     * @return array{
     *     vigente_ano: int,
     *     by_city_id: array<int, array<string, mixed>>,
     *     generated_at: string
     * }
     */
    private function loadOrBuild(): array
    {
        $vigenteYear = (int) config('rx.vigente_year', (int) date('Y'));
        $cacheKey = 'admin_home_map_rx:v2:'.$vigenteYear;

        $cached = AdminHomeMapCache::get($cacheKey);
        if (is_array($cached)) {
            PulseOperationRecorder::record('map:rx_snapshot|cache:hit', 1);

            return $cached;
        }

        $payload = PulseOperationRecorder::measure('map:rx_snapshot|cache:miss', function () use ($vigenteYear): array {
            $byCityId = [];

            City::query()
                ->orderBy('id')
                ->get()
                ->each(function (City $city) use (&$byCityId, $vigenteYear): void {
                    if (! $city->hasDataSetup()) {
                        return;
                    }
                    $row = $this->collector->collect($city, $vigenteYear);
                    $byCityId[(int) $city->id] = MunicipalityMapCadastroPresenter::fromRxRow($row, $vigenteYear);
                });

            return [
                'vigente_ano' => $vigenteYear,
                'by_city_id' => $byCityId,
                'generated_at' => now()->toIso8601String(),
            ];
        });

        AdminHomeMapCache::put($cacheKey, $payload);

        return $payload;
    }

    /**
     * @return array{
     *     vigente_ano: int,
     *     by_city_id: array<int, array<string, mixed>>,
     *     generated_at: string
     * }
     */
    private function emptyPayload(): array
    {
        return [
            'vigente_ano' => (int) config('rx.vigente_year', (int) date('Y')),
            'by_city_id' => [],
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
