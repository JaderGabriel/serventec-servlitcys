@props(['schoolUnitsData', 'yearFilterReady' => true, 'chartExportContext' => []])

@php
    $tab = is_array($schoolUnitsData) ? ($schoolUnitsData['tab'] ?? []) : [];
    $markers = $tab['markers'] ?? [];
    $transport = $tab['transport'] ?? null;
    $waiting = $tab['waiting'] ?? null;
    $geoNote = $tab['geo_note'] ?? null;
    $tabErr = $tab['error'] ?? null;
    $topErr = is_array($schoolUnitsData) ? ($schoolUnitsData['error'] ?? null) : null;
@endphp

<div class="space-y-6">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Unidades com coordenadas aparecem no mapa (OpenStreetMap). Transporte e lista de espera dependem das colunas existentes na sua base i-Educar.') }}
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

    @if ($geoNote)
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $geoNote }}</p>
    @endif

    @if ($yearFilterReady && count($markers) > 0)
        <div
            class="rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 overflow-hidden shadow-sm"
            x-data="schoolUnitsMap(@js($markers))"
        >
            <div class="border-b border-gray-100 dark:border-gray-700 px-4 py-3 bg-gray-50/90 dark:bg-gray-800/80">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Mapa das unidades (coordenadas na base)') }}</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Marcadores = escolas com latitude/longitude preenchidas.') }}</p>
            </div>
            <div
                x-ref="mapContainer"
                class="z-0 h-[min(28rem,55vh)] w-full min-h-[240px] bg-slate-100 dark:bg-slate-900 [&_.leaflet-container]:h-full [&_.leaflet-container]:z-[1]"
            ></div>
        </div>
    @elseif ($yearFilterReady)
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-8 text-center text-sm text-gray-500 dark:text-gray-400">
            {{ __('Sem coordenadas geográficas nas escolas do filtro — o mapa não pode ser montado.') }}
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
