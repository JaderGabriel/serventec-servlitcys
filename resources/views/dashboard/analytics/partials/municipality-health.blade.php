@props(['healthData', 'yearFilterReady' => false, 'chartExportContext' => []])

@php
    $h = is_array($healthData) ? $healthData : [];
    $summary = is_array($h['summary'] ?? null) ? $h['summary'] : [];
    $cadastro = is_array($h['cadastro_dimensions'] ?? null) ? $h['cadastro_dimensions'] : [];
    $fundebMods = is_array($h['fundeb_modules'] ?? null) ? $h['fundeb_modules'] : [];
    $topProblems = is_array($h['top_problems'] ?? null) ? $h['top_problems'] : [];
    $score = $h['compliance_score'] ?? null;
    $fmtBrl = static fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
    $scoreRing = match ($h['compliance_status'] ?? 'neutral') {
        'success' => 'border-emerald-400 bg-emerald-50/60 dark:bg-emerald-950/25',
        'warning' => 'border-amber-400 bg-amber-50/60 dark:bg-amber-950/25',
        'danger' => 'border-red-400 bg-red-50/60 dark:bg-red-950/25',
        default => 'border-slate-300 bg-slate-50/60',
    };
@endphp

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
            {{ __('Seleccione o ano letivo e aplique os filtros para ver a saúde de conformidade do município.') }}
        </p>
    @else
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="rounded-lg border border-teal-200 dark:border-teal-900/50 bg-teal-50/60 dark:bg-teal-950/20 px-4 py-3 text-sm space-y-2 flex-1">
                <h2 class="font-semibold text-teal-950 dark:text-teal-100">{{ __('Saúde do município') }}</h2>
                <p class="leading-relaxed text-teal-900/95 dark:text-teal-200/95">{{ $h['intro'] ?? '' }}</p>
                <p class="text-xs text-teal-800/90 dark:text-teal-300/90">
                    <span class="font-medium">{{ __('Contexto') }}:</span>
                    {{ $h['city_name'] ?? '' }}
                    @if (filled($h['year_label'] ?? null))
                        — {{ $h['year_label'] }}
                    @endif
                </p>
            </div>
            <div class="shrink-0">
                <x-dashboard.funding-loss-conditions-button />
            </div>
        </div>

        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ $h['footnote'] ?? '' }}
        </p>

        @if (! empty($h['error']))
            <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                {{ $h['error'] }}
            </div>
        @endif

        @if ($score !== null)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-1 rounded-xl border-2 {{ $scoreRing }} p-6 flex flex-col items-center justify-center text-center shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ __('Índice de saúde') }}</p>
                    <p class="mt-2 text-5xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ (int) $score }}</p>
                    <p class="mt-1 text-sm font-medium">{{ $h['compliance_label'] ?? '' }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 justify-center">
                        <button
                            type="button"
                            class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                            x-on:click="$dispatch('set-analytics-tab', 'discrepancies')"
                        >
                            {{ __('Ver discrepâncias') }}
                        </button>
                        <span class="text-gray-300 dark:text-gray-600">·</span>
                        <button
                            type="button"
                            class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                            x-on:click="$dispatch('set-analytics-tab', 'fundeb')"
                        >
                            {{ __('Ver FUNDEB') }}
                        </button>
                    </div>
                </div>
                <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-lg border border-rose-200/90 dark:border-rose-800/60 bg-white dark:bg-gray-900/40 p-4">
                        <p class="text-xs font-semibold uppercase text-rose-800/90 dark:text-rose-200/90">{{ __('Pendências de cadastro') }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-rose-700 dark:text-rose-300">{{ number_format((int) ($summary['pendencias_cadastro'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border border-amber-200/90 dark:border-amber-800/60 bg-white dark:bg-gray-900/40 p-4">
                        <p class="text-xs font-semibold uppercase text-amber-800/90 dark:text-amber-200/90">{{ __('Módulos FUNDEB em alerta') }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-700 dark:text-amber-300">{{ number_format((int) ($summary['modulos_fundeb_alerta'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border border-orange-200/90 dark:border-orange-800/60 bg-white dark:bg-gray-900/40 p-4">
                        <p class="text-xs font-semibold uppercase text-orange-800/90 dark:text-orange-200/90">{{ __('Perda estimada / ano') }}</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-orange-700 dark:text-orange-300">{{ $fmtBrl((float) ($summary['perda_estimada_anual'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border border-emerald-200/90 dark:border-emerald-800/60 bg-white dark:bg-gray-900/40 p-4">
                        <p class="text-xs font-semibold uppercase text-emerald-800/90 dark:text-emerald-200/90">{{ __('Ganho potencial / ano') }}</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $fmtBrl((float) ($summary['ganho_potencial_anual'] ?? 0)) }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                @if (! empty($h['chart_score']))
                    <x-dashboard.chart-panel
                        :chart="$h['chart_score']"
                        exportFilename="saude-municipio-score"
                        :exportMeta="$chartExportContext"
                        :compact="true"
                        chartPanelId="chart-saude-score"
                        panelTone="teal"
                    />
                @endif
                @if (! empty($h['chart_pendencias']))
                    <x-dashboard.chart-panel
                        :chart="$h['chart_pendencias']"
                        exportFilename="saude-municipio-pendencias"
                        :exportMeta="$chartExportContext"
                        :compact="true"
                        chartPanelId="chart-saude-pendencias"
                        panelTone="rose"
                    />
                @endif
            </div>
        @endif

        @if (count($topProblems) > 0)
            <section class="rounded-lg border border-rose-200 dark:border-rose-800/60 overflow-hidden">
                <header class="px-4 py-3 bg-rose-50/80 dark:bg-rose-950/30 border-b border-rose-200/80 dark:border-rose-800/50">
                    <h3 class="text-sm font-semibold text-rose-950 dark:text-rose-100">{{ __('Principais problemas (impacto financeiro indicativo)') }}</h3>
                </header>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($topProblems as $problem)
                        <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $problem['title'] ?? '' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ __(':n ocorrências', ['n' => number_format((int) ($problem['total'] ?? 0))]) }}
                                    @if (($problem['pct_rede'] ?? null) !== null)
                                        · {{ number_format((float) $problem['pct_rede'], 1, ',', '.') }}% {{ __('da rede') }}
                                    @endif
                                </p>
                            </div>
                            <p class="text-sm font-semibold tabular-nums text-emerald-700 dark:text-emerald-300 shrink-0">
                                {{ $fmtBrl((float) ($problem['ganho_potencial_anual'] ?? 0)) }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">{{ __('Conformidade por dimensão de cadastro') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach ($cadastro as $dim)
                    @php
                        $st = (string) ($dim['status'] ?? 'success');
                        $chip = match ($st) {
                            'danger' => 'border-red-300 bg-red-50/80 text-red-900 dark:border-red-800 dark:bg-red-950/30 dark:text-red-100',
                            'warning' => 'border-amber-300 bg-amber-50/80 text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100',
                            default => 'border-emerald-300 bg-emerald-50/80 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100',
                        };
                        $icon = match ($st) {
                            'danger', 'warning' => '⚠',
                            default => '✓',
                        };
                    @endphp
                    <div class="rounded-md border px-3 py-2 text-xs flex items-start gap-2 {{ $chip }}">
                        <span class="font-bold shrink-0" aria-hidden="true">{{ $icon }}</span>
                        <div class="min-w-0">
                            <p class="font-medium leading-snug">{{ $dim['title'] ?? '' }}</p>
                            @if ($dim['detected'] ?? false)
                                <p class="mt-0.5 tabular-nums">
                                    {{ number_format((int) ($dim['total'] ?? 0)) }} {{ __('ocorr.') }}
                                    @if (($dim['ganho_potencial_anual'] ?? 0) > 0)
                                        · {{ $fmtBrl((float) $dim['ganho_potencial_anual']) }}
                                    @endif
                                </p>
                            @else
                                <p class="mt-0.5 opacity-80">{{ __('Nenhuma pendência detectada no filtro') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if (count($fundebMods) > 0)
            <section>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">{{ __('Eixos FUNDEB / VAAR (roteiro)') }}</h3>
                <div class="space-y-2">
                    @foreach ($fundebMods as $mod)
                        @php
                            $mst = (string) ($mod['status'] ?? 'neutral');
                            $mchip = match ($mst) {
                                'success' => 'border-l-emerald-500',
                                'warning' => 'border-l-amber-500',
                                'danger' => 'border-l-red-500',
                                default => 'border-l-slate-400',
                            };
                        @endphp
                        <article class="rounded-md border border-gray-200 dark:border-gray-700 border-l-4 {{ $mchip }} px-3 py-2 text-xs">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $mod['title'] ?? '' }}</p>
                            <p class="text-gray-500 dark:text-gray-400 mt-0.5">{{ $mod['reference'] ?? '' }}</p>
                            <p class="mt-1 text-gray-700 dark:text-gray-300 leading-relaxed">{{ $mod['situacao'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
