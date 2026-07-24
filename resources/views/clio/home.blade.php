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
                            'hint' => __('Escolas na coleta: :n', ['n' => number_format($yearSchools ?? 0)]),
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
                            $errors = (int) $campaign->findings_error_count;
                            $warnings = (int) ($campaign->findings_warning_count ?? 0);
                        @endphp
                        <article class="clio-report-card">
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
                                    @if ($errors > 0)
                                        <span class="clio-chip clio-chip--error">{{ __(':n erro(s)', ['n' => $errors]) }}</span>
                                    @elseif ($warnings > 0)
                                        <span class="clio-chip clio-chip--warn">{{ __(':n aviso(s)', ['n' => $warnings]) }}</span>
                                    @endif
                                </div>

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

                                <div class="clio-report-card__footer">
                                    <a href="{{ $campaign->primaryReportUrl() }}"
                                       class="{{ $ready ? 'serv-btn-primary' : 'serv-btn-secondary' }} text-sm flex-1 sm:flex-none text-center">
                                        {{ $ready ? __('Abrir relatório') : __('Abrir coleta') }}
                                    </a>
                                    @if ($ready)
                                        @can('export', $campaign)
                                            @include('clio.campaigns.partials.downloads-menu', ['campaign' => $campaign])
                                        @endcan
                                    @endif
                                    <a href="{{ route('clio.campaigns.show', $campaign) }}" class="serv-link text-sm ml-auto">{{ __('Central') }}</a>
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
