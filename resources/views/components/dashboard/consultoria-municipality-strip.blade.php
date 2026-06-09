@props([
    'city' => null,
    'filters' => null,
    'yearFilterReady' => false,
])

@if ($city)
    <div class="flex flex-wrap items-center gap-2" role="navigation" aria-label="{{ __('Atalhos de análise') }}">
        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Atalhos:') }}</span>
        <button
            type="button"
            class="serv-tab-pill"
            x-on:click="$dispatch('set-analytics-tab', 'municipality_health')"
        >
            {{ __('Diagnóstico') }}
        </button>
        <button
            type="button"
            class="serv-tab-pill"
            x-on:click="$dispatch('set-analytics-tab', 'fundeb')"
        >
            {{ __('FUNDEB') }}
        </button>
        <button
            type="button"
            class="serv-tab-pill"
            x-on:click="$dispatch('set-analytics-tab', 'discrepancies')"
        >
            {{ __('Discrepâncias') }}
        </button>
        @unless ($yearFilterReady)
            <span class="text-xs text-amber-700 dark:text-amber-300">
                {{ __('Defina o ano letivo no rodapé para carregar indicadores.') }}
            </span>
        @endunless
    </div>
@endif
