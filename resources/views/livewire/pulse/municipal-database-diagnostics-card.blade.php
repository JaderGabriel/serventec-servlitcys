<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 2" :class="$class.' pulse-card-pro'">
    <x-pulse::card-header
        name="{{ __('SQL por município (i-Educar)') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Blocos CityDataConnection::run, queries lentas (≥ :q ms) e blocos lentos (≥ :r ms).', ['q' => number_format($slowQueryMs), 'r' => number_format($slowRunMs)]) }}"
    >
        <x-slot:icon>
            <x-pulse::icons.server />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.20s="">
        @if ($rows->isEmpty())
            <x-pulse::no-results />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="py-2 pe-3">{{ __('Município') }}</th>
                            <th class="py-2 pe-3">{{ __('Blocos run') }}</th>
                            <th class="py-2 pe-3">{{ __('Pior bloco') }}</th>
                            <th class="py-2 pe-3">{{ __('Blocos lentos') }}</th>
                            <th class="py-2 pe-3">{{ __('Queries lentas') }}</th>
                            <th class="py-2 pe-3">{{ __('Pior query') }}</th>
                            <th class="py-2">{{ __('SQL/pedido (máx.)') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($rows as $row)
                            @php
                                $attention = (bool) ($row['attention'] ?? false);
                            @endphp
                            <tr
                                wire:key="muni-db-{{ $row['city_id'] }}"
                                @class([
                                    'bg-amber-50/60 dark:bg-amber-950/15' => $attention,
                                ])
                            >
                                <td class="py-2 pe-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $row['name'] }}
                                    <span class="text-gray-400 font-normal">#{{ $row['city_id'] }}</span>
                                </td>
                                <td class="py-2 pe-3 tabular-nums">{{ number_format((int) $row['run_count']) }}</td>
                                <td class="py-2 pe-3 tabular-nums">{{ $row['run_max_ms'] !== null ? number_format((int) $row['run_max_ms']).' ms' : '—' }}</td>
                                <td class="py-2 pe-3 tabular-nums">{{ number_format((int) $row['run_slow_count']) }}</td>
                                <td class="py-2 pe-3 tabular-nums">{{ number_format((int) $row['slow_count']) }}</td>
                                <td class="py-2 pe-3 tabular-nums">{{ $row['slow_max_ms'] !== null ? number_format((int) $row['slow_max_ms']).' ms' : '—' }}</td>
                                <td class="py-2 tabular-nums">{{ $row['request_max_ms'] !== null ? number_format((int) $row['request_max_ms']).' ms' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
