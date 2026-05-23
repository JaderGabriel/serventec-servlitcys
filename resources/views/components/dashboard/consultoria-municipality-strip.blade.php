@props([
    'city' => null,
    'filters' => null,
    'yearFilterReady' => false,
])

@php
    use App\Support\Dashboard\IeducarFilterState;

    $cityName = $city?->name;
    $uf = $city?->uf;
    $yearLabel = null;
    if ($filters instanceof IeducarFilterState && $filters->hasYearSelected()) {
        $yearLabel = $filters->yearLabelForDisplay();
    }
@endphp

@if ($cityName)
    <div class="serv-municipality-strip" role="region" aria-label="{{ __('Município em análise') }}">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <p class="serv-eyebrow text-teal-100/90">{{ __('Município em análise') }}</p>
                <p class="font-display text-xl sm:text-2xl font-semibold text-white truncate">
                    {{ $cityName }}
                    @if ($uf)
                        <span class="text-teal-200/90 font-normal text-lg">— {{ $uf }}</span>
                    @endif
                </p>
                @if ($yearFilterReady && $yearLabel)
                    <p class="mt-1 text-sm text-teal-100/80">
                        {{ __('Recorte:') }} <span class="font-medium text-white">{{ $yearLabel }}</span>
                        {{ __('(aplique filtros de escola/curso/turno abaixo para refinar)') }}
                    </p>
                @elseif (! $yearFilterReady)
                    <p class="mt-1 text-sm text-amber-100/95">
                        {{ __('Selecione o ano letivo e aplique os filtros para carregar indicadores e saldo indicativo.') }}
                    </p>
                @endif
            </div>
            <div class="flex flex-col gap-3 shrink-0 w-full lg:w-[17.5rem]">
                @if ($city)
                    <x-city.reference-contact :city="$city" variant="agenda" tone="dark" class="w-full" />
                @endif
                <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="serv-tab-pill serv-tab-pill--on-dark"
                    x-on:click="$dispatch('set-analytics-tab', 'municipality_health')"
                >
                    {{ __('Diagnóstico') }}
                </button>
                <button
                    type="button"
                    class="serv-tab-pill serv-tab-pill--on-dark"
                    x-on:click="$dispatch('set-analytics-tab', 'fundeb')"
                >
                    {{ __('FUNDEB') }}
                </button>
                <button
                    type="button"
                    class="serv-tab-pill serv-tab-pill--on-dark"
                    x-on:click="$dispatch('set-analytics-tab', 'discrepancies')"
                >
                    {{ __('Discrepâncias') }}
                </button>
                </div>
            </div>
        </div>
    </div>
@endif
