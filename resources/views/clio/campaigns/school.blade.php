<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Clio') }} · {{ __('Escola') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ $school->name }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 font-mono">
                    INEP {{ $school->inep_code }} · {{ $campaign->municipality_name }}
                </p>
            </div>
            <a href="{{ route('clio.campaigns.analysis', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Voltar ao painel') }}</a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="serv-panel p-4">
                    <p class="serv-eyebrow">{{ __('Funcionamento') }}</p>
                    <p class="mt-1 text-sm font-medium">{{ $school->functioning_status ?: '—' }}</p>
                </div>
                <div class="serv-panel p-4">
                    <p class="serv-eyebrow">{{ __('Forma de coleta') }}</p>
                    <p class="mt-1 text-sm font-medium">{{ $school->collection_form ?: '—' }}</p>
                </div>
                <div class="serv-panel p-4">
                    <p class="serv-eyebrow">{{ __('Tríade') }}</p>
                    <p class="mt-1 text-sm font-medium">
                        @if ($coverageRow['triade'] ?? false)
                            {{ __('Completa') }}
                        @else
                            A{{ ($coverageRow['aluno'] ?? false) ? '✓' : '·' }}
                            T{{ ($coverageRow['turma'] ?? false) ? '✓' : '·' }}
                            P{{ ($coverageRow['profissional'] ?? false) ? '✓' : '·' }}
                        @endif
                    </p>
                </div>
            </div>

            <section class="serv-panel overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 class="font-medium text-serv-navy dark:text-white">{{ __('Arquivos') }}</h3>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($school->artifacts as $artifact)
                        <li class="flex flex-wrap justify-between gap-2 px-4 py-3 text-sm">
                            <div>
                                <p class="font-mono text-xs">{{ $artifact->original_name }}</p>
                                <p class="text-xs text-slate-500">{{ $artifact->kindLabel() }} · {{ $artifact->parse_status }}</p>
                            </div>
                            <span class="tabular-nums text-xs text-slate-500">{{ $artifact->row_count ?? '—' }} {{ __('linhas') }}</span>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-slate-500 text-sm">{{ __('Sem arquivos ligados a esta escola.') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="serv-panel overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 class="font-medium text-serv-navy dark:text-white">{{ __('Achados desta escola') }}</h3>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($findings as $finding)
                        <li class="px-4 py-3 text-sm">
                            <span class="font-mono text-xs text-slate-500">{{ $finding->code }}</span>
                            <p class="mt-0.5">{{ $finding->message }}</p>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-slate-500 text-sm">{{ __('Nenhum achado.') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
