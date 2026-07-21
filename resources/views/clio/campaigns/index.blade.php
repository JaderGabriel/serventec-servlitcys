<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Clio') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Campanhas Educacenso — 1ª etapa') }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-2xl leading-relaxed">
                    {{ __('Receba relatórios do portal, analise redes com ou sem i-Educar e acompanhe a coleta da Matrícula inicial.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @can('createCatalogCity', App\Models\Clio\ClioCampaign::class)
                    <a href="{{ route('clio.cities.create') }}" class="serv-btn-secondary text-sm">{{ __('Novo município (ficha leve)') }}</a>
                @endcan
                <a href="{{ route('clio.campaigns.create') }}" class="serv-btn-primary text-sm">{{ __('Nova campanha') }}</a>
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

            <section class="serv-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('Município') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Ano') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Perfil') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Estado') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Arquivos') }}</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($campaigns as $campaign)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-serv-navy dark:text-white">{{ $campaign->municipality_name }}</div>
                                        <div class="text-xs text-slate-500">{{ $campaign->uf }}@if($campaign->ibge_municipio) · {{ $campaign->ibge_municipio }}@endif</div>
                                    </td>
                                    <td class="px-4 py-3 tabular-nums">{{ $campaign->year }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $campaign->isAnalysisOnly() ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100' : 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-100' }}">
                                            {{ $campaign->profileLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $campaign->statusLabel() }}</td>
                                    <td class="px-4 py-3 tabular-nums">{{ $campaign->artifacts_count }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('clio.campaigns.show', $campaign) }}" class="serv-link text-sm font-medium">{{ __('Abrir') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                        {{ __('Nenhuma campanha ainda. Cadastre um município ficha leve ou use um existente e crie a primeira campanha.') }}
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
