@php
    $compact = (bool) ($compact ?? false);
@endphp

<div class="serv-horizonte-filters {{ $compact ? 'serv-horizonte-filters--dock' : '' }}">
    {{-- 1. Audiência (lente de decisão) --}}
    <div class="serv-horizonte-filters__section">
        <div class="serv-horizonte-filters__section-head">
            <span class="serv-horizonte-filters__step">1</span>
            <div>
                <p class="serv-horizonte-filters__title">{{ __('Quem abordar') }}</p>
                <p class="serv-horizonte-filters__hint">{{ __('Uma audiência por vez — como camadas em ferramentas GIS.') }}</p>
            </div>
        </div>
        <div class="serv-horizonte-lens-grid" role="group" aria-label="{{ __('Lente de decisão') }}">
            @foreach ($viewPresets as $key => $preset)
                <button
                    type="button"
                    :disabled="pageLoading || (isOverviewMode && @js($key) !== 'high_pressure')"
                    class="serv-horizonte-lens-card"
                    :class="decisionLensKey === @js($key) ? 'is-active' : ''"
                    @click="applyDecisionLens(@js($key))"
                >
                    <span class="serv-horizonte-lens-card__dot" style="background-color: {{ $preset['color'] }}"></span>
                    <span class="serv-horizonte-lens-card__label">{{ $preset['label'] }}</span>
                </button>
            @endforeach
            @foreach ($tierPresets as $key => $preset)
                <button
                    type="button"
                    :disabled="pageLoading || isOverviewMode"
                    class="serv-horizonte-lens-card"
                    :class="decisionLensKey === @js($key) ? 'is-active' : ''"
                    @click="applyDecisionLens(@js($key))"
                >
                    <span class="serv-horizonte-lens-card__dot" style="background-color: {{ $preset['color'] }}"></span>
                    <span class="serv-horizonte-lens-card__label">{{ $preset['label'] }}</span>
                </button>
            @endforeach
        </div>
        <p class="serv-horizonte-filters__note" x-show="isOverviewMode" x-cloak>
            {{ __('No Brasil, abra uma UF para aplicar filtros municipais.') }}
        </p>
    </div>

    {{-- 2. Refinar (sliders) --}}
    <div class="serv-horizonte-filters__section" x-show="isRegionalMode" x-cloak>
        <div class="serv-horizonte-filters__section-head">
            <span class="serv-horizonte-filters__step">2</span>
            <div>
                <p class="serv-horizonte-filters__title">{{ __('Refinar recorte') }}</p>
                <p class="serv-horizonte-filters__hint">{{ __('Opcional — combina com a lente acima.') }}</p>
            </div>
        </div>

        <div class="relative mb-3">
            <label for="horizonte-search" class="serv-horizonte-filters__field-label">{{ __('Buscar município') }}</label>
            <input
                id="horizonte-search"
                type="search"
                x-model="searchQuery"
                @input="markFiltersCustom()"
                :disabled="pageLoading"
                placeholder="{{ __('Nome ou IBGE…') }}"
                autocomplete="off"
                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 disabled:opacity-60"
            />
            <ul
                x-show="searchSuggestions.length > 0 && searchQuery.trim().length >= 2"
                x-cloak
                class="absolute z-30 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg text-sm"
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

        <div class="serv-horizonte-refine-grid">
            <label class="serv-horizonte-refine-field">
                <span class="serv-horizonte-filters__field-label">{{ __('Propensão mín.') }}</span>
                <input type="range" min="0" max="100" step="5" x-model.number="minSuccessScore" @input="markFiltersCustom()" class="w-full accent-rose-600" :disabled="pageLoading" />
                <span class="flex items-baseline justify-between gap-2">
                    <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minSuccessScore + '/100'"></span>
                    <span class="serv-horizonte-score-hint" x-text="'Alta ≥ ' + scoreThresholds.high"></span>
                </span>
            </label>
            <label class="serv-horizonte-refine-field">
                <span class="serv-horizonte-filters__field-label">{{ __('Benefício mín.') }}</span>
                <input type="range" min="0" max="100" step="5" x-model.number="minBenefitScore" @input="markFiltersCustom()" class="w-full accent-teal-600" :disabled="pageLoading" />
                <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minBenefitScore + '/100'"></span>
            </label>
            <label class="serv-horizonte-refine-field">
                <span class="serv-horizonte-filters__field-label">{{ __('Matrículas mín.') }}</span>
                <select x-model.number="minMatriculas" @change="markFiltersCustom()" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm w-full" :disabled="pageLoading">
                    <option value="0">{{ __('Qualquer') }}</option>
                    <option value="5000">5 000+</option>
                    <option value="15000">15 000+</option>
                    <option value="30000">30 000+</option>
                </select>
            </label>
            <label class="serv-horizonte-refine-field">
                <span class="serv-horizonte-filters__field-label">{{ __('Pressão FUNDEB mín.') }}</span>
                <input type="range" min="0" max="100" step="5" x-model.number="minFinancial" @input="markFiltersCustom()" class="w-full accent-rose-700" :disabled="pageLoading || viewPreset === 'high_pressure'" />
                <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minFinancial + '/100'"></span>
            </label>
            <label class="serv-horizonte-refine-field">
                <span class="serv-horizonte-filters__field-label">{{ __('Demanda social mín.') }}</span>
                <input type="range" min="0" max="100" step="5" x-model.number="minSocialDemand" @input="markFiltersCustom()" class="w-full accent-amber-600" :disabled="pageLoading" />
                <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minSocialDemand + '/100'"></span>
            </label>
        </div>
    </div>

    {{-- 3. Mapa & dados --}}
    <div class="serv-horizonte-filters__section" x-show="isRegionalMode" x-cloak>
        <div class="serv-horizonte-filters__section-head">
            <span class="serv-horizonte-filters__step">3</span>
            <div>
                <p class="serv-horizonte-filters__title">{{ __('Mapa e qualidade') }}</p>
                <p class="serv-horizonte-filters__hint">{{ __('Performance em UFs extensas — preferir coord. IBGE.') }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            <label class="serv-horizonte-toggle-pill">
                <input type="checkbox" x-model="hideApproxOnMap" class="rounded border-gray-300 text-amber-600" :disabled="pageLoading" />
                <span>{{ __('Só coord. IBGE no mapa') }}</span>
            </label>
            <label class="serv-horizonte-toggle-pill" x-show="viewPreset === 'all'" x-cloak>
                <input type="checkbox" x-model="hideConsultoria" @change="markFiltersCustom()" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" />
                <span>{{ __('Ocultar consultoria') }}</span>
            </label>
            @if ($canManageSge)
                <label class="serv-horizonte-toggle-pill serv-horizonte-toggle-pill--amber">
                    <input type="checkbox" x-model="onlyMissingSge" @change="markFiltersCustom()" class="rounded border-gray-300 text-amber-600" :disabled="pageLoading" />
                    <span>{{ __('Só sem SGE') }}</span>
                </label>
            @endif
        </div>
        <p class="serv-horizonte-filters__field-label mt-3 mb-1.5">{{ __('Exigir fontes') }}</p>
        <div class="flex flex-wrap gap-1.5">
            <label class="serv-horizonte-source-pill">
                <input type="checkbox" x-model="requireFundeb" @change="markFiltersCustom()" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>FUNDEB</span>
            </label>
            <label class="serv-horizonte-source-pill">
                <input type="checkbox" x-model="requireCenso" @change="markFiltersCustom()" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>Censo</span>
            </label>
            <label class="serv-horizonte-source-pill">
                <input type="checkbox" x-model="requireSaeb" @change="markFiltersCustom()" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>SAEB</span>
            </label>
            <label class="serv-horizonte-source-pill">
                <input type="checkbox" x-model="requireCadunico" @change="markFiltersCustom()" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>CadÚnico</span>
            </label>
        </div>
    </div>

    <div class="serv-horizonte-filters__footer" x-show="isRegionalMode" x-cloak>
        <button type="button" class="serv-btn-secondary text-xs w-full" @click="resetFilters()" :disabled="pageLoading">
            {{ __('Repor vista padrão') }}
        </button>
    </div>
</div>
