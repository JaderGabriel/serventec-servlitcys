@props([
    'metodologia' => null,
    'compact' => false,
    'defaultOpen' => false,
])

@php
    $met = is_array($metodologia) ? $metodologia : null;
    if ($met === null) {
        return;
    }
    $vaaf = is_array($met['vaaf_calculo'] ?? null) ? $met['vaaf_calculo'] : null;
    $ponderacoes = is_array($met['ponderacoes'] ?? null) ? $met['ponderacoes'] : [];
    $dist = is_array($met['distribuicao_legal'] ?? null) ? $met['distribuicao_legal'] : null;
    $portarias = is_array($met['portarias'] ?? null) ? $met['portarias'] : [];
    $fontes = is_array($met['fontes_dados'] ?? null) ? $met['fontes_dados'] : [];
@endphp

<details
    {{ $attributes->merge(['class' => 'serv-panel text-xs border border-teal-200/80 dark:border-teal-800/60']) }}
    @if ($defaultOpen) open @endif
>
    <summary class="cursor-pointer select-none px-3 py-2.5 font-semibold text-teal-950 dark:text-teal-100 bg-teal-50/60 dark:bg-teal-950/30 rounded-md">
        {{ $met['titulo'] ?? __('Regras FUNDEB e ponderações vigentes') }}
    </summary>
    <div class="px-3 pb-3 pt-2 space-y-3 text-slate-700 dark:text-slate-300 leading-relaxed">
        @if ($vaaf !== null)
            <div class="rounded-lg bg-white/80 dark:bg-gray-950/40 border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ __('VAAF usado nos cálculos') }}</p>
                <p class="mt-1 tabular-nums">
                    <span class="font-bold">{{ $vaaf['valor_label'] ?? '—' }}</span>/{{ __('aluno/ano') }}
                    · {{ $vaaf['rotulo'] ?? '' }}
                </p>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">
                    {{ __('Fonte:') }} {{ $vaaf['fonte_label'] ?? '—' }}
                    @if (filled($vaaf['ano'] ?? null))
                        · {{ __('ano :y', ['y' => $vaaf['ano']]) }}
                    @endif
                </p>
            </div>
        @endif

        @if (filled($met['formula_impacto'] ?? null))
            <p class="text-[11px]">{{ $met['formula_impacto'] }}</p>
        @endif

        @if (! $compact && ! empty($met['passos']) && is_array($met['passos']))
            <ol class="list-decimal list-inside space-y-1 text-[11px]">
                @foreach ($met['passos'] as $passo)
                    <li>{{ $passo }}</li>
                @endforeach
            </ol>
        @endif

        @if ($dist !== null && ! empty($dist['pisos']))
            <div>
                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ __('Distribuição legal (planejamento)') }}</p>
                <p class="text-[11px] mt-0.5">{{ $dist['referencia'] ?? '' }}</p>
                <ul class="mt-1 list-disc list-inside text-[11px]">
                    @foreach ($dist['pisos'] as $piso)
                        <li>{{ $piso['titulo'] ?? '' }} — {{ number_format((float) ($piso['percentual'] ?? 0), 0, ',', '.') }}%</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $compact && $ponderacoes !== [])
            <details class="serv-panel">
                <summary class="cursor-pointer px-2 py-1.5 font-medium text-slate-800 dark:text-slate-200">{{ __('Ponderações por tipo de discrepância') }}</summary>
                <ul class="px-2 pb-2 max-h-40 overflow-y-auto text-[11px] space-y-0.5">
                    @foreach (array_slice($ponderacoes, 0, 12) as $p)
                        <li class="flex justify-between gap-2">
                            <span class="truncate">{{ $p['label'] ?? $p['check_id'] ?? '' }}</span>
                            <span class="tabular-nums font-mono shrink-0">×{{ number_format((float) ($p['peso'] ?? 1), 2, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        @if ($portarias !== [])
            <div>
                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ __('Portarias e referências oficiais') }}</p>
                <ul class="mt-1 space-y-1 text-[11px]">
                    @foreach ($portarias as $p)
                        @if (filled($p['url'] ?? null))
                            <li>
                                <a href="{{ $p['url'] }}" target="_blank" rel="noopener noreferrer" class="text-teal-800 dark:text-teal-300 underline">
                                    {{ $p['label'] ?? __('Fonte') }}
                                </a>
                            </li>
                        @else
                            <li>{{ $p['label'] ?? '' }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($fontes !== [])
            <p class="text-[11px] text-slate-600 dark:text-slate-400">
                {{ __('Fontes de dados:') }}
                @foreach ($fontes as $i => $f)
                    @if ($i > 0), @endif
                    @if (filled($f['url'] ?? null))
                        <a href="{{ $f['url'] }}" class="underline" target="_blank" rel="noopener">{{ $f['label'] ?? '' }}</a>
                    @else
                        {{ $f['label'] ?? '' }}
                    @endif
                @endforeach
            </p>
        @endif

        @if (filled($met['aviso'] ?? null))
            <p class="text-[11px] italic text-amber-800/90 dark:text-amber-200/90 border-t border-slate-200 dark:border-slate-600 pt-2">{{ $met['aviso'] }}</p>
        @endif
    </div>
</details>
