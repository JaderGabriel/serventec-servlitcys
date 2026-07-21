<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Clio') }} · {{ $campaign->year }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ $campaign->municipality_name }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ $campaign->profileLabel() }} · {{ $campaign->statusLabel() }}
                    · {{ __(':n arquivo(s)', ['n' => $campaign->artifacts_count]) }}
                    · {{ __(':n escola(s)', ['n' => $campaign->schools_count]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('clio.campaigns.analysis', $campaign) }}" class="serv-btn-primary text-sm">{{ __('Painel analítico') }}</a>
                @can('export', $campaign)
                    <a href="{{ route('clio.campaigns.export.csv', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('CSV') }}</a>
                    <a href="{{ route('clio.campaigns.export.pdf', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('PDF') }}</a>
                @endcan
                @if ($campaign->city?->hasDataSetup())
                    <a href="{{ route('clio.campaigns.cross-check', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Cruzamento i-Educar') }}</a>
                @elseif (Auth::user()->can('linkConsultancy', $campaign))
                    <a href="{{ route('clio.campaigns.link', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Vincular i-Educar') }}</a>
                @endif
                @can('upload', $campaign)
                    <a href="{{ route('clio.campaigns.upload', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Enviar dados') }}</a>
                @else
                    <a href="{{ route('clio.campaigns.upload', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Inventário') }}</a>
                @endcan
                <a href="{{ route('clio.campaigns.index') }}" class="serv-btn-secondary text-sm">{{ __('Todas as campanhas') }}</a>
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

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="serv-panel p-5">
                    <p class="serv-eyebrow">{{ __('Perfil') }}</p>
                    <p class="mt-1 font-display text-lg font-semibold text-serv-navy dark:text-white">{{ $campaign->profileLabel() }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        @if ($campaign->isAnalysisOnly())
                            {{ __('Sem conexão i-Educar — análise dos relatórios do portal.') }}
                        @else
                            {{ __('Com i-Educar — use o cruzamento para ver o gap de escolas (INF-GAP).') }}
                        @endif
                    </p>
                </div>
                <div class="serv-panel p-5">
                    <p class="serv-eyebrow">{{ __('Estado') }}</p>
                    <p class="mt-1 font-display text-lg font-semibold text-serv-navy dark:text-white">{{ $campaign->statusLabel() }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('UUID') }}: {{ $campaign->uuid }}</p>
                </div>
                <div class="serv-panel p-5">
                    <p class="serv-eyebrow">{{ __('Inventário') }}</p>
                    <p class="mt-1 font-display text-lg font-semibold text-serv-navy dark:text-white">
                        {{ __(':n arquivo(s) · :s escola(s)', ['n' => $campaign->artifacts_count, 's' => $campaign->schools_count]) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Tríade completa: :c (:p%)', ['c' => $coverage['schools_triade_complete'] ?? 0, 'p' => $coverage['triade_coverage_pct'] ?? 0]) }}
                        @if (! empty($coverage['reference_date']))
                            · {{ __('Ref. :d', ['d' => $coverage['reference_date']]) }}
                        @endif
                    </p>
                    @can('upload', $campaign)
                        <a href="{{ route('clio.campaigns.upload', $campaign) }}" class="serv-link mt-2 inline-block text-sm font-medium">{{ __('Ir ao upload') }} →</a>
                    @else
                        <a href="{{ route('clio.campaigns.upload', $campaign) }}" class="serv-link mt-2 inline-block text-sm font-medium">{{ __('Ver inventário') }} →</a>
                    @endcan
                </div>
            </div>

            <section class="serv-panel overflow-hidden" id="artefactos">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 class="font-medium text-serv-navy dark:text-white">{{ __('Artefactos') }}</h3>
                    @can('upload', $campaign)
                        <a href="{{ route('clio.campaigns.upload', $campaign) }}#inventario" class="serv-link text-sm">{{ __('Gerir upload') }}</a>
                    @endcan
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('Nome') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Tipo') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Parse') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Linhas') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Tamanho') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($campaign->artifacts as $artifact)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $artifact->original_name }}</td>
                                    <td class="px-4 py-2">{{ $artifact->kindLabel() }}</td>
                                    <td class="px-4 py-2">{{ $artifact->parse_status }}</td>
                                    <td class="px-4 py-2 tabular-nums">{{ $artifact->row_count ?? '—' }}</td>
                                    <td class="px-4 py-2 tabular-nums">{{ number_format($artifact->size_bytes / 1024, 1) }} KB</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('Ainda sem arquivos. Use Enviar dados.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
