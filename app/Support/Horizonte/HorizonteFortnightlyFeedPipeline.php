<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Estado do abastecimento Horizonte em etapas (uma fase por invocação Artisan).
 */
final class HorizonteFortnightlyFeedPipeline
{
    private const CACHE_KEY = 'horizonte:fortnightly_feed:pipeline';

    public static function isActive(): bool
    {
        $state = self::get();

        return is_array($state) && ($state['status'] ?? '') === 'running';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function put(array $state): void
    {
        $ttl = max(3600, (int) config('horizonte.fortnightly_feed.pipeline_cache_ttl', 604800));
        Cache::put(self::CACHE_KEY, $state, now()->addSeconds($ttl));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        HorizonteSaebImportProgress::reset();
        HorizonteEducacensoImportProgress::reset();
        HorizonteSidraImportProgress::reset();
    }

    public static function forgetIncludingIbgeProgress(): void
    {
        self::forget();
        HorizonteIbgeWarmProgress::reset();
    }

    /**
     * @param  array<string, bool>  $options
     * @return array<string, mixed>
     */
    public static function start(array $options): array
    {
        $queue = HorizonteFortnightlyFeedPhaseCatalog::queueFromOptions($options);
        $runId = now()->format('Ymd-His').'-'.Str::lower(Str::random(6));

        $phases = array_map(static fn (string $key): array => [
            'key' => $key,
            'status' => 'pending',
            'success' => null,
            'message' => null,
            'started_at' => null,
            'finished_at' => null,
            'result' => null,
        ], $queue);

        $state = [
            'run_id' => $runId,
            'status' => $queue === [] ? 'completed' : 'running',
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'completed_at' => $queue === [] ? now()->toIso8601String() : null,
            'reference_year' => (int) config('horizonte.reference_year', (int) date('Y') - 1),
            'options' => $options,
            'phase_queue' => $queue,
            'current_index' => 0,
            'current_phase' => $queue[0] ?? null,
            'phases' => $phases,
            'success' => $queue === [] ? true : null,
            'message' => $queue === []
                ? __('Nenhuma fase seleccionada — ajuste os skips.')
                : null,
            'staged' => true,
        ];

        self::put($state);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $phaseResult
     * @return array<string, mixed>
     */
    public static function recordPhaseResult(array $state, array $phaseResult): array
    {
        $key = (string) ($phaseResult['key'] ?? '');
        $index = (int) ($state['current_index'] ?? 0);
        $queue = is_array($state['phase_queue'] ?? null) ? $state['phase_queue'] : [];
        $success = (bool) ($phaseResult['success'] ?? false);
        $partial = (bool) ($phaseResult['partial'] ?? false);
        $now = now()->toIso8601String();

        $phases = is_array($state['phases'] ?? null) ? $state['phases'] : [];
        if (isset($phases[$index]) && ($phases[$index]['key'] ?? '') === $key) {
            $status = $partial
                ? 'partial'
                : (($phaseResult['skipped'] ?? false)
                    ? 'skipped'
                    : ($success ? 'completed' : 'failed'));
            $phases[$index] = array_merge($phases[$index], [
                'status' => $status,
                'success' => $partial ? null : $success,
                'message' => (string) ($phaseResult['message'] ?? ''),
                'finished_at' => $partial ? null : $now,
                'result' => $phaseResult,
            ]);
            if (filled($phases[$index]['started_at'] ?? null) === false) {
                $phases[$index]['started_at'] = $now;
            }
        }

        $nextIndex = $partial ? $index : $index + 1;
        $done = ! $partial && $nextIndex >= count($queue);
        $phaseResults = array_values(array_filter(
            array_map(static fn (array $p): ?array => is_array($p['result'] ?? null) ? $p['result'] : null, $phases),
            static fn (?array $r): bool => $r !== null,
        ));

        $usable = self::pipelineHasUsableOutput($phaseResults);
        $hasWarnings = collect($phaseResults)->contains(
            static fn (array $p): bool => (bool) ($p['skipped'] ?? false) || ! ($p['success'] ?? false),
        );

        $state = array_merge($state, [
            'phases' => $phases,
            'current_index' => $done ? $index : $nextIndex,
            'current_phase' => $done ? null : ($queue[$nextIndex] ?? null),
            'status' => $done ? ($usable ? 'completed' : 'partial') : 'running',
            'updated_at' => $now,
            'completed_at' => $done ? $now : null,
            'success' => $done ? $usable : null,
            'message' => $done
                ? ($usable
                    ? ($hasWarnings
                        ? __('Abastecimento Horizonte concluído com avisos — mapa usa os dados disponíveis.')
                        : __('Abastecimento Horizonte concluído em etapas.'))
                    : __('Abastecimento Horizonte concluído sem dados novos — reveja os logs.'))
                : null,
        ]);

        self::put($state);

        if ($done) {
            HorizonteFortnightlyFeedCache::put([
                'success' => (bool) ($state['success'] ?? false),
                'phases' => $phaseResults,
                'message' => (string) ($state['message'] ?? ''),
                'run_id' => (string) ($state['run_id'] ?? ''),
                'staged' => true,
            ]);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function markPhaseRunning(array $state, string $phaseKey): array
    {
        $index = (int) ($state['current_index'] ?? 0);
        $phases = is_array($state['phases'] ?? null) ? $state['phases'] : [];

        if (isset($phases[$index]) && ($phases[$index]['key'] ?? '') === $phaseKey) {
            $phases[$index]['status'] = 'running';
            $phases[$index]['started_at'] = now()->toIso8601String();
        }

        $state['phases'] = $phases;
        $state['current_phase'] = $phaseKey;
        $state['updated_at'] = now()->toIso8601String();
        self::put($state);

        return $state;
    }

    /**
     * @param  list<array<string, mixed>>  $phaseResults
     */
    private static function pipelineHasUsableOutput(array $phaseResults): bool
    {
        foreach ($phaseResults as $phase) {
            if (! ($phase['success'] ?? false)) {
                continue;
            }
            if (($phase['imported'] ?? 0) > 0
                || ($phase['indexed'] ?? 0) > 0
                || ($phase['matched'] ?? 0) > 0
                || ($phase['ufs'] ?? 0) > 0) {
                return true;
            }
            if (($phase['skipped'] ?? false) && ($phase['key'] ?? '') !== 'sge_registry') {
                return true;
            }
        }

        return collect($phaseResults)->contains(fn (array $p): bool => (bool) ($p['success'] ?? false));
    }
}
