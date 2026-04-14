@props(['schoolUnitsData', 'yearFilterReady' => true, 'chartExportContext' => []])

@php
    $tab = is_array($schoolUnitsData) ? ($schoolUnitsData['tab'] ?? []) : [];
    $markers = $tab['markers'] ?? [];
    $transport = $tab['transport'] ?? null;
    $waiting = $tab['waiting'] ?? null;
    $geoNote = $tab['geo_note'] ?? null;
    $geoSource = $tab['geo_source'] ?? null;
    $geoAttribution = is_array($tab['geo_attribution'] ?? null) ? $tab['geo_attribution'] : [];
    $mapScope = $tab['map_scope'] ?? 'matricula';
    $showWaitingCapacity = (bool) ($tab['show_waiting_capacity'] ?? true);
    $geoDistribution = is_array($tab['geo_distribution'] ?? null) ? $tab['geo_distribution'] : null;
    $tabErr = $tab['error'] ?? null;
    $topErr = is_array($schoolUnitsData) ? ($schoolUnitsData['error'] ?? null) : null;
    $inepCatalogUrl = 'https://www.gov.br/inep/pt-br/acesso-a-informacao/dados-abertos/inep-data/catalogo-de-escolas';
    $markerCount = is_array($markers) ? count($markers) : 0;
    $mapPopupFootnote = __('O IDEB e o SAEB não são fornecidos pelo serviço ArcGIS; use o botão do QEdu ou o portal do INEP para indicadores oficiais por escola.');
    $qeduEscolaBaseUrl = rtrim((string) config('ieducar.inep_geocoding.qedu_escola_base_url', 'https://www.qedu.org.br/escola'), '/');
@endphp

<div class="space-y-6">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        @if ($mapScope === 'rede_escola')
            {{ __('Mapa OpenStreetMap com unidades da tabela escola. Coordenadas: cadastro local e, quando existir código INEP, Catálogo INEP (ArcGIS). Secções abaixo resumem capacidade, geografia e cobertura — evite comparar totais duplicados entre blocos; use o mapa principal para detalhe por unidade (modal).') }}
        @elseif ($mapScope === 'geo_cache')
            {{ __('Mapa a partir do cache local (school_unit_geos): não há matrículas no âmbito do filtro ou a consulta não devolveu escolas; posições vêm das coordenadas/INEP já guardados. Indicadores de turma/lista podem estar vazios.') }}
        @else
            {{ __('Mapa OpenStreetMap; prioridade a coordenadas na escola (i-Educar), depois INEP/ArcGIS. As secções seguintes condensam listas de espera, distribuição geográfica e cobertura; o mapa grande e o modal concentram a leitura por escola.') }}
        @endif
    </p>

    @if ($topErr)
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $topErr }}
        </div>
    @endif
    @if ($tabErr && $tabErr !== $topErr)
        <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 px-4 py-3 text-xs text-amber-900 dark:text-amber-100">
            {{ $tabErr }}
        </div>
    @endif

    {{-- 1) ORIGEM DOS DADOS --}}
    @if ($yearFilterReady && $geoAttribution !== [])
        <div class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-950/25 p-4 shadow-sm">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 mt-0.5 text-sky-700 dark:text-sky-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-9.75V21m-6 0h6m-9.75 0h3.75m-3.75 0V3.375c0-.621.504-1.125 1.125-1.125h9c.621 0 1.125.504 1.125 1.125V6.75" />
                </svg>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-sky-950 dark:text-sky-100">{{ __('ORIGEM DOS DADOS') }}</h3>
                    <p class="mt-1 text-xs text-sky-900/90 dark:text-sky-200/90 leading-relaxed">
                        {{ __('Fontes e regras usadas para obter coordenadas (base local e geocodificação INEP via ArcGIS quando necessário).') }}
                    </p>
                </div>
            </div>

            <ul class="mt-3 list-disc list-inside space-y-1.5 text-xs text-sky-900/95 dark:text-sky-200/95 leading-relaxed">
                @foreach ($geoAttribution as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-sky-800/90 dark:text-sky-300/90">
                <a href="{{ $inepCatalogUrl }}" class="font-medium text-sky-800 dark:text-sky-200 underline break-all" target="_blank" rel="noopener noreferrer">{{ $inepCatalogUrl }}</a>
            </p>
            @if ($geoSource === 'db')
                <p class="mt-2 text-[11px] font-medium text-sky-900 dark:text-sky-100">{{ __('Marcadores atuais: coordenadas na base local.') }}</p>
            @elseif ($geoSource === 'inep_arcgis')
                <p class="mt-2 text-[11px] font-medium text-sky-900 dark:text-sky-100">{{ __('Marcadores atuais: Catálogo de Escolas INEP (ArcGIS).') }}</p>
            @elseif ($geoSource === 'local_cache')
                <p class="mt-2 text-[11px] font-medium text-sky-900 dark:text-sky-100">{{ __('Marcadores atuais: cache local (school_unit_geos), sem lat/lng na escola neste momento.') }}</p>
            @elseif ($geoSource === 'mixed')
                <p class="mt-2 text-[11px] font-medium text-sky-900 dark:text-sky-100">{{ __('Marcadores atuais: combinação de base i-Educar, cache local e/ou Catálogo INEP.') }}</p>
            @elseif ($geoSource === 'none')
                <p class="mt-2 text-[11px] text-sky-800/85 dark:text-sky-200/85">{{ __('Ainda sem marcadores: confira código INEP e colunas de latitude/longitude.') }}</p>
            @endif
        </div>
    @endif

    {{-- 2) MAPA DAS UNIDADES ESCOLARES --}}
    @if ($yearFilterReady)
        <div class="rounded-xl border border-emerald-200/90 dark:border-emerald-800/80 bg-white dark:bg-gray-900 overflow-hidden shadow-sm ring-1 ring-emerald-100/80 dark:ring-emerald-900/40" x-data="{ geoHelpOpen: false }">
            <div class="border-b border-emerald-100 dark:border-emerald-900/50 px-4 py-3 bg-emerald-50/90 dark:bg-emerald-950/40">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <div class="flex items-start gap-3 min-w-0 flex-1">
                        <svg class="h-5 w-5 mt-0.5 shrink-0 text-emerald-800 dark:text-emerald-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.75 3.75H8.25A2.25 2.25 0 0 1 6 18.75V5.25A2.25 2.25 0 0 1 8.25 3h7.5A2.25 2.25 0 0 1 18 5.25v13.5A2.25 2.25 0 0 1 15.75 21Z" />
                        </svg>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold uppercase tracking-wide text-emerald-950 dark:text-emerald-100">{{ __('MAPA DAS UNIDADES ESCOLARES') }}</h3>
                            <p class="mt-1 text-xs text-emerald-900/85 dark:text-emerald-200/90 leading-relaxed">
                                @if ($markerCount > 0)
                                    {{ __('Clique num marcador para ver dados da base local, Catálogo INEP (ArcGIS) quando existir e links (QEdu). Com várias unidades, linhas tracejadas discretas ligam cada escola aos vizinhos mais próximos (grafo leve).') }}
                                @else
                                    {{ __('Sem coordenadas para posicionar unidades. Verifique latitude/longitude na base ou código INEP para geocodificação.') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-300/90 bg-white px-3 py-2.5 text-xs font-semibold text-emerald-900 shadow-sm hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-100 dark:hover:bg-emerald-900/60 sm:py-2"
                        @click="geoHelpOpen = true"
                    >
                        <svg class="h-4 w-4 text-emerald-700 dark:text-emerald-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        {{ __('Informações (mapa e coordenadas)') }}
                    </button>
                </div>
            </div>

            @if ($markerCount > 0)
                <div class="relative z-0" x-data="schoolUnitsMap(@js($markers), @js($mapPopupFootnote), { qeduEscolaBaseUrl: @js($qeduEscolaBaseUrl) })">
                    <div x-ref="mapContainer" class="z-0 h-[min(32rem,62vh)] w-full min-h-[280px] bg-slate-100 dark:bg-slate-900 [&_.leaflet-container]:h-full [&_.leaflet-container]:z-[1]"></div>

                    {{-- Modal (identidade do sistema) --}}
                    <template x-teleport="body">
                        <div
                            x-show="modalOpen"
                            x-transition.opacity.duration.150ms
                            @keydown.escape.window="closeSchoolModal()"
                            class="fixed inset-0 z-[240] flex items-center justify-center p-3 sm:p-4"
                            style="display: none;"
                            x-cloak
                        >
                            <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="closeSchoolModal()"></div>
                            <div
                                class="relative z-10 flex max-h-[95vh] w-full min-h-0 max-w-5xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800"
                                role="dialog"
                                aria-modal="true"
                            >
                                <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                                    <div class="min-w-0">
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 break-words" x-text="modal?.title || '—'"></h3>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 font-medium text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-200">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s6-5.686 6-10a6 6 0 1 0-12 0c0 4.314 6 10 6 10Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
                                                </svg>
                                                <span x-text="modal?.fonte_coordenada_label || (modal?.fonte_coordenada ? ('Fonte: ' + modal.fonte_coordenada) : 'Fonte: —')"></span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-900/60 dark:text-slate-200" x-show="modal?.inep">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0-9-9 9 9 0 0 0 9 9Z" />
                                                </svg>
                                                <span x-text="'INEP: ' + (modal?.inep || '—')"></span>
                                            </span>
                                            <span class="text-gray-500 dark:text-gray-400" x-show="modal?.status" x-text="modal?.status"></span>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        @click="closeSchoolModal()"
                                        title="{{ __('Fechar') }}"
                                        aria-label="{{ __('Fechar') }}"
                                    >
                                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain px-4 py-4 space-y-4 [scrollbar-gutter:stable]">
                                    {{-- Localização: duas colunas (geográfico | endereços) --}}
                                    <div class="w-full rounded-xl border border-slate-200/90 bg-gradient-to-b from-slate-50/95 to-white dark:from-slate-900/50 dark:to-gray-900/80 dark:border-slate-600 p-4 shadow-sm">
                                        <p class="text-center text-xs font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">{{ __('Localização') }}</p>
                                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6 md:items-start">
                                            <div class="min-w-0 rounded-lg border border-sky-200/80 bg-sky-50/80 px-3 py-3 dark:border-sky-900/50 dark:bg-sky-950/30">
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-900 dark:text-sky-100">{{ __('Dados geográficos') }}</p>
                                                <p class="mt-1 text-[11px] text-sky-700/90 dark:text-sky-200/80" x-text="modal?.fonte_coordenada_label || '—'"></p>
                                                <template x-if="modal?.geo_divergence && (modal.geo_divergence.official_lat != null || modal.geo_divergence.ieducar_lat != null || modal.geo_divergence.has_divergence)">
                                                    <div class="mt-3 rounded-md border border-amber-200/90 bg-amber-50/90 px-2 py-2 dark:border-amber-800/50 dark:bg-amber-950/35">
                                                        <p class="text-[11px] font-semibold text-amber-950 dark:text-amber-100">{{ __('Comparação i-Educar × INEP') }}</p>
                                                        <p class="mt-2 text-2xl font-bold tabular-nums text-amber-950 dark:text-amber-50" x-text="modal?.geo_divergence?.meters != null ? (Math.round(Number(modal.geo_divergence.meters)).toLocaleString('pt-BR') + ' m') : '—'"></p>
                                                        <dl class="mt-2 space-y-1 text-[11px] text-amber-950 dark:text-amber-100/95">
                                                            <div class="flex justify-between gap-2" x-show="modal?.geo_divergence?.ieducar_lat != null">
                                                                <dt class="opacity-80">{{ __('Lat/Lng i-Educar') }}</dt>
                                                                <dd class="tabular-nums text-right" x-text="(modal.geo_divergence.ieducar_lat != null && modal.geo_divergence.ieducar_lng != null) ? (Number(modal.geo_divergence.ieducar_lat).toFixed(5) + ', ' + Number(modal.geo_divergence.ieducar_lng).toFixed(5)) : '—'"></dd>
                                                            </div>
                                                            <div class="flex justify-between gap-2" x-show="modal?.geo_divergence?.official_lat != null">
                                                                <dt class="opacity-80">{{ __('Lat/Lng INEP') }}</dt>
                                                                <dd class="tabular-nums text-right" x-text="(modal.geo_divergence.official_lat != null && modal.geo_divergence.official_lng != null) ? (Number(modal.geo_divergence.official_lat).toFixed(5) + ', ' + Number(modal.geo_divergence.official_lng).toFixed(5)) : '—'"></dd>
                                                            </div>
                                                        </dl>
                                                    </div>
                                                </template>
                                                <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400 leading-snug" x-show="modal?.meta" x-text="modal?.meta"></p>
                                            </div>
                                            <div class="min-w-0 md:border-l md:border-slate-200 md:pl-6 dark:md:border-slate-600">
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('Endereço') }}</p>
                                                <p class="mt-2 text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">{{ __('Cadastro i-Educar') }}</p>
                                                <p class="mt-1 text-sm text-slate-900 dark:text-slate-100 leading-relaxed break-words" x-text="(modal?.base?.endereco_cadastro || modal?.base?.endereco) ? (modal.base.endereco_cadastro || modal.base.endereco) : '—'"></p>
                                                <template x-if="modal?.base?.endereco_inep && String(modal.base.endereco_inep).trim() !== ''">
                                                    <div class="mt-3 border-t border-slate-200/80 pt-3 dark:border-slate-600/80">
                                                        <p class="text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">{{ __('Catálogo INEP (ArcGIS)') }}</p>
                                                        <p class="mt-1 text-sm text-slate-800 dark:text-slate-200 leading-relaxed break-words" x-text="modal.base.endereco_inep"></p>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50/70 dark:bg-gray-900/40 p-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Contato (cadastro i-Educar)') }}</p>
                                            <dl class="mt-2 space-y-1 text-sm text-gray-800 dark:text-gray-200">
                                                <div class="flex items-start justify-between gap-2">
                                                    <dt class="text-gray-500 dark:text-gray-400 inline-flex items-center gap-1">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 0 0 2.25-2.25v-1.372a1.125 1.125 0 0 0-.852-1.091l-4.423-1.106a1.125 1.125 0 0 0-1.173.417l-.97 1.293a1.125 1.125 0 0 1-1.21.38 12.035 12.035 0 0 1-7.143-7.143 1.125 1.125 0 0 1 .38-1.21l1.293-.97a1.125 1.125 0 0 0 .417-1.173L6.963 3.102A1.125 1.125 0 0 0 5.872 2.25H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>
                                                        {{ __('Telefone') }}
                                                    </dt>
                                                    <dd class="text-right break-words" x-text="modal?.contato?.telefone || '—'"></dd>
                                                </div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <dt class="text-gray-500 dark:text-gray-400 inline-flex items-center gap-1">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15A2.25 2.25 0 0 1 2.25 17.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15A2.25 2.25 0 0 0 2.25 6.75m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                                                        {{ __('E-mail') }}
                                                    </dt>
                                                    <dd class="text-right break-words" x-text="modal?.contato?.email || '—'"></dd>
                                                </div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <dt class="text-gray-500 dark:text-gray-400 inline-flex items-center gap-1">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0v.75a.75.75 0 0 1-.75.75H5.25a.75.75 0 0 1-.75-.75v-.75Z" /></svg>
                                                        {{ __('Gestor') }}
                                                    </dt>
                                                    <dd class="text-right break-words" x-text="modal?.contato?.gestor || '—'"></dd>
                                                </div>
                                            </dl>
                                        </div>

                                        <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50/70 dark:bg-gray-900/40 p-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Matrículas, capacidade e vagas (turmas no filtro)') }}</p>
                                            <dl class="mt-2 space-y-1 text-sm text-gray-800 dark:text-gray-200">
                                                <div class="flex items-start justify-between gap-2">
                                                    <dt class="text-gray-500 dark:text-gray-400 inline-flex items-center gap-1">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18" /><path stroke-linecap="round" stroke-linejoin="round" d="M7 15l3-3 3 3 6-6" /></svg>
                                                        {{ __('Matrículas') }}
                                                    </dt>
                                                    <dd class="text-right tabular-nums" x-text="(modal?.base?.matriculas ?? null) === null ? '—' : Number(modal.base.matriculas).toLocaleString('pt-BR')"></dd>
                                                </div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <dt class="text-gray-500 dark:text-gray-400 inline-flex items-center gap-1">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15M4.5 12h15M4.5 17.25h15" /></svg>
                                                        {{ __('Capacidade') }}
                                                    </dt>
                                                    <dd class="text-right tabular-nums" x-text="(modal?.base?.capacidade_declarada ?? null) === null ? '—' : Number(modal.base.capacidade_declarada).toLocaleString('pt-BR')"></dd>
                                                </div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <dt class="text-gray-500 dark:text-gray-400 inline-flex items-center gap-1">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m9-9H3" /></svg>
                                                        {{ __('Vagas') }}
                                                    </dt>
                                                    <dd class="text-right tabular-nums" x-text="(modal?.base?.vagas_disponiveis ?? null) === null ? '—' : Number(modal.base.vagas_disponiveis).toLocaleString('pt-BR')"></dd>
                                                </div>
                                            </dl>
                                        </div>
                                    </div>

                                    <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800/40 p-3" x-show="Array.isArray(modal?.oferta) && modal.oferta.length">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Cursos e anos/séries ofertados (turmas no ano e filtros)') }}</p>
                                        <ul class="mt-2 list-disc list-outside space-y-1 pl-5 text-sm text-gray-800 dark:text-gray-200">
                                            <template x-for="(line, oi) in (modal?.oferta || [])" :key="oi">
                                                <li x-text="line"></li>
                                            </template>
                                        </ul>
                                    </div>

                                    <div class="rounded-lg border border-amber-200/80 bg-amber-50/80 dark:border-amber-900/50 dark:bg-amber-950/20 p-3" x-show="modal?.conciliation && modal?.conciliation?.catalogo_disponivel">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-200">{{ __('Alerta de conciliação (INEP × local)') }}</p>
                                        <p class="mt-2 text-sm text-amber-950 dark:text-amber-100 leading-relaxed">
                                            {{ __('Foram detectadas diferenças potenciais (nome/telefone/endereço) entre a base local e o catálogo do INEP. Use para validação de cadastro.') }}
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-2" x-show="Array.isArray(modal?.inep_links) && (modal.inep_links.filter(ln => ln && ln.id !== 'qedu').length > 0)">
                                        <template x-for="(ln, li) in (modal?.inep_links || [])" :key="li + '-' + (ln?.url || '')">
                                            <a
                                                x-show="ln && ln.id !== 'qedu'"
                                                class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-white px-3 py-2 text-xs font-medium text-indigo-800 shadow-sm hover:bg-indigo-50 dark:border-indigo-800 dark:bg-gray-900 dark:text-indigo-200 dark:hover:bg-indigo-950/50"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                :href="ln.url"
                                                x-text="ln.label || 'Link'"
                                            ></a>
                                        </template>
                                    </div>
                                </div>

                                <div class="shrink-0 border-t border-gray-100 px-4 py-3 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/40 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <a
                                        x-show="modal?.inep && modal?.qedu?.page_url && modal.qedu.page_url !== '#'"
                                        class="inline-flex w-full sm:w-auto items-center justify-center rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        :href="modal.qedu.page_url"
                                    >
                                        {{ __('QEdu — ficha e indicadores (abre em nova aba)') }}
                                    </a>
                                    <button
                                        type="button"
                                        class="w-full sm:w-auto rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:ml-auto"
                                        @click="closeSchoolModal()"
                                    >
                                        {{ __('Fechar') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            @else
                <div class="px-4 py-10 text-center text-sm text-gray-600 dark:text-gray-400 border-t border-emerald-100/60 dark:border-emerald-900/40">
                    {{ __('Sem marcadores no mapa para os filtros atuais.') }}
                    @if ($geoNote)
                        <span class="block mt-2 text-xs text-amber-800 dark:text-amber-200/90">{{ $geoNote }}</span>
                    @endif
                </div>
            @endif

            <template x-teleport="body">
                <div
                    x-show="geoHelpOpen"
                    x-transition.opacity.duration.150ms
                    @keydown.escape.window="geoHelpOpen = false"
                    class="fixed inset-0 z-[250] flex items-center justify-center p-3 sm:p-4"
                    style="display: none;"
                    x-cloak
                >
                    <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="geoHelpOpen = false" aria-hidden="true"></div>
                    <div
                        class="relative z-10 flex max-h-[95vh] w-full min-h-0 max-w-2xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="school-units-geo-help-title"
                    >
                        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                            <h3 id="school-units-geo-help-title" class="pr-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                                {{ __('Entenda o mapa e as coordenadas') }}
                            </h3>
                            <button
                                type="button"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                @click="geoHelpOpen = false"
                                title="{{ __('Fechar') }}"
                                aria-label="{{ __('Fechar') }}"
                            >
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain px-4 py-4 text-sm text-gray-700 dark:text-gray-300 space-y-5 leading-relaxed [scrollbar-gutter:stable]">
                            <p class="text-gray-600 dark:text-gray-400">
                                {{ __('Este painel mostra onde ficam as escolas no território, para apoiar planeamento, transporte e análise territorial. O fundo do mapa é o OpenStreetMap (mapa colaborativo mundial), não é um produto do MEC nem do INEP.') }}
                            </p>

                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ __('O que são os pontos no mapa?') }}</h4>
                                <p class="mt-2">
                                    {{ __('Cada marcador representa uma unidade escolar no âmbito dos filtros que escolheu (por exemplo ano letivo e, se aplicável, escola ou segmento). Se não houver matrículas ou turmas no filtro, o sistema pode mudar o modo de visualização — nesse caso o texto acima do mapa explica.') }}
                                </p>
                            </div>

                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ __('De onde vêm as coordenadas (em linguagem simples)?') }}</h4>
                                <ul class="mt-2 list-disc list-outside space-y-2 pl-5">
                                    <li>{{ __('Primeiro: se a escola tiver latitude e longitude registadas na própria base i-Educar (cadastro da escola), essas coordenadas são usadas — são a referência preferida para o dia a dia do município.') }}</li>
                                    <li>{{ __('Se ainda não houver coordenadas na escola, o sistema pode usar uma cópia guardada localmente (cache) de uma sincronização anterior, para não deixar o mapa vazio.') }}</li>
                                    <li>{{ __('Se existir o código INEP da escola, pode ser consultado o Catálogo Nacional de Escolas (INEP/MEC), através de um serviço público de mapas (ArcGIS). Isso posiciona a escola segundo o cadastro nacional, que pode ser útil para comparar com o cadastro local.') }}</li>
                                </ul>
                            </div>

                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ __('Por que às vezes aparece “divergência”?') }}</h4>
                                <p class="mt-2">
                                    {{ __('A escola na base municipal e a mesma escola no cadastro nacional podem não coincidir exatamente no mapa (por exemplo atualização em datas diferentes ou pequenas diferenças de registo). O painel pode indicar a distância em metros entre os dois pontos e um limiar configurável: acima desse valor, considera-se uma divergência a merecer atenção para conferência do cadastro — não é um erro automático, é um alerta para decisão.') }}
                                </p>
                            </div>

                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ __('O que ver ao clicar numa escola?') }}</h4>
                                <p class="mt-2">
                                    {{ __('Abre-se um painel com contactos e números de matrículas e vagas segundo os filtros atuais, indicação da fonte da coordenada e, quando existir, comparação com o INEP e links úteis (por exemplo o QEdu para indicadores oficiais divulgados pelo governo). O mapa em si não substitui relatórios oficiais do Censo ou do INEP.') }}
                                </p>
                            </div>

                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ __('Outras secções desta aba') }}</h4>
                                <p class="mt-2">
                                    {{ __('Abaixo do mapa pode haver resumos de capacidade das turmas, distribuição geográfica (totais), um mapa de “cobertura” com tamanho dos marcadores proporcional às matrículas, e transporte escolar — cada bloco responde a uma pergunta diferente; use o mapa principal para o detalhe por escola.') }}
                                </p>
                            </div>
                        </div>
                        <div class="shrink-0 border-t border-gray-100 px-4 py-3 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
                            <button
                                type="button"
                                class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                @click="geoHelpOpen = false"
                            >
                                {{ __('Fechar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endif

    {{-- 3) LISTA DE CAPACIDADE DAS TURMAS --}}
    @if ($showWaitingCapacity && $waiting && is_array($waiting))
        <div class="rounded-xl border border-emerald-100 dark:border-emerald-900/50 bg-emerald-50/70 dark:bg-emerald-950/25 p-4 shadow-sm">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 mt-0.5 text-emerald-800 dark:text-emerald-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6v12m-7.5-9v6m15-3H3" />
                </svg>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-emerald-950 dark:text-emerald-100">{{ __('LISTA DE CAPACIDADE DAS TURMAS') }}</h3>
                    <p class="mt-1 text-xs text-emerald-900/90 dark:text-emerald-100/90 leading-relaxed">
                        {{ __('Resumo de capacidade declarada, vagas e lista de espera com base em turmas/matrículas (quando as colunas existirem na base).') }}
                    </p>
                </div>
            </div>

            <p class="mt-2 text-xs text-emerald-900/90 dark:text-emerald-100/90">{{ $waiting['texto'] ?? '' }}</p>
            <dl class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm text-emerald-900 dark:text-emerald-100">
                @if (($waiting['turmas_com_lista'] ?? null) !== null)
                    <div class="rounded-lg bg-white/80 dark:bg-gray-900/40 px-3 py-2">
                        <dt class="text-[11px] uppercase text-emerald-700 dark:text-emerald-300">{{ __('Turmas com lista > 0') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ number_format((int) $waiting['turmas_com_lista']) }}</dd>
                    </div>
                @endif
                @if (($waiting['soma_lista'] ?? null) !== null)
                    <div class="rounded-lg bg-white/80 dark:bg-gray-900/40 px-3 py-2">
                        <dt class="text-[11px] uppercase text-emerald-700 dark:text-emerald-300">{{ __('Soma lista de espera') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ number_format((int) $waiting['soma_lista']) }}</dd>
                    </div>
                @endif
                @if (($waiting['vagas_declaradas'] ?? null) !== null)
                    <div class="rounded-lg bg-white/80 dark:bg-gray-900/40 px-3 py-2">
                        <dt class="text-[11px] uppercase text-emerald-700 dark:text-emerald-300">{{ __('Capacidade declarada (soma)') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ number_format((int) $waiting['vagas_declaradas']) }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    @endif

    {{-- 4) DISTRIBUIÇÃO GEOGRÁFICA --}}
    @if ($yearFilterReady && $geoDistribution !== null)
        <div class="rounded-xl border border-violet-200 dark:border-violet-900/50 bg-violet-50/70 dark:bg-violet-950/25 p-4 shadow-sm">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 mt-0.5 text-violet-800 dark:text-violet-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20.25H7.5a2.25 2.25 0 0 1-2.25-2.25V6A2.25 2.25 0 0 1 7.5 3.75H9m6 16.5h1.5A2.25 2.25 0 0 0 18.75 18V6a2.25 2.25 0 0 0-2.25-2.25H15m-6 0h6m-6 0V20.25m6-16.5V20.25" />
                </svg>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-violet-950 dark:text-violet-100">{{ __('DISTRIBUIÇÃO GEOGRÁFICA') }}</h3>
                    <p class="mt-1 text-xs text-violet-900/90 dark:text-violet-200/90 leading-relaxed">
                        @if (($geoDistribution['map_scope'] ?? '') === 'rede_escola')
                            {{ __('Resumo das unidades com posição no mapa (modo rede — cadastro na tabela escola).') }}
                        @elseif (($geoDistribution['map_scope'] ?? '') === 'geo_cache')
                            {{ __('Resumo com base no cache local (school_unit_geos): escolas com INEP ou coordenadas guardadas, quando o âmbito de matrículas não devolve unidades.') }}
                        @else
                            {{ __('Resumo das unidades no âmbito de matrículas ativas nos filtros e com coordenadas disponíveis.') }}
                        @endif
                    </p>
                </div>
            </div>

            <dl class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-2 text-sm text-violet-900 dark:text-violet-100">
                <div class="rounded-lg bg-white/80 dark:bg-gray-900/40 px-3 py-2">
                    <dt class="text-[11px] uppercase text-violet-700 dark:text-violet-300">{{ __('Escolas no escopo') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format((int) ($geoDistribution['escolas_no_escopo'] ?? 0)) }}</dd>
                </div>
                <div class="rounded-lg bg-white/80 dark:bg-gray-900/40 px-3 py-2">
                    <dt class="text-[11px] uppercase text-violet-700 dark:text-violet-300">{{ __('Coord. na base') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format((int) ($geoDistribution['com_coordenadas_base'] ?? 0)) }}</dd>
                </div>
                <div class="rounded-lg bg-white/80 dark:bg-gray-900/40 px-3 py-2">
                    <dt class="text-[11px] uppercase text-violet-700 dark:text-violet-300">{{ __('Coord. via INEP') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format((int) ($geoDistribution['com_coordenadas_inep'] ?? 0)) }}</dd>
                </div>
                @if (array_key_exists('com_coordenadas_cache_local', $geoDistribution))
                    <div class="rounded-lg bg-white/80 dark:bg-gray-900/40 px-3 py-2">
                        <dt class="text-[11px] uppercase text-violet-700 dark:text-violet-300">{{ __('Só cache local') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ number_format((int) ($geoDistribution['com_coordenadas_cache_local'] ?? 0)) }}</dd>
                    </div>
                @endif
                <div class="rounded-lg bg-white/80 dark:bg-gray-900/40 px-3 py-2">
                    <dt class="text-[11px] uppercase text-violet-700 dark:text-violet-300">{{ __('Total com posição') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format((int) ($geoDistribution['total_com_coordenadas'] ?? 0)) }}</dd>
                </div>
                <div class="rounded-lg bg-white/80 dark:bg-gray-900/40 px-3 py-2">
                    <dt class="text-[11px] uppercase text-violet-700 dark:text-violet-300">{{ __('Marcadores no mapa') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format((int) ($geoDistribution['marcadores_exibidos'] ?? 0)) }} / {{ number_format((int) ($geoDistribution['limite_marcadores'] ?? 120)) }}</dd>
                </div>
                @if (array_key_exists('inep_geocoding_ativo', $geoDistribution))
                    <div class="rounded-lg bg-white/80 dark:bg-gray-900/40 px-3 py-2 col-span-2 sm:col-span-1">
                        <dt class="text-[11px] uppercase text-violet-700 dark:text-violet-300">{{ __('Geocodificação INEP') }}</dt>
                        <dd class="font-semibold">{{ ($geoDistribution['inep_geocoding_ativo'] ?? false) ? __('Ativa') : __('Desativada') }}</dd>
                    </div>
                @endif
            </dl>

            <div class="mt-4 border-t border-violet-200/60 dark:border-violet-900/40 pt-4">
                <h4 class="text-[11px] font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-200">{{ __('Leitura no mapa principal') }}</h4>
                <p class="mt-1 text-[11px] text-violet-900/80 dark:text-violet-200/80 leading-relaxed">
                    @if ($markerCount > 0)
                        {{ __('Use o mapa na secção «Mapa das unidades escolares» (acima): clicar no marcador abre o modal com contacto, matrículas/vagas e links INEP/QEdu. Evita-se aqui um segundo mapa com os mesmos dados.') }}
                    @else
                        {{ __('Quando existirem marcadores, o mapa principal acima concentra a navegação por unidade.') }}
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- 5) COBERTURA DAS MATRÍCULAS --}}
    @if ($yearFilterReady)
        <div class="rounded-xl border border-fuchsia-200 dark:border-fuchsia-900/50 bg-fuchsia-50/70 dark:bg-fuchsia-950/20 p-4 shadow-sm">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 mt-0.5 text-fuchsia-800 dark:text-fuchsia-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M6 15l3-3 3 3 6-6" />
                </svg>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-fuchsia-950 dark:text-fuchsia-100">{{ __('COBERTURA DAS MATRÍCULAS') }}</h3>
                    <p class="mt-1 text-xs text-fuchsia-900/90 dark:text-fuchsia-200/90 leading-relaxed">
                        {{ __('Mapa de cobertura no escopo dos filtros. O raio do marcador é proporcional ao volume de matrículas da unidade (proxy visual de densidade/cobertura).') }}
                    </p>
                </div>
            </div>

            @if ($markerCount > 0)
                <div class="mt-3 rounded-lg overflow-hidden border border-fuchsia-200/70 dark:border-fuchsia-900/50 bg-white/80 dark:bg-gray-900/30" x-data="schoolUnitsMap(@js($markers), @js($mapPopupFootnote), { mode: 'coverage', qeduEscolaBaseUrl: @js($qeduEscolaBaseUrl) })">
                    <div x-ref="mapContainer" class="h-[min(22rem,52vh)] w-full min-h-[260px] bg-slate-100 dark:bg-slate-900 [&_.leaflet-container]:h-full [&_.leaflet-container]:z-[1]"></div>
                </div>
                <p class="mt-2 text-[11px] text-fuchsia-900/75 dark:text-fuchsia-200/75">
                    {{ __('Para curvas de nível, isócronas ou heatmap real (polígonos por área de abrangência), é necessário disponibilizar GeoJSON por unidade. Este mapa já melhora a leitura com base nas matrículas disponíveis.') }}
                </p>
            @else
                <div class="mt-3 rounded-lg bg-white/60 dark:bg-gray-900/30 border border-fuchsia-200/70 dark:border-fuchsia-900/50 px-3 py-3 text-xs text-fuchsia-900/80 dark:text-fuchsia-200/80">
                    {{ __('Sem marcadores para exibir cobertura neste momento.') }}
                </div>
            @endif
        </div>
    @endif

    @if ($geoNote && $markerCount > 0)
        <p class="text-xs text-amber-800 dark:text-amber-200/90 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 rounded-lg px-3 py-2">{{ $geoNote }}</p>
    @endif

    {{-- 6) TRANSPORTE ESCOLAR --}}
    @if ($transport && is_array($transport))
        <div class="rounded-xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50/60 dark:bg-indigo-950/30 p-4">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 mt-0.5 text-indigo-800 dark:text-indigo-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 1 0 3 0 1.5 1.5 0 0 0-3 0ZM12.75 18.75a1.5 1.5 0 1 0 3 0 1.5 1.5 0 0 0-3 0ZM3 6.75h13.5l2.25 6.75H6.75L5.25 9H3V6.75Z" />
                </svg>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-indigo-950 dark:text-indigo-100">{{ __('TRANSPORTE ESCOLAR') }}</h3>
                    <p class="mt-1 text-xs text-indigo-900/90 dark:text-indigo-200/90 leading-relaxed">
                        {{ __('Indicadores e notas de transporte escolar calculados a partir dos dados de matrícula (quando disponíveis).') }}
                    </p>
                </div>
            </div>

            <p class="mt-2 text-xs text-indigo-900/90 dark:text-indigo-200/90 leading-relaxed">{{ $transport['texto'] ?? '' }}</p>
            @if (! empty($transport['linhas']))
                <ul class="mt-3 space-y-1 text-xs font-mono text-indigo-900 dark:text-indigo-100">
                    @foreach ($transport['linhas'] as $ln)
                        <li>{{ $ln }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
