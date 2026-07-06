@php
    $mapSummary = is_array($mapSummary ?? null) ? $mapSummary : ['total' => count($mapMarkers ?? []), 'by_status' => [], 'legend' => []];
    $totalCities = (int) ($mapSummary['total'] ?? count($mapMarkers ?? []));
    $plottedOnMap = (int) ($mapSummary['on_map'] ?? $totalCities);
    $mapLegend = is_array($mapSummary['legend'] ?? null) ? $mapSummary['legend'] : [];
    $cadastroLegend = is_array($mapSummary['cadastro_legend'] ?? null) ? $mapSummary['cadastro_legend'] : [];
    $vigenteAno = (int) ($mapSummary['vigente_ano'] ?? config('rx.vigente_year', (int) date('Y')));
    $mapStatusColors = is_array($mapSummary['colors'] ?? null) ? $mapSummary['colors'] : array_merge(
        \App\Support\Dashboard\MunicipalityMapStatus::colorsForJs(),
        \App\Support\Dashboard\MunicipalityMapCadastroPresenter::fillColorsForJs(),
    );
    $cadastroSnapshotUrl = $mapSummary['cadastro_snapshot_url'] ?? route('dashboard.municipality-map.cadastro-snapshot');
    $deferCadastroSnapshot = (bool) config('performance.home_defer_map_rx_snapshot', true);
    $mapOptions = [
        'cadastroSnapshotUrl' => $cadastroSnapshotUrl,
        'deferCadastroSnapshot' => $deferCadastroSnapshot,
        'vigenteAno' => $vigenteAno,
        'cadastroLegend' => $cadastroLegend !== []
            ? $cadastroLegend
            : \App\Support\Dashboard\MunicipalityMapCadastroPresenter::legendItemsFromMarkers($mapMarkers ?? []),
    ];
@endphp
<section class="serv-panel overflow-hidden" aria-labelledby="home-map">
    <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h3 id="home-map" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100">{{ __('Municípios implementados') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    @if ($deferCadastroSnapshot)
                        {{ __(':total município(s) · :plotted no mapa. Com conexão OK, a cor do pin vem do cadastro RX (:ano) — azul claro enquanto carrega.', [
                            'total' => number_format($totalCities),
                            'plotted' => number_format($plottedOnMap),
                            'ano' => $vigenteAno,
                        ]) }}
                    @else
                        {{ __(':total município(s) · :plotted no mapa. Cor do pin = cadastro RX :ano; laranja/roxo/cinza = problemas de conexão.', [
                            'total' => number_format($totalCities),
                            'plotted' => number_format($plottedOnMap),
                            'ano' => $vigenteAno,
                        ]) }}
                    @endif
                </p>
            </div>
            <a href="{{ route('cities.create') }}" class="serv-link text-sm shrink-0 self-start">{{ __('Nova cidade') }}</a>
        </div>
    </div>

    <div
        x-data="brazilMunicipalitiesMap(@js($mapMarkers), @js($mapStatusColors), @js($mapOptions))"
        x-init="init()"
    >
        <div class="serv-map-legend px-5 py-3 border-b border-slate-200/90 dark:border-slate-700/90 flex flex-col gap-2.5 text-xs" aria-label="{{ __('Legendas do mapa') }}">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2" role="list" aria-label="{{ __('Conexão i-Educar') }}">
                <span class="serv-map-legend__group-label">{{ __('Conexão') }}</span>
                @foreach ($mapLegend as $item)
                    <span class="serv-map-legend__item" role="listitem" title="{{ $item['description'] ?? '' }}">
                        <span
                            class="serv-map-legend-swatch serv-map-legend-swatch--connection"
                            style="background-color: {{ $item['color'] ?? '#475569' }}"
                            aria-hidden="true"
                        ></span>
                        <span class="text-slate-600 dark:text-slate-300">
                            {{ $item['label'] ?? '' }}
                            <span class="tabular-nums font-semibold text-slate-800 dark:text-slate-200">({{ number_format((int) ($item['count'] ?? 0)) }})</span>
                        </span>
                    </span>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2" role="list" aria-label="{{ __('Cadastro ano vigente (RX)') }}">
                <span class="serv-map-legend__group-label">
                    {{ __('Cadastro :ano (RX)', ['ano' => $vigenteAno]) }}
                </span>
                <template x-for="item in cadastroLegend" :key="item.status">
                    <span
                        class="serv-map-legend__item"
                        role="listitem"
                        :title="item.description || ''"
                        :class="{ 'serv-map-legend__item--pending': item.status === 'cadastro_pending' && cadastroLoadState === 'loading' }"
                        x-show="item.count > 0 || item.status === 'cadastro_pending'"
                    >
                        <span
                            class="serv-map-legend-swatch serv-map-legend-swatch--rx"
                            :class="{ 'serv-map-legend-swatch--pulse': item.status === 'cadastro_pending' && cadastroLoadState === 'loading' }"
                            :style="'background-color:' + (item.color || '#38bdf8')"
                            aria-hidden="true"
                        ></span>
                        <span class="text-slate-600 dark:text-slate-300">
                            <span x-text="item.label"></span>
                            <span class="tabular-nums font-semibold text-slate-800 dark:text-slate-200">
                                (<span x-text="(item.count ?? 0).toLocaleString('pt-BR')"></span>)
                            </span>
                        </span>
                    </span>
                </template>
                <a href="{{ route('dashboard.rx') }}" class="serv-link">{{ __('Painel RX') }}</a>
            </div>
        </div>

        <div class="relative">
        <div x-ref="map" class="serv-brazil-map" role="application" aria-label="{{ __('Mapa do Brasil com municípios cadastrados') }}"></div>

        <div
            x-show="active"
            x-cloak
            x-transition.opacity.duration.150ms
            class="serv-brazil-map-tooltip serv-brazil-map-tooltip--wide"
            :style="tooltipStyle"
            @click.outside="closeTooltip()"
        >
            <template x-if="active">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900 dark:text-slate-100" x-text="active.name + ' — ' + active.uf"></p>
                            <p class="mt-0.5 text-xs flex items-center gap-1.5 flex-wrap">
                                <span
                                    class="h-2 w-2 rounded-full shrink-0 ring-1 ring-white/80 dark:ring-slate-800"
                                    :style="'background-color:' + (statusColors[markerFillKey(active)] || statusColors.inactive)"
                                    aria-hidden="true"
                                ></span>
                                <span
                                    x-show="active.cadastro?.semaforo_label"
                                    class="font-medium"
                                    :class="{
                                        'text-emerald-600 dark:text-emerald-400': active.cadastro?.semaforo === 'green',
                                        'text-amber-700 dark:text-amber-300': active.cadastro?.semaforo === 'yellow',
                                        'text-rose-700 dark:text-rose-300': active.cadastro?.semaforo === 'red',
                                        'text-slate-600 dark:text-slate-400': !['green','yellow','red'].includes(active.cadastro?.semaforo),
                                    }"
                                    x-text="active.cadastro ? (active.cadastro.semaforo_label + ' · ' + active.vigente_ano) : active.status_label"
                                ></span>
                                <span
                                    x-show="!active.cadastro?.semaforo_label"
                                    class="text-slate-500 dark:text-slate-400"
                                    x-text="active.status_label"
                                ></span>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            x-on:click="closeTooltip()"
                            aria-label="{{ __('Fechar') }}"
                        >&times;</button>
                    </div>

                    <template x-if="active.cadastro && (active.status === 'ready' || active.status === 'inactive_setup')">
                        <div class="space-y-2">
                            <div
                                class="rounded-lg border px-2.5 py-2 text-xs leading-snug"
                                :class="cadastroAttentionClass(active.cadastro.attention_level)"
                                :title="active.cadastro.semaforo_title || ''"
                            >
                                <p class="font-semibold" x-text="active.cadastro.attention_message"></p>
                            </div>
                            <div x-show="active.cadastro.progresso_label" class="space-y-1">
                                <div class="flex justify-between text-[10px] text-slate-500 dark:text-slate-400">
                                    <span>{{ __('Progresso meta cadastro') }}</span>
                                    <span class="font-semibold tabular-nums text-slate-800 dark:text-slate-100" x-text="active.cadastro.progresso_label"></span>
                                </div>
                                <div class="h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden" role="presentation">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="{
                                            'bg-emerald-500': active.cadastro.semaforo === 'green',
                                            'bg-amber-400': active.cadastro.semaforo === 'yellow',
                                            'bg-rose-500': active.cadastro.semaforo === 'red',
                                            'bg-slate-400': !['green','yellow','red'].includes(active.cadastro.semaforo),
                                        }"
                                        :style="'width:' + Math.min(100, Math.max(0, active.cadastro.progresso_pct ?? 0)) + '%'"
                                    ></div>
                                </div>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400" x-show="active.cadastro.registros_restantes > 0">
                                    <span x-text="active.cadastro.registros_restantes.toLocaleString('pt-BR')"></span>
                                    {{ __('registo(s) em falta (turmas + matrículas + enturmações)') }}
                                </p>
                                <p
                                    class="text-[10px] font-medium text-amber-800 dark:text-amber-200"
                                    x-show="active.cadastro.meta_ano_imediato_zerado && active.cadastro.meta_saltos > 0"
                                >
                                    {{ __('Ano') }}
                                    <span class="tabular-nums" x-text="active.cadastro.anterior_ano"></span>
                                    {{ __('sem cadastro — meta com') }}
                                    <span class="tabular-nums" x-text="active.cadastro.meta_saltos"></span>
                                    {{ __('salto(s) a partir de') }}
                                    <span class="tabular-nums" x-text="active.cadastro.meta_referencia_ano"></span>.
                                </p>
                            </div>
                            <a
                                :href="active.cadastro.rx_url"
                                class="text-xs serv-link inline-flex items-center gap-1"
                            >{{ __('Ver detalhe no painel RX') }} →</a>
                        </div>
                    </template>

                    <template x-if="active.reference_contact?.available">
                        <div class="rounded-lg border border-slate-200/90 dark:border-slate-700/90 p-2.5 bg-slate-50/80 dark:bg-slate-800/40">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">{{ __('Contato municipal') }}</p>
                            <p class="text-xs font-medium text-slate-800 dark:text-slate-100 truncate" x-show="active.reference_contact.name" x-text="active.reference_contact.name"></p>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                <a
                                    x-show="active.reference_contact.phone_href"
                                    :href="active.reference_contact.phone_href"
                                    class="text-xs text-serv-navy dark:text-sky-300 hover:underline"
                                    x-text="active.reference_contact.phone"
                                ></a>
                                <a
                                    x-show="active.reference_contact.whatsapp_href"
                                    :href="active.reference_contact.whatsapp_href"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-xs text-emerald-700 dark:text-emerald-400 hover:underline"
                                >WhatsApp</a>
                                <a
                                    x-show="active.reference_contact.email_href"
                                    :href="active.reference_contact.email_href"
                                    class="text-xs text-serv-navy dark:text-sky-300 hover:underline truncate max-w-full"
                                    x-text="active.reference_contact.email"
                                ></a>
                            </div>
                        </div>
                    </template>
                    <p
                        x-show="active.reference_contact && !active.reference_contact.available"
                        class="text-xs text-slate-500 dark:text-slate-400 italic"
                    >{{ __('Sem contato cadastrado — edite o município para incluir gestor ou ponto focal.') }}</p>

                    <dl class="text-xs space-y-1 text-slate-600 dark:text-slate-300">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Conexão') }}</dt>
                            <dd class="text-right" x-text="active.status_label"></dd>
                        </div>
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
                        <p x-show="!yearsLoading && !yearsError && schoolYears.length === 0 && active.status !== 'ready'" class="text-xs text-slate-500">{{ __('Configure a conexão i-Educar para listar anos.') }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <a
                            :href="active.analytics_url"
                            class="serv-btn-map-consultoria min-w-0"
                            title="{{ __('Abrir consultoria municipal (análise educacional)') }}"
                        >
                            <svg class="h-4 w-4 shrink-0 opacity-95" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                            <span>{{ __('Consultoria') }}</span>
                        </a>
                        <a
                            :href="active.ieducar_url || '#'"
                            :target="active.ieducar_url ? '_blank' : null"
                            :rel="active.ieducar_url ? 'noopener noreferrer' : null"
                            @click="!active.ieducar_url && $event.preventDefault()"
                            :class="active.ieducar_url ? 'serv-btn-map-ieducar' : 'serv-btn-map-ieducar serv-btn-map-ieducar--disabled'"
                            :aria-disabled="!active.ieducar_url"
                            :title="active.ieducar_url ? '{{ __('Abrir o i-Educar do município numa nova aba') }}' : '{{ __('Defina a URL do i-Educar no cadastro da cidade (ou IEDUCAR_APP_URLS no .env).') }}'"
                            class="min-w-0"
                        >
                            <svg class="h-4 w-4 shrink-0 opacity-95" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.697 50.697 0 017.74-3.342M6.75 21a3.75 3.75 0 003.75-3.75V15a3.75 3.75 0 10-7.5 0v2.25A3.75 3.75 0 006.75 21z" />
                            </svg>
                            <span>{{ __('i-Educar') }}</span>
                            <svg
                                x-show="active.ieducar_url"
                                class="h-3.5 w-3.5 shrink-0 opacity-90"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    </div>
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

        <div
            x-show="showCadastroStatusBar()"
            x-cloak
            class="serv-map-rx-status border-t border-slate-200/90 dark:border-slate-700/90 px-4 py-3"
            role="status"
            aria-live="polite"
        >
            <div x-show="cadastroLoadState === 'loading'" class="serv-map-rx-status__row serv-map-rx-status__row--loading">
                <span class="serv-map-rx-status__spinner" aria-hidden="true"></span>
                <div class="min-w-0">
                    <p class="serv-map-rx-status__title">
                        {{ __('A carregar cores de cadastro RX (:ano)…', ['ano' => $vigenteAno]) }}
                    </p>
                    <p class="serv-map-rx-status__detail">
                        {{ __('Marcadores azul-claro serão atualizados conforme a meta. Tempo:') }}
                        <span class="tabular-nums font-semibold" x-text="cadastroElapsedSeconds + ' s'"></span>
                    </p>
                </div>
            </div>

            <div x-show="cadastroLoadState === 'loaded'" class="serv-map-rx-status__row serv-map-rx-status__row--ok">
                <span class="serv-map-rx-status__icon serv-map-rx-status__icon--ok" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="serv-map-rx-status__title">
                        {{ __('Mapa atualizado — cores RX (:ano) aplicadas.', ['ano' => $vigenteAno]) }}
                    </p>
                    <p class="serv-map-rx-status__detail">
                        <span x-show="cadastroSnapshotCount > 0">
                            <span x-text="cadastroSnapshotCount.toLocaleString('pt-BR')"></span>
                            {{ __('município(s) com leitura i-Educar ·') }}
                        </span>
                        {{ __('Carregado em') }}
                        <span class="tabular-nums font-semibold" x-text="formatLoadDuration(cadastroLoadDurationMs)"></span>
                        <span x-show="formatGeneratedAt(cadastroGeneratedAt)">
                            · {{ __('dados de') }}
                            <span class="tabular-nums" x-text="formatGeneratedAt(cadastroGeneratedAt)"></span>
                        </span>
                    </p>
                </div>
            </div>

            <div x-show="cadastroLoadState === 'error'" class="serv-map-rx-status__row serv-map-rx-status__row--error">
                <span class="serv-map-rx-status__icon serv-map-rx-status__icon--error" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="serv-map-rx-status__title">{{ __('Não foi possível atualizar as cores RX') }}</p>
                    <p class="serv-map-rx-status__detail" x-text="cadastroError"></p>
                </div>
                <button
                    type="button"
                    class="serv-btn-secondary text-xs shrink-0"
                    x-on:click="retryCadastroSnapshot()"
                    :disabled="cadastroLoading"
                >{{ __('Tentar novamente') }}</button>
            </div>
        </div>
    </div>
</section>
