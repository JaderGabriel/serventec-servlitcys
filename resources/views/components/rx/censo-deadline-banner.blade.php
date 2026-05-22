@props(['deadline' => []])

@php
    $d = is_array($deadline) ? $deadline : [];
    $urgency = (string) ($d['urgency'] ?? 'ok');
    $pct = min(100, max(0, (float) ($d['elapsed_pct'] ?? 0)));
    $ring = match ($urgency) {
        'past' => 'border-slate-400 bg-slate-100 dark:bg-slate-800/80',
        'danger' => 'border-rose-400 bg-rose-50 dark:border-rose-700 dark:bg-rose-950/40',
        'warning' => 'border-amber-400 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40',
        default => 'border-teal-400 bg-teal-50 dark:border-teal-700 dark:bg-teal-950/40',
    };
    $bar = match ($urgency) {
        'past' => 'bg-slate-500',
        'danger' => 'bg-rose-500',
        'warning' => 'bg-amber-500',
        default => 'bg-teal-600',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border-2 px-5 py-4 '.$ring]) }} role="region" aria-label="{{ __('Prazo do Censo Escolar') }}">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                {{ __('Censo Escolar — prazo :ano', ['ano' => (string) ($d['ano'] ?? '')]) }}
            </p>
            <p class="mt-1 text-lg font-display font-semibold text-serv-navy dark:text-white">
                {{ $d['status_label'] ?? '' }}
                <span class="text-base font-normal text-slate-600 dark:text-slate-300">
                    — {{ __('até :data', ['data' => $d['collect_end_label'] ?? '']) }}
                </span>
            </p>
            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $d['message'] ?? '' }}</p>
        </div>
        <div class="shrink-0 w-full lg:w-72">
            <div class="flex justify-between text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">
                <span>{{ __('Jan') }}</span>
                <span>{{ __('Prazo') }}</span>
            </div>
            <div class="h-4 rounded-full bg-white/80 dark:bg-slate-900/60 overflow-hidden border border-slate-200/80 dark:border-slate-600">
                <div class="{{ $bar }} h-full rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
            </div>
            @if ($urgency !== 'past')
                <p class="mt-2 text-center text-2xl font-bold tabular-nums text-serv-navy dark:text-teal-100">
                    {{ (int) ($d['days_remaining'] ?? 0) }}
                    <span class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ __('dias restantes') }}</span>
                </p>
            @else
                <p class="mt-2 text-center text-sm font-medium text-slate-600 dark:text-slate-400">
                    {{ __('Priorize regularização e validação das escolas pendentes.') }}
                </p>
            @endif
        </div>
    </div>
</div>
