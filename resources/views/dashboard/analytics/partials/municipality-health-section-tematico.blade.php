@php
    $h = is_array($healthData ?? null) ? $healthData : [];
    $thematicBlocks = is_array($h['thematic_blocks'] ?? null) ? $h['thematic_blocks'] : [];
    $diagStep = is_array($diagStep ?? null) ? $diagStep : [];
@endphp

@if (count($thematicBlocks) > 0)
    <x-dashboard.consultoria-section
        :step="$diagStep['diag-tematico'] ?? null"
        anchor="diag-tematico"
        :title="__('Leitura temática')"
        :subtitle="__('Consolida i-Educar com indicadores públicos quando disponíveis.')"
    >
        <x-dashboard.consultoria-thematic-blocks :blocks="$thematicBlocks" />
    </x-dashboard.consultoria-section>
@else
    <p class="serv-callout text-sm text-slate-600 dark:text-slate-400">{{ __('Leitura temática indisponível para este filtro.') }}</p>
@endif

@if (is_array($h['summary_patch'] ?? null))
    <script type="application/json" data-health-summary-patch>@json($h['summary_patch'])</script>
@endif
