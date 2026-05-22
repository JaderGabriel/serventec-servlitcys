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
        'success' => 'border-l-teal-500',
        'warning' => 'border-l-amber-500',
        'danger' => 'border-l-rose-500',
        default => 'border-l-slate-400',
    };
@endphp

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="serv-callout serv-callout--warning text-sm">
            {{ __('Seleccione o ano letivo e aplique os filtros para consultar demais financiamentos.') }}
        </p>
    @else
        @include('dashboard.analytics.partials.tab-impact-strip', [
            'tab' => 'other_funding',
            'yearFilterReady' => $yearFilterReady,
            'municipalityContext' => $municipalityContext,
            'tabData' => ['otherFundingData' => $otherFundingData],
        ])

        <x-dashboard.serv-tab-intro :title="__('Financiamentos complementares')" tone="teal">
            {{ $d['intro'] ?? __('PNAE, PNATE, PDDE e demais programas com cobertura de cadastro no i-Educar do município filtrado.') }}
        </x-dashboard.serv-tab-intro>

        @if (filled($d['footnote'] ?? null))
            <p class="serv-callout">{{ $d['footnote'] }}</p>
        @endif

        <p class="serv-callout">
            {{ __('Aprofundar:') }}
            <x-consultoria-tab-link tab="fundeb" class="text-xs" />
            ·
            <x-consultoria-tab-link tab="discrepancies" class="text-xs" />
            {{ __('(impacto de cadastro nos repasses).') }}
        </p>

        @if (! empty($d['error']))
            <div class="serv-callout serv-callout--danger text-sm">
                {{ $d['error'] }}
            </div>
        @endif

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
                    <p class="text-sm font-semibold text-serv-navy dark:text-slate-100">
                        {{ __('Total no exercício') }}:
                        {{ \App\Support\Ieducar\DiscrepanciesFundingImpact::formatBrl((float) $transferSeries['total_ano']) }}
                    </p>
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
                                        <td class="text-xs text-slate-500">{{ $row['fonte'] ?? '' }}</td>
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

        @if (count($programs) === 0)
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
                :subtitle="__('Ligação com checks de cadastro que afetam programas federais.')"
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
    @endif
</div>
