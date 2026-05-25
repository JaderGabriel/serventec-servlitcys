@props([
    'panel',
    'chartExportContext' => [],
    'neeDetalheCatalogo' => null,
    'neeExtraCharts' => [],
    'calcNote' => null,
])

@php
    $legend = is_array($panel['legend'] ?? null) ? $panel['legend'] : [];
    $kpis = is_array($panel['kpis'] ?? null) ? $panel['kpis'] : [];
    $gauges = is_array($panel['gauges'] ?? null) ? $panel['gauges'] : [];
    $catalogChart = is_array($panel['catalog_chart'] ?? null) ? $panel['catalog_chart'] : null;
    $catalogWarning = filled($panel['catalog_warning'] ?? null) ? (string) $panel['catalog_warning'] : null;
    $toneRing = static fn (string $tone): string => match ($tone) {
        'teal' => 'border-teal-200/90 dark:border-teal-800/60',
        'amber' => 'border-amber-200/90 dark:border-amber-800/60',
        default => 'border-violet-200/90 dark:border-violet-800/60',
    };
    $legendDot = static fn (string $color): string => match ($color) {
        'indigo' => 'bg-indigo-600',
        'violet' => 'bg-violet-600',
        'amber' => 'bg-amber-500',
        default => 'bg-slate-500',
    };
@endphp

<x-dashboard.consultoria-section
    anchor="inclusao-indicadores-nee"
    :title="$panel['title'] ?? __('Indicadores NEE')"
    :subtitle="$panel['subtitle'] ?? null"
>
    @if ($catalogWarning !== null)
        <div class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-3 py-2 text-xs text-amber-950 dark:text-amber-100 leading-relaxed" role="status">
            {{ $catalogWarning }}
        </div>
    @endif

    @if (count($legend) > 0)
        <div class="flex flex-wrap gap-2 text-[11px]">
            @foreach ($legend as $item)
                <span class="inline-flex items-start gap-1.5 rounded-md border border-slate-200/90 dark:border-slate-700 bg-white/80 dark:bg-slate-900/50 px-2.5 py-1.5 max-w-xs">
                    <span class="mt-0.5 h-2 w-2 shrink-0 rounded-sm {{ $legendDot((string) ($item['color'] ?? '')) }}" aria-hidden="true"></span>
                    <span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $item['label'] ?? '' }}</span>
                        @if (filled($item['hint'] ?? null))
                            <span class="block text-gray-600 dark:text-gray-400 font-normal leading-snug">{{ $item['hint'] }}</span>
                        @endif
                    </span>
                </span>
            @endforeach
        </div>
    @endif

    @if (count($kpis) > 0)
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-2 text-sm">
            @foreach ($kpis as $kpi)
                <div class="serv-panel {{ $toneRing((string) ($kpi['tone'] ?? 'violet')) }} px-3 py-2.5">
                    <dt class="text-[11px] font-medium text-slate-600 dark:text-slate-400 leading-snug">{{ $kpi['label'] ?? '' }}</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-serv-navy dark:text-slate-100">{{ $kpi['value'] ?? '—' }}</dd>
                    @if (filled($kpi['hint'] ?? null))
                        <dd class="mt-1 text-[11px] text-slate-600 dark:text-slate-400 leading-snug">{{ $kpi['hint'] }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
    @endif

    @if (count($gauges) > 0)
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">{{ __('Medidores (educação especial)') }}</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($gauges as $idx => $gauge)
                    @php
                        $pctRede = (float) ($gauge['percent_rede'] ?? $gauge['chart']['gauge_dual']['percent_rede'] ?? 0);
                        $pctNee = (float) ($gauge['percent_nee'] ?? $gauge['chart']['gauge_dual']['percent_nee'] ?? 0);
                    @endphp
                    <div class="space-y-2 rounded-lg border border-slate-200/80 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 px-3 py-3">
                        <x-dashboard.chart-panel
                            :chart="$gauge['chart']"
                            :exportFilename="'inclusao-indicador-nee-'.$idx"
                            :exportMeta="$chartExportContext"
                            :compact="true"
                        />
                        <dl class="grid grid-cols-2 gap-2 text-center text-xs">
                            <div class="rounded-md bg-white/80 dark:bg-slate-800/60 px-2 py-1.5 border border-slate-200/80 dark:border-slate-600">
                                <dt class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Rede') }}</dt>
                                <dd class="mt-0.5 text-base font-semibold tabular-nums text-serv-navy dark:text-slate-100">{{ number_format($pctRede, 1, ',', '.') }}%</dd>
                            </div>
                            <div class="rounded-md bg-violet-50/90 dark:bg-violet-950/40 px-2 py-1.5 border border-violet-200/80 dark:border-violet-800/60">
                                <dt class="text-[10px] uppercase tracking-wide text-violet-700 dark:text-violet-300">{{ __('Universo NEE') }}</dt>
                                <dd class="mt-0.5 text-base font-semibold tabular-nums text-violet-900 dark:text-violet-100">{{ number_format($pctNee, 1, ',', '.') }}%</dd>
                            </div>
                        </dl>
                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-snug">{{ $gauge['caption'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($catalogChart !== null && ! empty($catalogChart['labels'] ?? null))
        <div class="space-y-2 min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Catálogo completo (MEC / i-Educar)') }}</p>
            <div class="min-w-0 w-full [&_.chart-panel-host]:min-h-[min(36rem,75vh)]">
                <x-dashboard.chart-panel
                    :chart="$catalogChart"
                    :exportFilename="'inclusao-nee-catalogo'"
                    :exportMeta="$chartExportContext"
                    :compact="false"
                    :suppressTitle="true"
                />
            </div>
        </div>
    @elseif (is_array($neeDetalheCatalogo) && (
        ! empty($neeDetalheCatalogo['deficiencias'])
        || ! empty($neeDetalheCatalogo['sindromes_tea'])
        || ! empty($neeDetalheCatalogo['ne_altas_habilidades'])
    ))
        @php $tot = $neeDetalheCatalogo['totais_por_secao'] ?? []; @endphp
        <div class="rounded-lg border border-violet-100 dark:border-violet-900/40 bg-violet-50/40 dark:bg-violet-950/20 px-4 py-4 space-y-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Detalhe por designação') }}</p>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 min-w-0 items-stretch">
                @foreach ([
                    'deficiencias' => __('Deficiências'),
                    'sindromes_tea' => __('Síndromes / TEA'),
                    'ne_altas_habilidades' => __('NE (altas habilidades)'),
                ] as $key => $sectionTitle)
                    <div class="rounded-md border border-white/80 dark:border-gray-700 bg-white/90 dark:bg-gray-800/70 min-h-[12rem] flex flex-col shadow-sm">
                        <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $sectionTitle }}</span>
                            <span class="tabular-nums text-xs font-medium text-violet-700 dark:text-violet-300">{{ number_format((int) ($tot[$key] ?? 0)) }}</span>
                        </div>
                        <ul class="flex-1 max-h-[min(28rem,50vh)] overflow-y-auto text-sm divide-y divide-gray-100 dark:divide-gray-700/80 [scrollbar-gutter:stable]">
                            @foreach ($neeDetalheCatalogo[$key] ?? [] as $row)
                                @if ((int) ($row['total'] ?? 0) > 0)
                                    <li class="px-3 py-1.5 flex justify-between gap-2">
                                        <span class="text-gray-800 dark:text-gray-200 break-words">{{ $row['nome'] ?? '—' }}</span>
                                        <span class="tabular-nums shrink-0">{{ number_format((int) ($row['total'] ?? 0)) }}</span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <p class="text-xs text-violet-900/90 dark:text-violet-200/90 rounded-md border border-dashed border-violet-200 dark:border-violet-800 px-3 py-2 leading-relaxed">
            {{ __('O catálogo completo de designações não foi gerado (sem entradas MEC/i-Educar na base ou erro na consulta).') }}
        </p>
    @endif

    @if (count($neeExtraCharts) > 0)
        <div class="space-y-3 pt-2 border-t border-slate-200/80 dark:border-slate-700">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Complementos NEE no recorte') }}</p>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 min-w-0 items-start">
                @foreach ($neeExtraCharts as $idx => $chart)
                    <x-dashboard.chart-panel
                        :chart="$chart"
                        :exportFilename="'inclusao-nee-extra-'.($idx + 1)"
                        :exportMeta="$chartExportContext"
                        :compact="false"
                    />
                @endforeach
            </div>
        </div>
    @endif

    @if (is_array($calcNote))
        <x-dashboard.section-calc-note
            :formula="$calcNote['formula'] ?? null"
            :note="$calcNote['note'] ?? null"
        />
    @endif
</x-dashboard.consultoria-section>
