<section class="serv-panel overflow-hidden" aria-labelledby="home-map">
    <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 id="home-map" class="font-display text-lg font-semibold text-serv-navy">{{ __('Municípios implementados') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Passe o rato sobre o ponto para ver o resumo e abrir a consultoria.') }}
            </p>
        </div>
        <div class="flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>{{ __('Pronto') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>{{ __('Incompleto') }}</span>
            <a href="{{ route('cities.create') }}" class="serv-link">{{ __('Nova cidade') }}</a>
        </div>
    </div>

    <div
        class="relative"
        x-data="brazilMunicipalitiesMap(@js($mapMarkers))"
        x-init="init()"
    >
        <div x-ref="map" class="serv-brazil-map" role="application" aria-label="{{ __('Mapa do Brasil com municípios cadastrados') }}"></div>

        <div
            x-show="active"
            x-cloak
            x-transition.opacity.duration.150ms
            class="serv-brazil-map-tooltip"
            :style="tooltipStyle"
            @mouseenter="tooltipPinned = true"
            @mouseleave="tooltipPinned = false; active = null"
        >
            <template x-if="active">
                <div>
                    <p class="font-semibold text-slate-900 dark:text-slate-100" x-text="active.name + ' — ' + active.uf"></p>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300" x-text="active.summary"></p>
                    <p class="mt-1 text-xs" :class="active.status === 'ready' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'" x-text="active.status_label"></p>
                    <a :href="active.analytics_url" class="mt-3 inline-flex serv-btn-secondary text-xs">{{ __('Consultoria') }}</a>
                </div>
            </template>
        </div>

        @if (count($mapMarkers) === 0)
            <p class="absolute inset-0 flex items-center justify-center text-sm text-slate-500 dark:text-slate-400 bg-white/80 dark:bg-slate-900/80 pointer-events-none">
                {{ __('Nenhum município activo no mapa.') }}
                <a href="{{ route('cities.create') }}" class="serv-link ms-1">{{ __('Cadastrar') }}</a>
            </p>
        @endif
    </div>
</section>
