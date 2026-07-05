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
    $saebYearsPerStep = max(1, (int) config('horizonte.fortnightly_feed.saeb_years_per_step', 1));
    $educacensoStepsPerStep = max(1, (int) config('horizonte.fortnightly_feed.educacenso_steps_per_step', 1));
    $pipelineOptions = is_array($pipeline['options'] ?? null) ? $pipeline['options'] : [];
    $scopedUf = strtoupper(trim((string) ($pipelineOptions['uf'] ?? '')));
@endphp

<div class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-slate-900 p-4 space-y-3">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h4 class="text-sm font-semibold text-sky-950 dark:text-sky-100">{{ __('Abastecimento em etapas') }}</h4>
            <p class="mt-1 text-xs text-sky-900 dark:text-sky-100">
                {{ __('Pipeline :id — :done/:total fases (:status).', [
                    'id' => $pipeline['run_id'] ?? '—',
                    'done' => (string) $done,
                    'total' => (string) $total,
                    'status' => $status,
                ]) }}
                @if ($scopedUf !== '')
                    · {{ __('UF :uf', ['uf' => $scopedUf]) }}
                @endif
            </p>
            @if ($status === 'running')
                <p class="mt-1 text-[11px] text-sky-800 dark:text-sky-200">
                    {{ __('Próximo passo a cada :n min (--staged --continue). Educacenso: :e passo(s) ano×UF/execução · SAEB: :y ano(s)/passo · IBGE: :u UF(s)/passo.', [
                        'n' => (string) $stepInterval,
                        'e' => (string) $educacensoStepsPerStep,
                        'u' => (string) $ibgeUfsPerStep,
                        'y' => (string) $saebYearsPerStep,
                    ]) }}
                </p>
            @endif
        </div>
        @if ($status === 'running')
            <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-semibold text-sky-900 dark:bg-sky-900 dark:text-sky-100">{{ __('Em curso') }}</span>
        @elseif (in_array($status, ['completed', 'partial'], true))
            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-900 dark:bg-emerald-900 dark:text-emerald-100">{{ __('Concluído') }}</span>
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
                    $saebDone = (int) ($phaseResult['saeb_done'] ?? 0);
                    $saebTotal = (int) ($phaseResult['saeb_total'] ?? 0);
                    $educacensoDone = (int) ($phaseResult['educacenso_done'] ?? 0);
                    $educacensoTotal = (int) ($phaseResult['educacenso_total'] ?? 0);
                    $isIbgeRunning = $phaseKey === 'ibge_catalog' && in_array($phaseStatus, ['running', 'pending', 'partial'], true);
                    $isSaebRunning = $phaseKey === 'saeb_planilhas' && in_array($phaseStatus, ['running', 'pending', 'partial'], true);
                    $isEducacensoRunning = $phaseKey === 'educacenso' && in_array($phaseStatus, ['running', 'pending', 'partial'], true);
                    $badge = match ($phaseStatus) {
                        'completed' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900 dark:text-emerald-100',
                        'partial' => 'bg-violet-100 text-violet-900 dark:bg-violet-900 dark:text-violet-100',
                        'skipped' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-100',
                        'failed' => 'bg-amber-100 text-amber-900 dark:bg-amber-900 dark:text-amber-100',
                        'running' => 'bg-sky-100 text-sky-900 dark:bg-sky-900 dark:text-sky-100',
                        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                    };
                    $statusLabel = match ($phaseStatus) {
                        'partial' => __('parcial'),
                        default => ($isIbgeRunning || $isSaebRunning || $isEducacensoRunning) && ($phaseResult['partial'] ?? false)
                            ? __('em progresso')
                            : $phaseStatus,
                    };
                @endphp
                <li class="rounded-lg border border-sky-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs">
                    <div class="flex flex-wrap items-start gap-2 justify-between">
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ HorizonteFortnightlyFeedPhaseCatalog::label($phaseKey) }}</span>
                            @if (filled($row['message'] ?? null))
                                <p class="mt-0.5 text-slate-600 dark:text-slate-400">{{ $row['message'] }}</p>
                            @endif
                            @if ($phaseKey === 'ibge_catalog' && $ibgeTotal > 0 && ($ibgeDone > 0 || $isIbgeRunning))
                                <div class="mt-2">
                                    <div class="flex justify-between text-[10px] text-slate-600 dark:text-slate-400 mb-1">
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
                            @if ($phaseKey === 'educacenso' && $educacensoTotal > 0 && ($educacensoDone > 0 || $isEducacensoRunning))
                                <div class="mt-2">
                                    <div class="flex justify-between text-[10px] text-slate-600 dark:text-slate-400 mb-1">
                                        <span>{{ __('Passos Educacenso (ano × UF)') }}</span>
                                        <span class="tabular-nums">{{ $educacensoDone }}/{{ $educacensoTotal }}</span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                        <div
                                            class="h-full rounded-full bg-teal-500 transition-all"
                                            style="width: {{ min(100, max(0, (int) round(($educacensoDone / max(1, $educacensoTotal)) * 100))) }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endif
                            @if ($phaseKey === 'saeb_planilhas' && $saebTotal > 0 && ($saebDone > 0 || $isSaebRunning))
                                <div class="mt-2">
                                    <div class="flex justify-between text-[10px] text-slate-600 dark:text-slate-400 mb-1">
                                        <span>{{ __('Anos SAEB importados') }}</span>
                                        <span class="tabular-nums">{{ $saebDone }}/{{ $saebTotal }}</span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                        <div
                                            class="h-full rounded-full bg-violet-500 transition-all"
                                            style="width: {{ min(100, max(0, (int) round(($saebDone / max(1, $saebTotal)) * 100))) }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $badge }}">{{ $statusLabel }}</span>
                    </div>
                    @if (filled($row['started_at'] ?? null) || filled($row['finished_at'] ?? null))
                        <p class="mt-1 text-[10px] text-slate-600 dark:text-slate-400 font-mono">
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
