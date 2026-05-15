<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Compatibilidade da base i-Educar') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Probe de schema, referências FUNDEB (VAAF/VAAT por ano) e rotinas de discrepância por município.') }}
            </p>
        </div>
    </x-slot>

    @php
        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
        $schema = is_array($report['recurso_prova_schema'] ?? null) ? $report['recurso_prova_schema'] : null;
        $routines = is_array($report['routines'] ?? null) ? $report['routines'] : [];
        $fmtBrl = [\App\Support\Ieducar\DiscrepanciesFundingImpact::class, 'formatBrl'];
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('fundeb_import_success'))
                <div class="rounded-md bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-100">
                    {{ session('fundeb_import_success') }}
                </div>
            @endif
            @if (session('fundeb_import_error'))
                <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    {{ session('fundeb_import_error') }}
                </div>
            @endif
            @php $bulkResult = session('fundeb_bulk_result'); @endphp
            @if (is_array($bulkResult))
                <div class="rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 text-sm space-y-2">
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $bulkResult['message'] ?? '' }}</p>
                    @if (! empty($bulkResult['failed']))
                        <details class="text-red-800 dark:text-red-200">
                            <summary class="cursor-pointer font-medium">{{ __('Falhas (:n)', ['n' => count($bulkResult['failed'])]) }}</summary>
                            <ul class="mt-2 list-disc ps-5 text-xs space-y-1 max-h-48 overflow-y-auto">
                                @foreach ($bulkResult['failed'] as $f)
                                    <li><span class="font-medium">{{ $f['city'] ?? '' }}</span> (IBGE {{ $f['ibge'] ?? '—' }}): {{ \Illuminate\Support\Str::limit($f['message'] ?? '', 140) }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    @if (! empty($bulkResult['ok']))
                        <p class="text-xs text-emerald-800 dark:text-emerald-200">{{ __('Gravados: :n município(s).', ['n' => count($bulkResult['ok'])]) }}</p>
                    @endif
                    @if (! empty($bulkResult['skipped']))
                        <p class="text-xs text-amber-800 dark:text-amber-200">{{ __('Sem IBGE: :n cidade(s).', ['n' => count($bulkResult['skipped'])]) }}</p>
                    @endif
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
                        <label for="fundeb_ano_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Ano FUNDEB') }}</label>
                        <input type="number" id="fundeb_ano_filter" name="fundeb_ano" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $fundebImportYear ?? $fundebSuggestedYear ?? (int) date('Y') - 1 }}" class="{{ $selectClass }} w-full">
                    </div>
                    <div class="sm:col-span-2 flex flex-wrap gap-2">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            {{ __('Executar probe') }}
                        </button>
                        @if ($city)
                            <a href="{{ route('admin.ieducar-compatibility.export', ['city_id' => $city->id]) }}" class="inline-flex items-center rounded-lg border border-indigo-300 dark:border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-800 dark:text-indigo-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/40">
                                {{ __('Exportar schema_probe.json') }}
                            </a>
                        @endif
                        <a href="{{ route('dashboard.analytics', ['city_id' => $city?->id, 'tab' => 'discrepancies']) }}" class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
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
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('Rotinas de discrepância') }} — {{ $report['city_name'] ?? '' }}
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800/80 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2 font-medium">{{ __('ID') }}</th>
                                    <th class="px-4 py-2 font-medium">{{ __('Título') }}</th>
                                    <th class="px-4 py-2 font-medium">{{ __('Estado') }}</th>
                                    <th class="px-4 py-2 font-medium tabular-nums text-right">{{ __('Escolas c/ pendência') }}</th>
                                    <th class="px-4 py-2 font-medium">{{ __('Hint') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($routines as $row)
                                    <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                        <td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $row['id'] ?? '' }}</td>
                                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $row['title'] ?? '' }}</td>
                                        <td class="px-4 py-2 font-medium {{ $row['ui_status_class'] ?? 'text-gray-500' }}">{{ $row['status_label'] ?? __('Indisponível') }}</td>
                                        <td class="px-4 py-2 tabular-nums text-right text-gray-900 dark:text-gray-100">{{ (int) ($row['row_count'] ?? 0) }}</td>
                                        <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-400 max-w-md">{{ $row['hint'] ?? '—' }}</td>
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
