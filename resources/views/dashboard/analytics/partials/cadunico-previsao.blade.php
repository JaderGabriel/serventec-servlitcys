@props([
    'cadunicoPrevisaoData',
    'yearFilterReady' => false,
    'chartExportContext' => [],
    'municipalityContext' => null,
    'selectedCity' => null,
    'filters' => null,
])

@php
    use App\Support\Dashboard\ConsultoriaFlow;

    $d = is_array($cadunicoPrevisaoData ?? null) ? $cadunicoPrevisaoData : [];
    $gap = is_array($d['gap'] ?? null) ? $d['gap'] : [];
    $kpis = is_array($d['kpis'] ?? null) ? $d['kpis'] : [];
    $porEtapa = is_array($gap['por_etapa'] ?? null) ? $gap['por_etapa'] : [];
    $porFaixa = is_array($gap['por_faixa'] ?? null) ? $gap['por_faixa'] : [];
    $informe = is_array($d['informe'] ?? null) ? $d['informe'] : [];
    $alerts = is_array($d['alerts'] ?? null) ? $d['alerts'] : [];
    $metodologia = is_array($d['metodologia'] ?? null) ? $d['metodologia'] : [];
    $publicSources = is_array($d['public_data_sources'] ?? null) ? $d['public_data_sources'] : [];
    $impacto = is_array($gap['impacto_financeiro'] ?? null) ? $gap['impacto_financeiro'] : [];

    $flowSteps = ConsultoriaFlow::numberedSteps([
        ['label' => __('Resumo'), 'anchor' => 'cad-previsao-resumo', 'visible' => count($kpis) > 0],
        ['label' => __('Informes'), 'anchor' => 'cad-previsao-informes', 'visible' => count($informe['blocos'] ?? []) > 0],
        ['label' => __('Por etapa'), 'anchor' => 'cad-previsao-etapa', 'visible' => count($porEtapa) > 0],
        ['label' => __('Faixas CadÚnico'), 'anchor' => 'cad-previsao-faixas', 'visible' => count($porFaixa) > 0],
        ['label' => __('Metodologia'), 'anchor' => 'cad-previsao-metodo', 'visible' => count($metodologia) > 0],
    ]);
    $cadStep = ConsultoriaFlow::stepMap($flowSteps);

    $ring = static fn (string $s): string => match ($s) {
        'danger', 'rose' => 'border-l-rose-500',
        'warning', 'amber' => 'border-l-amber-500',
        'emerald', 'success' => 'border-l-teal-500',
        default => 'border-l-slate-400',
    };

    $available = (bool) ($d['available'] ?? false);
    $needsSpecificYear = $filters !== null && $filters->hasYearSelected() && $filters->isAllSchoolYears();
    $cadunicoDataReady = $yearFilterReady && ! $needsSpecificYear;
    $exportParams = is_array($d['export_params'] ?? null) ? $d['export_params'] : request()->only(['city_id', 'ano_letivo', 'escola_id', 'curso_id', 'turno_id']);
    if (! isset($exportParams['city_id']) && $selectedCity !== null) {
        $exportParams['city_id'] = $selectedCity->id;
    }
@endphp

<x-dashboard.consultoria-tab-frame
    tab="cadunico_previsao"
    tone="indigo"
    :title="__('Previsão CadÚnico — fora da rede')"
    :intro="$d['intro'] ?? __('Cruza agregados Cecad (CadÚnico) com matrículas i-Educar para estimar crianças em idade escolar não refletidas na rede municipal e o impacto FUNDEB indicativo.')"
    :footnote="$d['footnote'] ?? null"
    :error="$d['error'] ?? null"
    :year-filter-ready="$yearFilterReady"
    :municipality-context="$municipalityContext"
    :tab-data="['cadunicoPrevisaoData' => $d]"
    :flow-steps="$flowSteps"
    flow-tone="indigo"
    :no-year-message="__('Selecione um ano letivo específico e aplique os filtros para carregar a previsão CadÚnico.')"
>
    <x-slot name="links">
        <span class="text-slate-600 dark:text-slate-400">{{ __('Relacionado:') }}</span>
        <x-consultoria-tab-link tab="enrollment" :label="__('Matrículas')" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="comparativo" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="fundeb" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="inclusion" :label="__('Inclusão')" class="text-xs" />
    </x-slot>

    @if ($needsSpecificYear)
        <p class="serv-callout serv-callout--warning text-sm">
            {{ __('A previsão CadÚnico exige um ano letivo específico. Nos filtros superiores, escolha um ano (não «Todos os anos») e clique em Aplicar filtros.') }}
        </p>
    @endif

    @if (! $yearFilterReady)
        <p class="serv-callout text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
            {{ __('Após aplicar cidade e ano letivo, o painel cruza agregados Cecad (MDS) com matrículas i-Educar. Enquanto isso, pode importar dados em Admin → CadÚnico/Cecad ou via `cadunico:sync-city`.') }}
        </p>
    @endif

    @if (filled($d['error'] ?? null) && (! $yearFilterReady || $needsSpecificYear))
        <div class="serv-callout serv-callout--danger text-sm">{{ $d['error'] }}</div>
    @endif

    @if (count($publicSources['categories'] ?? []) > 0)
        <x-dashboard.consultoria-public-sources :catalog="$publicSources" anchor="cad-previsao-fontes" />
    @endif

    @if (! ($gap['available'] ?? false) && $cadunicoDataReady)
        <p class="serv-callout serv-callout--warning text-sm">
            {{ __('Sincronize agregados Cecad (MDS) para o município e ano do filtro.') }}
            @if (Auth::user()?->canViewAdminDashboard())
                <a href="{{ route('admin.cadunico-sync.index') }}" class="font-medium underline">{{ __('Admin → CadÚnico / Cecad') }}</a>
                {{ __('ou') }}
            @endif
            <code class="text-xs">php artisan cadunico:sync-city {id} --ano={{ $filters?->ano_letivo ?? 'AAAA' }}</code>.
        </p>
    @endif

    @php
        $hasBody = count($kpis) > 0
            || count($informe['blocos'] ?? []) > 0
            || count($porEtapa) > 0
            || count($porFaixa) > 0
            || count($alerts) > 0
            || count($metodologia) > 0
            || count($publicSources['categories'] ?? []) > 0;
    @endphp
    @if ($cadunicoDataReady && ! $hasBody && ! filled($d['error'] ?? null))
        <p class="serv-callout text-sm text-slate-700 dark:text-slate-300">
            {{ __('Não há dados para exibir neste recorte. Confirme ano letivo, importação Cecad e conexão i-Educar.') }}
        </p>
    @endif

    @if ($cadunicoDataReady && $available)
        <div class="rounded-lg border border-indigo-200/80 dark:border-indigo-800/50 bg-indigo-50/40 dark:bg-indigo-950/25 px-4 py-4 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Exportar previsão CadÚnico') }}</h3>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                    {{ __('KPIs, lacuna por etapa, faixas etárias, impacto FUNDEB e informes — para reunião com a gestão.') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <a
                    href="{{ route('dashboard.analytics.cadunico-previsao.export', array_merge($exportParams, ['format' => 'pdf'])) }}"
                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 shadow-sm"
                >
                    {{ __('PDF') }}
                </a>
                <a
                    href="{{ route('dashboard.analytics.cadunico-previsao.export', array_merge($exportParams, ['format' => 'csv'])) }}"
                    class="serv-btn-secondary serv-btn-secondary--teal"
                >
                    {{ __('CSV') }}
                </a>
                <a
                    href="{{ route('dashboard.analytics.cadunico-previsao.export', array_merge($exportParams, ['format' => 'xlsx'])) }}"
                    class="serv-btn-secondary serv-btn-secondary--indigo"
                >
                    {{ __('Excel') }}
                </a>
            </div>
        </div>
    @endif

    @if (count($kpis) > 0)
        <x-dashboard.consultoria-section
            :step="$cadStep['cad-previsao-resumo'] ?? null"
            anchor="cad-previsao-resumo"
            :title="__('Indicadores principais')"
            :subtitle="($gap['cadunico_imported_at'] ?? null) ? __('CadÚnico importado em :data', ['data' => $gap['cadunico_imported_at']]) : ''"
        >
            <x-dashboard.consultoria-kpi-grid :items="$kpis" class="grid-cols-2 md:grid-cols-3 2xl:grid-cols-5 gap-2" />
            @if (filled($impacto['formula'] ?? null))
                <p class="serv-callout text-sm mt-3">{{ $impacto['formula'] }}</p>
            @endif
        </x-dashboard.consultoria-section>
    @endif

    @if (count($informe['blocos'] ?? []) > 0)
        @include('dashboard.analytics.partials.comparativo-informe', [
            'informe' => $informe,
            'anchor' => 'cad-previsao-informes',
            'title' => __('Informes CadÚnico'),
        ])
    @endif

    @if (count($porEtapa) > 0)
        <x-dashboard.consultoria-section
            :step="$cadStep['cad-previsao-etapa'] ?? null"
            anchor="cad-previsao-etapa"
            :title="__('Lacuna por nível de ensino (FUNDEB)')"
            :subtitle="__('CadÚnico estimado × matrículas i-Educar — impacto com VAAF de referência.')"
        >
            <div class="serv-panel overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Nível') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('CadÚnico (est.)') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('i-Educar') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Fora da rede') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('FUNDEB indic.') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($porEtapa as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium text-serv-navy dark:text-slate-100">{{ $row['etapa'] ?? '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ isset($row['cadunico_estimado']) ? number_format((int) $row['cadunico_estimado'], 0, ',', '.') : '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((int) ($row['ieducar_matriculas'] ?? 0), 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold text-amber-700 dark:text-amber-300">{{ $row['gap_fmt'] ?? '0' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['fundeb_gap_label'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-dashboard.consultoria-section>
    @endif

    @if (count($porFaixa) > 0)
        <x-dashboard.consultoria-section
            :step="$cadStep['cad-previsao-faixas'] ?? null"
            anchor="cad-previsao-faixas"
            :title="__('Faixas etárias no CadÚnico')"
            :subtitle="__('Extração Cecad — população 4-17 anos por faixa.')"
        >
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($porFaixa as $faixa)
                    <div class="serv-panel p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">{{ $faixa['faixa'] ?? '' }}</p>
                        <p class="text-2xl font-semibold tabular-nums text-serv-navy dark:text-slate-100 mt-1">{{ number_format((int) ($faixa['cadunico'] ?? 0), 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </x-dashboard.consultoria-section>
    @endif

    @if (count($alerts) > 0)
        <ul class="space-y-2">
            @foreach ($alerts as $alert)
                <li class="serv-panel border-l-4 {{ $ring((string) ($alert['tone'] ?? 'slate')) }} px-4 py-3 text-sm">
                    <p class="font-semibold text-serv-navy dark:text-slate-100">{{ $alert['title'] ?? '' }}</p>
                    <p class="text-slate-700 dark:text-slate-300 mt-0.5">{{ $alert['message'] ?? '' }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    @php
        $metodologiaRows = count($metodologia) > 0 ? $metodologia : [
            ['step' => '1', 'text' => __('Importar agregados municipais do Cecad (CSV) — sem dados pessoais.')],
            ['step' => '2', 'text' => __('Contar crianças/jovens 4-17 anos no CadÚnico no exercício de referência.')],
            ['step' => '3', 'text' => __('Comparar com matrículas ativas i-Educar no mesmo ano letivo e filtros.')],
            ['step' => '4', 'text' => __('Estimar lacuna e impacto FUNDEB indicativo (VAAF × população fora da rede).')],
        ];
        $showMetodologia = $cadunicoDataReady || ! $yearFilterReady || $needsSpecificYear;
    @endphp
    @if ($showMetodologia)
        <x-dashboard.consultoria-section
            :step="$cadStep['cad-previsao-metodo'] ?? null"
            anchor="cad-previsao-metodo"
            :title="__('Metodologia e limitações')"
        >
            <ol class="list-decimal list-inside text-sm text-slate-700 dark:text-slate-300 space-y-1">
                @foreach ($metodologiaRows as $step)
                    <li><span class="font-medium">{{ $step['step'] ?? '' }}.</span> {{ $step['text'] ?? '' }}</li>
                @endforeach
            </ol>
            @if (filled($gap['nota'] ?? null))
                <p class="serv-callout text-xs mt-3">{{ $gap['nota'] }}</p>
            @endif
            @if (filled($d['footnote'] ?? null))
                <p class="serv-callout text-xs mt-3">{{ $d['footnote'] }}</p>
            @endif
        </x-dashboard.consultoria-section>
    @endif
</x-dashboard.consultoria-tab-frame>
