<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 3" :class="$class.' pulse-card-pro'">
    <x-pulse::card-header
        name="{{ __('Municípios — infraestrutura e uso') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Inventário das bases i-Educar activas, motor SQL e volume de pedidos com contexto de cidade no período.') }}"
    >
        <x-slot:icon>
            <x-pulse::icons.server />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.20s="">
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="lg:col-span-3 space-y-4">
                <h3 class="pulse-card-pro__subtitle">{{ __('Tráfego por município (pedidos com city_id)') }}</h3>
                @if ($cities->isEmpty())
                    <x-pulse::no-results />
                @else
                    <div class="space-y-2.5">
                        @foreach ($cities->take(12) as $city)
                            @php
                                $pct = min(100, round(100 * ($city['requests'] / $maxRequests)));
                                $setup = (bool) ($city['setup'] ?? false);
                            @endphp
                            <div class="pulse-bar-row" wire:key="muni-bar-{{ $city['id'] }}">
                                <div class="pulse-bar-row__meta">
                                    <span class="pulse-bar-row__name" title="{{ $city['name'] }}">
                                        {{ $city['name'] }}
                                        @if (filled($city['uf']))
                                            <span class="text-gray-400">/{{ $city['uf'] }}</span>
                                        @endif
                                    </span>
                                    <span class="pulse-bar-row__tags">
                                        <span class="pulse-tag pulse-tag--{{ $setup ? 'ok' : 'warn' }}">
                                            {{ $setup ? __('OK') : __('Incompleto') }}
                                        </span>
                                        <span class="pulse-tag pulse-tag--neutral font-mono">{{ strtoupper($city['driver']) }}</span>
                                        @if (($city['db_slow_count'] ?? 0) > 0)
                                            <span class="pulse-tag pulse-tag--danger" title="{{ __('Queries lentas no período') }}">
                                                SQL {{ number_format((int) $city['db_slow_count']) }}
                                            </span>
                                        @endif
                                        @if (($city['db_run_max_ms'] ?? null) !== null)
                                            <span class="pulse-tag pulse-tag--{{ ($city['db_run_max_ms'] ?? 0) >= 1500 ? 'warn' : 'neutral' }} font-mono" title="{{ __('Pior bloco CityDataConnection::run') }}">
                                                {{ number_format((int) $city['db_run_max_ms']) }} ms
                                            </span>
                                        @endif
                                    </span>
                                </div>
                                <div class="pulse-bar-row__track" aria-hidden="true">
                                    <div class="pulse-bar-row__fill" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="pulse-bar-row__value tabular-nums">{{ number_format((int) $city['requests']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="lg:col-span-2 space-y-4">
                <h3 class="pulse-card-pro__subtitle">{{ __('Últimas tarefas de sincronização') }}</h3>
                @if (count($syncRecent) === 0)
                    <p class="text-xs text-gray-500 dark:text-gray-400 italic">{{ __('Nenhuma tarefa registada.') }}</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($syncRecent as $task)
                            @php
                                $st = (string) ($task['status'] ?? '');
                                $tag = match ($st) {
                                    'completed' => 'ok',
                                    'failed' => 'danger',
                                    'processing' => 'info',
                                    default => 'warn',
                                };
                            @endphp
                            <li class="rounded-lg border border-gray-200/80 dark:border-gray-700/80 px-3 py-2 text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $task['label'] }}</span>
                                    <span class="pulse-tag pulse-tag--{{ $tag }}">{{ $st }}</span>
                                </div>
                                <p class="mt-1 text-gray-500 dark:text-gray-400">
                                    {{ $task['city'] ?? __('Várias cidades') }} · {{ $task['created'] }}
                                </p>
                                <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.show', $task['id']) }}" class="mt-1 inline-block text-sky-600 dark:text-sky-400 hover:underline">
                                    {{ __('Detalhe') }} #{{ $task['id'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                    {{ __('Decisão: priorize municípios sem base configurada ou com sync em falha antes de expandir análises.') }}
                </p>
            </div>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
