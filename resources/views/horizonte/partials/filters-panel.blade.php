<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="relative sm:col-span-2 lg:col-span-2">
            <label for="horizonte-search" class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ __('Buscar município') }}</label>
            <input
                id="horizonte-search"
                type="search"
                x-model="searchQuery"
                :disabled="pageLoading"
                placeholder="{{ __('Nome ou IBGE…') }}"
                autocomplete="off"
                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 disabled:opacity-60"
            />
            <ul
                x-show="searchSuggestions.length > 0 && searchQuery.trim().length >= 2"
                x-cloak
                class="absolute z-30 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg text-sm"
            >
                <template x-for="item in searchSuggestions" :key="item.ibge">
                    <li>
                        <button type="button" class="w-full px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700/80" @click="pickSearch(item)">
                            <span x-text="item.name + ' — ' + item.uf"></span>
                            <span class="block text-xs text-gray-500" x-text="'IBGE ' + item.ibge"></span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    <div>
        <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">{{ __('Vista de decisão') }}</p>
        <div class="flex flex-wrap gap-1.5 text-xs">
            @foreach ($viewPresets as $key => $preset)
                <button
                    type="button"
                    :disabled="pageLoading || (isOverviewMode && @js($key) !== 'high_pressure' && @js($key) !== 'all')"
                    class="serv-horizonte-tier-pill bg-white/80 text-slate-700 ring-slate-200/80 dark:bg-slate-900/60 dark:text-slate-200 dark:ring-slate-600"
                    :class="(viewPreset === @js($key) || (viewPreset === 'custom' && filterTier === @js($key))) ? 'is-active bg-teal-50 dark:bg-teal-950/40' : ''"
                    @click="setViewPreset(@js($key))"
                >
                    <span class="inline-block h-2 w-2 rounded-full" style="background-color: {{ $preset['color'] }}"></span>
                    {{ $preset['label'] }}
                </button>
            @endforeach
            @foreach ($tierPresets as $key => $preset)
                <button
                    type="button"
                    :disabled="pageLoading || isOverviewMode"
                    class="serv-horizonte-tier-pill bg-white/80 text-slate-700 ring-slate-200/80 dark:bg-slate-900/60 dark:text-slate-200 dark:ring-slate-600"
                    :class="filterTier === @js($key) ? 'is-active bg-teal-50 dark:bg-teal-950/40' : ''"
                    @click="setViewPreset('custom'); setFilterTier(@js($key))"
                >
                    <span class="inline-block h-2 w-2 rounded-full" style="background-color: {{ $preset['color'] }}"></span>
                    {{ $preset['label'] }}
                </button>
            @endforeach
            <label class="inline-flex items-center gap-1.5 serv-horizonte-tier-pill bg-white/80 ring-slate-200/80 dark:bg-slate-900/60 dark:ring-slate-600" x-show="isRegionalMode" x-cloak>
                <input type="checkbox" x-model="hideConsultoria" @change="markFiltersCustom()" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" />
                <span>{{ __('Ocultar consultoria') }}</span>
            </label>
            <label class="inline-flex items-center gap-1.5 serv-horizonte-tier-pill bg-white/80 ring-slate-200/80 dark:bg-slate-900/60 dark:ring-slate-600" x-show="isRegionalMode" x-cloak>
                <input type="checkbox" x-model="hideApproxOnMap" class="rounded border-gray-300 text-amber-600" :disabled="pageLoading" />
                <span>{{ __('Só coord. IBGE no mapa') }}</span>
            </label>
            @if ($canManageSge)
                <label class="inline-flex items-center gap-1.5 serv-horizonte-tier-pill bg-amber-50/80 ring-amber-200/80 dark:bg-amber-950/30 dark:ring-amber-900/50" x-show="isRegionalMode" x-cloak>
                    <input type="checkbox" x-model="onlyMissingSge" @change="markFiltersCustom()" class="rounded border-gray-300 text-amber-600" :disabled="pageLoading" />
                    <span>{{ __('Só sem SGE') }}</span>
                </label>
            @endif
        </div>
        <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400" x-show="isOverviewMode" x-cloak>
            {{ __('No Brasil, seleccione uma UF para aplicar a camada municipal.') }}
        </p>
    </div>

    <div x-show="isRegionalMode" x-cloak class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-xs">
        <label class="flex flex-col gap-1">
            <span class="text-slate-600 dark:text-slate-400 font-medium">{{ __('Propensão mínima') }}</span>
            <input type="range" min="0" max="100" step="5" x-model.number="minSuccessScore" @input="markFiltersCustom()" class="w-full accent-rose-600" :disabled="pageLoading" />
            <span class="flex items-baseline justify-between gap-2">
                <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minSuccessScore + '/100'"></span>
                <span class="serv-horizonte-score-hint" x-text="'Alta ≥ ' + scoreThresholds.high"></span>
            </span>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-slate-600 dark:text-slate-400 font-medium">{{ __('Benefício mínimo') }}</span>
            <input type="range" min="0" max="100" step="5" x-model.number="minBenefitScore" @input="markFiltersCustom()" class="w-full accent-teal-600" :disabled="pageLoading" />
            <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minBenefitScore + '/100'"></span>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-slate-600 dark:text-slate-400 font-medium">{{ __('Matrículas mín.') }}</span>
            <select x-model.number="minMatriculas" @change="markFiltersCustom()" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm" :disabled="pageLoading">
                <option value="0">{{ __('Qualquer') }}</option>
                <option value="5000">5 000+</option>
                <option value="15000">15 000+</option>
                <option value="30000">30 000+</option>
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-slate-600 dark:text-slate-400 font-medium">{{ __('Pressão FUNDEB mín.') }}</span>
            <input type="range" min="0" max="100" step="5" x-model.number="minFinancial" @input="markFiltersCustom()" class="w-full accent-rose-700" :disabled="pageLoading || viewPreset === 'high_pressure'" />
            <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minFinancial + '/100'"></span>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-slate-600 dark:text-slate-400 font-medium">{{ __('Demanda social mín.') }}</span>
            <input type="range" min="0" max="100" step="5" x-model.number="minSocialDemand" @input="markFiltersCustom()" class="w-full accent-amber-600" :disabled="pageLoading" />
            <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minSocialDemand + '/100'"></span>
        </label>
    </div>

    <div x-show="isRegionalMode" x-cloak>
        <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">{{ __('Exigir fontes de dados') }}</p>
        <div class="flex flex-wrap gap-2">
            <label class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 ring-1 ring-slate-200/80 bg-white/80 dark:bg-slate-900/60 dark:ring-slate-600 has-[:checked]:ring-teal-400 has-[:checked]:bg-teal-50/80 dark:has-[:checked]:bg-teal-950/30">
                <input type="checkbox" x-model="requireFundeb" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>FUNDEB</span>
            </label>
            <label class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 ring-1 ring-slate-200/80 bg-white/80 dark:bg-slate-900/60 dark:ring-slate-600 has-[:checked]:ring-teal-400 has-[:checked]:bg-teal-50/80 dark:has-[:checked]:bg-teal-950/30">
                <input type="checkbox" x-model="requireCenso" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>Censo</span>
            </label>
            <label class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 ring-1 ring-slate-200/80 bg-white/80 dark:bg-slate-900/60 dark:ring-slate-600 has-[:checked]:ring-teal-400 has-[:checked]:bg-teal-50/80 dark:has-[:checked]:bg-teal-950/30">
                <input type="checkbox" x-model="requireSaeb" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>SAEB</span>
            </label>
            <label class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 ring-1 ring-slate-200/80 bg-white/80 dark:bg-slate-900/60 dark:ring-slate-600 has-[:checked]:ring-teal-400 has-[:checked]:bg-teal-50/80 dark:has-[:checked]:bg-teal-950/30">
                <input type="checkbox" x-model="requireCadunico" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>CadÚnico</span>
            </label>
        </div>
    </div>
</div>
