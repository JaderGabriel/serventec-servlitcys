@php
    use App\Support\Horizonte\HorizonteFortnightlyFeedPhaseCatalog;

    $pipeline = is_array($pipeline ?? null) ? $pipeline : null;
    if ($pipeline === null) {
        return;
    }

    $phases = is_array($pipeline['phases'] ?? null) ? $pipeline['phases'] : [];
    $queue = is_array($pipeline['phase_queue'] ?? null) ? $pipeline['phase_queue'] : [];
    $total = count($queue);
    $done = collect($phases)->whereIn('status', ['completed', 'skipped', 'failed'])->count();
    $status = (string) ($pipeline['status'] ?? 'idle');
    $stepInterval = (int) ($stepInterval ?? config('horizonte.fortnightly_feed.schedule.step_interval_minutes', 20));
    $ibgeUfsPerStep = max(1, (int) config('horizonte.fortnightly_feed.ibge_ufs_per_step', 1));
@endphp

<div class="rounded-xl border border-indigo-200/80 bg-indigo-50/40 dark:border-indigo-900/50 dark:bg-indigo-950/20 p-4 space-y-3">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h4 class="text-sm font-semibold text-indigo-950 dark:text-indigo-100">{{ __('Abastecimento em etapas') }}</h4>
            <p class="mt-1 text-xs text-indigo-900/80 dark:text-indigo-200/80">
                {{ __('Pipeline :id — :done/:total fases (:status).', [
                    'id' => $pipeline['run_id'] ?? '—',
                    'done' => (string) $done,
                    'total' => (string) $total,
                    'status' => $status,
                ]) }}
            </p>
            @if ($status === 'running')
                <p class="mt-1 text-[11px] text-indigo-800/70 dark:text-indigo-300/70">
                    {{ __('Próximo passo pelo agendador a cada :n min (--staged --continue). IBGE: :u UF(s) por passo.', [
                        'n' => (string) $stepInterval,
                        'u' => (string) $ibgeUfsPerStep,
                    ]) }}
                </p>
            @endif
        </div>
        @if ($status === 'running')
            <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-semibold text-sky-900 dark:bg-sky-950/50 dark:text-sky-200">{{ __('Em curso') }}</span>
        @elseif (in_array($status, ['completed', 'partial'], true))
            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200">{{ __('Concluído') }}</span>
        @endif
    </div>

    @if ($total > 0)
        <ol class="space-y-2">
            @foreach ($phases as $row)
                @php
                    $phaseStatus = (string) ($row['status'] ?? 'pending');
                    $phaseKey = (string) ($row['key'] ?? '');
                    $phaseResult = is_array($row['result'] ?? null) ? $row['result'] : [];
                    $ibgeDone = (int) ($phaseResult['ibge_done'] ?? 0);
                    $ibgeTotal = (int) ($phaseResult['ibge_total'] ?? 27);
                    $isIbgeRunning = $phaseKey === 'ibge_catalog' && $phaseStatus === 'running';
                    $badge = match ($phaseStatus) {
                        'completed' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200',
                        'skipped' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                        'failed' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
                        'running' => 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200',
                        default => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                    };
                    $statusLabel = $isIbgeRunning && ($phaseResult['partial'] ?? false)
                        ? __('em progresso')
                        : $phaseStatus;
                @endphp
                <li class="rounded-lg border border-indigo-100/80 dark:border-indigo-900/40 bg-white/70 dark:bg-slate-900/40 px-3 py-2 text-xs">
                    <div class="flex flex-wrap items-start gap-2 justify-between">
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ HorizonteFortnightlyFeedPhaseCatalog::label($phaseKey) }}</span>
                            @if (filled($row['message'] ?? null))
                                <p class="mt-0.5 text-slate-600 dark:text-slate-400">{{ $row['message'] }}</p>
                            @endif
                            @if ($isIbgeRunning && $ibgeTotal > 0)
                                <div class="mt-2">
                                    <div class="flex justify-between text-[10px] text-slate-500 mb-1">
                                        <span>{{ __('UFs aquecidas') }}</span>
                                        <span class="tabular-nums">{{ $ibgeDone }}/{{ $ibgeTotal }}</span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                        <div
                                            class="h-full rounded-full bg-sky-500 transition-all"
                                            style="width: {{ min(100, max(0, (int) round(($ibgeDone / max(1, $ibgeTotal)) * 100))) }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $badge }}">{{ $statusLabel }}</span>
                    </div>
                    @if (filled($row['started_at'] ?? null) || filled($row['finished_at'] ?? null))
                        <p class="mt-1 text-[10px] text-slate-500 font-mono">
                            @if (filled($row['started_at'] ?? null))
                                {{ \Illuminate\Support\Carbon::parse($row['started_at'])->timezone(config('app.timezone'))->format('d/m H:i:s') }}
                            @endif
                            @if (filled($row['finished_at'] ?? null))
                                → {{ \Illuminate\Support\Carbon::parse($row['finished_at'])->timezone(config('app.timezone'))->format('d/m H:i:s') }}
                            @endif
                        </p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
