<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 3" :class="$class.' pulse-card-pro'">
    <x-pulse::card-header
        name="{{ __('Diagnóstico SQL — sistema e municípios') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Consultas lentas e tempo SQL acumulado por âmbito. Limiar query: :q ms · bloco municipal: :r ms.', ['q' => number_format($payload['slow_ms']), 'r' => number_format($payload['slow_run_ms'])]) }}"
    >
        <x-slot:icon>
            <x-pulse::icons.circle-stack />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.20s="">
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-3">
                <h3 class="pulse-card-pro__subtitle">{{ __('Base do sistema (Laravel / MySQL)') }}</h3>
                @php
                    $sys = $payload['system'];
                    $builtin = $payload['system_builtin_slow'];
                @endphp
                <dl class="grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-lg border border-gray-200/80 dark:border-gray-700/80 p-3">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Queries lentas (app)') }}</dt>
                        <dd class="mt-1 text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((int) ($sys['slow_count'] ?? 0)) }}</dd>
                        <dd class="text-gray-500">{{ __('Pior: :ms ms', ['ms' => $sys['slow_max_ms'] !== null ? number_format((int) $sys['slow_max_ms']) : '—']) }}</dd>
                    </div>
                    <div class="rounded-lg border border-gray-200/80 dark:border-gray-700/80 p-3">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Slow queries (Pulse nativo)') }}</dt>
                        <dd class="mt-1 text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((int) ($builtin['count'] ?? 0)) }}</dd>
                        <dd class="text-gray-500">{{ __('Pior: :ms ms', ['ms' => ($builtin['max_ms'] ?? null) !== null ? number_format((int) $builtin['max_ms']) : '—']) }}</dd>
                    </div>
                    <div class="rounded-lg border border-gray-200/80 dark:border-gray-700/80 p-3 col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Tempo SQL total por pedido (máx. no período)') }}</dt>
                        <dd class="mt-1 font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                            {{ $sys['request_max_ms'] !== null ? number_format((int) $sys['request_max_ms']).' ms' : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="space-y-3">
                <h3 class="pulse-card-pro__subtitle">{{ __('Municípios com atenção (SQL)') }}</h3>
                @if (count($payload['municipal_hot']) === 0)
                    <p class="text-xs text-gray-500 dark:text-gray-400 italic">{{ __('Nenhum município acima dos limiares no período.') }}</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($payload['municipal_hot'] as $row)
                            <li class="rounded-lg border border-amber-200/80 dark:border-amber-900/50 bg-amber-50/50 dark:bg-amber-950/20 px-3 py-2 text-xs">
                                <span class="font-semibold text-gray-900 dark:text-gray-100">#{{ $row['city_id'] }}</span>
                                <span class="text-gray-600 dark:text-gray-300 ms-1">
                                    {{ __('runs: :r · slow q: :q · pior run: :pr ms · pior query: :pq ms', [
                                        'r' => number_format((int) ($row['run_count'] ?? 0)),
                                        'q' => number_format((int) ($row['slow_count'] ?? 0)),
                                        'pr' => ($row['run_max_ms'] ?? null) !== null ? number_format((int) $row['run_max_ms']) : '—',
                                        'pq' => ($row['slow_max_ms'] ?? null) !== null ? number_format((int) $row['slow_max_ms']) : '—',
                                    ]) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="mt-6 space-y-2">
            <h3 class="pulse-card-pro__subtitle">{{ __('Padrões SQL mais lentos (fingerprint)') }}</h3>
            @if (count($payload['slow_fingerprints']) === 0)
                <x-pulse::no-results />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="py-2 pe-2">{{ __('Âmbito') }}</th>
                                <th class="py-2 pe-2">{{ __('Ocorr.') }}</th>
                                <th class="py-2 pe-2">{{ __('Pior (ms)') }}</th>
                                <th class="py-2">{{ __('SQL (normalizado)') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($payload['slow_fingerprints'] as $fp)
                                <tr wire:key="fp-{{ md5($fp['scope_key'].$fp['label']) }}">
                                    <td class="py-2 pe-2 whitespace-nowrap">{{ $scopeLabel($fp['scope_key']) }}</td>
                                    <td class="py-2 pe-2 tabular-nums">{{ number_format((int) $fp['count']) }}</td>
                                    <td class="py-2 pe-2 tabular-nums font-medium">{{ number_format((int) $fp['max_ms']) }}</td>
                                    <td class="py-2 font-mono text-[10px] text-gray-600 dark:text-gray-300 break-all">{{ $fp['label'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-pulse::scroll>
</x-pulse::card>
