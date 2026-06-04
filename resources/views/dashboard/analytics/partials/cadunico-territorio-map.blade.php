@props([
    'territorial' => [],
    'schoolMarkers' => [],
])

@php
    $t = is_array($territorial) ? $territorial : [];
    $markers = is_array($t['markers'] ?? null) ? $t['markers'] : [];
    $schools = is_array($schoolMarkers) ? $schoolMarkers : [];
    $footnote = filled($t['nota'] ?? null) ? (string) $t['nota'] : null;
@endphp

<div
    class="space-y-2"
    x-data="cadunicoTerritoryMap(@js($markers), @js($schools), @js($footnote))"
    x-init="init()"
    @destroy.window="destroy()"
>
    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
        {{ __('Círculos laranja: pressão territorial (lacuna CadÚnico rateada). Pontos azuis: escolas da rede no filtro. Tamanho do círculo ∝ lacuna estimada no território.') }}
    </p>
    <div
        x-ref="mapContainer"
        class="z-0 h-[min(28rem,55vh)] w-full min-h-[240px] rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900 [&_.leaflet-container]:h-full [&_.leaflet-container]:z-[1]"
    ></div>
    @if ($footnote)
        <p class="text-[11px] text-slate-500 dark:text-slate-400 italic">{{ $footnote }}</p>
    @endif
</div>
