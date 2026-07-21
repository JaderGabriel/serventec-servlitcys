<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <p class="serv-eyebrow">{{ __('SERVLITCYS') }} · {{ __('Educacenso') }}</p>
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
        $toneBar = static function (?float $pct): string {
            $pct = (float) ($pct ?? 0);
            if ($pct >= 80) {
                return 'bg-emerald-500';
            }
            if ($pct >= 40) {
                return 'bg-amber-500';
            }

            return 'bg-rose-500';
        };
        $statusAccent = static function ($campaign): string {
            if ((int) $campaign->findings_error_count > 0) {
                return 'bg-rose-500';
            }
            if ($campaign->hasReportReady()) {
                return 'bg-emerald-500';
            }
            if ($campaign->status === \App\Models\Clio\ClioCampaign::STATUS_PARSED) {
                return 'bg-sky-500';
            }

            return 'bg-amber-500';
        };
    @endphp

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

            {{-- Faixa de identidade / exercício --}}
            <section class="serv-panel overflow-hidden border-blue-200/70 dark:border-blue-900/50" aria-labelledby="clio-home-brand">
                <div class="relative bg-gradient-to-br from-slate-900 via-slate-900 to-blue-950 px-5 py-6 sm:px-8 sm:py-8 text-white overflow-hidden">
                    <div class="pointer-events-none absolute inset-0 opacity-[0.12]"
                         style="background-image: radial-gradient(circle at 12% 20%, rgb(96 165 250), transparent 42%), radial-gradient(circle at 88% 10%, rgb(180 134 40 / 0.55), transparent 36%), linear-gradient(115deg, transparent 40%, rgb(37 99 235 / 0.35) 100%);"></div>
                    <div class="relative grid gap-6 lg:grid-cols-12 lg:items-end">
                        <div class="lg:col-span-7 space-y-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-blue-300/90">{{ __('Relatórios · 1ª etapa') }}</p>
                            <h3 id="clio-home-brand" class="font-display text-3xl sm:text-4xl font-semibold tracking-tight">
                                {{ __('Clio') }}
                                <span class="block sm:inline text-blue-200/90 font-medium text-xl sm:text-2xl sm:ml-2">{{ $filterYear }}</span>
                            </h3>
                            <p class="max-w-xl text-sm text-slate-300 leading-relaxed">
                                {{ __('Leitura operacional da Matrícula inicial: cobertura da tríade, inconsistências Educacenso e exportação PDF/CSV por município.') }}
                            </p>
                        </div>
                        <div class="lg:col-span-5">
                            <form method="get" action="{{ route('clio.home') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end rounded-xl border border-white/10 bg-white/5 p-3 backdrop-blur-sm">
                                <div class="sm:w-28">
                                    <label for="clio-home-year" class="block text-[10px] font-semibold uppercase tracking-wide text-blue-200/80">{{ __('Exercício') }}</label>
                                    <select id="clio-home-year" name="year" class="mt-1 w-full rounded-lg border-0 bg-white/95 text-slate-900 text-sm focus:ring-2 focus:ring-blue-400" onchange="this.form.submit()">
                                        @foreach ($years as $y)
                                            <option value="{{ $y }}" @selected((int) $filterYear === (int) $y)>{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label for="clio-home-q" class="block text-[10px] font-semibold uppercase tracking-wide text-blue-200/80">{{ __('Município') }}</label>
                                    <input id="clio-home-q" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Nome, UF ou IBGE…') }}"
                                           class="mt-1 w-full rounded-lg border-0 bg-white/95 text-slate-900 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-blue-400" />
                                </div>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">{{ __('Buscar') }}</button>
                            </form>
                            @if ($search !== '')
                                <p class="mt-2 text-right">
                                    <a href="{{ route('clio.home', ['year' => $filterYear]) }}" class="text-xs text-blue-200 hover:text-white underline-offset-2 hover:underline">{{ __('Limpar busca') }}</a>
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-5 gap-px bg-slate-200/80 dark:bg-slate-800">
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
                            'hint' => __('Cobertura aluno + turma + profissional'),
                        ],
                        [
                            'label' => __('Erros na rede'),
                            'value' => number_format($yearErrors ?? 0),
                            'hint' => __('Escolas na coleta: :n', ['n' => number_format($yearSchools ?? 0)]),
                        ],
                    ] as $kpi)
                        <div class="bg-white px-4 py-4 dark:bg-slate-900/60">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $kpi['label'] }}</p>
                            <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-serv-navy dark:text-white">{{ $kpi['value'] }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-500 leading-snug">{{ $kpi['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Biblioteca de relatórios --}}
            <section aria-labelledby="clio-home-reports-heading" class="space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 id="clio-home-reports-heading" class="font-display text-lg font-semibold text-serv-navy dark:text-white">
                            {{ __('Relatórios por município') }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Cada cartão é o relatório operacional da coleta — abra para indicadores, apontamentos e exportação.') }}
                        </p>
                    </div>
                    <a href="{{ route('clio.campaigns.index', ['year' => $filterYear]) }}" class="serv-link text-sm shrink-0">
                        {{ __('Vista em tabela') }} →
                    </a>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($campaigns as $campaign)
                        @php
                            $triade = $campaign->triadeCoveragePct();
                            $ready = $campaign->hasReportReady();
                            $errors = (int) $campaign->findings_error_count;
                            $warnings = (int) ($campaign->findings_warning_count ?? 0);
                        @endphp
                        <article class="serv-panel group relative flex flex-col transition hover:ring-blue-500/20 hover:border-blue-300/80 dark:hover:border-blue-700/60">
                            <div class="absolute inset-y-0 left-0 w-1 {{ $statusAccent($campaign) }}" aria-hidden="true"></div>
                            <div class="flex flex-1 flex-col p-4 sm:p-5 pl-5 sm:pl-6">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                                            {{ __('Relatório') }} · {{ $campaign->uf }}
                                        </p>
                                        <h4 class="mt-1 font-display text-lg font-semibold text-serv-navy dark:text-white leading-snug truncate">
                                            {{ $campaign->municipality_name }}
                                        </h4>
                                    </div>
                                    <span class="shrink-0 inline-flex rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $campaign->isAnalysisOnly() ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100' : 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-100' }}">
                                        {{ $campaign->profileLabel() }}
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11px]">
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {{ $campaign->statusLabel() }}
                                    </span>
                                    @if ($ready)
                                        <span class="inline-flex rounded-md bg-emerald-100 px-2 py-0.5 font-medium text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100">
                                            {{ __('Relatório pronto') }}
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-md bg-amber-100 px-2 py-0.5 font-medium text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                                            {{ __('Em preparação') }}
                                        </span>
                                    @endif
                                    @if ($errors > 0)
                                        <span class="inline-flex rounded-md bg-rose-100 px-2 py-0.5 font-medium text-rose-900 dark:bg-rose-950/40 dark:text-rose-100">
                                            {{ __(':n erro(s)', ['n' => $errors]) }}
                                        </span>
                                    @elseif ($warnings > 0)
                                        <span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 font-medium text-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                                            {{ __(':n aviso(s)', ['n' => $warnings]) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-4 space-y-1.5">
                                    <div class="flex items-baseline justify-between gap-2 text-xs">
                                        <span class="text-slate-500">{{ __('Cobertura da tríade') }}</span>
                                        <span class="tabular-nums font-semibold text-serv-navy dark:text-white">
                                            {{ $triade !== null ? number_format((float) $triade, 1, ',', '.').'%' : '—' }}
                                        </span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                        <div class="h-full rounded-full transition-all {{ $toneBar($triade) }}"
                                             style="width: {{ $triade !== null ? min(100, max(0, (float) $triade)) : 0 }}%"></div>
                                    </div>
                                </div>

                                <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-lg bg-slate-50 px-2 py-2 dark:bg-slate-800/60">
                                        <dt class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Escolas') }}</dt>
                                        <dd class="mt-0.5 font-display text-base font-semibold tabular-nums text-serv-navy dark:text-white">{{ $campaign->schools_count }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 px-2 py-2 dark:bg-slate-800/60">
                                        <dt class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Arquivos') }}</dt>
                                        <dd class="mt-0.5 font-display text-base font-semibold tabular-nums text-serv-navy dark:text-white">{{ $campaign->artifacts_count }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 px-2 py-2 dark:bg-slate-800/60">
                                        <dt class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Ref.') }}</dt>
                                        <dd class="mt-0.5 text-xs font-semibold tabular-nums text-serv-navy dark:text-white">
                                            {{ $campaign->reference_date ? $campaign->reference_date->format('d/m') : '—' }}
                                        </dd>
                                    </div>
                                </dl>

                                <div class="mt-auto pt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 dark:border-slate-800">
                                    <a href="{{ $campaign->primaryReportUrl() }}"
                                       class="{{ $ready ? 'serv-btn-primary' : 'serv-btn-secondary' }} text-sm flex-1 sm:flex-none text-center">
                                        {{ $ready ? __('Abrir relatório') : __('Abrir coleta') }}
                                    </a>
                                    @if ($ready)
                                        @can('export', $campaign)
                                            <a href="{{ route('clio.campaigns.export.pdf', $campaign) }}" class="serv-btn-secondary text-sm" title="{{ __('Exportar PDF') }}">{{ __('PDF') }}</a>
                                        @endcan
                                    @endif
                                    <a href="{{ route('clio.campaigns.show', $campaign) }}" class="serv-link text-sm ml-auto">{{ __('Central') }}</a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="sm:col-span-2 xl:col-span-3 serv-panel px-6 py-14 text-center">
                            <p class="serv-eyebrow">{{ __('Clio') }}</p>
                            <p class="mt-2 font-display text-xl font-semibold text-serv-navy dark:text-white">
                                {{ $search !== '' ? __('Nenhum município encontrado com este filtro.') : __('Ainda não há coletas neste exercício.') }}
                            </p>
                            <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">
                                {{ __('Cadastre o município, crie a coleta do ano e envie os CSV/ZIP do portal Educacenso para gerar o relatório.') }}
                            </p>
                            <div class="mt-6 flex flex-wrap justify-center gap-2">
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

            @if ($citiesWithoutCampaign->isNotEmpty())
                <section aria-labelledby="clio-home-pending-heading" class="serv-panel overflow-hidden">
                    <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-blue-50/40 px-4 py-4 dark:border-slate-800 dark:from-slate-900/80 dark:via-slate-900/40 dark:to-blue-950/20 sm:px-5">
                        <h3 id="clio-home-pending-heading" class="font-display text-base font-semibold text-serv-navy dark:text-white">
                            {{ __('Municípios sem coleta em :ano', ['ano' => $filterYear]) }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Já estão no catálogo Clio, mas ainda sem relatório neste exercício.') }}
                            @if ($citiesWithoutCampaignTotal > $citiesWithoutCampaign->count())
                                {{ __('Mostrando :n de :t.', ['n' => $citiesWithoutCampaign->count(), 't' => $citiesWithoutCampaignTotal]) }}
                            @endif
                        </p>
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($citiesWithoutCampaign as $city)
                            <li class="flex flex-col gap-2 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div>
                                    <p class="font-medium text-serv-navy dark:text-white">{{ $city->name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $city->uf }}
                                        @if ($city->ibge_municipio) · {{ $city->ibge_municipio }} @endif
                                        · {{ $city->hasDataSetup() ? __('Consultoria') : __('Só coleta') }}
                                    </p>
                                </div>
                                @can('create', App\Models\Clio\ClioCampaign::class)
                                    <a href="{{ route('clio.campaigns.create', ['city_id' => $city->id, 'year' => $filterYear]) }}" class="serv-btn-secondary text-sm shrink-0">
                                        {{ __('Iniciar coleta') }}
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
