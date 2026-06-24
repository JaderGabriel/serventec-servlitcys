<div
    x-show="ufSummaryOpen && isRegionalMode && scopeUf"
    x-cloak
    @keydown.escape.window="if (ufSummaryOpen) closeUfSummary()"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="serv-horizonte-uf-summary"
    role="region"
    :aria-label="ufLabel(scopeUf) + ' — {{ __('Resumo estadual') }}'"
>
    <div class="serv-horizonte-uf-summary__head">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="serv-horizonte-uf-summary__badge" x-text="scopeUf"></span>
                <h4 class="serv-horizonte-uf-summary__title" x-text="ufFundebInsights?.uf_name || ufLabel(scopeUf)"></h4>
            </div>
            <p class="serv-horizonte-uf-summary__meta" x-text="ufSummaryMetaLabel()"></p>
        </div>
        <button
            type="button"
            class="serv-horizonte-uf-summary__close"
            @click="closeUfSummary()"
            :aria-label="'{{ __('Ocultar resumo') }}'"
        >&times;</button>
    </div>

    <div class="serv-horizonte-uf-summary__pipeline" aria-label="{{ __('Indicadores comerciais na UF') }}">
        <div class="serv-horizonte-uf-summary__pipe-metric">
            <p class="serv-horizonte-uf-summary__pipe-label">{{ __('Alta pressão') }}</p>
            <p class="serv-horizonte-uf-summary__pipe-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.high_pressure)"></p>
        </div>
        <div class="serv-horizonte-uf-summary__pipe-metric">
            <p class="serv-horizonte-uf-summary__pipe-label">{{ __('Prospectos') }}</p>
            <p class="serv-horizonte-uf-summary__pipe-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.prospect_count)"></p>
        </div>
        <div class="serv-horizonte-uf-summary__pipe-metric">
            <p class="serv-horizonte-uf-summary__pipe-label">{{ __('Alta propensão') }}</p>
            <p class="serv-horizonte-uf-summary__pipe-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.high_prospect)"></p>
        </div>
        <div class="serv-horizonte-uf-summary__pipe-metric">
            <p class="serv-horizonte-uf-summary__pipe-label">{{ __('Matr. prospecto') }}</p>
            <p class="serv-horizonte-uf-summary__pipe-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(coverage.prospect_matriculas_censo)"></p>
        </div>
        <div class="serv-horizonte-uf-summary__pipe-metric" x-show="summary.consultoria_active != null" x-cloak>
            <p class="serv-horizonte-uf-summary__pipe-label">{{ __('Consultoria') }}</p>
            <p class="serv-horizonte-uf-summary__pipe-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.consultoria_active)"></p>
        </div>
    </div>

    <div class="serv-horizonte-uf-summary__fundeb" x-show="ufFundebInsights" x-cloak>
        <div class="serv-horizonte-uf-summary__fundeb-head">
            <p class="serv-horizonte-uf-summary__fundeb-kicker">{{ __('FUNDEB estadual') }}</p>
            <div class="serv-horizonte-uf-summary__portaria min-w-0 text-right">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700/90 dark:text-amber-300/90">{{ __('Portaria vigente') }}</p>
                <p class="mt-0.5 text-xs font-medium text-slate-700 dark:text-slate-200 line-clamp-2" x-text="ufFundebPortariaLabel()"></p>
            </div>
        </div>
        <div class="serv-horizonte-uf-summary__fundeb-grid">
            <div class="serv-horizonte-uf-summary__fundeb-metric">
                <p class="serv-horizonte-uf-summary__fundeb-label">{{ __('Receita portaria') }}</p>
                <p class="serv-horizonte-uf-summary__fundeb-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatFundebCurrency(ufFundebInsights?.receita_portaria_total)"></p>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400" x-text="ufFundebExerciseLabel()"></p>
            </div>
            <div class="serv-horizonte-uf-summary__fundeb-metric">
                <p class="serv-horizonte-uf-summary__fundeb-label">{{ __('Complementação') }}</p>
                <p class="serv-horizonte-uf-summary__fundeb-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatFundebCurrency(ufFundebInsights?.complementacao_total)"></p>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400" x-text="ufFundebMunicipalitiesLabel()"></p>
            </div>
            <div class="serv-horizonte-uf-summary__fundeb-metric" x-show="ufFundebInsights?.realtime?.available" x-cloak>
                <p class="serv-horizonte-uf-summary__fundeb-label">{{ __('Avanço :year', ['year' => date('Y')]) }}</p>
                <p class="serv-horizonte-uf-summary__fundeb-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatFundebPct(ufFundebInsights?.realtime?.pct_done)"></p>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400" x-text="ufFundebRealtimeSubLabel()"></p>
            </div>
            <div class="serv-horizonte-uf-summary__fundeb-metric" x-show="ufFundebInsights?.national?.rank_receita" x-cloak>
                <p class="serv-horizonte-uf-summary__fundeb-label">{{ __('Comparativo nacional') }}</p>
                <p class="serv-horizonte-uf-summary__fundeb-value" :class="kpiLoading ? 'is-loading' : ''" x-text="ufFundebNationalRankLabel()"></p>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400" x-text="ufFundebNationalSubLabel()"></p>
            </div>
        </div>
    </div>

    <p
        class="serv-horizonte-uf-summary__coverage"
        x-show="ufSummaryCoverageLabel()"
        x-cloak
        x-text="ufSummaryCoverageLabel()"
    ></p>
</div>
