@props(['discrepanciesData', 'yearFilterReady' => false, 'chartExportContext' => []])

@php
    $d = is_array($discrepanciesData) ? $discrepanciesData : [];
    $summary = is_array($d['summary'] ?? null) ? $d['summary'] : [];
    $checks = is_array($d['checks'] ?? null) ? $d['checks'] : [];
    $chartResumo = is_array($d['chart_resumo'] ?? null) ? $d['chart_resumo'] : null;
    $chartFinanceiro = is_array($d['chart_financeiro'] ?? null) ? $d['chart_financeiro'] : null;
    $fundingRef = is_array($d['funding_reference'] ?? null) ? $d['funding_reference'] : null;
    $pillars = is_array($d['funding_pillars'] ?? null) ? $d['funding_pillars'] : [];
    $fmtBrl = static fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
@endphp

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
            {{ __('Seleccione o ano letivo e aplique os filtros para executar as rotinas de discrepâncias.') }}
        </p>
    @else
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="rounded-lg border border-rose-200 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-950/20 px-4 py-3 text-sm space-y-2 flex-1">
                <h2 class="font-semibold text-rose-950 dark:text-rose-100">{{ __('Discrepâncias e Erros de cadastro') }}</h2>
                <p class="leading-relaxed text-rose-900/95 dark:text-rose-200/95">{{ $d['intro'] ?? '' }}</p>
                <p class="text-xs text-rose-800/90 dark:text-rose-300/90">
                    <span class="font-medium">{{ __('Contexto') }}:</span>
                    {{ $d['city_name'] ?? '' }}
                    @if (filled($d['year_label'] ?? null))
                        — {{ $d['year_label'] }}
                    @endif
                    @if (($d['total_matriculas'] ?? null) !== null)
                        · {{ __('Matrículas ativas no filtro:') }}
                        <span class="tabular-nums font-medium">{{ number_format((int) $d['total_matriculas']) }}</span>
                    @endif
                    @if ($fundingRef !== null && isset($fundingRef['vaa_label']))
                        · {{ __('VAAF referência:') }} <span class="font-medium">{{ $fundingRef['vaa_label'] }}</span>
                    @endif
                </p>
            </div>
            <div class="shrink-0">
                <x-dashboard.funding-loss-conditions-button />
            </div>
        </div>

        @if (filled($d['funding_aviso'] ?? null))
            <p class="text-xs text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700 bg-amber-50/70 dark:bg-amber-950/30 rounded-md px-3 py-2 leading-relaxed">
                {{ $d['funding_aviso'] }}
            </p>
        @endif

        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ $d['footnote'] ?? '' }}
        </p>

        @if (count($pillars) > 0)
            <section class="rounded-lg border border-indigo-200 dark:border-indigo-800/60 bg-indigo-50/40 dark:bg-indigo-950/25 px-4 py-3">
                <h3 class="text-sm font-semibold text-indigo-950 dark:text-indigo-100 mb-2">{{ __('Referências FUNDEB / VAAR / Censo') }}</h3>
                <ul class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-indigo-900/95 dark:text-indigo-200/90">
                    @foreach ($pillars as $pillar)
                        <li class="rounded-md border border-indigo-200/60 dark:border-indigo-700/50 bg-white/60 dark:bg-gray-900/30 px-3 py-2">
                            <p class="font-semibold">{{ $pillar['titulo'] ?? '' }}</p>
                            <p class="mt-1 leading-relaxed">{{ $pillar['descricao'] ?? '' }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($d['error']))
            <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                {{ $d['error'] }}
            </div>
        @endif

        @if (! empty($d['notes']))
            <div class="rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 px-4 py-3 text-xs text-slate-700 dark:text-slate-300 space-y-1.5">
                @foreach ($d['notes'] as $note)
                    <p>{{ $note }}</p>
                @endforeach
            </div>
        @endif

        @if (count($checks) > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="rounded-lg border border-rose-200/90 dark:border-rose-800/60 bg-white dark:bg-gray-900/40 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-800/90 dark:text-rose-200/90">{{ __('Ocorrências (soma)') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-rose-700 dark:text-rose-300">{{ number_format((int) ($summary['com_problema'] ?? 0)) }}</p>
                </div>
                <div class="rounded-lg border border-orange-200/90 dark:border-orange-800/60 bg-white dark:bg-gray-900/40 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-orange-800/90 dark:text-orange-200/90">{{ __('Perda estimada / ano') }}</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-orange-700 dark:text-orange-300">{{ $fmtBrl((float) ($summary['perda_estimada_anual'] ?? 0)) }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200/90 dark:border-emerald-800/60 bg-white dark:bg-gray-900/40 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800/90 dark:text-emerald-200/90">{{ __('Ganho potencial / ano') }}</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $fmtBrl((float) ($summary['ganho_potencial_anual'] ?? 0)) }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200/90 dark:border-emerald-800/60 bg-white dark:bg-gray-900/40 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800/90 dark:text-emerald-200/90">{{ __('Corrigíveis no i-Educar') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format((int) ($summary['corrigiveis'] ?? 0)) }}</p>
                </div>
                <div class="rounded-lg border border-indigo-200/90 dark:border-indigo-800/60 bg-white dark:bg-gray-900/40 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-800/90 dark:text-indigo-200/90">{{ __('Escolas afetadas') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-indigo-700 dark:text-indigo-300">{{ number_format((int) ($summary['escolas_afetadas'] ?? 0)) }}</p>
                </div>
            </div>

            @if ($chartFinanceiro !== null)
                <x-dashboard.chart-panel
                    :chart="$chartFinanceiro"
                    exportFilename="discrepancias-financeiro"
                    :exportMeta="$chartExportContext"
                    :compact="false"
                    chartPanelId="chart-discrepancias-financeiro"
                    panelTone="amber"
                />
            @endif

            @if ($chartResumo !== null)
                <x-dashboard.chart-panel
                    :chart="$chartResumo"
                    exportFilename="discrepancias-resumo"
                    :exportMeta="$chartExportContext"
                    :compact="false"
                    chartPanelId="chart-discrepancias-resumo"
                    panelTone="indigo"
                />
            @endif

            <div class="space-y-6">
                @foreach ($checks as $idx => $check)
                    @php
                        $ring = match ($check['status'] ?? 'neutral') {
                            'danger' => 'border-l-red-500 bg-red-50/40 dark:bg-red-950/20',
                            'warning' => 'border-l-amber-500 bg-amber-50/35 dark:bg-amber-950/20',
                            default => 'border-l-slate-400 bg-slate-50/40 dark:bg-slate-900/30',
                        };
                        $badge = match ($check['status'] ?? 'neutral') {
                            'danger' => 'bg-red-100 text-red-900 dark:bg-red-900/50 dark:text-red-100',
                            'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
                            default => 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100',
                        };
                        $vaarRefs = is_array($check['vaar_refs'] ?? null) ? $check['vaar_refs'] : [];
                    @endphp
                    <article class="rounded-lg border border-gray-200 dark:border-gray-700 border-l-4 {{ $ring }} shadow-sm overflow-hidden">
                        <header class="px-4 py-3 border-b border-gray-200/80 dark:border-gray-600/80 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $check['title'] ?? '' }}</h3>
                                <p class="mt-1 text-sm tabular-nums text-gray-700 dark:text-gray-300">
                                    {{ __('Total: :n', ['n' => number_format((int) ($check['total'] ?? 0))]) }}
                                    @if (($check['pct_rede'] ?? null) !== null)
                                        <span class="text-gray-500 dark:text-gray-400">({{ number_format((float) $check['pct_rede'], 1, ',', '.') }}% {{ __('da rede') }})</span>
                                    @endif
                                </p>
                                <p class="mt-1 text-sm font-medium text-orange-700 dark:text-orange-300 tabular-nums">
                                    {{ __('Ganho potencial indicativo:') }} {{ $fmtBrl((float) ($check['ganho_potencial_anual'] ?? 0)) }}
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium {{ $badge }} shrink-0">
                                {{ match ($check['severity'] ?? '') {
                                    'danger' => __('Alta prioridade'),
                                    'warning' => __('Média prioridade'),
                                    default => __('Verificar'),
                                } }}
                            </span>
                        </header>
                        <div class="px-4 py-3 space-y-4 text-sm text-gray-700 dark:text-gray-300">
                            @if (count($vaarRefs) > 0)
                                <p class="text-xs text-indigo-800 dark:text-indigo-200">
                                    <span class="font-semibold">{{ __('Eixos:') }}</span>
                                    {{ implode(' · ', $vaarRefs) }}
                                </p>
                            @endif
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('O que é') }}</p>
                                <p class="leading-relaxed">{{ $check['explanation'] ?? '' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300 mb-1">{{ __('Impacto financeiro / Censo') }}</p>
                                <p class="leading-relaxed">{{ $check['impact'] ?? '' }}</p>
                                @if (filled($check['funding_formula'] ?? null))
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 italic">{{ $check['funding_formula'] }}</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300 mb-1">{{ __('Correção possível') }}</p>
                                <p class="leading-relaxed">{{ $check['correction'] ?? '' }}</p>
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                                @if (! empty($check['chart_financeiro']))
                                    <x-dashboard.chart-panel
                                        :chart="$check['chart_financeiro']"
                                        :exportFilename="'discrepancia-fin-'.($check['id'] ?? $idx)"
                                        :exportMeta="$chartExportContext"
                                        :compact="true"
                                        :chartPanelId="'chart-discrep-fin-'.$idx"
                                        panelTone="amber"
                                    />
                                @endif
                                @if (! empty($check['chart_rede']))
                                    <x-dashboard.chart-panel
                                        :chart="$check['chart_rede']"
                                        :exportFilename="'discrepancia-rede-'.($check['id'] ?? $idx)"
                                        :exportMeta="$chartExportContext"
                                        :compact="true"
                                        :chartPanelId="'chart-discrep-rede-'.$idx"
                                        panelTone="indigo"
                                    />
                                @endif
                                @if (! empty($check['chart_escolas']))
                                    <x-dashboard.chart-panel
                                        :chart="$check['chart_escolas']"
                                        :exportFilename="'discrepancia-escolas-'.($check['id'] ?? $idx)"
                                        :exportMeta="$chartExportContext"
                                        :compact="true"
                                        :chartPanelId="'chart-discrep-esc-'.$idx"
                                        panelTone="indigo"
                                    />
                                @endif
                            </div>

                            @if (! empty($check['school_rows']) && is_array($check['school_rows']))
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ __('Onde ocorre (escola)') }}</p>
                                    <div class="overflow-x-auto max-h-72 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                        <table class="min-w-full text-xs text-left">
                                            <thead class="bg-gray-50 dark:bg-gray-900/60 sticky top-0">
                                                <tr>
                                                    <th class="px-3 py-2 font-medium">{{ __('Unidade escolar') }}</th>
                                                    <th class="px-3 py-2 font-medium text-right">{{ __('Ocorrências') }}</th>
                                                    <th class="px-3 py-2 font-medium text-right">{{ __('Ganho pot. indicativo') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                @foreach ($check['school_rows'] as $row)
                                                    <tr>
                                                        <td class="px-3 py-1.5 break-words max-w-[18rem]">{{ $row['escola'] ?? '—' }}</td>
                                                        <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ number_format((int) ($row['total'] ?? 0)) }}</td>
                                                        <td class="px-3 py-1.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $fmtBrl((float) ($row['ganho_potencial_anual'] ?? 0)) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @elseif (empty($d['error']))
            <p class="text-sm text-gray-600 dark:text-gray-400 rounded-md border border-dashed border-gray-300 dark:border-gray-600 px-4 py-6 text-center">
                {{ __('Nenhuma discrepância detectada com as rotinas disponíveis para estes filtros — ou a base não expõe as tabelas necessárias.') }}
            </p>
        @endif
    @endif
</div>
