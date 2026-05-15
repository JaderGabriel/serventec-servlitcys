@props([
    'explicacao' => null,
    'metodologia' => null,
    'resumo' => null,
    'compact' => false,
    'defaultOpen' => false,
])

@php
    $exp = is_array($explicacao) ? $explicacao : null;
    $met = is_array($metodologia) ? $metodologia : null;
    $res = is_array($resumo) ? $resumo : null;
    $hasContent = $exp !== null || $met !== null || $res !== null;
    $uid = 'fund-exp-'.substr(md5(json_encode([$exp, $met, $res])), 0, 8);
@endphp

@if ($hasContent)
    <details
        {{ $attributes->merge(['class' => 'rounded-md border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-900/40 text-xs']) }}
        @if ($defaultOpen) open @endif
    >
        <summary class="cursor-pointer select-none px-3 py-2 font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100/80 dark:hover:bg-slate-800/50 rounded-md">
            {{ $exp['formula_curta'] ?? ($met['titulo'] ?? __('Como se calcula este valor')) }}
        </summary>
        <div class="px-3 pb-3 pt-1 space-y-2.5 text-slate-600 dark:text-slate-300 leading-relaxed border-t border-slate-200/80 dark:border-slate-600/80">
            @if ($exp !== null)
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ __('Fórmula desta rotina') }}</p>
                <p class="font-mono text-[11px] bg-white dark:bg-gray-950/50 rounded px-2 py-1.5 border border-slate-200 dark:border-slate-700">
                    {{ $exp['formula_expandida'] ?? ($exp['formula_curta'] ?? '') }}
                </p>
                @if (filled($exp['perda_texto'] ?? null))
                    <p><span class="font-semibold text-orange-800 dark:text-orange-300">{{ __('Perda estimada:') }}</span> {{ $exp['perda_texto'] }}</p>
                @endif
                @if (filled($exp['ganho_texto'] ?? null))
                    <p><span class="font-semibold text-emerald-800 dark:text-emerald-300">{{ __('Ganho potencial:') }}</span> {{ $exp['ganho_texto'] }}</p>
                @endif
                @if (! empty($exp['passos']) && is_array($exp['passos']) && ! $compact)
                    <ul class="list-disc list-inside space-y-1 text-[11px]">
                        @foreach ($exp['passos'] as $passo)
                            <li>{{ $passo }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif

            @if ($res !== null)
                <p class="font-medium text-slate-800 dark:text-slate-100 pt-1">{{ $res['titulo'] ?? '' }}</p>
                @if (filled($res['detalhe'] ?? null))
                    <p>{{ $res['detalhe'] }}</p>
                @endif
                @if (! empty($res['passos']) && is_array($res['passos']) && ! $compact)
                    <ul class="list-disc list-inside space-y-1 text-[11px]">
                        @foreach ($res['passos'] as $passo)
                            <li>{{ $passo }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif

            @if ($met !== null && ! $compact)
                <p class="font-medium text-slate-800 dark:text-slate-100 pt-1">{{ $met['titulo'] ?? '' }}</p>
                @if (! empty($met['passos']) && is_array($met['passos']))
                    <ol class="list-decimal list-inside space-y-1 text-[11px]">
                        @foreach ($met['passos'] as $passo)
                            <li>{{ $passo }}</li>
                        @endforeach
                    </ol>
                @endif
                @if (filled($met['aviso'] ?? null))
                    <p class="text-[11px] italic text-amber-800/90 dark:text-amber-200/90 border-t border-slate-200 dark:border-slate-600 pt-2">{{ $met['aviso'] }}</p>
                @endif
            @endif
        </div>
    </details>
@endif
