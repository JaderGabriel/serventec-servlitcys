<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <p class="clio-eyebrow">{{ __('SERVLITCYS') }} · {{ __('Educacenso') }}</p>
                <h2 class="font-display font-semibold text-2xl sm:text-3xl text-serv-navy dark:text-white leading-tight tracking-tight">
                    {{ __('Clio') }}
                </h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                    {{ __('Central de relatórios da Matrícula inicial — escolha o município e abra o quadro analítico da coleta.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @can('createCatalogCity', App\Models\Clio\ClioCampaign::class)
                    <a href="{{ route('clio.cities.create') }}" class="serv-btn-secondary text-sm">{{ __('Novo município') }}</a>
                @endcan
                @can('create', App\Models\Clio\ClioCampaign::class)
                    <a href="{{ route('clio.campaigns.create') }}" class="serv-btn-primary text-sm">{{ __('Nova coleta') }}</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @php
        $meterTone = static function (?float $pct): string {
            $pct = (float) ($pct ?? 0);
            if ($pct >= 80) {
                return 'good';
            }
            if ($pct >= 40) {
                return 'mid';
            }

            return 'bad';
        };
        $railTone = static function ($campaign): string {
            if ((int) $campaign->findings_error_count > 0) {
                return 'error';
            }
            if ($campaign->hasReportReady()) {
                return 'ready';
            }
            if ($campaign->status === \App\Models\Clio\ClioCampaign::STATUS_PARSED) {
                return 'parsed';
            }

            return 'progress';
        };
    @endphp

    <div class="clio-page py-8 sm:py-10">
        <div class="clio-shell">
            @if (session('success'))
                <div class="clio-flash clio-flash--ok">{{ session('success') }}</div>
            @endif
            @if (session('warning'))
                <div class="clio-flash clio-flash--warn">{{ session('warning') }}</div>
            @endif

            <section class="overflow-hidden" aria-labelledby="clio-home-brand">
                <div class="clio-hero">
                    <div class="clio-hero__glow" aria-hidden="true"></div>
                    <div class="clio-hero__accent" aria-hidden="true"></div>
                    <div class="clio-hero__body">
                        <div class="lg:col-span-7 space-y-1">
                            <p class="clio-hero__kicker">{{ __('Relatórios · 1ª etapa') }}</p>
                            <h3 id="clio-home-brand" class="clio-hero__title">
                                {{ __('Clio') }}
                                <span class="clio-hero__year">{{ $filterYear }}</span>
                            </h3>
                            <p class="clio-hero__lead">
                                {{ __('Leitura operacional da Matrícula inicial: cobertura da tríade, inconsistências Educacenso e exportação PDF/CSV por município.') }}
                            </p>
                        </div>
                        <div class="lg:col-span-5">
                            <form method="get" action="{{ route('clio.home') }}" class="clio-hero__search">
                                <div class="sm:w-28">
                                    <label for="clio-home-year" class="clio-hero__label">{{ __('Exercício') }}</label>
                                    <select id="clio-home-year" name="year" class="clio-hero__field" onchange="this.form.submit()">
                                        @foreach ($years as $y)
                                            <option value="{{ $y }}" @selected((int) $filterYear === (int) $y)>{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label for="clio-home-q" class="clio-hero__label">{{ __('Município') }}</label>
                                    <input id="clio-home-q" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Nome, UF ou IBGE…') }}"
                                           class="clio-hero__field" />
                                </div>
                                <button type="submit" class="clio-hero__submit">{{ __('Buscar') }}</button>
                            </form>
                            @if ($search !== '')
                                <p class="mt-2 text-right">
                                    <a href="{{ route('clio.home', ['year' => $filterYear]) }}" class="clio-hero__clear">{{ __('Limpar busca') }}</a>
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="clio-kpi-strip">
                    @foreach ([
                        [
                            'label' => __('Municípios'),
                            'value' => number_format($campaigns->total()),
                            'hint' => __('Com coleta neste exercício'),
                        ],
                        [
                            'label' => __('Relatórios prontos'),
                            'value' => number_format($reportReadyCount),
                            'hint' => __('Análise ou cruzamento concluído'),
                        ],
                        [
                            'label' => __('Em andamento'),
                            'value' => number_format($inProgressCount ?? 0),
                            'hint' => __('Ainda sem relatório fechado'),
                        ],
                        [
                            'label' => __('Tríade média'),
                            'value' => $avgTriade !== null ? number_format($avgTriade, 1, ',', '.').'%' : '—',
                            'hint' => __('Só escolas em atividade (aluno + turma + profissional)'),
                        ],
                        [
                            'label' => __('Erros na rede'),
                            'value' => number_format($yearErrors ?? 0),
                            'hint' => __('Escolas ativas na coleta: :n', ['n' => number_format($yearSchools ?? 0)]),
                        ],
                    ] as $kpi)
                        <div class="clio-kpi-cell">
                            <p class="clio-kpi-cell__label">{{ $kpi['label'] }}</p>
                            <p class="clio-kpi-cell__value">{{ $kpi['value'] }}</p>
                            <p class="clio-kpi-cell__hint">{{ $kpi['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section aria-labelledby="clio-home-reports-heading" class="space-y-4">
                <div class="clio-section-head">
                    <div>
                        <h3 id="clio-home-reports-heading" class="clio-section-title">
                            {{ __('Relatórios por município') }}
                        </h3>
                        <p class="clio-section-lead">
                            {{ __('Cada cartão é o relatório operacional da coleta — abra para indicadores, apontamentos e exportação.') }}
                        </p>
                    </div>
                    <a href="{{ route('clio.campaigns.index', ['year' => $filterYear]) }}" class="serv-link text-sm shrink-0">
                        {{ __('Vista em tabela') }} →
                    </a>
                </div>

                <div class="clio-report-grid">
                    @forelse ($campaigns as $campaign)
                        @php
                            $scope = $campaign->schoolScopeStats();
                            $triade = $scope['triade_pct'];
                            $ready = $campaign->hasReportReady();
                            $errorCount = (int) $campaign->findings_error_count;
                            $warningCount = (int) ($campaign->findings_warning_count ?? 0);
                        @endphp
                        <article
                            class="clio-report-card"
                            x-data="clioReportCard(@js(route('clio.campaigns.enrollment-series', $campaign)))"
                        >
                            <div class="clio-report-card__rail clio-report-card__rail--{{ $railTone($campaign) }}" aria-hidden="true"></div>
                            <div class="clio-report-card__body">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="clio-report-card__meta">
                                            {{ $campaign->ibge_municipio ?: '—' }} · {{ $campaign->uf }}
                                        </p>
                                        <h4 class="clio-report-card__title">
                                            {{ $campaign->municipality_name }}
                                        </h4>
                                    </div>
                                    @include('clio.partials.profile-mark', ['analysisOnly' => $campaign->isAnalysisOnly()])
                                </div>

                                <div class="clio-report-card__chips">
                                    <span class="clio-chip clio-chip--neutral">{{ $campaign->statusLabel() }}</span>
                                    @if ($ready)
                                        <span class="clio-chip clio-chip--ready">{{ __('Relatório pronto') }}</span>
                                    @else
                                        <span class="clio-chip clio-chip--warn">{{ __('Em preparação') }}</span>
                                    @endif
                                    @if ($errorCount > 0)
                                        <span class="clio-chip clio-chip--error">{{ __(':n erro(s)', ['n' => $errorCount]) }}</span>
                                    @elseif ($warningCount > 0)
                                        <span class="clio-chip clio-chip--warn">{{ __(':n aviso(s)', ['n' => $warningCount]) }}</span>
                                    @endif
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
                                                <span class="clio-meter__value">
                                                    {{ $triade !== null ? number_format((float) $triade, 1, ',', '.').'%' : '—' }}
                                                </span>
                                            </div>
                                            <p class="clio-meter__hint">{{ __('Escolas em atividade') }}</p>
                                            <div class="clio-meter__track">
                                                <div class="clio-meter__fill clio-meter__fill--{{ $meterTone($triade) }}"
                                                     style="width: {{ $triade !== null ? min(100, max(0, (float) $triade)) : 0 }}%"></div>
                                            </div>
                                        </div>

                                        <dl class="clio-mini-stats">
                                            <div class="clio-mini-stat">
                                                <dt class="clio-mini-stat__label">{{ __('Escolas') }}</dt>
                                                <dd class="clio-mini-stat__value">{{ $scope['active'] }}</dd>
                                            </div>
                                            <div class="clio-mini-stat">
                                                <dt class="clio-mini-stat__label">{{ __('Arquivos') }}</dt>
                                                <dd class="clio-mini-stat__value">{{ $campaign->artifacts_count }}</dd>
                                            </div>
                                            <div class="clio-mini-stat">
                                                <dt class="clio-mini-stat__label">{{ __('Ref.') }}</dt>
                                                <dd class="clio-mini-stat__value clio-mini-stat__value--sm">
                                                    {{ $campaign->reference_date ? $campaign->reference_date->format('d/m') : '—' }}
                                                </dd>
                                            </div>
                                        </dl>
                                        @if (($scope['other'] ?? 0) > 0)
                                            <p class="clio-report-card__aside">
                                                {{ __('+:n demais situações (extinta, paralisada ou reforma)', ['n' => $scope['other']]) }}
                                            </p>
                                        @endif
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
                                    <a href="{{ route('clio.campaigns.show', $campaign) }}"
                                       class="clio-card-action clio-card-action--central"
                                       title="{{ __('Central da coleta — arquivos e processamento') }}">
                                        <span class="clio-card-action__icon" aria-hidden="true">
                                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75ZM2 10a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/></svg>
                                        </span>
                                        <span class="clio-card-action__label">{{ __('Central') }}</span>
                                    </a>

                                    <a href="{{ $campaign->primaryReportUrl() }}"
                                       class="clio-card-action clio-card-action--report {{ $ready ? 'clio-card-action--ready' : '' }}"
                                       title="{{ $ready ? __('Abrir relatório analítico') : __('Abrir coleta') }}">
                                        <span class="clio-card-action__icon" aria-hidden="true">
                                            @if ($ready)
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 2A2.25 2.25 0 0 0 2 4.25v11.5A2.25 2.25 0 0 0 4.25 18h11.5A2.25 2.25 0 0 0 18 15.75V4.25A2.25 2.25 0 0 0 15.75 2H4.25ZM3.5 4.25a.75.75 0 0 1 .75-.75h11.5a.75.75 0 0 1 .75.75v11.5a.75.75 0 0 1-.75.75H4.25a.75.75 0 0 1-.75-.75V4.25Zm2.5 2a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm0 3.5a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm0 3.5a.75.75 0 0 0 0 1.5h4a.75.75 0 0 0 0-1.5H6Z" clip-rule="evenodd"/></svg>
                                            @else
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M3.5 2.75a.75.75 0 0 0-1.5 0v14.5a.75.75 0 0 0 1.5 0v-4.392l1.657-.348a6.45 6.45 0 0 1 1.837.148l.1.023a7.95 7.95 0 0 0 2.265.186h3.832a.75.75 0 0 0 0-1.5H9.39a9.45 9.45 0 0 1-2.696-.223l-.1-.023a4.95 4.95 0 0 0-1.41-.113l-.854.18V2.75Z"/></svg>
                                            @endif
                                        </span>
                                        <span class="clio-card-action__label">{{ $ready ? __('Relatório') : __('Coleta') }}</span>
                                    </a>

                                    @if ($ready)
                                        <a href="{{ route('clio.campaigns.insights', $campaign) }}"
                                           class="clio-card-action clio-card-action--insights"
                                           title="{{ __('Insights gerenciais e dataset BI') }}">
                                            <span class="clio-card-action__icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M15.98 1.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.238a1 1 0 0 0 0 1.962l1.192.238a1 1 0 0 1 .785.785l.238 1.192a1 1 0 0 0 1.962 0l.238-1.192a1 1 0 0 1 .785-.785l1.192-.238a1 1 0 0 0 0-1.962l-1.192-.238a1 1 0 0 1-.785-.785l-.238-1.192ZM6.949 5.684a1 1 0 0 0-1.898 0l-.683 2.051a1 1 0 0 1-.633.633l-2.051.683a1 1 0 0 0 0 1.898l2.051.684a1 1 0 0 1 .633.632l.683 2.051a1 1 0 0 0 1.898 0l.683-2.051a1 1 0 0 1 .633-.633l2.051-.683a1 1 0 0 0 0-1.898l-2.051-.683a1 1 0 0 1-.633-.633L6.95 5.684ZM13.949 13.684a1 1 0 0 0-1.898 0l-.184.551a1 1 0 0 1-.632.633l-.551.183a1 1 0 0 0 0 1.898l.551.183a1 1 0 0 1 .633.633l.183.551a1 1 0 0 0 1.898 0l.184-.551a1 1 0 0 1 .632-.633l.551-.183a1 1 0 0 0 0-1.898l-.551-.184a1 1 0 0 1-.633-.632l-.183-.551Z"/></svg>
                                            </span>
                                            <span class="clio-card-action__label">{{ __('Insights') }}</span>
                                        </a>
                                    @else
                                        <span class="clio-card-action clio-card-action--insights clio-card-action--disabled"
                                              title="{{ __('Disponível após analisar a coleta') }}">
                                            <span class="clio-card-action__icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M15.98 1.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.238a1 1 0 0 0 0 1.962l1.192.238a1 1 0 0 1 .785.785l.238 1.192a1 1 0 0 0 1.962 0l.238-1.192a1 1 0 0 1 .785-.785l1.192-.238a1 1 0 0 0 0-1.962l-1.192-.238a1 1 0 0 1-.785-.785l-.238-1.192ZM6.949 5.684a1 1 0 0 0-1.898 0l-.683 2.051a1 1 0 0 1-.633.633l-2.051.683a1 1 0 0 0 0 1.898l2.051.684a1 1 0 0 1 .633.632l.683 2.051a1 1 0 0 0 1.898 0l.683-2.051a1 1 0 0 1 .633-.633l2.051-.683a1 1 0 0 0 0-1.898l-2.051-.683a1 1 0 0 1-.633-.633L6.95 5.684ZM13.949 13.684a1 1 0 0 0-1.898 0l-.184.551a1 1 0 0 1-.632.633l-.551.183a1 1 0 0 0 0 1.898l.551.183a1 1 0 0 1 .633.633l.183.551a1 1 0 0 0 1.898 0l.184-.551a1 1 0 0 1 .632-.633l.551-.183a1 1 0 0 0 0-1.898l-.551-.184a1 1 0 0 1-.633-.632l-.183-.551Z"/></svg>
                                            </span>
                                            <span class="clio-card-action__label">{{ __('Insights') }}</span>
                                        </span>
                                    @endif

                                    @if ($ready)
                                        @can('export', $campaign)
                                            @include('clio.campaigns.partials.downloads-menu', [
                                                'campaign' => $campaign,
                                                'variant' => 'card',
                                            ])
                                        @else
                                            <span class="clio-card-action clio-card-action--download clio-card-action--disabled"
                                                  title="{{ __('Sem permissão de exportação') }}">
                                                <span class="clio-card-action__icon" aria-hidden="true">
                                                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                                                </span>
                                                <span class="clio-card-action__label">{{ __('Exportar') }}</span>
                                            </span>
                                        @endcan
                                    @else
                                        <span class="clio-card-action clio-card-action--download clio-card-action--disabled"
                                              title="{{ __('Disponível após analisar a coleta') }}">
                                            <span class="clio-card-action__icon" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                                            </span>
                                            <span class="clio-card-action__label">{{ __('Exportar') }}</span>
                                        </span>
                                    @endif
                                </div>

                                @php $files = $campaign->fileProcessingSummary(); @endphp
                                <div class="clio-file-pulse clio-file-pulse--{{ $files['tone'] }}" title="{{ $files['acomp']['name'] ?? __('Relatório Acomp. Coleta 1ª etapa') }}">
                                    <span class="clio-file-pulse__item clio-file-pulse__item--{{ $files['tone'] }}">
                                        <span class="clio-file-pulse__icon" aria-hidden="true">
                                            @if ($files['tone'] === 'ok')
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                                            @elseif ($files['tone'] === 'error')
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                            @elseif ($files['tone'] === 'warn')
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.63-1.515 2.63H3.72c-1.347 0-2.188-1.463-1.515-2.63L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                            @else
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5A1.5 1.5 0 0 1 4.5 2h6.879a1.5 1.5 0 0 1 1.06.44l4.122 4.12A1.5 1.5 0 0 1 17 7.622V16.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 3 16.5v-13Z"/></svg>
                                            @endif
                                        </span>
                                        <span class="clio-file-pulse__text">{{ $files['label'] }}</span>
                                    </span>
                                    <span class="clio-file-pulse__sep" aria-hidden="true">·</span>
                                    <span class="clio-file-pulse__item clio-file-pulse__item--{{ $files['acomp']['tone'] }}">
                                        <span class="clio-file-pulse__icon" aria-hidden="true">
                                            @if ($files['acomp']['tone'] === 'ok')
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                                            @elseif ($files['acomp']['tone'] === 'error')
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                            @elseif ($files['acomp']['tone'] === 'warn')
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.63-1.515 2.63H3.72c-1.347 0-2.188-1.463-1.515-2.63L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                            @else
                                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.75 2A1.75 1.75 0 0 0 3 3.75v12.5c0 .966.784 1.75 1.75 1.75h10.5A1.75 1.75 0 0 0 17 16.25V7.56a1.75 1.75 0 0 0-.513-1.238L12.68 2.513A1.75 1.75 0 0 0 11.44 2H4.75Z"/></svg>
                                            @endif
                                        </span>
                                        <span class="clio-file-pulse__acomp">{{ $files['acomp']['label'] }}</span>
                                    </span>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="sm:col-span-2 xl:col-span-3 clio-empty">
                            <p class="clio-eyebrow">{{ __('Clio') }}</p>
                            <p class="clio-empty__title">
                                {{ $search !== '' ? __('Nenhum município encontrado com este filtro.') : __('Ainda não há coletas neste exercício.') }}
                            </p>
                            <p class="clio-empty__lead">
                                {{ __('Cadastre o município, crie a coleta do ano e envie os CSV/ZIP do portal Educacenso para gerar o relatório.') }}
                            </p>
                            <div class="clio-empty__actions">
                                @can('createCatalogCity', App\Models\Clio\ClioCampaign::class)
                                    <a href="{{ route('clio.cities.create') }}" class="serv-btn-secondary text-sm">{{ __('Novo município') }}</a>
                                @endcan
                                @can('create', App\Models\Clio\ClioCampaign::class)
                                    <a href="{{ route('clio.campaigns.create') }}" class="serv-btn-primary text-sm">{{ __('Nova coleta') }}</a>
                                @endcan
                            </div>
                        </div>
                    @endforelse
                </div>

                @if ($campaigns->hasPages())
                    <div class="pt-2">
                        {{ $campaigns->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
