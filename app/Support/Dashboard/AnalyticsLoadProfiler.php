<?php

namespace App\Support\Dashboard;

use Illuminate\Support\Facades\Log;

/**
 * Mede etapas do carregamento do painel analítico (filtros, repositórios, view).
 * Logs em analytics.profile / analytics.profile_summary quando debug ativo.
 */
final class AnalyticsLoadProfiler
{
    /** @var list<array{step: string, ms: float, meta?: array<string, mixed>}> */
    private array $steps = [];

    private readonly float $startedAt;

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    public static function enabled(): bool
    {
        return (bool) config('analytics.debug_log', false);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @param  array<string, mixed>  $meta
     * @return T
     */
    public function measure(string $step, callable $fn, array $meta = []): mixed
    {
        $t0 = microtime(true);
        try {
            return $fn();
        } finally {
            $ms = round((microtime(true) - $t0) * 1000, 1);
            $entry = ['step' => $step, 'ms' => $ms];
            if ($meta !== []) {
                $entry['meta'] = $meta;
            }
            $this->steps[] = $entry;
            if (self::enabled()) {
                Log::info('analytics.profile', array_merge([
                    'step' => $step,
                    'ms' => $ms,
                ], $meta));
            }
        }
    }

    /**
     * @return list<array{step: string, ms: float, meta?: array<string, mixed>}>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    public function totalMs(): float
    {
        return round((microtime(true) - $this->startedAt) * 1000, 1);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function flush(string $context, array $extra = []): void
    {
        if (! self::enabled()) {
            return;
        }

        Log::info('analytics.profile_summary', array_merge([
            'context' => $context,
            'total_ms' => $this->totalMs(),
            'steps' => $this->steps,
        ], $extra));
    }
}
