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
                            'value' => number_format($municipalityCards->total()),
                            'hint' => ($collectionsCount ?? 0) > $municipalityCards->total()
                                ? __(':n coletas neste exercício', ['n' => number_format($collectionsCount)])
                                : __('Com coleta neste exercício'),
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
                            {{ __('Um cartão por município — se houver várias coletas, alterne pelas datas de referência e de coleta. Cada coleta mantém análise, Insights e PDFs próprios.') }}
                        </p>
                    </div>
                    <a href="{{ route('clio.campaigns.index', ['year' => $filterYear]) }}" class="serv-link text-sm shrink-0">
                        {{ __('Vista em tabela') }} →
                    </a>
                </div>

                <div class="clio-report-grid">
                    @forelse ($municipalityCards as $card)
                        @include('clio.partials.home-municipality-card', ['card' => $card])
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

                @if ($municipalityCards->hasPages())
                    <div class="pt-2">
                        {{ $municipalityCards->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
