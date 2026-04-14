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
@endphp

<div class="space-y-6">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        @if ($mapScope === 'rede_escola')
            {{ __('O mapa usa OpenStreetMap e mostra unidades cadastradas na tabela escola (rede municipal). Quando não há posicionamento no âmbito de matrículas ativas nos filtros, este modo garante a visualização das unidades com coordenadas ou INEP. Transporte escolar continua baseado nas colunas de matrícula, se existirem.') }}
        @else
            {{ __('O mapa usa OpenStreetMap. As coordenadas vêm primeiro da base i-Educar (latitude/longitude na escola); se não existirem, tenta-se o código INEP da escola contra o Catálogo de Escolas (INEP/MEC), serviço público ArcGIS. Transporte e lista de espera dependem das colunas existentes na sua base.') }}
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

    @if ($yearFilterReady)
        {{-- Mapa em destaque: o cartão aparece sempre com ano aplicado; o mapa Leaflet só monta quando há marcadores. --}}
        <div class="rounded-xl border border-emerald-200/90 dark:border-emerald-800/80 bg-white dark:bg-gray-900 overflow-hidden shadow-sm ring-1 ring-emerald-100/80 dark:ring-emerald-900/40">
            <div class="border-b border-emerald-100 dark:border-emerald-900/50 px-4 py-3 bg-emerald-50/90 dark:bg-emerald-950/40">
                <h3 class="text-base font-semibold text-emerald-950 dark:text-emerald-100">{{ __('Mapa das unidades escolares') }}</h3>
                <p class="mt-1 text-xs text-emerald-900/85 dark:text-emerald-200/90 leading-relaxed">
                    @if ($markerCount > 0)
                        {{ __('Clique num marcador para ver dados da base local, catálogo INEP (ArcGIS) quando existir, conciliação de nomes/contactos e o atalho ao QEdu (IDEB/SAEB).') }}
                    @else
                        {{ __('Neste momento não há coordenadas para posicionar escolas. Verifique latitude/longitude na tabela escola ou código INEP para o Catálogo INEP.') }}
                    @endif
                </p>
            </div>
            @if ($markerCount > 0)
                <div
                    class="relative z-0"
                    x-data="schoolUnitsMap(@js($markers), @js($mapPopupFootnote))"
                >
                    <div
                        x-ref="mapContainer"
                        class="z-0 h-[min(28rem,55vh)] w-full min-h-[240px] bg-slate-100 dark:bg-slate-900 [&_.leaflet-container]:h-full [&_.leaflet-container]:z-[1]"
                    ></div>
                </div>
            @else
                <div class="px-4 py-10 text-center text-sm text-gray-600 dark:text-gray-400 border-t border-emerald-100/60 dark:border-emerald-900/40">
                    {{ __('Sem marcadores no mapa para os filtros atuais.') }}
                    @if ($geoNote)
                        <span class="block mt-2 text-xs text-amber-800 dark:text-amber-200/90">{{ $geoNote }}</span>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if ($yearFilterReady && $geoAttribution !== [])
        <div class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-950/25 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-sky-950 dark:text-sky-100">{{ __('Origem dos dados geográficos') }}</h3>
            <ul class="mt-2 list-disc list-inside space-y-1.5 text-xs text-sky-900/95 dark:text-sky-200/95 leading-relaxed">
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
            @elseif ($geoSource === 'mixed')
                <p class="mt-2 text-[11px] font-medium text-sky-900 dark:text-sky-100">{{ __('Marcadores atuais: mistura de coordenadas locais e do Catálogo INEP.') }}</p>
            @elseif ($geoSource === 'none')
                <p class="mt-2 text-[11px] text-sky-800/85 dark:text-sky-200/85">{{ __('Ainda sem marcadores: veja a mensagem no mapa ou confira código INEP e colunas de latitude/longitude.') }}</p>
            @endif
        </div>
    @endif

    @if ($yearFilterReady && $geoDistribution !== null)
        <div class="rounded-xl border border-violet-200 dark:border-violet-900/50 bg-violet-50/70 dark:bg-violet-950/25 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-violet-950 dark:text-violet-100">{{ __('Distribuição geográfica') }}</h3>
            <p class="mt-1 text-xs text-violet-900/90 dark:text-violet-200/90 leading-relaxed">
                @if (($geoDistribution['map_scope'] ?? '') === 'rede_escola')
                    {{ __('Resumo das unidades com posição no mapa (modo rede — cadastro na tabela escola).') }}
                @else
                    {{ __('Resumo das unidades no âmbito de matrículas ativas nos filtros e com coordenadas disponíveis.') }}
                @endif
            </p>
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
        </div>
    @endif

    @if ($geoNote && $markerCount > 0)
        <p class="text-xs text-amber-800 dark:text-amber-200/90 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 rounded-lg px-3 py-2">{{ $geoNote }}</p>
    @endif

    @if ($transport && is_array($transport))
        <div class="rounded-xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50/60 dark:bg-indigo-950/30 p-4">
            <h3 class="text-sm font-semibold text-indigo-950 dark:text-indigo-100">{{ __('Transporte escolar (matrícula)') }}</h3>
            <p class="mt-1 text-xs text-indigo-900/90 dark:text-indigo-200/90 leading-relaxed">{{ $transport['texto'] ?? '' }}</p>
            @if (! empty($transport['linhas']))
                <ul class="mt-3 space-y-1 text-xs font-mono text-indigo-900 dark:text-indigo-100">
                    @foreach ($transport['linhas'] as $ln)
                        <li>{{ $ln }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($showWaitingCapacity && $waiting && is_array($waiting))
        <div class="rounded-xl border border-emerald-100 dark:border-emerald-900/50 bg-emerald-50/70 dark:bg-emerald-950/25 p-4">
            <h3 class="text-sm font-semibold text-emerald-950 dark:text-emerald-100">{{ __('Lista de espera e capacidade (turmas)') }}</h3>
            <p class="mt-1 text-xs text-emerald-900/90 dark:text-emerald-100/90">{{ $waiting['texto'] ?? '' }}</p>
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
</div>
