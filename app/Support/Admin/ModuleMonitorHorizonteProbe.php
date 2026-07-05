<?php

namespace App\Support\Admin;

use Illuminate\Support\Carbon;

/**
 * Sonda estrutural do módulo Horizonte a partir do payload do hub de abastecimento.
 */
final class ModuleMonitorHorizonteProbe
{
    /**
     * @param  array<string, mixed>  $status
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    public static function probe(array $status): array
    {
        if (! (bool) ($status['enabled'] ?? false)) {
            return self::result('unknown', __('Horizonte desactivado na configuração.'));
        }

        $coverage = is_array($status['coverage'] ?? null) ? $status['coverage'] : [];
        $universe = max(1, (int) ($coverage['universe_municipios'] ?? 0));
        $triad = (int) ($coverage['with_full_triad'] ?? 0);
        $triadPct = (int) round(($triad / $universe) * 100);

        $phases = is_array($status['phases'] ?? null) ? $status['phases'] : [];
        $phasesOk = count(array_filter($phases, static fn (array $p): bool => (bool) ($p['ok'] ?? false)));
        $phasesTotal = max(1, count($phases));

        $tags = [
            __('triád :n/:u', ['n' => $triad, 'u' => $universe]),
            __('fases :ok/:total', ['ok' => $phasesOk, 'total' => $phasesTotal]),
        ];

        $pipeline = is_array($status['pipeline'] ?? null) ? $status['pipeline'] : null;
        $pipelineStatus = is_string($pipeline['status'] ?? null) ? (string) $pipeline['status'] : null;
        if ($pipelineStatus === 'running') {
            $step = is_string($pipeline['current_phase'] ?? null) ? (string) $pipeline['current_phase'] : null;

            return self::result(
                'degraded',
                __('Abastecimento Horizonte em curso — aguardar conclusão do pipeline.'),
                tags: array_merge($tags, array_filter([
                    __('pipeline activo'),
                    $step !== null ? __('fase :step', ['step' => $step]) : null,
                ])),
            );
        }

        $lastFeed = is_array($status['last_feed'] ?? null) ? $status['last_feed'] : null;
        $finishedAt = self::parseTime($lastFeed['finished_at'] ?? null);
        $feedSuccess = $lastFeed !== null && ($lastFeed['success'] ?? false) !== false;

        $staleDays = max(14, (int) config('module_monitor.probe.horizonte_feed_stale_days', 70));
        if ($finishedAt === null) {
            return self::result(
                'degraded',
                __('Nenhum abastecimento bimestral concluído — executar horizonte:fortnightly-feed.'),
                tags: $tags,
            );
        }

        if ($finishedAt->lt(now()->subDays($staleDays))) {
            return self::result(
                'degraded',
                __('Último abastecimento há :when — ciclo bimestral (:days d) possivelmente vencido.', [
                    'when' => $finishedAt->diffForHumans(),
                    'days' => (string) $staleDays,
                ]),
                $finishedAt->toIso8601String(),
                tags: array_merge($tags, [__('feed antigo')]),
            );
        }

        if (! $feedSuccess) {
            return self::result(
                'failed',
                __('Último abastecimento falhou :when.', ['when' => $finishedAt->diffForHumans()]),
                $finishedAt->toIso8601String(),
                self::parseTime($lastFeed['failed_at'] ?? null)?->toIso8601String(),
                array_merge($tags, [__('feed falhou')]),
            );
        }

        if ($phasesOk < (int) ceil($phasesTotal * 0.5)) {
            return self::result(
                'degraded',
                __('Só :ok/:total fases de cobertura OK — rever hub de abastecimento.', [
                    'ok' => $phasesOk,
                    'total' => $phasesTotal,
                ]),
                $finishedAt->toIso8601String(),
                tags: $tags,
            );
        }

        if ($triadPct < 40) {
            return self::result(
                'degraded',
                __('Triád FUNDEB×Censo×SAEB em :pct% — cobertura nacional incompleta.', ['pct' => $triadPct]),
                $finishedAt->toIso8601String(),
                tags: $tags,
            );
        }

        if (! (bool) ($coverage['microdados_ok'] ?? false)) {
            return self::result(
                'degraded',
                __('Microdados Educacenso ausentes — fases Censo/Educacenso bloqueadas.'),
                $finishedAt->toIso8601String(),
                tags: array_merge($tags, [__('sem microdados')]),
            );
        }

        return self::result(
            'operational',
            __('Abastecimento :when — triád :pct%, :ok/:total fases OK.', [
                'when' => $finishedAt->diffForHumans(),
                'pct' => $triadPct,
                'ok' => $phasesOk,
                'total' => $phasesTotal,
            ]),
            $finishedAt->toIso8601String(),
            tags: $tags,
        );
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{triad: int, universe: int, phases_ok: int, phases_total: int, pipeline_running: bool, feed_age_days: ?int}
     */
    public static function kpiSummary(array $status): array
    {
        $coverage = is_array($status['coverage'] ?? null) ? $status['coverage'] : [];
        $phases = is_array($status['phases'] ?? null) ? $status['phases'] : [];
        $pipeline = is_array($status['pipeline'] ?? null) ? $status['pipeline'] : null;
        $finishedAt = self::parseTime(is_array($status['last_feed'] ?? null) ? ($status['last_feed']['finished_at'] ?? null) : null);

        return [
            'triad' => (int) ($coverage['with_full_triad'] ?? 0),
            'universe' => max(0, (int) ($coverage['universe_municipios'] ?? 0)),
            'phases_ok' => count(array_filter($phases, static fn (array $p): bool => (bool) ($p['ok'] ?? false))),
            'phases_total' => count($phases),
            'pipeline_running' => is_string($pipeline['status'] ?? null) && ($pipeline['status'] ?? '') === 'running',
            'feed_age_days' => $finishedAt !== null ? (int) $finishedAt->diffInDays(now()) : null,
        ];
    }

    private static function parseTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string|null>  $tags
     * @return array{signal: string, detail: string, last_success_at: ?string, last_failure_at: ?string, tags: list<string>}
     */
    private static function result(
        string $signal,
        string $detail,
        ?string $lastSuccessAt = null,
        ?string $lastFailureAt = null,
        array $tags = [],
    ): array {
        return [
            'signal' => $signal,
            'detail' => $detail,
            'last_success_at' => $lastSuccessAt,
            'last_failure_at' => $lastFailureAt,
            'tags' => array_values(array_filter($tags, static fn (?string $t): bool => filled($t))),
        ];
    }
}
