@props(['card' => []])

@php
    $c = is_array($card) ? $card : [];
    $linhas = is_array($c['linhas'] ?? null) ? $c['linhas'] : [];
    $totais = is_array($c['totais'] ?? null) ? $c['totais'] : [];
    $eja = is_array($c['eja'] ?? null) ? $c['eja'] : [];
    $prioridades = is_array($c['prioridades_acao'] ?? null) ? $c['prioridades_acao'] : [];
    $legenda = is_array($c['legenda'] ?? null) ? $c['legenda'] : [];
    $prioridadeBadge = static fn (?string $p): string => match ($p) {
        'alta' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
        'media' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100',
        'baixa' => 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-100',
        default => '',
    };
@endphp

@if ($c['available'] ?? false)
    <div class="space-y-4">
        @if (count($prioridades) > 0)
            <div class="serv-callout serv-callout--warning text-sm space-y-1">
                <p class="font-semibold text-amber-900 dark:text-amber-100">{{ __('Prioridades para decisão') }}</p>
                <ul class="list-disc ps-5 space-y-1">
                    @foreach ($prioridades as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 text-sm">
            <div class="serv-panel p-3 border-l-4 border-l-indigo-500">
                <p class="text-xs text-slate-500 uppercase">{{ __('CadÚnico 4–17') }}</p>
                <p class="text-lg font-semibold tabular-nums">{{ $totais['cadunico_fmt'] ?? '—' }}</p>
            </div>
            <div class="serv-panel p-3 border-l-4 border-l-blue-500">
                <p class="text-xs text-slate-500 uppercase">{{ __('Na rede municipal') }}</p>
                <p class="text-lg font-semibold tabular-nums">{{ $totais['na_rede_municipal_fmt'] ?? '—' }}</p>
                <p class="text-xs text-slate-500">{{ $totais['cobertura_rede_label'] ?? '' }}</p>
            </div>
            <div class="serv-panel p-3 border-l-4 border-l-violet-500">
                <p class="text-xs text-slate-500 uppercase">{{ __('No município (Censo)') }}</p>
                <p class="text-lg font-semibold tabular-nums">{{ $totais['no_municipio_censo_fmt'] ?? '—' }}</p>
            </div>
            <div class="serv-panel p-3 border-l-4 border-l-amber-500">
                <p class="text-xs text-slate-500 uppercase">{{ __('Fora da rede municipal') }}</p>
                <p class="text-lg font-semibold tabular-nums text-amber-700 dark:text-amber-300">{{ $totais['fora_rede_municipal_fmt'] ?? '—' }}</p>
            </div>
            <div class="serv-panel p-3 border-l-4 border-l-rose-500">
                <p class="text-xs text-slate-500 uppercase">{{ __('Possível fora da escola') }}</p>
                <p class="text-lg font-semibold tabular-nums">{{ $totais['possivel_fora_escola_fmt'] ?? '—' }}</p>
            </div>
        </div>

        <div class="serv-panel overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/60">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold">{{ __('Faixa etária') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold" title="{{ $legenda['cadunico'] ?? '' }}">{{ __('CadÚnico') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold" title="{{ $legenda['na_rede'] ?? '' }}">{{ __('Na rede') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold" title="{{ $legenda['censo'] ?? '' }}">{{ __('Censo munic.') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold" title="{{ $legenda['fora_rede'] ?? '' }}">{{ __('Fora rede') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold" title="{{ $legenda['fora_escola'] ?? '' }}">{{ __('Fora escola') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold">{{ __('Leitura') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($linhas as $linha)
                        <tr>
                            <td class="px-3 py-2 font-medium">
                                {{ $linha['faixa'] ?? '' }}
                                @if ($linha['prioridade'] ?? null)
                                    <span class="ms-1 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $prioridadeBadge($linha['prioridade']) }}">
                                        {{ $linha['prioridade'] }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $linha['cadunico_fmt'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ $linha['na_rede_municipal_fmt'] ?? '—' }}
                                @if ($linha['ieducar_por_idade'] ?? false)
                                    <span class="block text-[10px] text-emerald-600 dark:text-emerald-400">{{ __('idade 31/03') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $linha['no_municipio_censo_fmt'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold text-amber-700 dark:text-amber-300">{{ $linha['fora_rede_municipal_fmt'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $linha['possivel_fora_escola_fmt'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600 dark:text-slate-400 max-w-md">{{ $linha['decisao'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($eja['available'] ?? false)
            <div class="serv-panel p-4 border border-violet-200 dark:border-violet-800/60 bg-violet-50/50 dark:bg-violet-950/20">
                <h4 class="text-sm font-semibold text-violet-900 dark:text-violet-100 mb-2">{{ __('EJA — Educação de Jovens e Adultos') }}</h4>
                <p class="text-xs text-slate-600 dark:text-slate-400 mb-3">{{ $legenda['eja'] ?? '' }}</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm mb-3">
                    <div>
                        <p class="text-xs text-slate-500 uppercase">{{ __('Rede municipal (i-Educar)') }}</p>
                        <p class="font-semibold tabular-nums">{{ $eja['ieducar_municipal_fmt'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase">{{ __('Censo — total') }}</p>
                        <p class="font-semibold tabular-nums">{{ $eja['censo_total_fmt'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase">{{ __('Censo — municipal') }}</p>
                        <p class="font-semibold tabular-nums">{{ $eja['censo_municipal_fmt'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase">{{ __('Censo — não municipal') }}</p>
                        <p class="font-semibold tabular-nums">{{ $eja['censo_nao_municipal_fmt'] ?? '—' }}</p>
                    </div>
                </div>
                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $eja['decisao'] ?? '' }}</p>
            </div>
        @endif

        <details class="text-xs text-slate-600 dark:text-slate-400">
            <summary class="cursor-pointer font-medium">{{ __('Como ler este painel') }}</summary>
            <ul class="mt-2 list-disc ps-5 space-y-1">
                <li>{{ $legenda['cadunico'] ?? '' }}</li>
                <li>{{ $legenda['na_rede'] ?? '' }}</li>
                <li>{{ $legenda['censo'] ?? '' }}</li>
                <li>{{ $legenda['fora_rede'] ?? '' }}</li>
                <li>{{ $legenda['fora_escola'] ?? '' }}</li>
                @if ($c['censo_ajuste_aplicado'] ?? false)
                    <li>{{ __('Lacuna global já desconta matrículas em redes não municipais quando o Censo INEP está indexado.') }}</li>
                @endif
            </ul>
        </details>
    </div>
@elseif (filled($c['message'] ?? null))
    <p class="serv-callout text-sm text-slate-700 dark:text-slate-300">{{ $c['message'] }}</p>
@endif
