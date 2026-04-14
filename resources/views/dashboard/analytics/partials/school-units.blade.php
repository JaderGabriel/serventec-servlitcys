@props(['schoolUnitsData', 'yearFilterReady' => true, 'chartExportContext' => []])

@php
    $tab = is_array($schoolUnitsData) ? ($schoolUnitsData['tab'] ?? []) : [];
    $markers = $tab['markers'] ?? [];
    $transport = $tab['transport'] ?? null;
    $waiting = $tab['waiting'] ?? null;
    $geoNote = $tab['geo_note'] ?? null;
    $geoSource = $tab['geo_source'] ?? null;
    $geoAttribution = is_array($tab['geo_attribution'] ?? null) ? $tab['geo_attribution'] : [];
    $tabErr = $tab['error'] ?? null;
    $topErr = is_array($schoolUnitsData) ? ($schoolUnitsData['error'] ?? null) : null;
    $inepCatalogUrl = 'https://www.gov.br/inep/pt-br/acesso-a-informacao/dados-abertos/inep-data/catalogo-de-escolas';
@endphp

<div class="space-y-6">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('O mapa usa OpenStreetMap. As coordenadas vêm primeiro da base i-Educar (latitude/longitude na escola); se não existirem, tenta-se o código INEP da escola contra o Catálogo de Escolas (INEP/MEC), serviço público ArcGIS. Transporte e lista de espera dependem das colunas existentes na sua base.') }}
    </p>

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
                <p class="mt-2 text-[11px] text-sky-800/85 dark:text-sky-200/85">{{ __('Ainda sem marcadores: veja a mensagem abaixo ou confira código INEP e colunas de latitude/longitude.') }}</p>
            @endif
        </div>
    @endif

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

    @if ($geoNote)
        <p class="text-xs text-amber-800 dark:text-amber-200/90 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 rounded-lg px-3 py-2">{{ $geoNote }}</p>
    @endif

    @if ($yearFilterReady && count($markers) > 0)
        <div
            class="rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 overflow-hidden shadow-sm"
            x-data="schoolUnitsMap(@js($markers))"
        >
            <div class="border-b border-gray-100 dark:border-gray-700 px-4 py-3 bg-gray-50/90 dark:bg-gray-800/80">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Mapa das unidades') }}</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Clique num marcador para ver a origem da coordenada (base i-Educar ou INEP).') }}</p>
            </div>
            <div
                x-ref="mapContainer"
                class="z-0 h-[min(28rem,55vh)] w-full min-h-[240px] bg-slate-100 dark:bg-slate-900 [&_.leaflet-container]:h-full [&_.leaflet-container]:z-[1]"
            ></div>
        </div>
    @elseif ($yearFilterReady)
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-8 text-center text-sm text-gray-500 dark:text-gray-400">
            {{ __('Sem coordenadas para montar o mapa com os filtros atuais. Preencha latitude/longitude na escola ou o código INEP para consulta ao Catálogo INEP.') }}
        </div>
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

    @if ($waiting && is_array($waiting))
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
