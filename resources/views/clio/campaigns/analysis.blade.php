<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Clio') }} · {{ __('Painel analítico') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ $campaign->municipality_name }} — {{ $campaign->year }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ $campaign->statusLabel() }}
                    @if (! empty($coverage['reference_date']))
                        · {{ __('Ref. :d', ['d' => $coverage['reference_date']]) }}
                    @endif
                    · {{ __('Tríade :p%', ['p' => $coverage['triade_coverage_pct'] ?? 0]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="post" action="{{ route('clio.campaigns.analyze', $campaign) }}">
                    @csrf
                    <button type="submit" class="serv-btn-primary text-sm">{{ __('Correr análise') }}</button>
                </form>
                <a href="{{ route('clio.campaigns.show', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Hub') }}</a>
                <a href="{{ route('clio.campaigns.upload', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Upload') }}</a>
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

            @if ($inferences->isEmpty())
                <div class="serv-panel p-6 text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Ainda sem inferências. Envie os CSV e clique em «Correr análise».') }}
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (['INF-COL', 'INF-ESC', 'INF-MAT', 'INF-TUR', 'INF-DOC', 'INF-NEE', 'INF-COE', 'INF-DUP', 'INF-DELTA'] as $code)
                        @php $inf = $inferences->get($code); @endphp
                        @if ($inf)
                            <div class="serv-panel p-4">
                                <p class="serv-eyebrow">{{ $inf->label() }}</p>
                                <p class="mt-1 text-sm font-medium text-serv-navy dark:text-white leading-snug">{{ $inf->summary }}</p>
                                <p class="mt-1 font-mono text-[10px] text-slate-400">{{ $code }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <section class="serv-panel overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 class="font-medium text-serv-navy dark:text-white">{{ __('Escolas') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('INEP') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Nome') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Coleta') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Tríade') }}</th>
                                <th class="px-4 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($coverage['schools'] ?? [] as $row)
                                @php
                                    $school = $campaign->schools->firstWhere('inep_code', $row['inep']);
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $row['inep'] }}</td>
                                    <td class="px-4 py-2">{{ $row['name'] }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $school?->collection_form ?? $school?->functioning_status ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($row['triade'])
                                            <span class="text-emerald-700 dark:text-emerald-300">✓</span>
                                        @else
                                            <span class="text-amber-700 dark:text-amber-300">·</span>
                                            <span class="text-xs text-slate-500">
                                                {{ $row['aluno'] ? 'A' : '·' }}{{ $row['turma'] ? 'T' : '·' }}{{ $row['profissional'] ? 'P' : '·' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('clio.campaigns.school', [$campaign, $row['inep']]) }}" class="serv-link text-sm">{{ __('Detalhe') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('Sem escolas. Faça upload e parse primeiro.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="serv-panel overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800 flex items-center justify-between">
                    <h3 class="font-medium text-serv-navy dark:text-white">{{ __('Achados') }}</h3>
                    <span class="text-xs text-slate-500">{{ $campaign->findings_count }} · {{ __('máx. 100') }}</span>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($findings as $finding)
                        <li class="px-4 py-3 text-sm">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <span class="font-mono text-xs text-slate-500">{{ $finding->code }}</span>
                                <span class="rounded px-1.5 py-0.5 text-[10px] uppercase tracking-wide
                                    @if($finding->severity === 'error') bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-100
                                    @elseif($finding->severity === 'warning') bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100
                                    @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200 @endif">
                                    {{ $finding->severity }}
                                </span>
                                @if ($finding->school)
                                    <span class="font-mono text-xs">{{ $finding->school->inep_code }}</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-slate-700 dark:text-slate-300">{{ $finding->message }}</p>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-slate-500 text-sm">{{ __('Nenhum achado.') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
