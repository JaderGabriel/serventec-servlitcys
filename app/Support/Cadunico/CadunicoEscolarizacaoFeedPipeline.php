<?php

namespace App\Support\Cadunico;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Estado do abastecimento bimestral do card Escolarização (uma fase por invocação). */
final class CadunicoEscolarizacaoFeedPipeline
{
    private const CACHE_KEY = 'cadunico:escolarizacao_feed:pipeline';

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
        $ttl = max(3600, (int) config('ieducar.cadunico.escolarizacao_feed.pipeline_cache_ttl', 604800));
        Cache::put(self::CACHE_KEY, $state, now()->addSeconds($ttl));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public static function start(): array
    {
        $queue = CadunicoEscolarizacaoFeedPhaseCatalog::phaseKeys();
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
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'completed_at' => null,
            'phase_queue' => $queue,
            'current_index' => 0,
            'current_phase' => $queue[0] ?? null,
            'phases' => $phases,
            'success' => null,
            'message' => null,
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
        $now = now()->toIso8601String();

        $phases = is_array($state['phases'] ?? null) ? $state['phases'] : [];
        if (isset($phases[$index]) && ($phases[$index]['key'] ?? '') === $key) {
            $status = ($phaseResult['skipped'] ?? false)
                ? 'skipped'
                : ($success ? 'completed' : 'failed');
            $phases[$index] = array_merge($phases[$index], [
                'status' => $status,
                'success' => $success,
                'message' => (string) ($phaseResult['message'] ?? ''),
                'finished_at' => $now,
                'result' => $phaseResult,
            ]);
            if (filled($phases[$index]['started_at'] ?? null) === false) {
                $phases[$index]['started_at'] = $now;
            }
        }

        $nextIndex = $index + 1;
        $done = $nextIndex >= count($queue);
        $phaseResults = array_values(array_filter(
            array_map(static fn (array $p): ?array => is_array($p['result'] ?? null) ? $p['result'] : null, $phases),
            static fn (?array $r): bool => $r !== null,
        ));

        $usable = self::pipelineHasUsableOutput($phaseResults);

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
                    ? __('Abastecimento escolarização concluído — card CadÚnico atualizado.')
                    : __('Abastecimento escolarização concluído sem dados novos — reveja os logs.'))
                : null,
        ]);

        self::put($state);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
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
            if (($phase['imported'] ?? 0) > 0 || ($phase['indexed'] ?? 0) > 0) {
                return true;
            }
            if ($phase['skipped'] ?? false) {
                return true;
            }
        }

        return collect($phaseResults)->contains(fn (array $p): bool => (bool) ($p['success'] ?? false));
    }
}
