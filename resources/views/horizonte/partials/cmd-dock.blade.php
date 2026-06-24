{{-- Barra fixa de recorte + painel expansível de indicadores --}}
<section
    class="serv-horizonte-cmd"
    :class="cmdBarExpanded ? 'is-expanded' : 'is-collapsed'"
    x-ref="cmdDock"
    aria-label="{{ __('Painel de decisão') }}"
    data-horizonte-tour="kpi"
>
    <div class="serv-horizonte-cmd__bar" data-horizonte-tour="recorte">
        <div class="serv-horizonte-cmd__accent" aria-hidden="true"></div>

        <div class="serv-horizonte-cmd__bar-grid">
            <div class="serv-horizonte-cmd__recorte">
                <label class="serv-horizonte-cmd__recorte-label">
                    <span class="text-slate-500 shrink-0">{{ __('Recorte') }}</span>
                    <select
                        x-model="scopeUf"
                        @change="onScopeUfPick($event)"
                        :disabled="pageLoading || regionalLoading"
                        class="serv-horizonte-cmd__recorte-select"
                    >
                        <option value="">{{ __('Brasil (por UF)') }}</option>
                        @foreach ($ufNames as $code => $name)
                            <option value="{{ $code }}">{{ $code }} — {{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <span class="serv-horizonte-gis__mode-pill" :class="isOverviewMode ? 'is-national' : 'is-regional'">
                    <span x-show="isOverviewMode">{{ __('Visão nacional') }}</span>
                    <span x-show="isRegionalMode" x-cloak x-text="ufLabel(scopeUf)"></span>
                </span>
                <button
                    type="button"
                    x-show="isRegionalMode"
                    x-cloak
                    class="serv-btn-secondary text-xs shrink-0"
                    @click="backToOverview()"
                    :disabled="pageLoading || regionalLoading"
                >{{ __('← Brasil') }}</button>
            </div>

            <div class="serv-horizonte-cmd__highlight" role="status">
                <span class="serv-horizonte-cmd__highlight-item serv-horizonte-cmd__highlight-item--rose">
                    <span class="serv-horizonte-cmd__highlight-label">{{ __('Alta pressão') }}</span>
                    <span class="serv-horizonte-cmd__highlight-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.high_pressure)"></span>
                </span>
                <span class="serv-horizonte-cmd__highlight-sep" aria-hidden="true"></span>
                <span class="serv-horizonte-cmd__highlight-item">
                    <span class="serv-horizonte-cmd__highlight-label">{{ __('Prospectos') }}</span>
                    <span class="serv-horizonte-cmd__highlight-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.prospect_count)"></span>
                </span>
                <span class="serv-horizonte-cmd__highlight-sep" aria-hidden="true" x-show="isRegionalMode" x-cloak></span>
                <span class="serv-horizonte-cmd__highlight-item serv-horizonte-cmd__highlight-item--blue" x-show="isRegionalMode" x-cloak>
                    <span class="serv-horizonte-cmd__highlight-label">{{ __('No recorte') }}</span>
                    <span class="serv-horizonte-cmd__highlight-value tabular-nums" x-text="filteredCount.toLocaleString('pt-BR')"></span>
                </span>
                <span class="serv-horizonte-cmd__highlight-sep" aria-hidden="true"></span>
                <span class="serv-horizonte-cmd__highlight-item serv-horizonte-cmd__highlight-item--muted min-w-0">
                    <span class="serv-horizonte-cmd__highlight-label">{{ __('Lente') }}</span>
                    <span class="serv-horizonte-cmd__highlight-value truncate max-w-[9rem] sm:max-w-[14rem]" x-text="decisionLensLabel"></span>
                </span>
            </div>

            <div class="serv-horizonte-cmd__bar-actions">
                <button
                    type="button"
                    class="serv-btn-secondary text-xs xl:hidden"
                    data-horizonte-tour="filters-hint"
                    @click="openFiltersDock()"
                    :disabled="pageLoading || isOverviewMode"
                >
                    {{ __('Filtros') }}
                    <span x-show="activeFilterCount > 0" x-cloak class="ms-1 rounded-full bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold text-white tabular-nums" x-text="activeFilterCount"></span>
                </button>
                <button type="button" class="serv-btn-secondary text-xs hidden sm:inline-flex" @click="resetFilters()" :disabled="pageLoading">
                    {{ __('Vista padrão') }}
                </button>
                <button
                    type="button"
                    class="serv-horizonte-cmd__expand-btn"
                    @click="toggleCmdBarExpanded()"
                    :aria-expanded="cmdBarExpanded"
                    :title="cmdBarExpandLabel()"
                >
                    <span class="hidden sm:inline" x-text="cmdBarExpandLabel()"></span>
                    <svg
                        class="h-4 w-4 shrink-0 transition-transform duration-200"
                        :class="cmdBarExpanded ? 'rotate-180' : ''"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                        aria-hidden="true"
                    ><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
            </div>
        </div>
    </div>

    <div
        class="serv-horizonte-cmd__drawer"
        x-show="cmdBarExpanded"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
    >
        <div class="serv-horizonte-cmd__hero">
            <div class="serv-horizonte-cmd__primary">
                <p class="text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-300">{{ __('Prioridade comercial') }}</p>
                <p class="serv-horizonte-cmd__primary-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.high_pressure)">…</p>
                <p class="mt-1 text-sm text-rose-900/80 dark:text-rose-100/80 line-clamp-3">
                    {{ __('Municípios em alta pressão FUNDEB (≥ :min) ou alta propensão — camada inicial do mapa.', ['min' => $pressureMin]) }}
                </p>
            </div>
            <div class="serv-horizonte-cmd__metrics">
                <div class="serv-horizonte-cmd__metric" title="{{ $kpiHints['prospect_count']['hint'] ?? '' }}">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 truncate">{{ __('Prospectos') }}</p>
                    <p class="serv-horizonte-cmd__metric-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.prospect_count)">…</p>
                </div>
                <div class="serv-horizonte-cmd__metric" title="{{ $kpiHints['high_prospect']['hint'] ?? '' }}">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 truncate">{{ __('Alta propensão') }}</p>
                    <p class="serv-horizonte-cmd__metric-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.high_prospect)">…</p>
                </div>
                <div class="serv-horizonte-cmd__metric" title="{{ $kpiHints['consultoria_active']['hint'] ?? '' }}">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 truncate">{{ __('Consultoria') }}</p>
                    <p class="serv-horizonte-cmd__metric-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.consultoria_active)">…</p>
                </div>
                <div class="serv-horizonte-cmd__metric" title="{{ $kpiHints['prospect_matriculas']['hint'] ?? '' }}">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 truncate">{{ __('Matr. prospecto') }}</p>
                    <p class="serv-horizonte-cmd__metric-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(coverage.prospect_matriculas_censo)">…</p>
                </div>
            </div>
        </div>

        <div
            class="serv-horizonte-cmd__fundeb"
            x-show="isRegionalMode && ufFundebInsights"
            x-cloak
            data-horizonte-tour="fundeb-uf"
        >
            <div class="serv-horizonte-cmd__fundeb-head">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-200">
                        {{ __('FUNDEB estadual') }}
                    </p>
                    <p class="mt-0.5 text-sm font-semibold text-serv-navy dark:text-slate-100" x-text="ufFundebInsights?.uf_name || ufLabel(scopeUf)"></p>
                </div>
                <div class="serv-horizonte-cmd__fundeb-portaria min-w-0 text-right">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700/90 dark:text-amber-300/90">
                        {{ __('Portaria vigente') }}
                    </p>
                    <p class="mt-0.5 text-xs font-medium text-slate-700 dark:text-slate-200 line-clamp-2" x-text="ufFundebPortariaLabel()"></p>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400" x-show="ufFundebInsights?.portaria?.fundeb_imported_label" x-cloak>
                        <span>{{ __('Receita FNDE') }}:</span>
                        <span x-text="ufFundebInsights?.portaria?.fundeb_imported_label"></span>
                    </p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400" x-show="ufFundebInsights?.portaria?.transfer_imported_label" x-cloak>
                        <span>{{ __('Repasses Tesouro') }}:</span>
                        <span x-text="ufFundebInsights?.portaria?.transfer_imported_label"></span>
                    </p>
                </div>
            </div>

            <div class="serv-horizonte-cmd__fundeb-metrics">
                <div class="serv-horizonte-cmd__fundeb-metric">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/80">{{ __('Receita portaria') }}</p>
                    <p class="serv-horizonte-cmd__fundeb-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatFundebCurrency(ufFundebInsights?.receita_portaria_total)"></p>
                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400" x-text="ufFundebExerciseLabel()"></p>
                </div>
                <div class="serv-horizonte-cmd__fundeb-metric">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/80">{{ __('Complementação') }}</p>
                    <p class="serv-horizonte-cmd__fundeb-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatFundebCurrency(ufFundebInsights?.complementacao_total)"></p>
                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400" x-text="ufFundebMunicipalitiesLabel()"></p>
                </div>
                <div class="serv-horizonte-cmd__fundeb-metric" x-show="ufFundebInsights?.realtime?.available" x-cloak>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/80">
                        {{ __('Avanço :year', ['year' => date('Y')]) }}
                    </p>
                    <p class="serv-horizonte-cmd__fundeb-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatFundebPct(ufFundebInsights?.realtime?.pct_done)"></p>
                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400" x-text="ufFundebRealtimeSubLabel()"></p>
                </div>
                <div class="serv-horizonte-cmd__fundeb-metric" x-show="ufFundebInsights?.national?.rank_receita" x-cloak>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/80">{{ __('Comparativo nacional') }}</p>
                    <p class="serv-horizonte-cmd__fundeb-value" :class="kpiLoading ? 'is-loading' : ''" x-text="ufFundebNationalRankLabel()"></p>
                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400" x-text="ufFundebNationalSubLabel()"></p>
                </div>
            </div>
        </div>

        <div class="serv-horizonte-cmd__segments" data-horizonte-tour="segments" x-show="!kpiLoading && focusSegments.length > 0" x-cloak>
            <template x-for="seg in focusSegments" :key="seg.key">
                <button
                    type="button"
                    class="serv-horizonte-cmd__segment"
                    :disabled="pageLoading"
                    @click="applyFocusSegment(seg); workspaceTab = 'list'"
                >
                    <span class="text-[10px] font-bold uppercase tracking-wide text-blue-800 dark:text-blue-300" x-text="seg.label"></span>
                    <span class="serv-horizonte-cmd__segment-count" x-text="Number(seg.count ?? 0).toLocaleString('pt-BR')"></span>
                    <span class="mt-0.5 line-clamp-2 text-[11px] text-slate-500 dark:text-slate-400" x-text="seg.description"></span>
                </button>
            </template>
        </div>

        <div class="serv-horizonte-cmd__drawer-foot sm:hidden">
            <button type="button" class="serv-btn-secondary text-xs w-full" @click="resetFilters()" :disabled="pageLoading">
                {{ __('Vista padrão') }}
            </button>
        </div>
    </div>
</section>
