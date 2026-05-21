@props([
    'comparacao' => null,
    'divergencia' => null,
    'previsaoComparacao' => null,
    'compact' => false,
])

@php
    $vaaf = is_array($comparacao) ? $comparacao : null;
    $prev = is_array($previsaoComparacao) ? $previsaoComparacao : null;
    $div = is_array($divergencia) ? $divergencia : null;
@endphp

@if ($vaaf !== null || $prev !== null)
    <div @class([
        'space-y-3',
        $compact ? 'text-xs' : 'text-sm',
    ])>
        @if ($vaaf !== null)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="serv-panel border-emerald-200/90 dark:border-emerald-800/60 bg-emerald-50/40 dark:bg-emerald-950/20 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase text-emerald-800 dark:text-emerald-300">{{ $vaaf['real']['label'] ?? __('Valor municipal') }}</p>
                    <p @class(['font-semibold tabular-nums text-emerald-950 dark:text-emerald-100', $compact ? 'text-base' : 'text-lg'])>{{ $vaaf['real']['value'] ?? '—' }}</p>
                    @if (filled($vaaf['real']['hint'] ?? null))
                        <p class="text-[11px] text-emerald-900/80 dark:text-emerald-200/80">{{ $vaaf['real']['hint'] }}</p>
                    @endif
                </div>
                <div class="serv-panel border-sky-200/90 dark:border-sky-800/60 bg-sky-50/40 dark:bg-sky-950/20 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase text-sky-800 dark:text-sky-300">{{ $vaaf['previa']['label'] ?? __('Prévia federal') }}</p>
                    <p @class(['font-semibold tabular-nums text-sky-950 dark:text-sky-100', $compact ? 'text-base' : 'text-lg'])>{{ $vaaf['previa']['value'] ?? '—' }}</p>
                    @if (filled($vaaf['previa']['hint'] ?? null))
                        <p class="text-[11px] text-sky-900/80 dark:text-sky-200/80">{{ $vaaf['previa']['hint'] }}</p>
                    @endif
                </div>
            </div>
        @endif

        @if ($prev !== null)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-slate-200/80 dark:border-slate-700/80">
                <div class="serv-panel border-teal-200/90 dark:border-teal-800/60 bg-teal-50/40 dark:bg-teal-950/20 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase text-teal-800 dark:text-teal-300">{{ $prev['real']['label'] ?? '' }}</p>
                    <p class="font-semibold tabular-nums text-teal-950 dark:text-teal-100">{{ $prev['real']['value'] ?? '—' }}</p>
                    @if (filled($prev['real']['hint'] ?? null))
                        <p class="text-[11px] text-teal-900/80 dark:text-teal-200/80">{{ $prev['real']['hint'] }}</p>
                    @endif
                </div>
                <div class="serv-panel border-slate-200/90 dark:border-slate-600/80 bg-slate-50/50 dark:bg-slate-900/40 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase text-slate-700 dark:text-slate-300">{{ $prev['previa']['label'] ?? '' }}</p>
                    <p class="font-semibold tabular-nums text-serv-navy dark:text-slate-100">{{ $prev['previa']['value'] ?? '—' }}</p>
                    @if (filled($prev['previa']['hint'] ?? null))
                        <p class="text-[11px] text-slate-600 dark:text-slate-400">{{ $prev['previa']['hint'] }}</p>
                    @endif
                </div>
            </div>
        @endif

        @if (filled($div['mensagem'] ?? null))
            <p class="serv-callout serv-callout--warning">
                {{ $div['mensagem'] }}
            </p>
        @endif

        <p class="serv-callout text-[11px]">
            {{ __('Os impactos financeiros desta aba usam o VAAF municipal quando importado; a prévia federal serve só para comparação com painéis do governo.') }}
        </p>
    </div>
@endif
