<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use Illuminate\Support\Facades\Cache;

/** Progresso resumível da sincronização de centroides IBGE para o mapa Horizonte. */
final class HorizonteIbgeCentroidSyncProgress
{
    private const CACHE_KEY = 'horizonte:ibge_centroid_sync:progress';

    public static function hasStarted(): bool
    {
        return Cache::has(self::CACHE_KEY);
    }

    /**
     * @return array{done_ufs: list<string>, uf_order: list<string>, started_at?: string}
     */
    public static function state(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (! is_array($cached)) {
            return ['done_ufs' => [], 'uf_order' => []];
        }

        return [
            'done_ufs' => array_values(array_filter($cached['done_ufs'] ?? [])),
            'uf_order' => array_values(array_filter($cached['uf_order'] ?? [])),
            'started_at' => isset($cached['started_at']) ? (string) $cached['started_at'] : null,
        ];
    }

    public static function initialize(): void
    {
        Cache::put(self::CACHE_KEY, [
            'done_ufs' => [],
            'uf_order' => IbgeMunicipalityCatalog::brazilianUfsByMunicipalityCountAsc(),
            'started_at' => now()->toIso8601String(),
        ], now()->addDays(30));
    }

    public static function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<string>
     */
    public static function doneUfs(): array
    {
        return self::state()['done_ufs'];
    }

    /**
     * @return list<string>
     */
    public static function ufOrder(): array
    {
        $order = self::state()['uf_order'];
        if ($order !== []) {
            return $order;
        }

        return IbgeMunicipalityCatalog::brazilianUfsByMunicipalityCountAsc();
    }

    /**
     * @return list<string>
     */
    public static function remainingUfs(): array
    {
        $done = self::doneUfs();

        return array_values(array_filter(
            self::ufOrder(),
            static fn (string $uf): bool => ! in_array($uf, $done, true),
        ));
    }

    public static function isComplete(): bool
    {
        return self::remainingUfs() === [];
    }

    public static function markDone(string $uf): void
    {
        $state = self::state();
        $done = array_values(array_unique(array_merge(
            $state['done_ufs'],
            [strtoupper(trim($uf))],
        )));
        $state['done_ufs'] = $done;
        Cache::put(self::CACHE_KEY, $state, now()->addDays(30));
    }
}
