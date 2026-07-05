<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** Progresso incremental Educacenso Horizonte — um passo = ano × UF. */
final class HorizonteEducacensoImportProgress
{
    private const CACHE_KEY_STEPS = 'horizonte:educacenso_import:steps_done';

    private const CACHE_KEY_FAILED_STEP = 'horizonte:educacenso_import:last_failed_step';

    /** @deprecated legado — limpo no reset */
    private const CACHE_KEY = 'horizonte:educacenso_import:progress';

    /** @deprecated legado */
    private const CACHE_KEY_FAILED = 'horizonte:educacenso_import:last_failed';

    private static bool $hydrating = false;

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
        self::ensureHydrated();

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
        self::ensureHydrated();

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
        self::ensureHydrated();

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
        self::ensureHydrated();

        $failedKey = self::stepKey($year, $uf);
        $ttl = self::cacheTtl();
        Cache::put(self::CACHE_KEY_FAILED_STEP, $failedKey, now()->addSeconds($ttl));
        HorizonteEducacensoImportProgressSnapshot::write(self::doneSteps(), $failedKey);
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
        HorizonteEducacensoImportProgressSnapshot::write(self::doneSteps(), null);
    }

    public static function reset(): void
    {
        Cache::forget(self::CACHE_KEY_STEPS);
        Cache::forget(self::CACHE_KEY_FAILED_STEP);
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY_FAILED);
        HorizonteEducacensoImportProgressSnapshot::delete();
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

    private static function ensureHydrated(): void
    {
        if (self::$hydrating || Cache::has(self::CACHE_KEY_STEPS)) {
            return;
        }

        self::$hydrating = true;
        try {
            $snapshot = HorizonteEducacensoImportProgressSnapshot::read();
            if ($snapshot !== null && $snapshot['steps_done'] !== []) {
                self::restoreFromSnapshot($snapshot, 'snapshot');

                return;
            }

            if (filter_var(
                config('horizonte.fortnightly_feed.educacenso_infer_progress_from_db', true),
                FILTER_VALIDATE_BOOLEAN,
            )) {
                $years = HorizonteEducacensoYearWindow::years();
                $inferred = HorizonteEducacensoImportProgressInferrer::inferDoneSteps($years);
                if ($inferred !== []) {
                    self::restoreFromSnapshot([
                        'steps_done' => $inferred,
                        'last_failed_step' => null,
                        'source' => 'inferred',
                    ], 'inferred');
                }
            }
        } finally {
            self::$hydrating = false;
        }
    }

    /**
     * @param  array{steps_done: list<string>, last_failed_step: ?string, source?: ?string}  $snapshot
     */
    private static function restoreFromSnapshot(array $snapshot, string $source): void
    {
        $steps = array_values(array_unique(array_filter(array_map('strval', $snapshot['steps_done'] ?? []))));
        if ($steps === []) {
            return;
        }

        $ttl = self::cacheTtl();
        Cache::put(self::CACHE_KEY_STEPS, $steps, now()->addSeconds($ttl));

        $failed = $snapshot['last_failed_step'] ?? null;
        if (is_string($failed) && $failed !== '') {
            Cache::put(self::CACHE_KEY_FAILED_STEP, $failed, now()->addSeconds($ttl));
        }

        HorizonteEducacensoImportProgressSnapshot::write(
            $steps,
            is_string($failed) && $failed !== '' ? $failed : null,
            $source,
        );

        Log::info('horizonte.educacenso_progress_hydrated', [
            'source' => $source,
            'steps' => count($steps),
        ]);
    }

    /**
     * @param  list<string>  $steps
     */
    private static function storeSteps(array $steps): void
    {
        $steps = array_values(array_unique(array_filter(array_map('strval', $steps))));
        Cache::put(self::CACHE_KEY_STEPS, $steps, now()->addSeconds(self::cacheTtl()));
        HorizonteEducacensoImportProgressSnapshot::write($steps, self::lastFailedStepFromCache());
    }

    private static function lastFailedStepFromCache(): ?string
    {
        $step = Cache::get(self::CACHE_KEY_FAILED_STEP);

        return is_string($step) && $step !== '' ? $step : null;
    }

    private static function cacheTtl(): int
    {
        return max(3600, (int) config('horizonte.fortnightly_feed.pipeline_cache_ttl', 604800));
    }
}
