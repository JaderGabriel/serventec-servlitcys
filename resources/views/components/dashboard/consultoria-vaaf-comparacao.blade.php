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
                <div class="rounded-md border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase text-emerald-800 dark:text-emerald-300">{{ $vaaf['real']['label'] ?? __('Valor municipal') }}</p>
                    <p @class(['font-semibold tabular-nums text-emerald-950 dark:text-emerald-100', $compact ? 'text-base' : 'text-lg'])>{{ $vaaf['real']['value'] ?? '—' }}</p>
                    @if (filled($vaaf['real']['hint'] ?? null))
                        <p class="text-[11px] text-emerald-900/80 dark:text-emerald-200/80">{{ $vaaf['real']['hint'] }}</p>
                    @endif
                </div>
                <div class="rounded-md border border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/20 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase text-sky-800 dark:text-sky-300">{{ $vaaf['previa']['label'] ?? __('Prévia federal') }}</p>
                    <p @class(['font-semibold tabular-nums text-sky-950 dark:text-sky-100', $compact ? 'text-base' : 'text-lg'])>{{ $vaaf['previa']['value'] ?? '—' }}</p>
                    @if (filled($vaaf['previa']['hint'] ?? null))
                        <p class="text-[11px] text-sky-900/80 dark:text-sky-200/80">{{ $vaaf['previa']['hint'] }}</p>
                    @endif
                </div>
            </div>
        @endif

        @if ($prev !== null)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-gray-200/80 dark:border-gray-700/80">
                <div class="rounded-md border border-indigo-200/80 dark:border-indigo-800/60 bg-indigo-50/40 dark:bg-indigo-950/20 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase text-indigo-800 dark:text-indigo-300">{{ $prev['real']['label'] ?? '' }}</p>
                    <p class="font-semibold tabular-nums text-indigo-950 dark:text-indigo-100">{{ $prev['real']['value'] ?? '—' }}</p>
                    @if (filled($prev['real']['hint'] ?? null))
                        <p class="text-[11px] text-indigo-900/80 dark:text-indigo-200/80">{{ $prev['real']['hint'] }}</p>
                    @endif
                </div>
                <div class="rounded-md border border-violet-200/80 dark:border-violet-800/60 bg-violet-50/40 dark:bg-violet-950/20 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase text-violet-800 dark:text-violet-300">{{ $prev['previa']['label'] ?? '' }}</p>
                    <p class="font-semibold tabular-nums text-violet-950 dark:text-violet-100">{{ $prev['previa']['value'] ?? '—' }}</p>
                    @if (filled($prev['previa']['hint'] ?? null))
                        <p class="text-[11px] text-violet-900/80 dark:text-violet-200/80">{{ $prev['previa']['hint'] }}</p>
                    @endif
                </div>
            </div>
        @endif

        @if (filled($div['mensagem'] ?? null))
            <p class="text-xs text-amber-900 dark:text-amber-100 bg-amber-50/90 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
                {{ $div['mensagem'] }}
            </p>
        @endif

        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
            {{ __('Os impactos financeiros desta aba usam o VAAF municipal quando importado; a prévia federal serve só para comparação com painéis do governo.') }}
        </p>
    </div>
@endif
