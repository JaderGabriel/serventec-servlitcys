@props([
    'status' => 'neutral',
    'label' => '',
    'score' => null,
    'help' => '',
    'issues' => [],
    'mode' => 'tab',
])

@php
    $statusRing = match ((string) $status) {
        'success' => 'stroke-emerald-500 text-emerald-700 dark:text-emerald-300',
        'warning' => 'stroke-amber-500 text-amber-700 dark:text-amber-300',
        'danger' => 'stroke-rose-500 text-rose-700 dark:text-rose-300',
        default => 'stroke-slate-400 text-slate-600 dark:text-slate-400',
    };
    $statusBg = match ((string) $status) {
        'success' => 'bg-emerald-50/90 dark:bg-emerald-950/35 border-emerald-200/80 dark:border-emerald-800/60',
        'warning' => 'bg-amber-50/90 dark:bg-amber-950/30 border-amber-200/80 dark:border-amber-800/60',
        'danger' => 'bg-rose-50/90 dark:bg-rose-950/30 border-rose-200/80 dark:border-rose-800/60',
        default => 'bg-slate-50/80 dark:bg-slate-900/50 border-slate-200/80 dark:border-slate-700/60',
    };
    $pct = $score !== null ? max(0, min(100, (int) $score)) : null;
    $circ = $pct !== null ? round(2 * 3.14159 * 16 * (1 - $pct / 100), 1) : null;
    $modeLabel = $mode === 'system'
        ? __('Consolidado do sistema')
        : __('Nesta aba (filtro)');
@endphp

<div {{ $attributes->merge(['class' => 'serv-tab-status-inline shrink-0 max-w-[min(100%,20rem)]']) }}>
    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 text-right mb-1.5">
        {{ __('Status') }}
        <span class="font-normal normal-case tracking-normal text-slate-400 dark:text-slate-500">· {{ $modeLabel }}</span>
    </p>
    <div class="flex items-center gap-2.5 justify-end">
        @if ($pct !== null && $circ !== null)
            <div class="relative shrink-0 w-11 h-11" aria-hidden="true">
                <svg class="w-11 h-11 -rotate-90" viewBox="0 0 40 40">
                    <circle cx="20" cy="20" r="16" fill="none" stroke-width="3.5" class="stroke-slate-200 dark:stroke-slate-700"/>
                    <circle cx="20" cy="20" r="16" fill="none" stroke-width="3.5" stroke-linecap="round"
                        class="{{ $statusRing }}"
                        stroke-dasharray="100" stroke-dashoffset="{{ $circ }}"/>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold tabular-nums">{{ $pct }}</span>
            </div>
        @endif
        <div class="rounded-lg border px-2.5 py-1.5 min-w-0 {{ $statusBg }}">
            <p class="text-xs font-medium leading-snug text-slate-800 dark:text-slate-100 line-clamp-3">{{ $label }}</p>
        </div>
        @if (filled($help) || count($issues) > 0)
            <details class="serv-tab-status-help relative">
                <summary class="serv-tab-status-help__btn" title="{{ __('Texto explicativo') }}">
                    <span class="sr-only">{{ __('Abrir explicação do status') }}</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.75.388-1.25 1.01-1.25 1.757V13M12 17h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </summary>
                <div class="serv-tab-status-help__panel">
                    @if (filled($help))
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ $help }}</p>
                    @endif
                    @if (count($issues) > 0)
                        <ul class="mt-2 space-y-1 text-xs">
                            @foreach ($issues as $issue)
                                @php
                                    $tone = (string) ($issue['type'] ?? 'pending');
                                    $dot = match ($tone) {
                                        'error' => 'bg-rose-500',
                                        'unavailable' => 'bg-slate-400',
                                        default => 'bg-amber-500',
                                    };
                                @endphp
                                <li class="flex items-start gap-2">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $dot }}"></span>
                                    <span>
                                        <span class="font-medium">{{ $issue['label'] ?? '' }}</span>
                                        @if (($issue['count'] ?? 0) > 0)
                                            <span class="text-slate-500 dark:text-slate-400"> — {{ number_format((int) $issue['count'], 0, ',', '.') }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </details>
        @endif
    </div>
</div>
