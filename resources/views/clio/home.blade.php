<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <p class="serv-eyebrow">{{ __('Educacenso · 1ª etapa') }}</p>
                <h2 class="font-display font-semibold text-2xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Clio') }}
                </h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                    {{ __('Escolha o município para abrir o relatório da Matrícula inicial — cobertura, inconsistências e, quando houver i-Educar, o cruzamento.') }}
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

    <div class="py-8 sm:py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ session('warning') }}
                </div>
            @endif

            <form method="get" action="{{ route('clio.home') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div>
                    <label for="clio-home-year" class="block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Exercício') }}</label>
                    <select id="clio-home-year" name="year" class="serv-input mt-1 text-sm" onchange="this.form.submit()">
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected((int) $filterYear === (int) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[12rem]">
                    <label for="clio-home-q" class="block text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Buscar município') }}</label>
                    <input id="clio-home-q" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Nome, UF ou IBGE…') }}"
                           class="serv-input mt-1 w-full text-sm" />
                </div>
                <button type="submit" class="serv-btn-secondary text-sm">{{ __('Filtrar') }}</button>
                @if ($search !== '')
                    <a href="{{ route('clio.home', ['year' => $filterYear]) }}" class="text-sm text-slate-500 hover:underline self-center">{{ __('Limpar') }}</a>
                @endif
            </form>

            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600 dark:text-slate-400">
                <span>{{ __(':n município(s) com coleta', ['n' => $campaigns->total()]) }}</span>
                <span>{{ __(':n com relatório pronto', ['n' => $reportReadyCount]) }}</span>
                @if ($avgTriade !== null)
                    <span>{{ __('Tríade média :p%', ['p' => $avgTriade]) }}</span>
                @endif
            </div>

            <section aria-labelledby="clio-home-reports-heading">
                <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                    <h3 id="clio-home-reports-heading" class="font-display text-lg font-semibold text-serv-navy dark:text-white">
                        {{ __('Relatórios por município') }}
                    </h3>
                    <a href="{{ route('clio.campaigns.index', ['year' => $filterYear]) }}" class="serv-link text-sm">
                        {{ __('Vista em tabela') }} →
                    </a>
                </div>

                @forelse ($campaigns as $campaign)
                    @php
                        $triade = $campaign->triadeCoveragePct();
                        $ready = $campaign->hasReportReady();
                    @endphp
                    <article class="serv-panel mb-3 p-4 sm:p-5 transition hover:border-sky-300/70 dark:hover:border-sky-700/60">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="font-display text-base font-semibold text-serv-navy dark:text-white truncate">
                                        {{ $campaign->municipality_name }}
                                    </h4>
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $campaign->isAnalysisOnly() ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100' : 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-100' }}">
                                        {{ $campaign->profileLabel() }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $campaign->uf }}
                                    @if ($campaign->ibge_municipio)
                                        · {{ $campaign->ibge_municipio }}
                                    @endif
                                    · {{ $campaign->statusLabel() }}
                                    @if ($triade !== null)
                                        · {{ __('Tríade :p%', ['p' => number_format((float) $triade, 1, ',', '.')]) }}
                                    @endif
                                    @if ((int) $campaign->findings_error_count > 0)
                                        · <span class="text-rose-700 dark:text-rose-300 font-medium">{{ __(':n erro(s)', ['n' => $campaign->findings_error_count]) }}</span>
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ __(':a arquivo(s) · :s escola(s)', ['a' => $campaign->artifacts_count, 's' => $campaign->schools_count]) }}
                                    @if ($campaign->reference_date)
                                        · {{ __('Ref. :d', ['d' => $campaign->reference_date->format('d/m/Y')]) }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <a href="{{ $campaign->primaryReportUrl() }}"
                                   class="{{ $ready ? 'serv-btn-primary' : 'serv-btn-secondary' }} text-sm">
                                    {{ $ready ? __('Abrir relatório') : __('Abrir coleta') }}
                                </a>
                                @if ($ready)
                                    @can('export', $campaign)
                                        <a href="{{ route('clio.campaigns.export.pdf', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('PDF') }}</a>
                                    @endcan
                                @endif
                                <a href="{{ route('clio.campaigns.show', $campaign) }}" class="serv-link text-sm">{{ __('Central') }}</a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="serv-panel px-6 py-12 text-center">
                        <p class="font-display text-lg font-semibold text-serv-navy dark:text-white">
                            {{ $search !== '' ? __('Nenhum município encontrado com este filtro.') : __('Ainda não há coletas neste exercício.') }}
                        </p>
                        <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">
                            {{ __('Cadastre o município (só coleta ou consultoria), crie a coleta do ano e envie os CSV/ZIP do portal Educacenso.') }}
                        </p>
                        <div class="mt-5 flex flex-wrap justify-center gap-2">
                            @can('createCatalogCity', App\Models\Clio\ClioCampaign::class)
                                <a href="{{ route('clio.cities.create') }}" class="serv-btn-secondary text-sm">{{ __('Novo município') }}</a>
                            @endcan
                            @can('create', App\Models\Clio\ClioCampaign::class)
                                <a href="{{ route('clio.campaigns.create') }}" class="serv-btn-primary text-sm">{{ __('Nova coleta') }}</a>
                            @endcan
                        </div>
                    </div>
                @endforelse

                @if ($campaigns->hasPages())
                    <div class="mt-4">
                        {{ $campaigns->links() }}
                    </div>
                @endif
            </section>

            @if ($citiesWithoutCampaign->isNotEmpty())
                <section aria-labelledby="clio-home-pending-heading" class="border-t border-slate-200 pt-8 dark:border-slate-800">
                    <h3 id="clio-home-pending-heading" class="font-display text-lg font-semibold text-serv-navy dark:text-white">
                        {{ __('Municípios sem coleta em :ano', ['ano' => $filterYear]) }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('Já estão no catálogo Clio, mas ainda sem coleta neste exercício.') }}
                        @if ($citiesWithoutCampaignTotal > $citiesWithoutCampaign->count())
                            {{ __('Mostrando :n de :t.', ['n' => $citiesWithoutCampaign->count(), 't' => $citiesWithoutCampaignTotal]) }}
                        @endif
                    </p>
                    <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800 serv-panel overflow-hidden">
                        @foreach ($citiesWithoutCampaign as $city)
                            <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium text-serv-navy dark:text-white">{{ $city->name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $city->uf }}
                                        @if ($city->ibge_municipio) · {{ $city->ibge_municipio }} @endif
                                        · {{ $city->hasDataSetup() ? __('Consultoria') : __('Só coleta') }}
                                    </p>
                                </div>
                                @can('create', App\Models\Clio\ClioCampaign::class)
                                    <a href="{{ route('clio.campaigns.create', ['city_id' => $city->id, 'year' => $filterYear]) }}" class="serv-link text-sm font-medium shrink-0">
                                        {{ __('Iniciar coleta') }} →
                                    </a>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
