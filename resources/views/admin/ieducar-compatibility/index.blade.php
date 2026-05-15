<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Compatibilidade da base i-Educar') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Probe de schema e estado das rotinas de discrepância por município (available / indisponível / com pendência).') }}
            </p>
        </div>
    </x-slot>

    @php
        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
        $schema = is_array($report['recurso_prova_schema'] ?? null) ? $report['recurso_prova_schema'] : null;
        $routines = is_array($report['routines'] ?? null) ? $report['routines'] : [];
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="get" action="{{ route('admin.ieducar-compatibility.index') }}" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 sm:p-6 shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
                    <div>
                        <label for="city_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Cidade') }}</label>
                        <select id="city_id" name="city_id" class="{{ $selectClass }}">
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}" @selected($city && (int) $city->id === (int) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2 flex gap-2">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            {{ __('Executar probe') }}
                        </button>
                        <a href="{{ route('dashboard.analytics', ['city_id' => $city?->id, 'tab' => 'discrepancies']) }}" class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                            {{ __('Abrir Discrepâncias') }}
                        </a>
                    </div>
                </div>
            </form>

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
                                    @php
                                        use App\Support\Ieducar\DiscrepanciesRoutineStatus;

                                        $st = (string) ($row['status'] ?? $row['availability'] ?? DiscrepanciesRoutineStatus::UNAVAILABLE);
                                        $statusClass = match ($st) {
                                            'danger' => 'text-red-700 dark:text-red-300',
                                            'warning' => 'text-amber-700 dark:text-amber-300',
                                            DiscrepanciesRoutineStatus::OK => 'text-emerald-700 dark:text-emerald-300',
                                            DiscrepanciesRoutineStatus::NO_DATA => 'text-sky-700 dark:text-sky-300',
                                            default => 'text-gray-500 dark:text-gray-400',
                                        };
                                        $statusLabel = (string) ($row['status_label'] ?? match ($st) {
                                            'danger', 'warning' => __('Com pendência'),
                                            DiscrepanciesRoutineStatus::OK => __('Sem pendência'),
                                            DiscrepanciesRoutineStatus::NO_DATA => __('Sem dados para analisar'),
                                            default => __('Indisponível'),
                                        });
                                    @endphp
                                    <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                        <td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $row['id'] ?? '' }}</td>
                                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $row['title'] ?? '' }}</td>
                                        <td class="px-4 py-2 font-medium {{ $statusClass }}">{{ $statusLabel }}</td>
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
