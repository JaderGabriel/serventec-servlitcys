@php
    /** @var array $card */
    $alpine = $card['alpine'];
@endphp
<article
    class="clio-report-card"
    x-data="clioReportCard(@js($alpine))"
    @click.outside="exportOpen = false"
>
    <div
        class="clio-report-card__rail"
        :class="'clio-report-card__rail--' + (current.rail_tone || 'progress')"
        aria-hidden="true"
    ></div>
    <div class="clio-report-card__body">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="clio-report-card__meta">
                    {{ $card['ibge'] ?: '—' }} · {{ $card['uf'] }}
                </p>
                <h4 class="clio-report-card__title">
                    {{ $card['municipality_name'] }}
                </h4>
            </div>
            <span
                class="clio-profile-mark"
                :class="current.analysis_only ? 'clio-profile-mark--analysis' : 'clio-profile-mark--consultancy'"
                :title="current.analysis_only ? '{{ __('Só coleta') }}' : '{{ __('Consultoria') }}'"
                :aria-label="current.analysis_only ? '{{ __('Só coleta') }}' : '{{ __('Consultoria') }}'"
            >
                <svg x-show="current.analysis_only" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 0 0 3 3.5v13A1.5 1.5 0 0 0 4.5 18h11a1.5 1.5 0 0 0 1.5-1.5V7.621a1.5 1.5 0 0 0-.44-1.06l-3.12-3.122A1.5 1.5 0 0 0 12.378 3H4.5Zm7.75 2.378L15.122 7.25H13a.75.75 0 0 1-.75-.75V4.378ZM6.5 9.25a.75.75 0 0 0 0 1.5h7a.75.75 0 0 0 0-1.5h-7Zm0 3a.75.75 0 0 0 0 1.5h4a.75.75 0 0 0 0-1.5h-4Z" clip-rule="evenodd" />
                </svg>
                <svg x-show="!current.analysis_only" x-cloak viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4 3.5A1.5 1.5 0 0 1 5.5 2h9A1.5 1.5 0 0 1 16 3.5v13a.5.5 0 0 1-.5.5H13v-2.25a.75.75 0 0 0-.75-.75h-2.5a.75.75 0 0 0-.75.75V17H4.5a.5.5 0 0 1-.5-.5v-13ZM7 5.75A.75.75 0 0 1 7.75 5h.5a.75.75 0 0 1 0 1.5h-.5A.75.75 0 0 1 7 5.75Zm0 3A.75.75 0 0 1 7.75 8h.5a.75.75 0 0 1 0 1.5h-.5A.75.75 0 0 1 7 8.75Zm0 3a.75.75 0 0 1 .75-.75h.5a.75.75 0 0 1 0 1.5h-.5a.75.75 0 0 1-.75-.75ZM11.75 5a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5h-.5Zm0 3a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5h-.5Zm0 3a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5h-.5Z" clip-rule="evenodd" />
                </svg>
            </span>
        </div>

        <div class="clio-report-card__coleta" x-show="hasMultipleCollections" x-cloak>
            <label class="clio-report-card__coleta-label">
                {{ __('Coleta') }}
                <span class="clio-report-card__coleta-count" x-text="'(' + collections.length + ')'"></span>
            </label>
            <select
                class="clio-report-card__coleta-select"
                x-model="selectedId"
                @change="selectCollection($event.target.value)"
            >
                <template x-for="item in collections" :key="item.id">
                    <option :value="item.id" x-text="item.label"></option>
                </template>
            </select>
        </div>
        <p class="clio-report-card__coleta-meta" x-show="!hasMultipleCollections && current.label" x-text="current.label"></p>

        <div class="clio-report-card__chips">
            <span class="clio-chip clio-chip--neutral" x-text="current.status_label || '—'"></span>
            <span class="clio-chip clio-chip--ready" x-show="current.ready" x-cloak>{{ __('Relatório pronto') }}</span>
            <span class="clio-chip clio-chip--warn" x-show="!current.ready" x-cloak>{{ __('Em preparação') }}</span>
            <span
                class="clio-chip clio-chip--error"
                x-show="(current.error_count || 0) > 0"
                x-cloak
                x-text="(current.error_count || 0) + ' {{ __('erro(s)') }}'"
            ></span>
            <span
                class="clio-chip clio-chip--warn"
                x-show="!(current.error_count > 0) && (current.warning_count || 0) > 0"
                x-cloak
                x-text="(current.warning_count || 0) + ' {{ __('aviso(s)') }}'"
            ></span>
            <button
                type="button"
                class="clio-card-slide-toggle"
                @click="togglePanel()"
                :title="toggleTitle"
                :aria-pressed="isSeries.toString()"
            >
                <span class="clio-card-slide-toggle__icon" aria-hidden="true">
                    <svg x-show="!isSeries" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3.5A1.5 1.5 0 0 1 4.5 2h11A1.5 1.5 0 0 1 17 3.5v2.879a1.5 1.5 0 0 1-.44 1.06l-3.621 3.62a1.5 1.5 0 0 0-.44 1.061V16.5A1.5 1.5 0 0 1 11 18h-2a1.5 1.5 0 0 1-1.5-1.5v-4.38a1.5 1.5 0 0 0-.44-1.06L3.44 7.44A1.5 1.5 0 0 1 3 6.378V3.5Zm1.5-.5a.5.5 0 0 0-.5.5v2.879a.5.5 0 0 0 .147.353l3.62 3.621A2.5 2.5 0 0 1 8.5 11.62V16.5a.5.5 0 0 0 .5.5h2a.5.5 0 0 0 .5-.5v-4.88a2.5 2.5 0 0 1 .733-1.767l3.62-3.621A.5.5 0 0 0 16 6.379V3.5a.5.5 0 0 0-.5-.5h-11Z" clip-rule="evenodd"/></svg>
                    <svg x-show="isSeries" x-cloak viewBox="0 0 20 20" fill="currentColor"><path d="M15.5 2A1.5 1.5 0 0 1 17 3.5v13a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 3 16.5v-13A1.5 1.5 0 0 1 4.5 2h11ZM6.25 12.5a.75.75 0 0 0-1.5 0v2.5a.75.75 0 0 0 1.5 0v-2.5Zm3.5-4a.75.75 0 0 0-1.5 0v6.5a.75.75 0 0 0 1.5 0V8.5Zm3.5-2.5a.75.75 0 0 0-1.5 0v9a.75.75 0 0 0 1.5 0v-9Z"/></svg>
                </span>
                <span class="clio-card-slide-toggle__label" x-text="toggleLabel"></span>
            </button>
        </div>

        <div class="clio-report-card__stage">
            <div class="clio-report-card__panel" x-show="!isSeries">
                <div class="clio-meter">
                    <div class="clio-meter__row">
                        <span class="clio-meter__label">{{ __('Cobertura da tríade') }}</span>
                        <span class="clio-meter__value" x-text="current.triade_label || '—'"></span>
                    </div>
                    <p class="clio-meter__hint">{{ __('Escolas em atividade') }}</p>
                    <div class="clio-meter__track">
                        <div
                            class="clio-meter__fill"
                            :class="'clio-meter__fill--' + (current.meter_tone || 'bad')"
                            :style="'width: ' + (current.triade_width || 0) + '%'"
                        ></div>
                    </div>
                </div>

                <dl class="clio-mini-stats">
                    <div class="clio-mini-stat">
                        <dt class="clio-mini-stat__label">{{ __('Escolas') }}</dt>
                        <dd class="clio-mini-stat__value" x-text="current.schools_active ?? 0"></dd>
                    </div>
                    <div class="clio-mini-stat">
                        <dt class="clio-mini-stat__label">{{ __('Arquivos') }}</dt>
                        <dd class="clio-mini-stat__value" x-text="current.artifacts_count ?? 0"></dd>
                    </div>
                    <div class="clio-mini-stat">
                        <dt class="clio-mini-stat__label">{{ __('Ref.') }}</dt>
                        <dd class="clio-mini-stat__value clio-mini-stat__value--sm" x-text="current.ref_short || '—'"></dd>
                    </div>
                </dl>
                <p class="clio-report-card__aside" x-show="(current.schools_other || 0) > 0" x-cloak>
                    <span x-text="'+' + (current.schools_other || 0) + ' {{ __('demais situações (extinta, paralisada ou reforma)') }}'"></span>
                </p>
            </div>

            <div class="clio-report-card__panel clio-report-card__panel--series" x-show="isSeries" x-cloak>
                <div class="clio-card-series__head">
                    <p class="clio-card-series__title">{{ __('Matrículas — Censo INEP') }}</p>
                    <p class="clio-card-series__sub" x-show="latestSummary">
                        <span x-text="latestSummary?.ano ?? ''"></span>
                        <template x-if="latestSummary?.total != null">
                            <span> · <span x-text="formatCounter(latestSummary.total)"></span> {{ __('matrículas') }}</span>
                        </template>
                        <span> · {{ __('rede municipal') }}</span>
                    </p>
                </div>
                <div class="clio-card-series__chart-wrap" :class="{ 'is-loading': loading }">
                    <canvas x-ref="seriesCanvas" aria-label="{{ __('Série histórica de matrículas') }}"></canvas>
                    <div class="clio-card-series__loading" x-show="loading" x-cloak>
                        <span class="clio-card-series__spinner" aria-hidden="true"></span>
                        <span>{{ __('A carregar…') }}</span>
                    </div>
                </div>
                <p class="clio-card-series__error" x-show="error" x-text="error" x-cloak></p>
                <dl class="clio-card-series__stages" x-show="!loading && !error && stageCounters.length" x-cloak>
                    <template x-for="item in stageCounters" :key="item.key">
                        <div class="clio-card-series__stage">
                            <dd class="clio-card-series__stage-value" x-text="formatCounter(item.value)"></dd>
                            <dt class="clio-card-series__stage-label" x-text="item.label"></dt>
                        </div>
                    </template>
                </dl>
            </div>
        </div>

        <div class="clio-report-card__footer" role="group" aria-label="{{ __('Acções da coleta') }}">
            <a :href="current.show_url" class="clio-card-action clio-card-action--central" title="{{ __('Central da coleta — arquivos e processamento') }}">
                <span class="clio-card-action__icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75ZM2 10a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/></svg>
                </span>
                <span class="clio-card-action__label">{{ __('Central') }}</span>
            </a>

            <a
                :href="current.report_url"
                class="clio-card-action clio-card-action--report"
                :class="{ 'clio-card-action--ready': current.ready }"
                :title="current.ready ? '{{ __('Abrir relatório analítico') }}' : '{{ __('Abrir coleta') }}'"
            >
                <span class="clio-card-action__icon" aria-hidden="true">
                    <svg x-show="current.ready" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 2A2.25 2.25 0 0 0 2 4.25v11.5A2.25 2.25 0 0 0 4.25 18h11.5A2.25 2.25 0 0 0 18 15.75V4.25A2.25 2.25 0 0 0 15.75 2H4.25ZM3.5 4.25a.75.75 0 0 1 .75-.75h11.5a.75.75 0 0 1 .75.75v11.5a.75.75 0 0 1-.75.75H4.25a.75.75 0 0 1-.75-.75V4.25Zm2.5 2a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm0 3.5a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm0 3.5a.75.75 0 0 0 0 1.5h4a.75.75 0 0 0 0-1.5H6Z" clip-rule="evenodd"/></svg>
                    <svg x-show="!current.ready" x-cloak viewBox="0 0 20 20" fill="currentColor"><path d="M3.5 2.75a.75.75 0 0 0-1.5 0v14.5a.75.75 0 0 0 1.5 0v-4.392l1.657-.348a6.45 6.45 0 0 1 1.837.148l.1.023a7.95 7.95 0 0 0 2.265.186h3.832a.75.75 0 0 0 0-1.5H9.39a9.45 9.45 0 0 1-2.696-.223l-.1-.023a4.95 4.95 0 0 0-1.41-.113l-.854.18V2.75Z"/></svg>
                </span>
                <span class="clio-card-action__label" x-text="current.ready ? '{{ __('Relatório') }}' : '{{ __('Coleta') }}'"></span>
            </a>

            <a
                x-show="current.ready"
                x-cloak
                :href="current.insights_url"
                class="clio-card-action clio-card-action--insights"
                title="{{ __('Insights gerenciais e dataset BI') }}"
            >
                <span class="clio-card-action__icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M15.98 1.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.238a1 1 0 0 0 0 1.962l1.192.238a1 1 0 0 1 .785.785l.238 1.192a1 1 0 0 0 1.962 0l.238-1.192a1 1 0 0 1 .785-.785l1.192-.238a1 1 0 0 0 0-1.962l-1.192-.238a1 1 0 0 1-.785-.785l-.238-1.192ZM6.949 5.684a1 1 0 0 0-1.898 0l-.683 2.051a1 1 0 0 1-.633.633l-2.051.683a1 1 0 0 0 0 1.898l2.051.684a1 1 0 0 1 .633.632l.683 2.051a1 1 0 0 0 1.898 0l.683-2.051a1 1 0 0 1 .633-.633l2.051-.683a1 1 0 0 0 0-1.898l-2.051-.683a1 1 0 0 1-.633-.633L6.95 5.684ZM13.949 13.684a1 1 0 0 0-1.898 0l-.184.551a1 1 0 0 1-.632.633l-.551.183a1 1 0 0 0 0 1.898l.551.183a1 1 0 0 1 .633.633l.183.551a1 1 0 0 0 1.898 0l.184-.551a1 1 0 0 1 .632-.633l.551-.183a1 1 0 0 0 0-1.898l-.551-.184a1 1 0 0 1-.633-.632l-.183-.551Z"/></svg>
                </span>
                <span class="clio-card-action__label">{{ __('Insights') }}</span>
            </a>
            <span
                x-show="!current.ready"
                x-cloak
                class="clio-card-action clio-card-action--insights clio-card-action--disabled"
                title="{{ __('Disponível após analisar a coleta') }}"
            >
                <span class="clio-card-action__icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M15.98 1.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.238a1 1 0 0 0 0 1.962l1.192.238a1 1 0 0 1 .785.785l.238 1.192a1 1 0 0 0 1.962 0l.238-1.192a1 1 0 0 1 .785-.785l1.192-.238a1 1 0 0 0 0-1.962l-1.192-.238a1 1 0 0 1-.785-.785l-.238-1.192ZM6.949 5.684a1 1 0 0 0-1.898 0l-.683 2.051a1 1 0 0 1-.633.633l-2.051.683a1 1 0 0 0 0 1.898l2.051.684a1 1 0 0 1 .633.632l.683 2.051a1 1 0 0 0 1.898 0l.683-2.051a1 1 0 0 1 .633-.633l2.051-.683a1 1 0 0 0 0-1.898l-2.051-.683a1 1 0 0 1-.633-.633L6.95 5.684ZM13.949 13.684a1 1 0 0 0-1.898 0l-.184.551a1 1 0 0 1-.632.633l-.551.183a1 1 0 0 0 0 1.898l.551.183a1 1 0 0 1 .633.633l.183.551a1 1 0 0 0 1.898 0l.184-.551a1 1 0 0 1 .632-.633l.551-.183a1 1 0 0 0 0-1.898l-.551-.184a1 1 0 0 1-.633-.632l-.183-.551Z"/></svg>
                </span>
                <span class="clio-card-action__label">{{ __('Insights') }}</span>
            </span>

            <div class="clio-card-action-wrap relative" x-show="current.ready && current.can_export" x-cloak>
                <button
                    type="button"
                    class="clio-card-action clio-card-action--download w-full"
                    @click="exportOpen = !exportOpen"
                    :aria-expanded="exportOpen.toString()"
                    title="{{ __('Exportar PDF ou Excel') }}"
                >
                    <span class="clio-card-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                    </span>
                    <span class="clio-card-action__label">{{ __('Exportar') }}</span>
                </button>
                <div
                    x-show="exportOpen"
                    x-cloak
                    class="absolute z-[80] bottom-full mb-2 end-0 w-56 rounded-xl shadow-lg shadow-slate-900/10 dark:shadow-black/30"
                >
                    <div class="rounded-xl ring-1 ring-slate-200/90 dark:ring-gray-600/90 py-1 bg-white dark:bg-gray-800">
                        <a :href="current.export_pdf" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-gray-700/80">{{ __('PDF Detalhado') }}</a>
                        <a :href="current.export_gestor" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-gray-700/80">{{ __('PDF Gerencial') }}</a>
                        <a :href="current.export_final" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-gray-700/80">{{ __('PDF Final') }}</a>
                        <a :href="current.export_mapa" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-gray-700/80">{{ __('MAPA de Coleta') }}</a>
                        <a :href="current.export_xlsx" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-gray-700/80">{{ __('Excel') }}</a>
                        <a :href="current.export_xlsx_filtros" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-gray-700/80">{{ __('Excel filtros') }}</a>
                    </div>
                </div>
            </div>
            <span
                x-show="current.ready && !current.can_export"
                x-cloak
                class="clio-card-action clio-card-action--download clio-card-action--disabled"
                title="{{ __('Sem permissão de exportação') }}"
            >
                <span class="clio-card-action__icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                </span>
                <span class="clio-card-action__label">{{ __('Exportar') }}</span>
            </span>
            <span
                x-show="!current.ready"
                x-cloak
                class="clio-card-action clio-card-action--download clio-card-action--disabled"
                title="{{ __('Disponível após analisar a coleta') }}"
            >
                <span class="clio-card-action__icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                </span>
                <span class="clio-card-action__label">{{ __('Exportar') }}</span>
            </span>
        </div>

        <div
            class="clio-file-pulse"
            :class="'clio-file-pulse--' + (current.files?.tone || 'neutral')"
            :title="current.files?.acomp?.name || '{{ __('Relatório Acomp. Coleta 1ª etapa') }}'"
        >
            <span class="clio-file-pulse__item" :class="'clio-file-pulse__item--' + (current.files?.tone || 'neutral')">
                <span class="clio-file-pulse__text" x-text="current.files?.label || '—'"></span>
            </span>
            <span class="clio-file-pulse__sep" aria-hidden="true">·</span>
            <span class="clio-file-pulse__item" :class="'clio-file-pulse__item--' + (current.files?.acomp?.tone || 'neutral')">
                <span class="clio-file-pulse__acomp" x-text="current.files?.acomp?.label || '—'"></span>
            </span>
        </div>
    </div>
</article>
