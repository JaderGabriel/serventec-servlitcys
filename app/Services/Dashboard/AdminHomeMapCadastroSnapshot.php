<?php

namespace App\Services\Dashboard;

use App\Models\City;
use App\Support\Dashboard\MunicipalityMapCadastroPresenter;
use App\Support\Rx\RxCityMetricsCollector;
use Illuminate\Support\Facades\Cache;

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
        $vigenteYear = (int) config('rx.vigente_year', (int) date('Y'));
        $cacheKey = 'admin_home_map_rx:'.$vigenteYear;
        $ttlMinutes = 20;

        return Cache::remember($cacheKey, now()->addMinutes($ttlMinutes), function () use ($vigenteYear): array {
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
    }
}
