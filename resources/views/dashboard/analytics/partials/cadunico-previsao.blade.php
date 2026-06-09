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
    $cenarios = is_array($gap['cenarios_financeiros'] ?? null) ? $gap['cenarios_financeiros'] : [];
    $vulnerabilidade = is_array($gap['vulnerabilidade'] ?? null) ? $gap['vulnerabilidade'] : [];
    $territorial = is_array($d['territorial'] ?? null) ? $d['territorial'] : [];
    $demandaOferta = is_array($d['demanda_oferta'] ?? null) ? $d['demanda_oferta'] : [];
    $rankingTerr = is_array($territorial['ranking'] ?? null) ? $territorial['ranking'] : [];

    $flowSteps = ConsultoriaFlow::numberedSteps([
        ['label' => __('Resumo'), 'anchor' => 'cad-previsao-resumo', 'visible' => count($kpis) > 0],
        ['label' => __('Demanda'), 'anchor' => 'cad-previsao-demanda', 'visible' => ($demandaOferta['available'] ?? false)],
        ['label' => __('Cenários'), 'anchor' => 'cad-previsao-cenarios', 'visible' => ($cenarios['available'] ?? false)],
        ['label' => __('Mapa'), 'anchor' => 'cad-previsao-mapa', 'visible' => count($territorial['markers'] ?? []) > 0],
        ['label' => __('Informes'), 'anchor' => 'cad-previsao-informes', 'visible' => count($informe['blocos'] ?? []) > 0],
        ['label' => __('Por etapa'), 'anchor' => 'cad-previsao-etapa', 'visible' => count($porEtapa) > 0],
        ['label' => __('Faixas'), 'anchor' => 'cad-previsao-faixas', 'visible' => count($porFaixa) > 0],
        ['label' => __('Territórios'), 'anchor' => 'cad-previsao-territorios', 'visible' => count($rankingTerr) > 0],
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

    @if ($yearFilterReady)
        @include('dashboard.analytics.partials.cadunico-faixas-fundeb-callout')
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

    @if ($demandaOferta['available'] ?? false)
        <x-dashboard.consultoria-section
            :step="$cadStep['cad-previsao-demanda'] ?? null"
            anchor="cad-previsao-demanda"
            :title="__('Demanda × oferta (indicativo)')"
            :subtitle="__('Ligação com backlog INT-01 — lacuna CadÚnico face à oferta municipal.')"
        >
            <p class="text-sm text-slate-700 dark:text-slate-300">{{ $demandaOferta['mensagem'] ?? '' }}</p>
            <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                <div class="serv-panel p-3">
                    <p class="text-xs text-slate-500 uppercase">{{ __('Demanda') }}</p>
                    <p class="font-semibold tabular-nums">{{ $demandaOferta['demanda_fmt'] ?? '—' }}</p>
                </div>
                <div class="serv-panel p-3">
                    <p class="text-xs text-slate-500 uppercase">{{ __('Oferta (matr.)') }}</p>
                    <p class="font-semibold tabular-nums">{{ number_format((int) ($demandaOferta['oferta_matriculas'] ?? 0), 0, ',', '.') }}</p>
                </div>
                <div class="serv-panel p-3">
                    <p class="text-xs text-slate-500 uppercase">{{ __('Cobertura') }}</p>
                    <p class="font-semibold tabular-nums">{{ $demandaOferta['cobertura_label'] ?? '—' }}</p>
                </div>
                <div class="serv-panel p-3">
                    <p class="text-xs text-slate-500 uppercase">{{ __('Territórios') }}</p>
                    <p class="font-semibold tabular-nums">{{ count($demandaOferta['territorios_prioritarios'] ?? []) }}</p>
                </div>
            </div>
        </x-dashboard.consultoria-section>
    @endif

    @if ($cenarios['available'] ?? false)
        <x-dashboard.consultoria-section
            :step="$cadStep['cad-previsao-cenarios'] ?? null"
            anchor="cad-previsao-cenarios"
            :title="__('Cenários financeiros sobre a lacuna')"
            :subtitle="__('NEE, AEE e VAAR — proporções observadas na rede; não identifica beneficiários no CadÚnico.')"
        >
            @if (filled($cenarios['aviso_geral'] ?? null))
                <p class="serv-callout text-sm mb-3">{{ $cenarios['aviso_geral'] }}</p>
            @endif
            <div class="serv-panel overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold">{{ __('Cenário') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold">{{ __('Qtd. indicativa') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold">{{ __('Valor/ano') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($cenarios['itens'] ?? [] as $item)
                            <tr>
                                <td class="px-3 py-2">
                                    <span class="font-medium">{{ $item['titulo'] ?? '' }}</span>
                                    @if (filled($item['aviso'] ?? null))
                                        <p class="text-[11px] text-slate-500 mt-0.5">{{ $item['aviso'] }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ isset($item['quantidade']) ? number_format((int) $item['quantidade'], 0, ',', '.') : '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ $item['valor_label'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-indigo-50/80 dark:bg-indigo-950/40 font-semibold">
                            <td class="px-3 py-2" colspan="2">{{ __('Soma indicativa dos cenários') }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $cenarios['total_cenarios_label'] ?? '—' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-dashboard.consultoria-section>
    @endif

    @if (($vulnerabilidade['available'] ?? false) && (($vulnerabilidade['pct_criancas_pbf_label'] ?? null) !== null))
        <x-dashboard.consultoria-section
            anchor="cad-previsao-vulnerabilidade"
            :title="__('Vulnerabilidade familiar (agregado)')"
            :subtitle="__('Indicadores Misocial/Cecad no município — sem endereço individual.')"
        >
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div class="serv-panel p-3">
                    <p class="text-xs uppercase text-slate-500">{{ __('Famílias cadastradas') }}</p>
                    <p class="text-lg font-semibold tabular-nums">{{ number_format((int) ($vulnerabilidade['familias_cadastradas'] ?? 0), 0, ',', '.') }}</p>
                </div>
                <div class="serv-panel p-3">
                    <p class="text-xs uppercase text-slate-500">{{ __('Crianças 4-17 CadÚnico') }}</p>
                    <p class="text-lg font-semibold tabular-nums">{{ number_format((int) ($vulnerabilidade['criancas_escolar_cadunico'] ?? 0), 0, ',', '.') }}</p>
                </div>
                <div class="serv-panel p-3">
                    <p class="text-xs uppercase text-slate-500">{{ __('Crianças PBF (est.)') }}</p>
                    <p class="text-lg font-semibold tabular-nums">{{ $vulnerabilidade['pct_criancas_pbf_label'] ?? '—' }}</p>
                </div>
                <div class="serv-panel p-3">
                    <p class="text-xs uppercase text-slate-500">{{ __('Fonte') }}</p>
                    <p class="text-sm font-medium">{{ $vulnerabilidade['fonte'] ?? '—' }}</p>
                </div>
            </div>
        </x-dashboard.consultoria-section>
    @endif

    @if (count($rankingTerr) > 0 || count($territorial['markers'] ?? []) > 0)
        @include('dashboard.analytics.partials.cadunico-pressao-callout')
    @endif

    @if (count($territorial['markers'] ?? []) > 0)
        <x-dashboard.consultoria-section
            :step="$cadStep['cad-previsao-mapa'] ?? null"
            anchor="cad-previsao-mapa"
            :title="__('Mapa de pressão territorial')"
            :subtitle="__(':total território(s) · priorize bairros/setores com maior lacuna e distância à escola.', [
                'total' => number_format((int) ($territorial['territorios_count'] ?? count($territorial['markers'] ?? [])), 0, ',', '.'),
            ])"
        >
            @include('dashboard.analytics.partials.cadunico-territorio-map', [
                'territorial' => $territorial,
                'schoolMarkers' => is_array($territorial['school_markers'] ?? null) ? $territorial['school_markers'] : [],
            ])
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
            :title="__('Faixas etárias — CadÚnico e lacuna')"
            :subtitle="__('Faixas 4–17 anos (pré-escola a ensino médio). Lacuna e FUNDEB indicativo por faixa = gap × VAAF; não inclui creche 0–3.')"
        >
            <div class="serv-panel overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold">{{ __('Faixa') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold">{{ __('CadÚnico') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold">{{ __('Rede (est.)') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold">{{ __('Lacuna') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold">{{ __('Cobertura') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold">{{ __('FUNDEB') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($porFaixa as $faixa)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $faixa['faixa'] ?? '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((int) ($faixa['cadunico'] ?? 0), 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((int) ($faixa['ieducar_estimado'] ?? 0), 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold text-amber-700 dark:text-amber-300">{{ $faixa['gap_fmt'] ?? '0' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $faixa['cobertura_label'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $faixa['fundeb_gap_label'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-dashboard.consultoria-section>
    @endif

    @if (count($rankingTerr) > 0)
        <x-dashboard.consultoria-section
            :step="$cadStep['cad-previsao-territorios'] ?? null"
            anchor="cad-previsao-territorios"
            :title="__('Prioridade por território')"
            :subtitle="__(':total território(s) · pressão = lacuna × vulnerabilidade × distância à escola. Código IBGE distingue setores com o mesmo nome.', [
                'total' => number_format((int) ($territorial['territorios_count'] ?? count($rankingTerr)), 0, ',', '.'),
            ])"
        >
            <div class="serv-panel overflow-x-auto max-h-96 overflow-y-auto">
                <table class="min-w-full text-sm divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold">{{ __('Território') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">{{ __('Código') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold">{{ __('Tipo') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold">{{ __('CadÚnico') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold">{{ __('Lacuna est.') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold">{{ __('Dist. escola') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold" title="{{ __('Índice de prioridade: lacuna × vulnerabilidade × distância à escola (ver destaque acima).') }}">
                                {{ __('Pressão') }}
                                <span class="block font-normal text-[10px] text-amber-700 dark:text-amber-300 normal-case">{{ __('prioridade') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($rankingTerr as $row)
                            <tr @class(['opacity-80' => empty($row['no_mapa'])])>
                                <td class="px-3 py-2 font-medium">
                                    {{ $row['nome'] ?? '' }}
                                    @if (empty($row['no_mapa']))
                                        <span class="block text-[10px] font-normal text-amber-700 dark:text-amber-300">{{ __('Sem coordenadas no mapa') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs font-mono text-slate-600 dark:text-slate-400">{{ $row['codigo'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs">{{ $row['tipo'] ?? '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((int) ($row['cadunico'] ?? 0), 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ $row['gap_fmt'] ?? '0' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ isset($row['distancia_escola_km']) ? number_format((float) $row['distancia_escola_km'], 1, ',', '.').' km' : '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ number_format((float) ($row['pressao'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
            ['step' => '2', 'text' => __('Contar população CadÚnico nas faixas 4–17 (escolaridade obrigatória); 0–3 só com CSV Cecad dedicado.')],
            ['step' => '3', 'text' => __('Comparar com matrículas ativas i-Educar no mesmo ano letivo e filtros.')],
            ['step' => '4', 'text' => __('Estimar lacuna e impacto FUNDEB indicativo (VAAF × fora da rede; cenários NEE/AEE/VAAR). VAAT e IEI de creche ficam fora desta aba.')],
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
