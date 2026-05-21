<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Compatibilidade da base i-Educar') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Probe na hora; importações FUNDEB e export JSON vão para a fila.') }}
            </p>
        </div>
    </x-slot>

    @php
        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
        $schema = is_array($report['recurso_prova_schema'] ?? null) ? $report['recurso_prova_schema'] : null;
        $routines = is_array($report['routines'] ?? null) ? $report['routines'] : [];
        $discSummary = is_array($report['discrepancy_summary'] ?? null) ? $report['discrepancy_summary'] : [];
        $fmtBrl = [\App\Support\Ieducar\DiscrepanciesFundingImpact::class, 'formatBrl'];
        $anoLetivo = $filters?->ano_letivo ?? 'all';
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @include('admin.partials.sync-queued-alert')

            <x-admin.queue-banner compact />

            @if (session('fundeb_import_error'))
                <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    {{ session('fundeb_import_error') }}
                </div>
            @endif

            <form method="get" action="{{ route('admin.ieducar-compatibility.index') }}" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 sm:p-6 shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <div>
                        <label for="city_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Cidade') }}</label>
                        <select id="city_id" name="city_id" class="{{ $selectClass }}">
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}" @selected($city && (int) $city->id === (int) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="ano_letivo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Ano letivo (probe)') }}</label>
                        <select id="ano_letivo" name="ano_letivo" class="{{ $selectClass }}">
                            <option value="all" @selected($anoLetivo === 'all')>{{ __('Todos (consolidado)') }}</option>
                            @for ($y = (int) date('Y') + 1; $y >= 2018; $y--)
                                <option value="{{ $y }}" @selected((string) $anoLetivo === (string) $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label for="fundeb_ano_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Ano FUNDEB') }}</label>
                        <input type="number" id="fundeb_ano_filter" name="fundeb_ano" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $fundebImportYear ?? $fundebSuggestedYear ?? (int) date('Y') - 1 }}" class="{{ $selectClass }} w-full">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-1 flex flex-wrap gap-2">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            {{ __('Executar probe') }}
                        </button>
                        @if ($city)
                            <a href="{{ route('admin.ieducar-compatibility.export', ['city_id' => $city->id]) }}" class="inline-flex items-center rounded-lg border border-indigo-300 dark:border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-800 dark:text-indigo-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/40" title="{{ __('Cria tarefa na fila; descarregue o JSON quando concluir.') }}">
                                {{ __('Enfileirar export JSON') }}
                            </a>
                        @endif
                        <a href="{{ route('dashboard.analytics', array_filter(['city_id' => $city?->id, 'tab' => 'discrepancies', 'ano_letivo' => $anoLetivo !== 'all' ? $anoLetivo : null])) }}" class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                            {{ __('Abrir Discrepâncias') }}
                        </a>
                    </div>
                </div>
            </form>

            @include('admin.ieducar-compatibility.partials.fundeb-card', [
                'city' => $city,
                'fundebStored' => $fundebStored ?? [],
                'fundebResolved' => $fundebResolved ?? null,
                'fundebImportYear' => $fundebImportYear,
                'fundebApiDiagnostics' => $fundebApiDiagnostics ?? [],
                'fundebCoverage' => $fundebCoverage ?? [],
                'fundebSuggestedYear' => $fundebSuggestedYear ?? null,
                'selectClass' => $selectClass,
                'fmtBrl' => $fmtBrl,
            ])

            @if ($error)
                <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    {{ $error }}
                </div>
            @endif

            @if ($schema !== null)
                <div class="rounded-xl border border-violet-200 dark:border-violet-900/50 bg-violet-50/40 dark:bg-violet-950/20 p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Recursos de prova INEP (schema)') }}</h3>
                    <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Disponível') }}</dt>
                            <dd class="font-medium {{ ! empty($schema['available']) ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">
                                {{ ! empty($schema['available']) ? __('Sim') : __('Não') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Tabela pivô') }}</dt>
                            <dd class="font-mono text-xs text-gray-800 dark:text-gray-200">{{ $schema['pivot_table'] ?? '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Nota') }}</dt>
                            <dd class="text-gray-800 dark:text-gray-200">{{ $schema['discovery_note'] ?? '—' }}</dd>
                        </div>
                        @if (! empty($schema['discovered_tables']))
                            <div class="sm:col-span-2">
                                <dt class="text-gray-500 dark:text-gray-400 mb-1">{{ __('Tabelas descobertas (amostra)') }}</dt>
                                <dd class="font-mono text-xs text-gray-700 dark:text-gray-300 break-all">{{ implode(', ', array_slice($schema['discovered_tables'], 0, 12)) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            @if ($report !== null && $routines !== [])
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-sm space-y-0">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 space-y-1">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('Rotinas de discrepância') }} — {{ $report['city_name'] ?? '' }}
                        </h3>
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            {{ $report['filters_label'] ?? '' }}
                            @if (($report['total_matriculas'] ?? 0) > 0)
                                · {{ number_format((int) $report['total_matriculas'], 0, ',', '.') }} {{ __('matrículas activas no filtro') }}
                            @endif
                        </p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-500">
                            {{ __('Métricas alinhadas à aba Discrepâncias: ocorrências = soma por escola; escolas = linhas com pendência; impacto = VAAF × peso × ocorrências.') }}
                        </p>
                    </div>

                    @if ($discSummary !== [])
                        <div class="px-4 py-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 border-b border-gray-100 dark:border-gray-800 bg-slate-50/60 dark:bg-slate-900/40">
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Ocorrências') }}</p>
                                <p class="text-lg font-semibold tabular-nums text-rose-800 dark:text-rose-200">{{ number_format((int) ($discSummary['com_problema'] ?? 0), 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Escolas afetadas') }}</p>
                                <p class="text-lg font-semibold tabular-nums text-indigo-800 dark:text-indigo-200">{{ number_format((int) ($discSummary['escolas_afetadas'] ?? 0), 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Rotinas c/ pendência') }}</p>
                                <p class="text-lg font-semibold tabular-nums">{{ number_format((int) ($discSummary['rotinas_com_pendencia'] ?? 0), 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Perda est. / ano') }}</p>
                                <p class="text-sm font-semibold tabular-nums text-orange-800 dark:text-orange-200">{{ $fmtBrl((float) ($discSummary['perda_estimada_anual'] ?? 0)) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Ganho pot. / ano') }}</p>
                                <p class="text-sm font-semibold tabular-nums text-emerald-800 dark:text-emerald-200">{{ $fmtBrl((float) ($discSummary['ganho_potencial_anual'] ?? 0)) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Rotinas analisadas') }}</p>
                                <p class="text-lg font-semibold tabular-nums">{{ number_format((int) ($discSummary['rotinas_analisadas'] ?? 0), 0, ',', '.') }}/{{ count($routines) }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800/80 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2 font-medium">{{ __('Rotina') }}</th>
                                    <th class="px-4 py-2 font-medium">{{ __('Estado') }}</th>
                                    <th class="px-4 py-2 font-medium tabular-nums text-right">{{ __('Ocorrências') }}</th>
                                    <th class="px-4 py-2 font-medium tabular-nums text-right">{{ __('Escolas') }}</th>
                                    <th class="px-4 py-2 font-medium tabular-nums text-right">{{ __('% rede') }}</th>
                                    <th class="px-4 py-2 font-medium tabular-nums text-right">{{ __('Perda est.') }}</th>
                                    <th class="px-4 py-2 font-medium">{{ __('Correlação / nota') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($routines as $row)
                                    <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                        <td class="px-4 py-2">
                                            <span class="font-mono text-[10px] text-gray-500 block">{{ $row['id'] ?? '' }}</span>
                                            <span class="text-gray-900 dark:text-gray-100">{{ $row['title'] ?? '' }}</span>
                                        </td>
                                        <td class="px-4 py-2 font-medium {{ $row['ui_status_class'] ?? 'text-gray-500' }}">{{ $row['status_label'] ?? __('Indisponível') }}</td>
                                        <td class="px-4 py-2 tabular-nums text-right font-semibold text-gray-900 dark:text-gray-100">
                                            @if ($row['has_issue'] ?? false)
                                                {{ number_format((int) ($row['occurrences_total'] ?? 0), 0, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 tabular-nums text-right text-gray-900 dark:text-gray-100">
                                            @if ($row['has_issue'] ?? false)
                                                {{ number_format((int) ($row['schools_count'] ?? $row['row_count'] ?? 0), 0, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 tabular-nums text-right text-gray-700 dark:text-gray-300">
                                            @if (($row['pct_rede'] ?? null) !== null)
                                                {{ number_format((float) $row['pct_rede'], 1, ',', '.') }}%
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 tabular-nums text-right text-xs text-orange-800 dark:text-orange-200">
                                            @if ((float) ($row['perda_estimada_anual'] ?? 0) > 0)
                                                {{ $fmtBrl((float) $row['perda_estimada_anual']) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-400 max-w-sm">
                                            @if (filled($row['correlacao_resumo'] ?? null))
                                                <span class="block text-gray-800 dark:text-gray-200">{{ $row['correlacao_resumo'] }}</span>
                                            @endif
                                            <span class="block mt-0.5">{{ $row['hint'] ?? '—' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
