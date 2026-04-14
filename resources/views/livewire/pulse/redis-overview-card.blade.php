<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 2" :class="$class">
    <x-pulse::card-header
        name="{{ __('Redis (servidor e cache)') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Ligações `default` e `cache` de config/database.php; DBSIZE no DB lógico; INFO memory. Período:') }} {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.server />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.10s="">
        <div class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
            <div class="rounded-md bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700 px-3 py-2 text-xs">
                <p class="font-medium text-gray-800 dark:text-gray-100">{{ __('Cache da aplicação Laravel') }}</p>
                <p class="mt-1 text-gray-600 dark:text-gray-400">
                    {{ __('Driver actual:') }} <code class="font-mono">{{ $cacheStore }}</code>
                    @if ($cacheStore !== 'redis')
                        <span class="text-amber-600 dark:text-amber-400"> — {{ __('o cartão «Cache» do Pulse mostra hits/misses; este bloco mostra o servidor Redis usado para filas/sessões se existir.') }}</span>
                    @endif
                </p>
                <p class="mt-1 text-gray-600 dark:text-gray-400">
                    {{ __('Prefixo Laravel cache:') }} <code class="font-mono break-all">{{ $cachePrefix ?: '—' }}</code>
                </p>
                <p class="mt-1 text-gray-600 dark:text-gray-400">
                    {{ __('Prefixo Redis (database.redis.options.prefix):') }} <code class="font-mono break-all">{{ $redisPrefix ?: '—' }}</code>
                </p>
            </div>

            @if (! $payload['ok'] && $payload['error'])
                <div class="rounded-md border border-red-200 dark:border-red-900/50 bg-red-50/80 dark:bg-red-900/20 px-3 py-2 text-xs text-red-800 dark:text-red-200">
                    {{ $payload['error'] }}
                </div>
            @endif

            @foreach ($payload['connections'] as $c)
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50/80 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700 font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('Ligação') }} <code class="font-mono">{{ $c['name'] }}</code>
                        @isset($c['db_index'])
                            <span class="font-normal text-gray-500 dark:text-gray-400">— DB {{ $c['db_index'] }}</span>
                        @endisset
                    </div>
                    <div class="px-3 py-2 space-y-1 text-xs">
                        @isset($c['error'])
                            <p class="text-red-600 dark:text-red-400">{{ $c['error'] }}</p>
                        @else
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                                <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Versão Redis') }}</dt><dd class="font-mono">{{ $c['redis_version'] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Memória usada') }}</dt><dd class="font-mono">{{ $c['used_memory_human'] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Clientes ligados') }}</dt><dd class="font-mono">{{ $c['connected_clients'] ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Comandos processados (total)') }}</dt><dd class="font-mono">{{ isset($c['total_commands_processed']) ? number_format($c['total_commands_processed']) : '—' }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">{{ __('DBSIZE (chaves neste DB)') }}</dt><dd class="font-mono">{{ isset($c['dbsize']) ? number_format($c['dbsize']) : '—' }}</dd></div>
                                <div class="sm:col-span-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('Keyspace (INFO)') }}</dt><dd class="font-mono break-all">{{ $c['keyspace'] ?? '—' }}</dd></div>
                            </dl>
                        @endisset
                    </div>
                </div>
            @endforeach

            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Chaves individuais não são listadas (pode ser milhões). O cartão «Cache» do Pulse agrupa interacções por etiqueta; os prefixos acima explicam o que entra no Redis com o prefixo global.') }}
            </p>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
