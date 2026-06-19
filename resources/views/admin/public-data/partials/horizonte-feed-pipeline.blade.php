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
                    {{ __('Próximas fases pelo agendador a cada :n min (horizonte:fortnightly-feed --staged --continue).', ['n' => (string) $stepInterval]) }}
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
                    $badge = match ($phaseStatus) {
                        'completed' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200',
                        'skipped' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                        'failed' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
                        'running' => 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200',
                        default => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                    };
                @endphp
                <li class="rounded-lg border border-indigo-100/80 dark:border-indigo-900/40 bg-white/70 dark:bg-slate-900/40 px-3 py-2 text-xs">
                    <div class="flex flex-wrap items-start gap-2 justify-between">
                        <div class="min-w-0">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ HorizonteFortnightlyFeedPhaseCatalog::label((string) ($row['key'] ?? '')) }}</span>
                            @if (filled($row['message'] ?? null))
                                <p class="mt-0.5 text-slate-600 dark:text-slate-400">{{ $row['message'] }}</p>
                            @endif
                        </div>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $badge }}">{{ $phaseStatus }}</span>
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
