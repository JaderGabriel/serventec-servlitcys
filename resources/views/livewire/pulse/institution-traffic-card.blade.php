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
                <p class="mt-1 text-xs italic text-gray-500 dark:text-gray-400">{{ __('Inclui painel, análise e rotas sem cidade selecionada — visão agregada de todas as instituições.') }}</p>
            </div>

            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ __('Pedidos com contexto de cidade (por instituição)') }}</h3>
                @if ($cityRows->isEmpty())
                    <x-pulse::no-results />
                    <p class="mt-2 text-xs italic text-gray-500 dark:text-gray-400">{{ __('Ainda não há dados: use o painel com uma cidade selecionada (parâmetro city_id) ou rotas de gestão de cidades.') }}</p>
                @else
                    @php
                        $maxCity = max(1, (int) $cityRows->max('count'));
                    @endphp
                    <div class="space-y-2">
                        @foreach ($cityRows->take(15) as $row)
                            @php
                                $pct = min(100, round(100 * ($row->count / $maxCity)));
                            @endphp
                            <div class="pulse-bar-row" wire:key="inst-bar-{{ $row->city_id }}">
                                <div class="pulse-bar-row__meta">
                                    <span class="pulse-bar-row__name">{{ $row->city_name }}</span>
                                    @if ($row->city_id > 0)
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400">id {{ $row->city_id }}</span>
                                    @endif
                                </div>
                                <div class="pulse-bar-row__track" aria-hidden="true">
                                    <div class="pulse-bar-row__fill" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="pulse-bar-row__value">{{ number_format($row->count) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
