@props(['fundebData', 'yearFilterReady' => false, 'chartExportContext' => [], 'municipalityContext' => null])

@php
    $publicSources = is_array($fundebData['public_data_sources'] ?? null) ? $fundebData['public_data_sources'] : [];
    $proj = is_array($fundebData['resource_projection'] ?? null) ? $fundebData['resource_projection'] : [];
    $projAvailable = (bool) ($proj['available'] ?? false);
    $distLegal = is_array($proj['distribuicao_legal'] ?? null) ? $proj['distribuicao_legal'] : [];
    $distItens = is_array($distLegal['itens'] ?? null) ? $distLegal['itens'] : [];
    $porEtapa = is_array($proj['por_etapa'] ?? null) ? $proj['por_etapa'] : [];
    $informe = is_array($fundebData['complementacao_informe'] ?? null) ? $fundebData['complementacao_informe'] : [];
    $informeBlocos = is_array($informe['blocos'] ?? null) ? $informe['blocos'] : [];
    $moduleRing = static fn (string $s): string => match ($s) {
        'success' => 'border-l-teal-500',
        'warning' => 'border-l-amber-500',
        'danger' => 'border-l-rose-500',
        default => 'border-l-slate-400',
    };
    $informeRing = static fn (string $s): string => match ($s) {
        'success' => 'border-l-teal-500',
        'warning' => 'border-l-amber-500',
        'danger' => 'border-l-rose-500',
        default => 'border-l-slate-400',
    };
@endphp

@php
    $fundebMeta = null;
    if (filled($fundebData['city_name'] ?? null) || filled($fundebData['year_label'] ?? null)) {
        $fundebMeta = '<span class="font-medium">'.e(__('Contexto')).':</span> '
            .e($fundebData['city_name'] ?? '');
        if (filled($fundebData['year_label'] ?? null)) {
            $fundebMeta .= ' — '.e($fundebData['year_label']);
        }
    }
@endphp

<x-dashboard.consultoria-tab-frame
    tab="fundeb"
    tone="teal"
    :title="__('FUNDEB e repasses')"
    :intro="$fundebData['intro'] ?? __('Previsão de recursos, complementação VAAR e roteiro de condicionalidades.')"
    :meta="$fundebMeta"
    :footnote="$fundebData['footnote'] ?? null"
    :year-filter-ready="$yearFilterReady"
    :municipality-context="$municipalityContext"
    :tab-data="['fundebData' => $fundebData]"
    :no-year-message="__('Selecione o ano letivo (ou «Todos os anos») e aplique os filtros para gerar o relatório FUNDEB.')"
>
    <x-slot name="links">
        <span class="text-slate-600 dark:text-slate-400">{{ __('Aprofundar:') }}</span>
        <x-consultoria-tab-link tab="municipality_health" :label="__('Diagnóstico')" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="discrepancies" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="work_done" :label="__('Censo')" class="text-xs" />
    </x-slot>

        @if (count($publicSources['categories'] ?? []) > 0)
            <x-dashboard.consultoria-public-sources
                :catalog="$publicSources"
                anchor="fundeb-fontes-publicas"
            />
        @endif

        @include('dashboard.analytics.partials.fundeb-vaaf-profile', [
            'profile' => $fundebData['vaaf_profile'] ?? [],
        ])

        <x-dashboard.consultoria-section
            anchor="fundeb-previsao-recursos"
            :title="__('Previsão de recursos e distribuição legal')"
            :subtitle="__('Estimativa anual com base nas matrículas ativas do filtro e nos pisos mínimos de aplicação do FUNDEB (Lei nº 14.113/2020). Valores indicativos para planejamento — não substituem repasse do FNDE nem prestação de contas.')"
        >
            @if (! $projAvailable)
                <p class="serv-callout serv-callout--warning text-sm">{{ $proj['formula_base'] ?? __('Sem matrículas no filtro para calcular a previsão.') }}</p>
            @else
                @if (filled($proj['formula_base'] ?? null))
                    <p class="serv-callout text-sm">{{ $proj['formula_base'] }}</p>
                @endif

                @if (filled($proj['vaa_fonte_label'] ?? null))
                    <p class="serv-callout">
                        <span class="font-medium">{{ __('Fonte do VAAF (cálculo):') }}</span>
                        {{ $proj['vaa_fonte_label'] }}
                        @if (filled($proj['vaa_ano'] ?? null))
                            · {{ __('ano :y', ['y' => $proj['vaa_ano']]) }}
                        @endif
                    </p>
                @endif

                @if (is_array($proj['vaaf_comparacao'] ?? null))
                    <x-dashboard.consultoria-vaaf-comparacao
                        :comparacao="$proj['vaaf_comparacao']"
                        :divergencia="$proj['divergencia_vaaf'] ?? null"
                    />
                @endif

                <p class="serv-callout text-[11px]">
                    {{ __('Detalhe por escola e rotinas completas:') }}
                    <x-consultoria-tab-link tab="discrepancies" :label="__('aba Discrepâncias')" class="text-xs" />.
                    @if (! config('analytics.fundeb_load_discrepancies_summary', true))
                        {{ __('Resumo de risco/ganho desativado (ANALYTICS_FUNDEB_DISC_SUMMARY=false).') }}
                    @endif
                </p>

                @if (filled($proj['aviso'] ?? null))
                    <p class="serv-callout">{{ $proj['aviso'] }}</p>
                @endif

                @if (count($proj['kpis'] ?? []) > 0)
                    <x-dashboard.consultoria-kpi-grid :items="$proj['kpis']" />
                    <x-dashboard.consultoria-funding-explanation
                        :metodologia="\App\Support\Ieducar\DiscrepanciesFundingImpact::metodologiaResumo()"
                        class="mt-2"
                    />
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @if (! empty($proj['chart_previsao']))
                        <x-dashboard.chart-panel
                            :chart="$proj['chart_previsao']"
                            exportFilename="fundeb-previsao-cenarios"
                            :exportMeta="$chartExportContext"
                            :compact="true"
                            chartPanelId="chart-fundeb-previsao"
                        />
                    @endif
                    @if (! empty($proj['chart_distribuicao']))
                        <x-dashboard.chart-panel
                            :chart="$proj['chart_distribuicao']"
                            exportFilename="fundeb-distribuicao-legal"
                            :exportMeta="$chartExportContext"
                            :compact="true"
                            chartPanelId="chart-fundeb-distribuicao"
                        />
                    @endif
                </div>

                @if (count($distItens) > 0)
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ __('Pisos legais de aplicação (sobre previsão base :total)', ['total' => $distLegal['total_base_label'] ?? '']) }}
                        </p>
                        @if (filled($distLegal['referencia_legal'] ?? null))
                            <p class="text-[11px] text-slate-600 dark:text-slate-400">{{ $distLegal['referencia_legal'] }}</p>
                        @endif
                        <div class="serv-panel overflow-x-auto">
                            <table class="min-w-full text-sm divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-900/60">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Finalidade') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Piso') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Valor mínimo (ano)') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900/30">
                                    @foreach ($distItens as $item)
                                        <tr>
                                            <td class="px-3 py-2.5">
                                                <p class="font-medium text-serv-navy dark:text-slate-100">{{ $item['titulo'] ?? '' }}</p>
                                                @if (filled($item['descricao'] ?? null))
                                                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">{{ $item['descricao'] }}</p>
                                                @endif
                                                @if (filled($item['nota'] ?? null))
                                                    <p class="text-[11px] text-teal-800/90 dark:text-teal-300/90 mt-0.5">{{ $item['nota'] }}</p>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300 whitespace-nowrap">{{ $item['percentual_label'] ?? '' }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-serv-navy dark:text-slate-100 whitespace-nowrap">{{ $item['valor_label'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if (filled($distLegal['nota'] ?? null))
                            <p class="text-[11px] text-slate-600 dark:text-slate-400">{{ $distLegal['nota'] }}</p>
                        @endif
                    </div>
                @endif

                @if (count($porEtapa) > 0)
                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Participação por nível de ensino (previsão base)') }}</p>
                        @if (! empty($proj['chart_etapa']))
                            <x-dashboard.chart-panel
                                :chart="$proj['chart_etapa']"
                                exportFilename="fundeb-previsao-etapa"
                                :exportMeta="$chartExportContext"
                                :compact="true"
                                chartPanelId="chart-fundeb-etapa"
                            />
                        @endif
                        <div class="serv-panel overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 dark:bg-slate-900/60">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Nível') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Matrículas') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('% rede') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('FUNDEB base') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($porEtapa as $row)
                                        <tr>
                                            <td class="px-3 py-2 text-serv-navy dark:text-slate-100">{{ $row['etapa'] ?? '' }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((int) ($row['matriculas'] ?? 0), 0, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($row['participacao_pct'] ?? 0), 1, ',', '.') }}%</td>
                                            <td class="px-3 py-2 text-right tabular-nums font-medium">{{ $row['fundeb_label'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <p class="serv-callout text-xs">
                    <x-consultoria-tab-link tab="discrepancies" :label="__('Ver impacto de cadastro em Discrepâncias')" class="text-xs" />
                </p>
            @endif
        </x-dashboard.consultoria-section>

        @if (count($informeBlocos) > 0)
            <x-dashboard.consultoria-section
                anchor="fundeb-complementacao-informe"
                :title="__('Informes VAAF, VAAT e complementação VAAR')"
                :subtitle="$informe['aviso'] ?? ''"
            >
                @foreach ($informeBlocos as $bloco)
                    @php
                        $st = (string) ($bloco['status'] ?? 'neutral');
                        $indicadores = is_array($bloco['indicadores'] ?? null) ? $bloco['indicadores'] : [];
                        $acoes = is_array($bloco['acoes'] ?? null) ? $bloco['acoes'] : [];
                    @endphp
                    <article class="serv-panel border-l-4 {{ $informeRing($st) }} px-4 py-3 space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                            <div>
                                <h4 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ $bloco['titulo'] ?? '' }}</h4>
                                @if (filled($bloco['subtitulo'] ?? null))
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $bloco['subtitulo'] }}</p>
                                @endif
                            </div>
                            @if (filled($bloco['status_label'] ?? null))
                                <x-status-pill :status="$st" :label="$bloco['status_label']" class="shrink-0" />
                            @endif
                        </div>
                        @foreach ($bloco['paragrafos'] ?? [] as $par)
                            <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">{{ $par }}</p>
                        @endforeach
                        @if (count($indicadores) > 0)
                            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-sm">
                                @foreach ($indicadores as $ind)
                                    <div class="serv-panel border-slate-200/90 dark:border-slate-700/60 px-3 py-2">
                                        <dt class="text-[11px] text-slate-500 dark:text-slate-400">{{ $ind['label'] ?? '' }}</dt>
                                        <dd class="font-semibold tabular-nums text-serv-navy dark:text-slate-100">{{ $ind['value'] ?? '' }}</dd>
                                        @if (is_array($ind['comparacao'] ?? null))
                                            <dd class="mt-2 pt-2 border-t border-slate-200 dark:border-slate-600 space-y-1 text-[10px] text-slate-600 dark:text-slate-400">
                                                <p><span class="font-medium text-teal-800 dark:text-teal-300">{{ __('Real') }}:</span> {{ $ind['comparacao']['real']['value'] ?? '—' }}</p>
                                                <p><span class="font-medium text-slate-700 dark:text-slate-300">{{ __('Prévia') }}:</span> {{ $ind['comparacao']['previa']['value'] ?? '—' }}</p>
                                            </dd>
                                        @endif
                                        @if (filled($ind['hint'] ?? null))
                                            <dd class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $ind['hint'] }}</dd>
                                        @endif
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                        @if (count($acoes) > 0)
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">{{ __('Recomendações') }}</p>
                                <ul class="list-disc list-inside text-xs text-slate-700 dark:text-slate-300 space-y-0.5">
                                    @foreach ($acoes as $acao)
                                        <li>{{ $acao }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </article>
                @endforeach
            </x-dashboard.consultoria-section>
        @endif

        <div class="space-y-4">
            @foreach ($fundebData['modules'] ?? [] as $mod)
                @php
                    $st = (string) ($mod['status'] ?? 'neutral');
                    $badgeLabel = match ($st) {
                        'success' => __('Dados locais favoráveis'),
                        'warning' => __('Atenção / parcial'),
                        'danger' => __('Lacuna na base ou erro'),
                        default => __('Comprovar fora do i-Educar'),
                    };
                @endphp
                <article class="serv-panel border-l-4 {{ $moduleRing($st) }} overflow-hidden">
                    <header class="px-4 py-3 border-b border-slate-200/80 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ $mod['title'] ?? '' }}</h3>
                            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">{{ $mod['reference'] ?? '' }}</p>
                        </div>
                        <x-status-pill :status="$st" :label="$badgeLabel" class="shrink-0" />
                    </header>
                    <div class="px-4 py-3 space-y-3 text-sm text-slate-700 dark:text-slate-300">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">{{ __('O que este módulo cobre') }}</p>
                            <p class="leading-relaxed">{{ $mod['explanation'] ?? '' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">{{ __('Situação com base no filtro atual (i-Educar)') }}</p>
                            <p class="leading-relaxed">{{ $mod['situacao'] ?? '' }}</p>
                        </div>
                        @if (! empty($mod['evidencias']))
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">{{ __('Evidências no painel') }}</p>
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach ($mod['evidencias'] as $ev)
                                        <li>{{ $ev }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if (! empty($mod['gaps']))
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300 mb-1">{{ __('Pontos a verificar ou lacunas') }}</p>
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach ($mod['gaps'] as $gap)
                                        <li>{{ $gap }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
</x-dashboard.consultoria-tab-frame>
