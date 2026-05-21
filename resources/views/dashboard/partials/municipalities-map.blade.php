<section class="serv-panel overflow-hidden" aria-labelledby="home-map">
    <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 id="home-map" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100">{{ __('Municípios implementados') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Clique num ponto para ver a data de implementação e os anos letivos cadastrados na base i-Educar.') }}
            </p>
        </div>
        <div class="flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>{{ __('Activo') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>{{ __('Incompleto') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>{{ __('Inactivo') }}</span>
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
            @click.outside="closeTooltip()"
        >
            <template x-if="active">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100" x-text="active.name + ' — ' + active.uf"></p>
                            <p class="mt-0.5 text-xs" :class="active.status === 'ready' ? 'text-emerald-600 dark:text-emerald-400' : (active.status === 'incomplete' ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400')" x-text="active.status_label"></p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            x-on:click="closeTooltip()"
                            aria-label="{{ __('Fechar') }}"
                        >&times;</button>
                    </div>

                    <dl class="text-xs space-y-1 text-slate-600 dark:text-slate-300">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Implementação') }}</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-100" x-text="active.implemented_at_label || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-2" x-show="active.ibge">
                            <dt class="text-slate-500 dark:text-slate-400">IBGE</dt>
                            <dd class="font-mono" x-text="active.ibge"></dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Motor') }}</dt>
                            <dd x-text="active.driver"></dd>
                        </div>
                    </dl>

                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">{{ __('Anos letivos cadastrados') }}</p>
                        <p x-show="yearsLoading" class="text-xs text-slate-500 animate-pulse">{{ __('A carregar…') }}</p>
                        <p x-show="yearsError" class="text-xs text-amber-700 dark:text-amber-300" x-text="yearsError"></p>
                        <ul x-show="!yearsLoading && !yearsError && schoolYears.length > 0" class="max-h-36 overflow-y-auto space-y-1 pr-1">
                            <template x-for="item in schoolYears" :key="item.year">
                                <li class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                                    <span class="shrink-0" x-show="yearStateIcon(item.state) === 'open'" title="{{ __('Em andamento') }}">
                                        <svg class="h-4 w-4 text-emerald-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="10" cy="10" r="6"/></svg>
                                    </span>
                                    <span class="shrink-0" x-show="yearStateIcon(item.state) === 'closed'" title="{{ __('Fechado') }}">
                                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="9" width="10" height="7" rx="1"/><path d="M7 9V7a3 3 0 116 0v2"/></svg>
                                    </span>
                                    <span class="shrink-0" x-show="yearStateIcon(item.state) === 'unknown'" title="{{ __('Situação indisponível') }}">
                                        <svg class="h-4 w-4 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="10" cy="10" r="6" opacity="0.35"/></svg>
                                    </span>
                                    <span class="font-semibold tabular-nums" x-text="item.year"></span>
                                    <span class="text-slate-500 dark:text-slate-400 truncate" x-text="item.state_label"></span>
                                </li>
                            </template>
                        </ul>
                        <p x-show="!yearsLoading && !yearsError && schoolYears.length === 0 && active.status === 'ready'" class="text-xs text-slate-500">{{ __('Nenhum ano letivo encontrado na base.') }}</p>
                        <p x-show="!yearsLoading && !yearsError && schoolYears.length === 0 && active.status !== 'ready'" class="text-xs text-slate-500">{{ __('Configure a ligação i-Educar para listar anos.') }}</p>
                    </div>

                    <a :href="active.analytics_url" class="inline-flex serv-btn-secondary text-xs w-full justify-center">{{ __('Consultoria') }}</a>
                </div>
            </template>
        </div>

        @if (count($mapMarkers) === 0)
            <p class="absolute inset-0 flex items-center justify-center text-sm text-slate-500 dark:text-slate-400 bg-white/80 dark:bg-slate-900/80 pointer-events-none">
                {{ __('Nenhum município cadastrado.') }}
                <a href="{{ route('cities.create') }}" class="serv-link ms-1">{{ __('Cadastrar') }}</a>
            </p>
        @endif
    </div>
</section>
