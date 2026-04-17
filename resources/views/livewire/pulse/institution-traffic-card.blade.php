<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 2" :class="$class">
    <x-pulse::card-header
        name="{{ __('Tráfego por instituição (cidade)') }}"
        x-bind:title="`{{ __('Global') }}: {{ number_format($timeGlobal) }}ms @ {{ $runAtGlobal }} · {{ __('Por cidade') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Pedidos com parâmetro city_id ou rotas de cidades; total global = todos os pedidos (exceto /pulse). Período:') }} {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.circle-stack />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.10s="">
        <div class="space-y-6">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ __('Total de pedidos (aplicação inteira)') }}</h3>
                <p class="text-3xl font-bold tabular-nums text-gray-800 dark:text-gray-100">{{ number_format((int) $globalTotal) }}</p>
                <p class="mt-1 text-xs italic text-gray-500 dark:text-gray-400">{{ __('Inclui painel, análise e rotas sem cidade seleccionada — visão agregada de todas as instituições.') }}</p>
            </div>

            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ __('Pedidos com contexto de cidade (por instituição)') }}</h3>
                @if ($cityRows->isEmpty())
                    <x-pulse::no-results />
                    <p class="mt-2 text-xs italic text-gray-500 dark:text-gray-400">{{ __('Ainda não há dados: use o painel com uma cidade seleccionada (parâmetro city_id) ou rotas de gestão de cidades.') }}</p>
                @else
                    <x-pulse::table>
                        <x-pulse::thead>
                            <tr>
                                <x-pulse::th>{{ __('Cidade / ID') }}</x-pulse::th>
                                <x-pulse::th class="text-right">{{ __('Pedidos') }}</x-pulse::th>
                            </tr>
                        </x-pulse::thead>
                        <tbody>
                            @foreach ($cityRows as $row)
                                <tr wire:key="city-{{ $row->city_id }}-{{ $row->city_name }}">
                                    <x-pulse::td>
                                        <span class="font-medium text-gray-800 dark:text-gray-100">{{ $row->city_name }}</span>
                                        @if ($row->city_id > 0)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">· id {{ $row->city_id }}</span>
                                        @endif
                                    </x-pulse::td>
                                    <x-pulse::td numeric class="font-bold tabular-nums text-gray-700 dark:text-gray-200">
                                        {{ number_format($row->count) }}
                                    </x-pulse::td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-pulse::table>
                @endif
            </div>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
