<template x-teleport="body">
<div
    x-show="active && tooltipPinned"
    x-cloak
    x-transition.opacity.duration.150ms
    class="serv-horizonte-muni-overlay"
    role="presentation"
    @click.self="closeTooltip()"
    @keydown.escape.window="if (active && tooltipPinned) closeTooltip()"
>
    <div
        class="serv-horizonte-muni-modal"
        :style="tooltipStyle"
        role="dialog"
        aria-modal="true"
        :aria-label="active?.name ? active.name + ' — ' + (active.uf || '') : '{{ __('Município') }}'"
        @click.stop
    >
        <header class="serv-horizonte-muni-modal__chrome">
            <div class="serv-horizonte-muni-modal__chrome-row">
                <div class="serv-horizonte-muni-modal__title-block">
                    <h3 class="serv-horizonte-muni-modal__name" x-text="active?.name"></h3>
                    <p class="serv-horizonte-muni-modal__location" x-show="active" x-cloak>
                        <span class="serv-horizonte-muni-modal__uf" x-text="modalHeaderUfLabel(active)"></span>
                        <span
                            class="serv-horizonte-muni-modal__location-sep"
                            x-show="modalHeaderMesoLabel(active)"
                            aria-hidden="true"
                        >·</span>
                        <span
                            class="serv-horizonte-muni-modal__meso"
                            x-show="modalHeaderMesoLabel(active)"
                            x-text="modalHeaderMesoLabel(active)"
                        ></span>
                    </p>
                    <div class="serv-horizonte-muni-modal__facts" x-show="active" x-cloak>
                        <div class="serv-horizonte-muni-modal__facts-primary">
                            <span class="serv-horizonte-muni-modal__fact serv-horizonte-muni-modal__fact--ibge" x-text="modalHeaderMeta(active)"></span>
                            <div
                                class="serv-horizonte-muni-modal__saeb"
                                x-show="modalHeaderHasSaeb(active)"
                                x-cloak
                            >
                                <span class="serv-horizonte-muni-modal__saeb-tag">SAEB</span>
                                <template x-for="(row, idx) in modalHeaderSaebByYear(active)" :key="'saeb-' + row.year + '-' + idx">
                                    <span
                                        class="serv-horizonte-muni-modal__fact serv-horizonte-muni-modal__fact--saeb-year"
                                        :class="modalHeaderSaebYearToneClass(idx)"
                                        x-text="modalHeaderSaebYearLabel(row)"
                                    ></span>
                                </template>
                            </div>
                        </div>
                        <div class="serv-horizonte-muni-modal__geo" x-show="hasModalHeaderGeoInfo(active)" x-cloak>
                            <div
                                class="serv-horizonte-muni-modal__geo-pos-wrap"
                                x-show="hasModalHeaderGeoPosition(active)"
                                x-cloak
                            >
                                <span
                                    class="serv-horizonte-muni-modal__fact serv-horizonte-muni-modal__fact--geo serv-horizonte-muni-modal__fact--geo-pos"
                                    :class="active?.coord_approximate ? 'serv-horizonte-muni-modal__fact--geo-pos-approx' : ''"
                                >
                                    <svg class="serv-horizonte-muni-modal__fact__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                                    <span x-text="modalHeaderGeoPositionLabel(active)"></span>
                                </span>
                                <button
                                    type="button"
                                    class="serv-horizonte-muni-modal__geo-copy"
                                    @click.stop="copyModalGeoPosition(active)"
                                    :title="'{{ __('Copiar coordenadas (decimal)') }}'"
                                    aria-label="{{ __('Copiar coordenadas') }}"
                                >
                                    <svg class="serv-horizonte-muni-modal__geo-copy__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125H5.625c-.621 0-1.125-.504-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125h3.375m6 0V4.875c0-.621-.504-1.125-1.125-1.125h-3.375c-.621 0-1.125.504-1.125 1.125v3.375m6 0h-3.375" /></svg>
                                </button>
                                <span
                                    class="serv-horizonte-muni-modal__geo-copy-confirm"
                                    x-show="geoCoordCopied"
                                    x-cloak
                                    role="status"
                                >{{ __('Copiado!') }}</span>
                            </div>
                            <span
                                class="serv-horizonte-muni-modal__fact serv-horizonte-muni-modal__fact--geo serv-horizonte-muni-modal__fact--geo-dist"
                                x-show="hasModalHeaderGeoDistance(active)"
                            >
                                <svg class="serv-horizonte-muni-modal__fact__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18h10.5V3.75M6.75 9h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" /></svg>
                                <span x-text="modalHeaderGeoDistanceLabel(active)"></span>
                            </span>
                            <span
                                class="serv-horizonte-muni-modal__fact serv-horizonte-muni-modal__fact--geo serv-horizonte-muni-modal__fact--geo-area"
                                x-show="hasModalHeaderGeoArea(active)"
                            >
                                <svg class="serv-horizonte-muni-modal__fact__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" /></svg>
                                <span x-text="modalHeaderGeoAreaLabel(active)"></span>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="serv-horizonte-muni-modal__chrome-side">
                    <div
                        class="serv-horizonte-muni-modal__propensity"
                        x-show="active && (active.success_score != null || propensityLevelShort(active))"
                        x-cloak
                    >
                        <div
                            class="serv-horizonte-muni-modal__propensity-ring"
                            :style="propensityRingStyle(active)"
                            role="img"
                            :aria-label="propensityLevelShort(active) + ' — ' + propensityPercentLabel(active)"
                        >
                            <span class="serv-horizonte-muni-modal__propensity-ring-inner">
                                <span class="serv-horizonte-muni-modal__propensity-ring-value" x-text="propensityPercentLabel(active)"></span>
                            </span>
                        </div>
                        <span class="serv-horizonte-muni-modal__propensity-tier" x-text="propensityLevelShort(active)"></span>
                    </div>
                    <button
                        type="button"
                        class="serv-horizonte-muni-tooltip__close serv-horizonte-muni-modal__close"
                        x-on:click.stop="closeTooltip()"
                        aria-label="{{ __('Fechar') }}"
                    >&times;</button>
                </div>
            </div>
        </header>

        <div class="serv-horizonte-muni-modal__body">
            <div
                x-show="active?.muni_alerts"
                x-cloak
                class="serv-horizonte-muni-tooltip__alert-status serv-horizonte-muni-tooltip__alert-status--block"
                :class="{
                    'is-found': active?.muni_alerts?.status === 'found',
                    'is-clear': active?.muni_alerts?.status === 'clear',
                    'is-unavailable': active?.muni_alerts?.status === 'unavailable',
                }"
            >
                <template x-if="active?.muni_alerts?.status === 'found'">
                    <a
                        class="serv-horizonte-muni-tooltip__alert-chip serv-horizonte-muni-tooltip__alert-chip--found"
                        :class="active?.muni_alerts?.count > 1 ? 'is-multi' : ''"
                        :href="active.muni_alerts.detail_url"
                        :target="active.muni_alerts.detail_url ? '_blank' : null"
                        rel="noopener noreferrer"
                        @click.stop
                    >
                        <span class="serv-horizonte-muni-tooltip__alert-chip-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                                <line x1="12" y1="9" x2="12" y2="13" />
                                <line x1="12" y1="17" x2="12.01" y2="17" />
                            </svg>
                        </span>
                        <span class="serv-horizonte-muni-tooltip__alert-chip-body">
                            <span class="serv-horizonte-muni-tooltip__alert-chip-label" x-text="active.muni_alerts.status_label"></span>
                            <span class="serv-horizonte-muni-tooltip__alert-chip-text" x-text="active.muni_alerts.headline"></span>
                        </span>
                        <span class="serv-horizonte-muni-tooltip__alert-chip-link" x-show="active.muni_alerts.detail_url">{{ __('Ver detalhes') }} ↗</span>
                    </a>
                </template>
                <template x-if="active?.muni_alerts?.status === 'clear'">
                    <div class="serv-horizonte-muni-tooltip__alert-chip serv-horizonte-muni-tooltip__alert-chip--clear">
                        <span class="serv-horizonte-muni-tooltip__alert-chip-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                <polyline points="22 4 12 14.01 9 11.01" />
                            </svg>
                        </span>
                        <span class="serv-horizonte-muni-tooltip__alert-chip-body">
                            <span class="serv-horizonte-muni-tooltip__alert-chip-label" x-text="active.muni_alerts.status_label"></span>
                            <span class="serv-horizonte-muni-tooltip__alert-chip-text" x-text="active.muni_alerts.headline"></span>
                        </span>
                    </div>
                </template>
                <template x-if="active?.muni_alerts?.status === 'unavailable'">
                    <div class="serv-horizonte-muni-tooltip__alert-chip serv-horizonte-muni-tooltip__alert-chip--unavailable">
                        <span class="serv-horizonte-muni-tooltip__alert-chip-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                                <line x1="12" y1="17" x2="12.01" y2="17" />
                            </svg>
                        </span>
                        <span class="serv-horizonte-muni-tooltip__alert-chip-body">
                            <span class="serv-horizonte-muni-tooltip__alert-chip-label" x-text="active.muni_alerts.status_label"></span>
                            <span class="serv-horizonte-muni-tooltip__alert-chip-text" x-text="active.muni_alerts.headline"></span>
                        </span>
                    </div>
                </template>
            </div>

            <div
                x-show="active && tooltipMunicipalContextHtml(active)"
                x-cloak
                class="serv-horizonte-muni-tooltip__municipal-context"
                x-html="active ? tooltipMunicipalContextHtml(active) : ''"
            ></div>

            <section
                x-show="active && shouldShowEnrollmentSeries(active)"
                x-cloak
                class="serv-horizonte-muni-tooltip__enrollment-series"
            >
                <div class="serv-horizonte-muni-tooltip__enrollment-series-toolbar">
                    <div class="serv-horizonte-muni-tooltip__enrollment-series-head">
                        <h4 class="serv-horizonte-muni-tooltip__enrollment-series-title">{{ __('Matrículas — Censo INEP') }}</h4>
                        <p class="serv-horizonte-muni-tooltip__enrollment-series-subtitle">
                            <span x-show="enrollmentSeriesStageYear && !enrollmentSeriesLoading">
                                <span x-text="enrollmentSeriesStageYear"></span>
                                <span x-show="enrollmentSeriesLatestTotal != null">
                                    · <span x-text="formatEnrollmentStageCounter(enrollmentSeriesLatestTotal)"></span> {{ __('matrículas') }}
                                </span>
                                <span x-show="enrollmentSeriesDependenciaLabel"> · <span x-text="enrollmentSeriesDependenciaLabel"></span></span>
                            </span>
                            <span x-show="!enrollmentSeriesStageYear || enrollmentSeriesLoading">{{ __('Últimos 5 anos · Educacenso') }}</span>
                        </p>
                    </div>
                    <div
                        x-show="!enrollmentSeriesLoading && !enrollmentSeriesError"
                        class="serv-horizonte-muni-tooltip__enrollment-series-filters"
                        role="group"
                        :aria-label="@js(__('Recorte por dependência administrativa'))"
                    >
                        <template x-for="option in enrollmentSeriesDependenciaOptions" :key="option.value">
                            <button
                                type="button"
                                class="serv-horizonte-muni-tooltip__enrollment-series-filter"
                                :class="{ 'is-active': enrollmentSeriesDependencia === option.value }"
                                :aria-pressed="enrollmentSeriesDependencia === option.value"
                                @click.stop="setEnrollmentSeriesDependencia(option.value)"
                                x-text="option.label"
                            ></button>
                        </template>
                    </div>
                    <span
                        x-show="enrollmentSeriesLoading"
                        class="serv-horizonte-muni-tooltip__enrollment-series-status"
                    >{{ __('A carregar…') }}</span>
                </div>
                <p
                    x-show="enrollmentSeriesError"
                    x-text="enrollmentSeriesError"
                    class="serv-horizonte-muni-tooltip__enrollment-series-error"
                ></p>
                <div
                    x-show="!enrollmentSeriesLoading && !enrollmentSeriesError && enrollmentSeriesReady"
                    class="serv-horizonte-muni-tooltip__enrollment-series-body"
                >
                    <div class="serv-horizonte-muni-tooltip__enrollment-series-chart-wrap">
                        <canvas x-ref="enrollmentSeriesCanvas" height="148" aria-hidden="true"></canvas>
                    </div>
                    <div
                        x-show="enrollmentSeriesStageCounters.length"
                        class="serv-horizonte-muni-tooltip__enrollment-series-stages"
                    >
                        <dl class="serv-horizonte-muni-tooltip__enrollment-series-stages-grid">
                            <template x-for="item in enrollmentSeriesStageCounters" :key="item.key">
                                <div class="serv-horizonte-muni-tooltip__enrollment-series-stage">
                                    <dd
                                        class="serv-horizonte-muni-tooltip__enrollment-series-stage-value"
                                        x-text="formatEnrollmentStageCounter(item.value)"
                                    ></dd>
                                    <dt
                                        class="serv-horizonte-muni-tooltip__enrollment-series-stage-label"
                                        x-text="item.label"
                                    ></dt>
                                    <span
                                        class="serv-horizonte-muni-tooltip__enrollment-series-stage-hint"
                                        x-text="enrollmentStageHint(item)"
                                    ></span>
                                </div>
                            </template>
                        </dl>
                    </div>
                </div>
                <p
                    x-show="enrollmentSeriesFootnote"
                    x-text="enrollmentSeriesFootnote"
                    class="serv-horizonte-muni-tooltip__enrollment-series-footnote"
                ></p>
            </section>
            <div x-show="active" x-html="active ? tooltipBodyHtml(active) : ''"></div>
            <div x-show="canManageSge && active && canEditSgeFor(active)" x-cloak class="pt-2 border-t border-slate-200/80 dark:border-slate-700/80 space-y-2">
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Inteligência de concorrência — não cadastra o município no catálogo Consultoria.') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="serv-btn-secondary text-xs" @click.stop="openSgeForm(active, { readOnly: true })">{{ __('Consultar') }}</button>
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500" @click.stop="openSgeForm(active)">
                        <span x-text="sgeRegistryActionLabel(active)"></span>
                    </button>
                </div>
            </div>
            <div x-show="canManageSge && active && active.in_catalog" x-cloak class="pt-2 border-t border-slate-200/80 dark:border-slate-700/80 space-y-2">
                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Município no catálogo Consultoria — o SGE é gerido na ficha da cidade.') }}</p>
                <div class="flex flex-wrap gap-2">
                    <a x-show="active?.cities_url" :href="active.cities_url" target="_blank" rel="noopener noreferrer" class="serv-btn-secondary text-xs">{{ __('Consultar ficha') }}</a>
                    <a x-show="active?.analytics_url" :href="active.analytics_url" target="_blank" rel="noopener noreferrer" class="serv-btn-primary text-xs">{{ __('Abrir consultoria') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
</template>

<div
    x-show="sgeFormOpen"
    x-cloak
    class="serv-horizonte-sge-overlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="horizonte-sge-form-title"
    @keydown.escape.window="closeSgeForm()"
    @click.self="closeSgeForm()"
>
    <div class="serv-horizonte-sge-panel" @click.stop>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 id="horizonte-sge-form-title" class="text-sm font-semibold text-serv-navy dark:text-slate-100">
                    <span x-show="sgeFormReadOnly">{{ __('Consulta SGE') }}</span>
                    <span x-show="!sgeFormReadOnly">{{ __('SGE concorrente / próprio') }}</span>
                </h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 max-w-md">
                    {{ __('Registo de inteligência Horizonte — acompanha sistemas rivais ou municipais. Não cria cidade nem activa Consultoria i-Educar.') }}
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-show="sgeForm.ibge">
                    <span x-text="sgeForm.name + ' — ' + sgeForm.uf"></span>
                    · IBGE <span x-text="sgeForm.ibge"></span>
                </p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeSgeForm()" aria-label="{{ __('Fechar') }}">&times;</button>
        </div>
        <form class="space-y-3" @submit.prevent="saveSgeEntry()">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Sistema (SGE)') }} <span class="text-red-600" x-show="!sgeFormReadOnly">*</span></label>
                <input type="text" x-model="sgeForm.system" :required="!sgeFormReadOnly" :readonly="sgeFormReadOnly" maxlength="120" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 read-only:bg-slate-50 read-only:text-slate-600 dark:read-only:bg-slate-800/60" placeholder="Ex.: GDAE, Proesc, SIGE municipal…" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Fornecedor / secretaria') }}</label>
                <input type="text" x-model="sgeForm.vendor" :readonly="sgeFormReadOnly" maxlength="120" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 read-only:bg-slate-50 read-only:text-slate-600 dark:read-only:bg-slate-800/60" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('URL do portal') }}</label>
                <input type="url" x-model="sgeForm.app_url" :readonly="sgeFormReadOnly" maxlength="500" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 read-only:bg-slate-50 read-only:text-slate-600 dark:read-only:bg-slate-800/60" placeholder="https://…" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Observações') }}</label>
                <textarea x-model="sgeForm.notes" :readonly="sgeFormReadOnly" rows="3" maxlength="2000" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 read-only:bg-slate-50 read-only:text-slate-600 dark:read-only:bg-slate-800/60" placeholder="{{ __('Ex.: sistema próprio da secretaria; concorrente regional; observações de campo…') }}"></textarea>
            </div>
            <p x-show="sgeFormReadOnly && !sgeForm.has_entry && !sgeForm.system" class="text-xs text-slate-500 dark:text-slate-400">{{ __('Sem registo Horizonte para este município — use «Cadastrar» para criar.') }}</p>
            <p x-show="sgeFormError" x-text="sgeFormError" class="text-xs text-red-600 dark:text-red-400"></p>
            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                <button type="button" x-show="!sgeFormReadOnly && sgeForm.has_entry" class="text-xs text-red-700 dark:text-red-400 hover:underline" @click="deleteSgeEntry()">{{ __('Remover registo') }}</button>
                <div class="flex gap-2 ms-auto">
                    <button type="button" class="serv-btn-secondary text-xs" @click="closeSgeForm()">{{ __('Fechar') }}</button>
                    <button type="button" x-show="sgeFormReadOnly" class="serv-btn-primary text-xs" @click="enableSgeFormEdit()">{{ __('Editar') }}</button>
                    <button type="submit" x-show="!sgeFormReadOnly" class="serv-btn-primary text-xs" :disabled="sgeFormSaving">
                        <span x-show="!sgeFormSaving">{{ __('Gravar') }}</span>
                        <span x-show="sgeFormSaving">{{ __('A gravar…') }}</span>
                    </button>
                </div>
            </div>
        </form>
        <p class="text-[10px] text-slate-500" x-show="!sgeFormReadOnly">{{ __('Gravado em storage/app/horizonte/sge_registry.json — actualiza o mapa de imediato.') }}</p>
    </div>
</div>
