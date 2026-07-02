<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/** Progresso da malha municipal + área IBGE (por UF, persistido em storage). */
final class HorizonteIbgeMunicipalGeoImportProgress
{
    private const RECENT_KEY = 'horizonte:ibge_municipal_geo:recent';

    /**
     * @return list<string>
     */
    public static function cachedMalhaUfs(): array
    {
        $dir = trim((string) config('horizonte.geo_malha.cache_dir', 'horizonte/geo'), '/');
        $disk = Storage::disk('local');
        $ufs = [];

        foreach (IbgeMunicipalityCatalog::brazilianUfs() as $uf) {
            if ($disk->exists("{$dir}/municipal-{$uf}.json")) {
                $ufs[] = $uf;
            }
        }

        return $ufs;
    }

    /**
     * @return list<string>
     */
    public static function doneUfs(): array
    {
        return self::cachedMalhaUfs();
    }

    public static function totalUfs(): int
    {
        return count(IbgeMunicipalityCatalog::brazilianUfs());
    }

    public static function doneCount(): int
    {
        return count(self::cachedMalhaUfs());
    }

    public static function isComplete(): bool
    {
        return self::remainingUfs() === [];
    }

    /**
     * @param  list<string>  $ufs
     */
    public static function markDone(array $ufs): void
    {
        foreach ($ufs as $uf) {
            self::recordStep([
                'uf' => strtoupper(trim($uf)),
                'success' => true,
            ]);
        }
    }

    public static function reset(): void
    {
        Cache::forget(self::RECENT_KEY);
    }

    /**
     * @return list<string>
     */
    public static function remainingUfs(): array
    {
        return array_values(array_diff(
            IbgeMunicipalityCatalog::brazilianUfs(),
            self::cachedMalhaUfs(),
        ));
    }

    /**
     * @param  array{uf: string, success?: bool, imported?: int, features?: int, message?: ?string}  $step
     */
    public static function recordStep(array $step): void
    {
        $uf = strtoupper(trim((string) ($step['uf'] ?? '')));
        if ($uf === '') {
            return;
        }

        $recent = Cache::get(self::RECENT_KEY);
        $recent = is_array($recent) ? $recent : [];
        array_unshift($recent, [
            'uf' => $uf,
            'imported' => (int) ($step['imported'] ?? 0),
            'features' => (int) ($step['features'] ?? 0),
            'success' => (bool) ($step['success'] ?? true),
            'at' => now()->toIso8601String(),
        ]);
        $recent = array_slice($recent, 0, 15);

        $ttl = max(3600, (int) config('horizonte.fortnightly_feed.pipeline_cache_ttl', 604800));
        Cache::put(self::RECENT_KEY, $recent, now()->addSeconds($ttl));
    }

    /**
     * @return list<array{uf: string, imported: int, features: int, success: bool, at?: string}>
     */
    public static function recentSteps(int $limit = 15): array
    {
        $recent = Cache::get(self::RECENT_KEY);

        if (! is_array($recent)) {
            return [];
        }

        return array_slice(array_values(array_filter($recent, 'is_array')), 0, max(1, $limit));
    }
}
