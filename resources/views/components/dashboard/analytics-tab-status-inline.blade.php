@props([
    'status' => 'neutral',
    'label' => '',
    'score' => null,
    'help' => '',
    'issues' => [],
    'mode' => 'tab',
    'tab' => '',
])

@php
    $statusRing = match ((string) $status) {
        'success' => 'stroke-emerald-500 text-emerald-800 dark:text-emerald-200',
        'warning' => 'stroke-amber-500 text-amber-800 dark:text-amber-200',
        'danger' => 'stroke-rose-500 text-rose-800 dark:text-rose-200',
        default => 'stroke-slate-400 text-slate-700 dark:text-slate-300',
    };
    $statusAccent = match ((string) $status) {
        'success' => 'serv-tab-status-panel--success',
        'warning' => 'serv-tab-status-panel--warning',
        'danger' => 'serv-tab-status-panel--danger',
        default => 'serv-tab-status-panel--neutral',
    };
    $pct = $score !== null ? max(0, min(100, (int) $score)) : null;
    $circ = $pct !== null ? round(2 * 3.14159 * 22 * (1 - $pct / 100), 1) : null;
    $modeLabel = $mode === 'system'
        ? __('Consolidado do sistema')
        : __('Nesta aba (filtro)');
    $hasHelp = filled($help) || count($issues) > 0;
    $modalId = 'tab-status-help-'.preg_replace('/[^a-z0-9_-]/i', '-', (string) $tab) ?: 'default';
@endphp

<div
    {{ $attributes->merge(['class' => 'serv-tab-status-panel '.$statusAccent]) }}
    x-data="{ helpOpen: false }"
    x-effect="document.body.classList.toggle('overflow-y-hidden', helpOpen)"
    @keydown.escape.window="helpOpen = false"
>
    <div class="serv-tab-status-panel__inner">
        <div class="serv-tab-status-panel__main">
            @if ($pct !== null && $circ !== null)
                <div class="serv-tab-status-panel__ring" aria-hidden="true">
                    <svg class="w-[4.5rem] h-[4.5rem] -rotate-90" viewBox="0 0 52 52">
                        <circle cx="26" cy="26" r="22" fill="none" stroke-width="4" class="stroke-slate-200/90 dark:stroke-slate-700"/>
                        <circle cx="26" cy="26" r="22" fill="none" stroke-width="4" stroke-linecap="round"
                            class="{{ $statusRing }}"
                            stroke-dasharray="138" stroke-dashoffset="{{ $circ }}"/>
                    </svg>
                    <span class="serv-tab-status-panel__score tabular-nums">{{ $pct }}</span>
                </div>
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {{ __('Status') }}
                    </p>
                    <span class="serv-tab-status-panel__mode">{{ $modeLabel }}</span>
                </div>
                <p class="mt-1 text-sm sm:text-base font-semibold leading-snug text-slate-900 dark:text-slate-50">
                    {{ $label }}
                </p>
            </div>
        </div>

        @if ($hasHelp)
            <button
                type="button"
                class="serv-tab-status-help__btn shrink-0"
                title="{{ __('Como interpretar este status') }}"
                aria-haspopup="dialog"
                :aria-expanded="helpOpen"
                @click="helpOpen = true"
            >
                <span class="sr-only">{{ __('Abrir explicação do status') }}</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.75.388-1.25 1.01-1.25 1.757V13M12 17h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </button>
        @endif
    </div>

    @if ($hasHelp)
        <div
            x-show="helpOpen"
            x-cloak
            class="serv-tab-status-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $modalId }}-title"
            @click.self="helpOpen = false"
        >
            <div class="serv-tab-status-modal__backdrop" aria-hidden="true"></div>
            <div class="serv-tab-status-modal__dialog">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200/90 dark:border-slate-700 px-5 py-4 shrink-0">
                    <div class="min-w-0">
                        <h2 id="{{ $modalId }}-title" class="text-base font-semibold text-slate-900 dark:text-slate-100">
                            {{ __('Explicação do status') }}
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $modeLabel }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"
                        @click="helpOpen = false"
                        aria-label="{{ __('Fechar') }}"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="overflow-y-auto px-5 py-4 space-y-4 text-sm max-h-[min(70vh,28rem)]">
                    @if (filled($help))
                        <p class="text-slate-700 dark:text-slate-300 leading-relaxed">{{ $help }}</p>
                    @endif
                    @if (count($issues) > 0)
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
                                {{ __('Pendências e alertas') }}
                            </p>
                            <ul class="space-y-2">
                                @foreach ($issues as $issue)
                                    @php
                                        $tone = (string) ($issue['type'] ?? 'pending');
                                        $dot = match ($tone) {
                                            'error' => 'bg-rose-500',
                                            'unavailable' => 'bg-slate-400',
                                            default => 'bg-amber-500',
                                        };
                                        $rowBg = match ($tone) {
                                            'error' => 'bg-rose-50/80 dark:bg-rose-950/25 border-rose-200/80 dark:border-rose-900/50',
                                            'unavailable' => 'bg-slate-50/80 dark:bg-slate-900/40 border-slate-200/80 dark:border-slate-700',
                                            default => 'bg-amber-50/80 dark:bg-amber-950/25 border-amber-200/80 dark:border-amber-900/50',
                                        };
                                    @endphp
                                    <li class="flex items-start gap-2.5 rounded-lg border px-3 py-2 {{ $rowBg }}">
                                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>
                                        <span class="text-slate-800 dark:text-slate-200">
                                            <span class="font-medium">{{ $issue['label'] ?? '' }}</span>
                                            @if (($issue['count'] ?? 0) > 0)
                                                <span class="text-slate-600 dark:text-slate-400"> — {{ number_format((int) $issue['count'], 0, ',', '.') }}</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="border-t border-slate-200/90 dark:border-slate-700 px-5 py-3 shrink-0 flex justify-end">
                    <button type="button" class="serv-btn-secondary text-sm" @click="helpOpen = false">{{ __('Fechar') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
