<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 3" :class="$class.' pulse-card-pro'">
    <x-pulse::card-header
        name="{{ __('Operações da aplicação') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Abas Analytics, RX, sync, PDF, mapa e exports — limiar lento: :ms ms.', ['ms' => number_format($payload['slow_ms'])]) }}"
    >
        <x-slot:icon>
            <x-pulse::icons.queue-list />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.20s="">
        @if (count($payload['by_prefix']) > 0)
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                @foreach ($payload['by_prefix'] as $group)
                    <div class="rounded-lg border border-gray-200/80 dark:border-gray-700/80 p-3 text-xs">
                        <p class="text-gray-500 dark:text-gray-400">{{ $prefixLabel($group['prefix']) }}</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                            {{ number_format((int) $group['count']) }}
                        </p>
                        <p class="text-gray-500">
                            {{ __('Pior: :ms ms · lentas: :s', [
                                'ms' => number_format((int) $group['max_ms']),
                                's' => number_format((int) $group['slow_count']),
                            ]) }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="space-y-2 mb-6">
            <h3 class="pulse-card-pro__subtitle">{{ __('Operações mais lentas (máx. no período)') }}</h3>
            @if (count($payload['operations']) === 0)
                <x-pulse::no-results />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="py-2 pe-2">{{ __('Operação') }}</th>
                                <th class="py-2 pe-2">{{ __('Ocorr.') }}</th>
                                <th class="py-2 pe-2">{{ __('Pior (ms)') }}</th>
                                <th class="py-2">{{ __('Lentas') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($payload['operations'] as $row)
                                <tr wire:key="op-{{ md5($row['key']) }}">
                                    <td class="py-2 pe-2 font-mono text-[10px] text-gray-600 dark:text-gray-300 break-all">{{ $row['key'] }}</td>
                                    <td class="py-2 pe-2 tabular-nums">{{ number_format((int) $row['count']) }}</td>
                                    <td class="py-2 pe-2 tabular-nums font-medium">{{ number_format((int) $row['max_ms']) }}</td>
                                    <td class="py-2 tabular-nums">{{ number_format((int) ($row['slow_count'] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if (count($payload['errors']) > 0)
            <div class="space-y-2">
                <h3 class="pulse-card-pro__subtitle text-rose-600 dark:text-rose-400">{{ __('Falhas registadas') }}</h3>
                <ul class="space-y-1 text-xs">
                    @foreach ($payload['errors'] as $err)
                        <li class="font-mono text-[10px] text-gray-600 dark:text-gray-300">
                            {{ $err['key'] }} — {{ number_format((int) $err['count']) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
