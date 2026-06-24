@props(['otherFundingData', 'yearFilterReady' => false, 'chartExportContext' => [], 'municipalityContext' => null])

@php
    $d = is_array($otherFundingData) ? $otherFundingData : [];
    $programs = is_array($d['programs'] ?? null) ? $d['programs'] : [];
    $transport = is_array($d['transport'] ?? null) ? $d['transport'] : null;
    $pillars = is_array($d['funding_pillars'] ?? null) ? $d['funding_pillars'] : [];
    $chartProgramas = is_array($d['chart_programas'] ?? null) ? $d['chart_programas'] : null;
    $publicMunicipal = is_array($d['public_municipal'] ?? null) ? $d['public_municipal'] : [];
    $transferSeries = is_array($d['transfer_series'] ?? null) ? $d['transfer_series'] : [];
    $programRing = static fn (string $s): string => match ($s) {
        'success' => 'border-l-blue-500',
        'warning' => 'border-l-amber-500',
        'danger' => 'border-l-rose-500',
        default => 'border-l-slate-400',
    };
@endphp

<x-dashboard.consultoria-tab-frame
    tab="other_funding"
    tone="amber"
    :title="__('Financiamentos complementares')"
    :intro="$d['intro'] ?? __('PNAE, PNATE, PDDE e cobertura de campos no i-Educar.')"
    :footnote="$d['footnote'] ?? null"
    :error="$d['error'] ?? null"
    :year-filter-ready="$yearFilterReady"
    :municipality-context="$municipalityContext"
    :tab-data="['otherFundingData' => $otherFundingData]"
    :no-year-message="__('Selecione o ano letivo e aplique os filtros para consultar demais financiamentos.')"
>
    <x-slot name="links">
        <span class="text-slate-600 dark:text-slate-400">{{ __('Aprofundar:') }}</span>
        <x-consultoria-tab-link tab="finance_realtime" :label="__('Tempo Real (FUNDEB)')" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="fundeb" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="discrepancies" class="text-xs" />
    </x-slot>

        <p class="serv-callout serv-callout--warning text-xs leading-relaxed">
            {{ __('Esta aba cruza programas complementares (PNAE, PNATE, PDDE) com cadastro i-Educar. Valores em R$ vêm de importações deduplicadas por programa — não some com VAAF (FUNDEB) nem duplique a leitura da aba Tempo Real.') }}
        </p>

        <x-dashboard.municipal-public-queries
            :snapshot="$publicMunicipal"
            anchor="financiamentos-consultas-publicas"
        />

        @if ($transferSeries['available'] ?? false)
            <x-dashboard.consultoria-section
                anchor="financiamentos-repasse-observado"
                :title="__('Repasse observado (série histórica)')"
                :subtitle="$transferSeries['intro'] ?? ''"
            >
                @if (filled($transferSeries['total_ano'] ?? null))
                    <div class="rounded-md border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20 px-3 py-2 space-y-1">
                        <p class="text-sm font-semibold text-serv-navy dark:text-slate-100">
                            {{ __('Soma deduplicada por programa') }}:
                            {{ \App\Support\Ieducar\DiscrepanciesFundingImpact::formatBrl((float) $transferSeries['total_ano']) }}
                        </p>
                        @if (filled($transferSeries['total_ano_note'] ?? null))
                            <p class="text-xs text-amber-900/90 dark:text-amber-200/90">{{ $transferSeries['total_ano_note'] }}</p>
                        @endif
                    </div>
                @endif
                @if (count($transferSeries['rows'] ?? []) > 0)
                    <div class="overflow-x-auto">
                        <table class="serv-table w-full text-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Programa') }}</th>
                                    <th class="text-right">{{ __('Valor') }}</th>
                                    <th>{{ __('Fonte') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transferSeries['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['label'] ?? '' }}</td>
                                        <td class="text-right tabular-nums">{{ $row['valor_fmt'] ?? '' }}</td>
                                        <td class="text-xs text-slate-500">
                                            {{ $row['fonte'] ?? '' }}
                                            @if (($row['fontes_ignoradas'] ?? 0) > 0)
                                                <span class="block text-[10px] text-amber-700 dark:text-amber-300" title="{{ __('Outras fontes do mesmo programa não entram no total') }}">
                                                    +{{ (int) $row['fontes_ignoradas'] }} {{ __('fonte(s) alternativa(s) omitida(s)') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if (is_array($transferSeries['chart'] ?? null))
                    <x-dashboard.chart-panel
                        :chart="$transferSeries['chart']"
                        exportFilename="repasse-observado-serie"
                        :exportMeta="$chartExportContext"
                        chartPanelId="chart-other-funding-repasse-serie"
                    />
                @endif
            </x-dashboard.consultoria-section>
        @endif

        @if ($chartProgramas !== null)
            <x-dashboard.chart-panel
                :chart="$chartProgramas"
                exportFilename="demais-financiamentos-cobertura"
                :exportMeta="$chartExportContext"
                chartPanelId="chart-other-funding-cobertura"
            />
        @endif

        @if ($transport !== null)
            <x-dashboard.consultoria-section
                anchor="financiamentos-pnate"
                :title="__('Transporte escolar (PNATE)')"
                :subtitle="__('Indicadores derivados do cadastro e consultas configuradas para o município.')"
            >
                <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">{{ $transport['texto'] ?? '' }}</p>
                @if (count($transport['linhas'] ?? []) > 0)
                    <ul class="serv-panel px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 space-y-1">
                        @foreach ($transport['linhas'] as $linha)
                            <li>{{ $linha }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.consultoria-section>
        @endif

        @if (count($programs) === 0 && empty($d['error']))
            <p class="serv-callout serv-callout--warning text-sm">
                {{ __('Nenhum programa configurado em ieducar.other_funding.programs.') }}
            </p>
        @endif

        @foreach ($programs as $prog)
            @php
                $st = (string) ($prog['status'] ?? 'neutral');
                $progStatusLabel = $prog['status_label'] ?? match ($st) {
                    'success' => __('Cobertura adequada'),
                    'warning' => __('Atenção na cobertura'),
                    'danger' => __('Cobertura crítica'),
                    default => __('Sem leitura automática'),
                };
            @endphp
            <article class="serv-panel border-l-4 {{ $programRing($st) }} overflow-hidden scroll-mt-6">
                <header class="px-4 py-3 border-b border-slate-200/80 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-900/30">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ $prog['titulo'] ?? '' }}</h3>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $prog['descricao'] ?? '' }}</p>
                        </div>
                        <x-status-pill :status="$st" :label="$progStatusLabel" class="shrink-0" />
                    </div>
                    @if (filled($prog['fnde_url'] ?? null))
                        <a href="{{ $prog['fnde_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-xs serv-inline-tab-link">
                            {{ __('Referência FNDE →') }}
                        </a>
                    @endif
                </header>
                <div class="px-4 py-4 space-y-3">
                    @if (count($prog['kpis'] ?? []) > 0)
                        <x-dashboard.consultoria-kpi-grid
                            :items="collect($prog['kpis'])->map(fn ($k) => array_merge($k, ['tone' => $k['tone'] ?? 'slate']))->all()"
                            class="!grid-cols-2 sm:!grid-cols-3 lg:!grid-cols-4"
                        />
                    @endif
                    @php $repasse = is_array($prog['repasse_observado'] ?? null) ? $prog['repasse_observado'] : null; @endphp
                    @if ($repasse !== null)
                        <div class="serv-callout text-sm">
                            <p><strong>{{ __('Repasse observado') }}:</strong> {{ $repasse['valor_fmt'] ?? '—' }}</p>
                            @if (isset($repasse['elegiveis']))
                                <p class="mt-1">{{ __('Matrículas elegíveis (cadastro)') }}: {{ number_format((int) $repasse['elegiveis'], 0, ',', '.') }}
                                    @if (filled($repasse['repasse_por_aluno_fmt'] ?? null))
                                        · {{ $repasse['repasse_por_aluno_fmt'] }}
                                    @endif
                                </p>
                            @endif
                            @if (filled($repasse['nota'] ?? null))
                                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $repasse['nota'] }}</p>
                            @endif
                        </div>
                    @endif
                    @foreach ($prog['distributions'] ?? [] as $col => $dist)
                        @if (is_array($dist) && count($dist['rows'] ?? []) > 0)
                            <div>
                                <p class="text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ __('Campo :col', ['col' => $dist['col'] ?? $col]) }}</p>
                                <ul class="serv-panel px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 space-y-0.5">
                                    @foreach ($dist['rows'] as $row)
                                        <li>{{ $row['value'] ?? '—' }}: {{ number_format((int) ($row['count'] ?? 0), 0, ',', '.') }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                </div>
            </article>
        @endforeach

        @if (count($pillars) > 0)
            <x-dashboard.consultoria-section
                anchor="financiamentos-pilares"
                :title="__('Pilar «Programas complementares» (discrepâncias)')"
                :subtitle="__('Conexão com checks de cadastro que afetam programas federais.')"
            >
                @foreach ($pillars as $pillar)
                    <p class="text-sm text-slate-700 dark:text-slate-300">
                        <span class="font-medium text-serv-navy dark:text-slate-100">{{ $pillar['titulo'] ?? '' }}</span>
                        — {{ $pillar['descricao'] ?? '' }}
                    </p>
                @endforeach
                <p class="serv-callout text-xs">
                    <x-consultoria-tab-link tab="discrepancies" :label="__('Ver checks em Discrepâncias')" class="text-xs" />
                </p>
            </x-dashboard.consultoria-section>
        @endif
</x-dashboard.consultoria-tab-frame>
