@props([
    'comparativoData',
    'yearFilterReady' => false,
    'chartExportContext' => [],
    'municipalityContext' => null,
    'baseYear' => null,
    'selectedCity' => null,
    'filters' => null,
    'pdfExportsRecent' => [],
])

@php
    use App\Support\Dashboard\ConsultoriaFlow;

    $data = is_array($comparativoData ?? null) ? $comparativoData : [];
    $available = (bool) ($data['available'] ?? false);
    $baseYear = (int) ($baseYear ?? $data['base_year'] ?? 0);
    $prevYear = (int) ($data['prev_year'] ?? ($baseYear > 0 ? $baseYear - 1 : 0));
    $nextYear = (int) ($data['next_year'] ?? ($baseYear > 0 ? $baseYear + 1 : 0));
    $yearOptions = is_array($data['year_options'] ?? null) ? $data['year_options'] : [];
    $alerts = is_array($data['alerts'] ?? null) ? $data['alerts'] : [];
    $variacoes = is_array($data['variacoes'] ?? null) ? $data['variacoes'] : [];
    $summaryKpis = is_array($data['summary_kpis'] ?? null) ? $data['summary_kpis'] : [];
    $detail = is_array($data['base_year_detail'] ?? null) ? $data['base_year_detail'] : [];
    $porEtapa = is_array($detail['por_etapa'] ?? null) ? $detail['por_etapa'] : [];
    $projNext = is_array($data['next_year_projection'] ?? null) ? $data['next_year_projection'] : [];
    $fundebSeries = is_array($data['fundeb_series'] ?? null) ? $data['fundeb_series'] : [];
    $informe = is_array($data['informe'] ?? null) ? $data['informe'] : [];
    $exportParams = is_array($data['export_params'] ?? null) ? $data['export_params'] : request()->only(['city_id', 'ano_letivo', 'ano_base', 'escola_id', 'curso_id', 'turno_id']);
    if (! isset($exportParams['city_id']) && $selectedCity !== null) {
        $exportParams['city_id'] = $selectedCity->id;
    }
    if (! isset($exportParams['ano_base']) && $baseYear > 0) {
        $exportParams['ano_base'] = $baseYear;
    }

    $alertRing = static fn (string $s): string => match ($s) {
        'danger', 'rose' => 'border-l-rose-500',
        'warning', 'amber' => 'border-l-amber-500',
        'emerald', 'success' => 'border-l-teal-500',
        'sky' => 'border-l-sky-500',
        default => 'border-l-slate-400',
    };

    $hasInforme = count($informe['blocos'] ?? []) > 0;
    $flowSteps = ConsultoriaFlow::numberedSteps([
        ['label' => __('Resumo'), 'anchor' => 'comparativo-resumo', 'visible' => $available && count($summaryKpis) > 0],
        ['label' => __('Informes'), 'anchor' => 'comparativo-informes', 'visible' => $hasInforme],
        ['label' => __('Variação'), 'anchor' => 'comparativo-variacoes', 'visible' => count($variacoes) > 0],
        ['label' => __('FUNDEB ano base'), 'anchor' => 'comparativo-fundeb-base', 'visible' => $available],
        ['label' => __('Projeção'), 'anchor' => 'comparativo-proximo-ano', 'visible' => ($projNext['available'] ?? false) || filled($projNext['previsao_label'] ?? null)],
        ['label' => __('Série VAAF'), 'anchor' => 'comparativo-serie-vaaf', 'visible' => count($fundebSeries) > 0],
        ['label' => __('Alertas'), 'anchor' => 'comparativo-alertas', 'visible' => count($alerts) > 0],
    ]);
    $cmpStep = ConsultoriaFlow::stepMap($flowSteps);

    $baseYearResolved = max(0, (int) ($baseYear ?? $data['base_year'] ?? 0));
    $needsSpecificYear = $filters !== null
        && $filters->hasYearSelected()
        && $filters->isAllSchoolYears()
        && $baseYearResolved <= 0;
    $comparativoDataReady = $baseYearResolved > 0 && ! $needsSpecificYear;
    $hasBody = $available
        || count($summaryKpis) > 0
        || count($variacoes) > 0
        || count($alerts) > 0
        || count($fundebSeries) > 0
        || $hasInforme
        || (($projNext['available'] ?? false) || filled($projNext['previsao_label'] ?? null));
@endphp

@php
    $meta = null;
    if ($baseYear > 0) {
        $meta = '<span class="font-medium">'.e(__('Ano base')).':</span> '.e((string) $baseYear)
            .' · <span class="font-medium">'.e(__('Comparar com')).':</span> '.e((string) $prevYear)
            .' · <span class="font-medium">'.e(__('Projeção')).':</span> '.e((string) $nextYear);
        if (filled($detail['matriculas_fmt'] ?? null) && $detail['matriculas_fmt'] !== '—') {
            $meta .= ' · '.e(__('Matrículas:')).' <span class="tabular-nums font-medium">'.e($detail['matriculas_fmt']).'</span>';
        }
    }
@endphp

<x-dashboard.consultoria-tab-frame
    tab="comparativo"
    tone="teal"
    :title="__('Comparativo anual')"
    :intro="$data['intro'] ?? __('Evolução de matrículas, alunos, turmas e recursos FUNDEB para apresentação à gestão municipal.')"
    :meta="$meta"
    :footnote="$data['footnote'] ?? null"
    :error="$data['error'] ?? null"
    :year-filter-ready="$yearFilterReady"
    :municipality-context="$municipalityContext"
    :tab-data="['comparativoData' => $data]"
    :flow-steps="$flowSteps"
    flow-tone="teal"
    :no-year-message="__('Selecione o ano letivo nos filtros superiores (ou o ano base abaixo) e aplique para carregar o comparativo.')"
>
    <x-slot name="links">
        <span class="text-slate-600 dark:text-slate-400">{{ __('Relacionado:') }}</span>
        <x-consultoria-tab-link tab="municipality_health" :label="__('Diagnóstico')" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="fundeb" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="discrepancies" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="enrollment" :label="__('Matrículas')" class="text-xs" />
    </x-slot>

    @if (! $yearFilterReady)
        <p class="serv-callout text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
            {{ __('Defina o município e o ano letivo nos filtros superiores (ou escolha o ano base abaixo) e clique em Aplicar filtros. O comparativo cruza matrículas, VAAF e projeção FUNDEB entre exercícios.') }}
        </p>
        @if (filled($data['footnote'] ?? null))
            <p class="serv-callout text-xs leading-relaxed">{{ $data['footnote'] }}</p>
        @endif
    @endif

    @if ($needsSpecificYear)
        <p class="serv-callout serv-callout--warning text-sm">
            {{ __('Com «Todos os anos» no filtro superior, escolha o ano base do comparativo no seletor abaixo ou aplique um ano letivo específico.') }}
        </p>
    @endif

    @if (filled($data['error'] ?? null) && (! $yearFilterReady || $needsSpecificYear || ! $comparativoDataReady))
        <div class="serv-callout serv-callout--danger text-sm">{{ $data['error'] }}</div>
    @endif

    @if (count($yearOptions) > 0)
        <form
            method="get"
            action="{{ request()->url() }}"
            class="serv-panel p-4 flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-3"
            data-comparativo-year-form
        >
            @foreach (request()->except(['ano_base', 'tab', 'page']) as $key => $val)
                @if (is_array($val))
                    @foreach ($val as $v)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}" />
                    @endforeach
                @else
                    <input type="hidden" name="{{ $key }}" value="{{ $val }}" />
                @endif
            @endforeach
            <input type="hidden" name="tab" value="comparativo" />
            <div class="flex-1 min-w-[10rem]">
                <label for="comparativo-ano-base" class="block text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-1">
                    {{ __('Ano base do comparativo') }}
                </label>
                <select
                    id="comparativo-ano-base"
                    name="ano_base"
                    class="serv-input w-full max-w-xs"
                    onchange="this.form.submit()"
                >
                    @foreach ($yearOptions as $opt)
                        <option value="{{ $opt['value'] }}" @selected((int) $opt['value'] === $baseYear)>
                            {{ $opt['label'] }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1">
                    {{ __('Mantém escola, curso e turno dos filtros superiores; altera apenas o exercício de referência.') }}
                </p>
            </div>
        </form>
    @elseif ($yearFilterReady && $baseYearResolved <= 0)
        <p class="serv-callout serv-callout--warning text-sm">
            {{ __('Não foi possível listar anos letivos da base i-Educar. Confirme a conexão do município ou informe o ano base na URL (?ano_base=AAAA).') }}
        </p>
    @endif

    @if ($comparativoDataReady && $available)
        <div class="rounded-lg border border-indigo-200/80 dark:border-indigo-800/50 bg-indigo-50/40 dark:bg-indigo-950/25 px-4 py-4 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Exportar comparativo') }}</h3>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                    {{ __('Resumo executivo, variações, etapas FUNDEB, projeção e informes — no formato escolhido para reunião com a gestão.') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <a
                    href="{{ route('dashboard.analytics.comparativo.export', array_merge($exportParams, ['format' => 'pdf'])) }}"
                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 shadow-sm"
                >
                    {{ __('PDF') }}
                </a>
                <a
                    href="{{ route('dashboard.analytics.comparativo.export', array_merge($exportParams, ['format' => 'csv'])) }}"
                    class="serv-btn-secondary serv-btn-secondary--teal"
                >
                    {{ __('CSV') }}
                </a>
                <a
                    href="{{ route('dashboard.analytics.comparativo.export', array_merge($exportParams, ['format' => 'xlsx'])) }}"
                    class="serv-btn-secondary serv-btn-secondary--indigo"
                >
                    {{ __('Excel') }}
                </a>
            </div>
        </div>

        @if (auth()->user()?->canExportAnalyticsPdf())
            @include('dashboard.analytics.partials.serventec-pdf-export', [
                'selectedCity' => $selectedCity,
                'filters' => $filters,
                'yearFilterReady' => $yearFilterReady,
                'pdfExportsRecent' => $pdfExportsRecent,
            ])
        @endif
    @endif

    @if ($comparativoDataReady && ! $hasBody && ! filled($data['error'] ?? null))
        <p class="serv-callout text-sm text-slate-700 dark:text-slate-300">
            {{ __('Não há dados para exibir neste recorte. Confirme ano base, conexão i-Educar e importação de referências FUNDEB (VAAF).') }}
        </p>
    @endif

    @if ($comparativoDataReady && $available && count($summaryKpis) > 0)
        <x-dashboard.consultoria-section
            :step="$cmpStep['comparativo-resumo'] ?? null"
            anchor="comparativo-resumo"
            :title="__('Resumo do exercício :ano', ['ano' => (string) $baseYear])"
            :subtitle="__('Valores do ano base e variação face a :anterior.', ['anterior' => (string) $prevYear])"
        >
            <x-dashboard.consultoria-kpi-grid :items="$summaryKpis" />
        </x-dashboard.consultoria-section>
    @endif

    @include('dashboard.analytics.partials.comparativo-informe', ['informe' => $informe])

    @if (count($variacoes) > 0)
        <x-dashboard.consultoria-section
            :step="$cmpStep['comparativo-variacoes'] ?? null"
            anchor="comparativo-variacoes"
            :title="__('Variação ano a ano')"
            :subtitle="__('Comparação directa entre :base e :anterior.', ['base' => (string) $baseYear, 'anterior' => (string) $prevYear])"
        >
            <div class="serv-panel overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Indicador') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __(':ano (base)', ['ano' => (string) $baseYear]) }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __(':ano (anterior)', ['ano' => (string) $prevYear]) }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Variação') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Leitura') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900/30">
                        @foreach ($variacoes as $row)
                            @php
                                $tone = (string) ($row['tone'] ?? 'slate');
                                $deltaClass = match ($tone) {
                                    'emerald' => 'text-emerald-700 dark:text-emerald-300',
                                    'rose' => 'text-rose-700 dark:text-rose-300',
                                    'amber' => 'text-amber-700 dark:text-amber-300',
                                    default => 'text-slate-700 dark:text-slate-300',
                                };
                            @endphp
                            <tr>
                                <td class="px-3 py-2.5 font-medium text-serv-navy dark:text-slate-100">{{ $row['label'] ?? '' }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['base_fmt'] ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ $row['prev_fmt'] ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-semibold {{ $deltaClass }}">{{ $row['delta_label'] ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $row['leitura'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-dashboard.consultoria-section>
    @endif

    @if ($comparativoDataReady && $available)
        <x-dashboard.consultoria-section
            :step="$cmpStep['comparativo-fundeb-base'] ?? null"
            anchor="comparativo-fundeb-base"
            :title="__('Matrículas e FUNDEB — exercício :ano', ['ano' => (string) $baseYear])"
            :subtitle="__('Detalhe por nível de ensino (participação na rede e valor indicativo matrícula × VAAF).')"
        >
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <div class="serv-panel p-4 border border-teal-200/80 dark:border-teal-800/50">
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-800/90 dark:text-teal-200/90">{{ __('Matrículas ativas') }}</p>
                    <p class="text-2xl font-semibold tabular-nums text-serv-navy dark:text-slate-100 mt-1">{{ $detail['matriculas_fmt'] ?? '—' }}</p>
                </div>
                <div class="serv-panel p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">{{ __('VAAF de referência') }}</p>
                    <p class="text-2xl font-semibold tabular-nums text-serv-navy dark:text-slate-100 mt-1">{{ $detail['vaaf_label'] ?? '—' }}</p>
                    @if (filled($detail['vaaf_fonte'] ?? null))
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1">{{ $detail['vaaf_fonte'] }}</p>
                    @endif
                </div>
                <div class="serv-panel p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">{{ __('Previsão base (ano)') }}</p>
                    <p class="text-2xl font-semibold tabular-nums text-serv-navy dark:text-slate-100 mt-1">{{ $detail['previsao_base_label'] ?? '—' }}</p>
                </div>
            </div>

            @if (count($porEtapa) > 0)
                <div class="serv-panel overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Nível de ensino') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Matrículas') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('% rede (ponderação)') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('FUNDEB indicativo') }}</th>
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
            @else
                <p class="serv-callout serv-callout--warning text-sm">{{ __('Sem repartição por etapa — confira cadastro de cursos/séries ou abra Matrículas no mesmo ano base.') }}</p>
            @endif
        </x-dashboard.consultoria-section>
    @endif

    @if (($projNext['available'] ?? false) || filled($projNext['previsao_label'] ?? null))
        <x-dashboard.consultoria-section
            :step="$cmpStep['comparativo-proximo-ano'] ?? null"
            anchor="comparativo-proximo-ano"
            :title="__('Projeção para :ano', ['ano' => (string) $nextYear])"
            :subtitle="__('Estimativa se as matrículas do ano base se mantiverem e o VAAF do exercício seguinte for aplicável.')"
        >
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="serv-panel p-4 border-l-4 {{ $alertRing((string) ($projNext['tone'] ?? 'teal')) }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">{{ __('Previsão indicativa') }}</p>
                    <p class="text-2xl font-semibold tabular-nums text-serv-navy dark:text-slate-100 mt-1">{{ $projNext['previsao_label'] ?? '—' }}</p>
                    <p class="text-sm text-slate-700 dark:text-slate-300 mt-2">
                        {{ __('Face ao ano base (:base): :delta', [
                            'base' => $projNext['previsao_base_label'] ?? '—',
                            'delta' => $projNext['delta_label'] ?? '—',
                        ]) }}
                    </p>
                </div>
                <div class="serv-panel p-4 text-sm text-slate-700 dark:text-slate-300 space-y-2">
                    <p><span class="font-medium">{{ __('Matrículas assumidas') }}:</span> {{ $projNext['matriculas_fmt'] ?? '—' }}</p>
                    <p><span class="font-medium">{{ __('VAAF :ano', ['ano' => (string) $nextYear]) }}:</span> {{ $projNext['vaaf_label'] ?? '—' }}</p>
                    @if (filled($projNext['vaaf_fonte'] ?? null))
                        <p class="text-[11px] text-slate-600 dark:text-slate-400">{{ $projNext['vaaf_fonte'] }}</p>
                    @endif
                    @if (filled($projNext['note'] ?? null))
                        <p class="text-[11px] serv-callout">{{ $projNext['note'] }}</p>
                    @endif
                </div>
            </div>
        </x-dashboard.consultoria-section>
    @endif

    @if (count($fundebSeries) > 0)
        <x-dashboard.consultoria-section
            :step="$cmpStep['comparativo-serie-vaaf'] ?? null"
            anchor="comparativo-serie-vaaf"
            :title="__('Série histórica VAAF')"
            :subtitle="__('Referências importadas por exercício (FNDE / dados abertos).')"
        >
            <div class="serv-panel overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Ano') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('VAAF') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Var. VAAF') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Fonte') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($fundebSeries as $row)
                            <tr @class(['bg-teal-50/60 dark:bg-teal-950/30' => ! empty($row['is_anchor'])])>
                                <td class="px-3 py-2 font-medium text-serv-navy dark:text-slate-100">
                                    {{ $row['ano'] ?? '' }}
                                    @if (! empty($row['is_anchor']))
                                        <span class="ml-1 text-[10px] uppercase font-semibold text-teal-700 dark:text-teal-300">{{ __('base') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['vaaf'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['variacao'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-slate-600 dark:text-slate-400">{{ $row['fonte'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-dashboard.consultoria-section>
    @endif

    @if (count($alerts) > 0)
        <x-dashboard.consultoria-section
            :step="$cmpStep['comparativo-alertas'] ?? null"
            anchor="comparativo-alertas"
            :title="__('Alertas automáticos')"
            :subtitle="__('Retrocessos, avanços e lacunas de dados para apoiar a consultoria.')"
        >
            <ul class="space-y-2">
                @foreach ($alerts as $alert)
                    <li class="serv-panel border-l-4 {{ $alertRing((string) ($alert['tone'] ?? 'slate')) }} px-4 py-3 text-sm">
                        <p class="font-semibold text-serv-navy dark:text-slate-100">{{ $alert['title'] ?? '' }}</p>
                        <p class="text-slate-700 dark:text-slate-300 mt-0.5">{{ $alert['message'] ?? '' }}</p>
                    </li>
                @endforeach
            </ul>
        </x-dashboard.consultoria-section>
    @endif
</x-dashboard.consultoria-tab-frame>
