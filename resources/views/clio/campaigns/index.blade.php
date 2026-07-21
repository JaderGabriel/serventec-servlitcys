<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Clio') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Coletas — vista em tabela') }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-2xl leading-relaxed">
                    {{ __('Lista operacional do exercício. Para abrir relatórios por município, use o início do Clio.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('clio.home', ['year' => $filterYear]) }}" class="serv-btn-secondary text-sm">{{ __('Início Clio') }}</a>
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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ session('success') }}
                </div>
            @endif

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <form method="get" class="flex flex-wrap items-center gap-2">
                    <label for="clio-year" class="text-sm text-slate-600 dark:text-slate-400">{{ __('Exercício') }}</label>
                    <select id="clio-year" name="year" class="serv-input text-sm" onchange="this.form.submit()">
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected((int) $filterYear === (int) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </form>
                <div class="flex flex-wrap gap-4 text-sm text-slate-600 dark:text-slate-400">
                    <span>{{ __(':n coleta(s)', ['n' => $comparativo['total'] ?? 0]) }}</span>
                    <span>{{ __(':n analisada(s)', ['n' => $comparativo['analyzed'] ?? 0]) }}</span>
                    @if ($comparativo['avg_triade'] !== null)
                        <span>{{ __('Tríade média :p%', ['p' => $comparativo['avg_triade']]) }}</span>
                    @endif
                </div>
            </div>

            <section class="serv-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('Município') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Perfil') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Estado') }}</th>
                                <th class="px-4 py-3 font-medium text-right">{{ __('Tríade %') }}</th>
                                <th class="px-4 py-3 font-medium text-right">{{ __('Erros') }}</th>
                                <th class="px-4 py-3 font-medium text-right">{{ __('Arquivos') }}</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($campaigns as $campaign)
                                @php
                                    $triade = $campaign->triadeCoveragePct();
                                @endphp
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-serv-navy dark:text-white">{{ $campaign->municipality_name }}</div>
                                        <div class="text-xs text-slate-500">{{ $campaign->uf }}@if($campaign->ibge_municipio) · {{ $campaign->ibge_municipio }}@endif</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $campaign->isAnalysisOnly() ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100' : 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-100' }}">
                                            {{ $campaign->profileLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $campaign->statusLabel() }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {{ $triade !== null ? number_format((float) $triade, 1, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums {{ (int) $campaign->findings_error_count > 0 ? 'text-rose-700 dark:text-rose-300 font-medium' : '' }}">
                                        {{ $campaign->findings_error_count }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $campaign->artifacts_count }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ $campaign->primaryReportUrl() }}" class="serv-link text-sm font-medium">
                                            {{ $campaign->hasReportReady() ? __('Relatório') : __('Abrir') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                        {{ __('Nenhuma coleta neste exercício.') }}
                                        @can('create', App\Models\Clio\ClioCampaign::class)
                                            {{ __('Cadastre um município (só coleta ou consultoria) ou use um existente e crie a primeira coleta.') }}
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($campaigns->hasPages())
                    <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                        {{ $campaigns->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
