<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use Illuminate\Support\Facades\Cache;

/** Progresso incremental Educacenso Horizonte — um passo = ano × UF. */
final class HorizonteEducacensoImportProgress
{
    private const CACHE_KEY_STEPS = 'horizonte:educacenso_import:steps_done';

    private const CACHE_KEY_FAILED_STEP = 'horizonte:educacenso_import:last_failed_step';

    /** @deprecated legado — limpo no reset */
    private const CACHE_KEY = 'horizonte:educacenso_import:progress';

    /** @deprecated legado */
    private const CACHE_KEY_FAILED = 'horizonte:educacenso_import:last_failed';

    public static function stepKey(int $year, string $uf): string
    {
        return $year.':'.strtoupper(trim($uf));
    }

    /**
     * @return array{year: int, uf: string}|null
     */
    public static function parseStepKey(string $key): ?array
    {
        if (! preg_match('/^(\d{4}):([A-Z]{2})$/', strtoupper(trim($key)), $m)) {
            return null;
        }

        return ['year' => (int) $m[1], 'uf' => $m[2]];
    }

    /**
     * @return list<string>
     */
    public static function doneSteps(): array
    {
        $cached = Cache::get(self::CACHE_KEY_STEPS);

        return is_array($cached)
            ? array_values(array_unique(array_filter(array_map('strval', $cached))))
            : [];
    }

    /**
     * @return list<array{year: int, uf: string, indexed?: int}>
     */
    public static function recentDoneSteps(int $limit = 12): array
    {
        $steps = self::doneSteps();
        $slice = array_slice($steps, -max(1, $limit));
        $out = [];
        foreach ($slice as $key) {
            $parsed = self::parseStepKey($key);
            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return array_reverse($out);
    }

    public static function lastFailedStep(): ?string
    {
        $step = Cache::get(self::CACHE_KEY_FAILED_STEP);

        return is_string($step) && $step !== '' ? $step : null;
    }

    /** @deprecated use lastFailedStep() */
    public static function lastFailedYear(): ?int
    {
        $step = self::lastFailedStep();
        if ($step === null) {
            return null;
        }
        $parsed = self::parseStepKey($step);

        return $parsed['year'] ?? null;
    }

    /**
     * @param  list<int>  $allYears
     * @return list<int>
     */
    public static function doneYears(array $allYears): array
    {
        $done = [];
        foreach (array_map('intval', $allYears) as $year) {
            if (self::yearIsComplete($year)) {
                $done[] = $year;
            }
        }
        sort($done, SORT_NUMERIC);

        return $done;
    }

    /**
     * @param  list<int>  $allYears
     * @return list<int>
     */
    public static function remainingYears(array $allYears): array
    {
        $done = array_flip(self::doneYears($allYears));

        return array_values(array_filter(
            array_map('intval', $allYears),
            static fn (int $year): bool => ! isset($done[$year]),
        ));
    }

    /**
     * @param  list<int>  $allYears
     * @return list<array{year: int, uf: string}>
     */
    public static function remainingSteps(array $allYears): array
    {
        $done = array_flip(self::doneSteps());
        $out = [];
        foreach (array_map('intval', $allYears) as $year) {
            foreach (self::allUfs() as $uf) {
                $key = self::stepKey($year, $uf);
                if (! isset($done[$key])) {
                    $out[] = ['year' => $year, 'uf' => $uf];
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $allYears
     * @return list<array{year: int, uf: string}>
     */
    public static function orderedRemainingSteps(array $allYears): array
    {
        $remaining = self::remainingSteps($allYears);
        $failed = self::lastFailedStep();
        if ($failed !== null && count($remaining) > 1) {
            $remaining = array_values(array_filter(
                $remaining,
                static fn (array $step): bool => self::stepKey($step['year'], $step['uf']) !== $failed,
            ));
            $parsed = self::parseStepKey($failed);
            if ($parsed !== null) {
                $remaining[] = $parsed;
            }
        }

        return $remaining;
    }

    /**
     * @param  list<int>  $allYears
     */
    public static function totalSteps(array $allYears): int
    {
        return count($allYears) * count(self::allUfs());
    }

    public static function doneStepCount(): int
    {
        return count(self::doneSteps());
    }

    /**
     * @param  list<int>  $allYears
     */
    public static function isComplete(array $allYears): bool
    {
        return self::remainingSteps($allYears) === [];
    }

    public static function yearIsComplete(int $year): bool
    {
        foreach (self::allUfs() as $uf) {
            if (! in_array(self::stepKey($year, $uf), self::doneSteps(), true)) {
                return false;
            }
        }

        return true;
    }

    public static function markStepDone(int $year, string $uf): void
    {
        $steps = self::doneSteps();
        $key = self::stepKey($year, $uf);
        if (! in_array($key, $steps, true)) {
            $steps[] = $key;
        }
        self::storeSteps($steps);
        self::clearFailedStep();
    }

    /** Marca todos os passos UF de um ano (compatibilidade com testes legados). */
    public static function markDone(int $year): void
    {
        foreach (self::allUfs() as $uf) {
            self::markStepDone($year, $uf);
        }
    }

    public static function markStepFailed(int $year, string $uf): void
    {
        $ttl = self::cacheTtl();
        Cache::put(self::CACHE_KEY_FAILED_STEP, self::stepKey($year, $uf), now()->addSeconds($ttl));
    }

    /** @deprecated use markStepFailed() */
    public static function markFailed(int $year): void
    {
        self::markStepFailed($year, self::allUfs()[0] ?? 'SP');
    }

    public static function clearFailedStep(): void
    {
        Cache::forget(self::CACHE_KEY_FAILED_STEP);
        Cache::forget(self::CACHE_KEY_FAILED);
    }

    public static function reset(): void
    {
        Cache::forget(self::CACHE_KEY_STEPS);
        Cache::forget(self::CACHE_KEY_FAILED_STEP);
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY_FAILED);
    }

    /**
     * @return list<array{year: int, uf: string}>
     */
    public static function orderedRemainingYears(array $allYears): array
    {
        return self::orderedRemainingSteps($allYears);
    }

    /**
     * @return list<string>
     */
    public static function allUfs(): array
    {
        return IbgeMunicipalityCatalog::brazilianUfs();
    }

    /**
     * @param  list<string>  $steps
     */
    private static function storeSteps(array $steps): void
    {
        Cache::put(self::CACHE_KEY_STEPS, $steps, now()->addSeconds(self::cacheTtl()));
    }

    private static function cacheTtl(): int
    {
        return max(3600, (int) config('horizonte.fortnightly_feed.pipeline_cache_ttl', 604800));
    }
}
