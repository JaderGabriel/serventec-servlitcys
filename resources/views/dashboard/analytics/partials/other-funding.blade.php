@props(['otherFundingData', 'yearFilterReady' => false, 'chartExportContext' => [], 'municipalityContext' => null])

@php
    $d = is_array($otherFundingData) ? $otherFundingData : [];
    $programs = is_array($d['programs'] ?? null) ? $d['programs'] : [];
    $transport = is_array($d['transport'] ?? null) ? $d['transport'] : null;
    $pillars = is_array($d['funding_pillars'] ?? null) ? $d['funding_pillars'] : [];
    $chartProgramas = is_array($d['chart_programas'] ?? null) ? $d['chart_programas'] : null;
    $publicMunicipal = is_array($d['public_municipal'] ?? null) ? $d['public_municipal'] : [];
    $transferSeries = is_array($d['transfer_series'] ?? null) ? $d['transfer_series'] : [];
    $portalQ = collect($publicMunicipal['queries'] ?? [])->firstWhere('id', 'portal_transparencia');
    $portalOk = is_array($portalQ) && ($portalQ['status'] ?? '') === 'success';
    $portalSkipped = is_array($portalQ) && ($portalQ['status'] ?? '') === 'skipped';

    $coverageRows = [];
    foreach ($programs as $prog) {
        if (! is_array($prog)) {
            continue;
        }
        $st = (string) ($prog['status'] ?? 'neutral');
        $pct = null;
        foreach ($prog['kpis'] ?? [] as $kpi) {
            if (! is_array($kpi)) {
                continue;
            }
            $label = mb_strtolower((string) ($kpi['label'] ?? ''));
            if (str_contains($label, 'preench') || str_contains($label, 'cobertura')) {
                $pct = (string) ($kpi['value'] ?? '');
                break;
            }
        }
        if ($pct === null && count($prog['kpis'] ?? []) > 0) {
            $pct = (string) ($prog['kpis'][0]['value'] ?? '—');
        }
        $repasse = is_array($prog['repasse_observado'] ?? null) ? $prog['repasse_observado'] : null;
        $coverageRows[] = [
            'id' => (string) ($prog['id'] ?? ''),
            'titulo' => (string) ($prog['titulo'] ?? ''),
            'status' => $st,
            'status_label' => $prog['status_label'] ?? match ($st) {
                'success' => __('OK'),
                'warning' => __('Atenção'),
                'danger' => __('Crítico'),
                default => __('—'),
            },
            'cobertura' => $pct ?? '—',
            'repasse_fmt' => $repasse['valor_fmt'] ?? null,
            'elegiveis' => isset($repasse['elegiveis']) ? (int) $repasse['elegiveis'] : null,
            'fnde_url' => $prog['fnde_url'] ?? null,
        ];
    }

    $okCount = count(array_filter($coverageRows, static fn (array $r): bool => $r['status'] === 'success'));
    $warnCount = count(array_filter($coverageRows, static fn (array $r): bool => in_array($r['status'], ['warning', 'danger'], true)));
@endphp

<x-dashboard.consultoria-tab-frame
    tab="other_funding"
    tone="amber"
    :title="__('Financiamentos complementares')"
    :intro="$d['intro'] ?? __('Repasses públicos de educação (Portal) e série observada; cadastro i-Educar só como cobertura.')"
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

        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            {{ __('Hierarquia: (1) Portal — recursos e convênios. (2) Emendas educação. (3) Série observada. (4) Cadastro i-Educar — só cobertura. Não some blocos entre si nem com Tempo Real / VAAF.') }}
        </p>

        <x-dashboard.municipal-public-queries
            :snapshot="$publicMunicipal"
            anchor="financiamentos-portal"
        />

        @if ($portalSkipped)
            <p class="text-xs text-amber-900 dark:text-amber-100 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
                {{ __('Portal ainda sem chave: defina PORTAL_TRANSPARENCIA_API_KEY e rode funding:enrich-consultoria-financiamentos.') }}
            </p>
        @elseif ($portalOk && ! ($transferSeries['available'] ?? false))
            <p class="text-xs text-sky-900 dark:text-sky-100 bg-sky-50/80 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800 rounded-md px-3 py-2">
                {{ __('Portal com amostra acima; para consolidar a série histórica, rode funding:enrich-consultoria-financiamentos no ano do filtro.') }}
            </p>
        @endif

        @php
            $emendas = is_array($d['emendas'] ?? null) ? $d['emendas'] : [];
            $emendasRows = is_array($emendas['rows'] ?? null) ? $emendas['rows'] : [];
            $emendasCount = (int) ($emendas['count'] ?? count($emendasRows));
        @endphp

        <x-dashboard.consultoria-section
            anchor="financiamentos-emendas"
            :title="__('Emendas (educação)')"
            :subtitle="$emendas['intro'] ?? __('Emendas parlamentares com função Educação no Portal da Transparência.')"
        >
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <p class="tabular-nums font-semibold text-slate-800 dark:text-slate-100">
                    {{ trans_choice(':count emenda|:count emendas', $emendasCount, ['count' => $emendasCount]) }}
                    @if (filled($emendas['year'] ?? null))
                        <span class="font-normal text-slate-500 dark:text-slate-400">· {{ $emendas['year'] }}</span>
                    @endif
                </p>
                @if (filled($emendas['portal_url'] ?? null))
                    <a href="{{ $emendas['portal_url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-amber-800 underline dark:text-amber-200">
                        {{ __('Consulta no Portal') }}
                    </a>
                @endif
            </div>

            @if (filled($emendas['total_empenhado_fmt'] ?? null) || filled($emendas['total_pago_fmt'] ?? null))
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @if (filled($emendas['total_empenhado_fmt'] ?? null))
                        <div class="rounded-md border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/40 dark:bg-amber-950/20 px-3 py-2">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/80">{{ __('Total empenhado (catálogo)') }}</p>
                            <p class="text-base font-bold tabular-nums text-amber-950 dark:text-amber-50">{{ $emendas['total_empenhado_fmt'] }}</p>
                        </div>
                    @endif
                    @if (filled($emendas['total_pago_fmt'] ?? null))
                        <div class="rounded-md border border-amber-200/80 dark:border-amber-800/60 bg-amber-50/40 dark:bg-amber-950/20 px-3 py-2">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/80">{{ __('Total pago (catálogo)') }}</p>
                            <p class="text-base font-bold tabular-nums text-amber-950 dark:text-amber-50">{{ $emendas['total_pago_fmt'] }}</p>
                        </div>
                    @endif
                </div>
            @endif

            @if ($emendasCount > 0)
                <div class="overflow-x-auto">
                    <table class="serv-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Autor') }}</th>
                                <th>{{ __('Tipo / nº') }}</th>
                                <th class="text-right">{{ __('Empenhado') }}</th>
                                <th class="text-right">{{ __('Pago') }}</th>
                                <th>{{ __('Detalhe') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($emendasRows as $em)
                                <tr class="align-top">
                                    <td>
                                        <p class="font-medium leading-snug">{{ $em['autor'] ?? '—' }}</p>
                                        <p class="mt-0.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $em['codigo'] ?? '' }}</p>
                                        @if (filled($em['subfuncao'] ?? null))
                                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ $em['subfuncao'] }}</p>
                                        @endif
                                    </td>
                                    <td class="text-xs text-slate-700 dark:text-slate-300 max-w-[14rem]">
                                        <p class="leading-snug">{{ $em['tipo'] ?? '—' }}</p>
                                        @if (filled($em['numero'] ?? null))
                                            <p class="mt-0.5 tabular-nums text-slate-500">n.º {{ $em['numero'] }}</p>
                                        @endif
                                    </td>
                                    <td class="text-right tabular-nums font-medium whitespace-nowrap">{{ $em['valor_empenhado_fmt'] ?? '—' }}</td>
                                    <td class="text-right tabular-nums font-medium whitespace-nowrap">{{ $em['valor_pago_fmt'] ?? '—' }}</td>
                                    <td class="text-xs">
                                        <details class="group">
                                            <summary class="cursor-pointer font-semibold text-amber-800 dark:text-amber-200 list-none [&::-webkit-details-marker]:hidden">
                                                {{ __('Ver') }}
                                                @if (($em['documentos_count'] ?? 0) > 0)
                                                    <span class="font-normal text-slate-500">({{ (int) $em['documentos_count'] }} {{ __('docs') }})</span>
                                                @endif
                                            </summary>
                                            <div class="mt-2 space-y-1.5 text-[11px] text-slate-600 dark:text-slate-300 max-w-xs">
                                                <p><span class="font-semibold">{{ __('Localidade') }}:</span> {{ $em['localidade'] ?? '—' }}</p>
                                                <p><span class="font-semibold">{{ __('Função') }}:</span> {{ $em['funcao'] ?? '—' }}</p>
                                                <p><span class="font-semibold">{{ __('Liquidado') }}:</span> {{ $em['valor_liquidado_fmt'] ?? '—' }}</p>
                                                @if (($em['documentos_count'] ?? 0) > 0)
                                                    <p class="font-semibold pt-1">{{ __('Documentos orçamentais') }}</p>
                                                    <ul class="space-y-1">
                                                        @foreach ($em['documentos'] as $doc)
                                                            <li class="rounded border border-slate-200 dark:border-slate-700 px-2 py-1">
                                                                <span class="font-medium">{{ $doc['fase'] ?? '—' }}</span>
                                                                · {{ $doc['data'] ?? '—' }}
                                                                <span class="block font-mono text-slate-500">{{ $doc['codigo'] ?? '' }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <p class="text-slate-500">{{ __('Sem documentos importados para esta emenda.') }}</p>
                                                @endif
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded-md border border-amber-200/80 dark:border-amber-800/50 bg-amber-50/50 dark:bg-amber-950/20 px-3 py-3 space-y-1.5">
                    <p class="text-sm text-amber-950 dark:text-amber-100">
                        {{ $emendas['empty_message'] ?? __('Nenhuma emenda de educação catalogada para este município/ano.') }}
                    </p>
                    @if (filled($emendas['enrich_hint'] ?? null))
                        <p class="text-[11px] font-mono text-amber-900/90 dark:text-amber-200/90 break-all">{{ $emendas['enrich_hint'] }}</p>
                    @endif
                </div>
            @endif

            @if (filled($emendas['footnote'] ?? null))
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">{{ $emendas['footnote'] }}</p>
            @endif
        </x-dashboard.consultoria-section>

        @if ($transferSeries['available'] ?? false)
            <x-dashboard.consultoria-section
                anchor="financiamentos-repasse-observado"
                :title="__('Série observada (importada)')"
                :subtitle="$transferSeries['intro'] ?? __('Valores deduplicados por programa a partir do Portal/Tesouro — uma fonte prioritária por linha.')"
            >
                @if (filled($transferSeries['total_ano'] ?? null))
                    <div class="rounded-md border border-teal-200/80 dark:border-teal-800/60 bg-teal-50/40 dark:bg-teal-950/20 px-3 py-2.5 space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-teal-800/80 dark:text-teal-200/80">{{ __('Total do exercício (deduplicado)') }}</p>
                        <p class="text-lg font-bold tabular-nums text-teal-950 dark:text-teal-50">
                            {{ \App\Support\Ieducar\DiscrepanciesFundingImpact::formatBrl((float) $transferSeries['total_ano']) }}
                        </p>
                        @if (filled($transferSeries['total_ano_note'] ?? null))
                            <p class="text-[11px] text-teal-900/80 dark:text-teal-200/80">{{ $transferSeries['total_ano_note'] }}</p>
                        @endif
                    </div>
                @endif
                @if (count($transferSeries['rows'] ?? []) > 0)
                    <div class="overflow-x-auto">
                        <table class="serv-table w-full text-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Programa / rubrica') }}</th>
                                    <th class="text-right">{{ __('Valor') }}</th>
                                    <th>{{ __('Fonte') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transferSeries['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['label'] ?? '' }}</td>
                                        <td class="text-right tabular-nums font-medium">{{ $row['valor_fmt'] ?? '' }}</td>
                                        <td class="text-xs text-slate-500">
                                            {{ $row['fonte'] ?? '' }}
                                            @if (($row['fontes_ignoradas'] ?? 0) > 0)
                                                <span class="block text-[10px] text-amber-700 dark:text-amber-300">
                                                    +{{ (int) $row['fontes_ignoradas'] }} {{ __('alt. omitida(s)') }}
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

        {{-- Cadastro i-Educar: bloco secundário, compacto e agregado --}}
        <section id="financiamentos-ieducar" class="scroll-mt-6 rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/20 px-3 py-3 space-y-2.5">
            <header class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1">
                <div>
                    <h3 class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ __('Cadastro i-Educar (cobertura)') }}</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ __('Indicativo de preenchimento de campos — não são R$ de repasse. Detalhe em Discrepâncias se necessário.') }}
                    </p>
                </div>
                @if (count($coverageRows) > 0)
                    <p class="text-[10px] tabular-nums text-slate-500 dark:text-slate-400">
                        {{ __(':ok ok · :w atenção', ['ok' => (string) $okCount, 'w' => (string) $warnCount]) }}
                        @if (isset($d['total_matriculas']))
                            · {{ number_format((int) $d['total_matriculas'], 0, ',', '.') }} {{ __('matrículas') }}
                        @endif
                    </p>
                @endif
            </header>

            @if (count($coverageRows) === 0 && empty($d['error']))
                <p class="text-[11px] text-amber-800 dark:text-amber-200">
                    {{ __('Nenhum programa de cobertura configurado (ieducar.other_funding.programs).') }}
                </p>
            @elseif (count($coverageRows) > 0)
                <div class="overflow-x-auto rounded border border-slate-200/80 dark:border-slate-700/80 bg-white/70 dark:bg-slate-950/30">
                    <table class="w-full text-[11px]">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
                                <th class="px-2 py-1.5 font-medium">{{ __('Programa') }}</th>
                                <th class="px-2 py-1.5 font-medium">{{ __('Cobertura') }}</th>
                                <th class="px-2 py-1.5 font-medium">{{ __('Estado') }}</th>
                                <th class="px-2 py-1.5 font-medium text-right">{{ __('Repasse (se importado)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($coverageRows as $row)
                                <tr class="border-b border-slate-100 dark:border-slate-800/80 last:border-0">
                                    <td class="px-2 py-1.5 text-slate-800 dark:text-slate-200">
                                        {{ \Illuminate\Support\Str::before($row['titulo'], '—') ?: $row['titulo'] }}
                                        @if (filled($row['fnde_url']))
                                            <a href="{{ $row['fnde_url'] }}" target="_blank" rel="noopener noreferrer" class="ml-1 text-sky-600 dark:text-sky-400 hover:underline">↗</a>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 tabular-nums font-medium text-slate-900 dark:text-slate-100">{{ $row['cobertura'] }}</td>
                                    <td class="px-2 py-1.5">
                                        <x-status-pill :status="$row['status']" :label="$row['status_label']" class="!text-[9px] !py-0" />
                                    </td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-slate-700 dark:text-slate-300">
                                        @if (filled($row['repasse_fmt']))
                                            {{ $row['repasse_fmt'] }}
                                            @if ($row['elegiveis'] !== null)
                                                <span class="block text-[9px] text-slate-400">{{ number_format($row['elegiveis'], 0, ',', '.') }} {{ __('elegíveis') }}</span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($transport !== null || count($pillars) > 0 || $chartProgramas !== null)
                <details class="group rounded border border-slate-200/80 dark:border-slate-700/70 bg-white/50 dark:bg-slate-950/20">
                    <summary class="cursor-pointer list-none px-2.5 py-1.5 text-[11px] font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex items-center gap-1">
                            <span class="text-slate-400 group-open:rotate-90 transition-transform">▸</span>
                            {{ __('Mais detalhe do cadastro (transporte, pilares, gráfico)') }}
                        </span>
                    </summary>
                    <div class="border-t border-slate-200/80 dark:border-slate-700/70 px-2.5 py-2 space-y-3">
                        @if ($transport !== null)
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Transporte / PNATE') }}</p>
                                <p class="mt-1 text-[11px] text-slate-700 dark:text-slate-300 leading-relaxed">{{ $transport['texto'] ?? '' }}</p>
                                @if (count($transport['linhas'] ?? []) > 0)
                                    <ul class="mt-1 text-[10px] font-mono text-slate-600 dark:text-slate-400 space-y-0.5">
                                        @foreach (array_slice($transport['linhas'], 0, 6) as $linha)
                                            <li>{{ $linha }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endif
                        @if (count($pillars) > 0)
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Ligação a Discrepâncias') }}</p>
                                @foreach ($pillars as $pillar)
                                    <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400">
                                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ $pillar['titulo'] ?? '' }}</span>
                                        — {{ $pillar['descricao'] ?? '' }}
                                    </p>
                                @endforeach
                                <x-consultoria-tab-link tab="discrepancies" :label="__('Abrir Discrepâncias')" class="text-[11px] mt-1 inline-block" />
                            </div>
                        @endif
                        @if ($chartProgramas !== null)
                            <x-dashboard.chart-panel
                                :chart="$chartProgramas"
                                exportFilename="demais-financiamentos-cobertura"
                                :exportMeta="$chartExportContext"
                                chartPanelId="chart-other-funding-cobertura"
                            />
                        @endif
                    </div>
                </details>
            @endif
        </section>
</x-dashboard.consultoria-tab-frame>
