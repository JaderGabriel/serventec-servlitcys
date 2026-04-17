<x-pulse::card :cols="$cols ?? 6" :rows="$rows ?? 1" :class="$class">
    <x-pulse::card-header
        name="{{ __('Filas & falhas') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Jobs pendentes na conexão predefinida e jobs falhados persistidos. Período:') }} {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.queue-list />
        </x-slot:icon>
    </x-pulse::card-header>
    <x-pulse::scroll :expand="$expand" wire:poll.20s="">
        <div class="space-y-3 text-sm">
            @if ($data['error'])
                <div class="rounded-xl border border-amber-200/80 bg-amber-50/70 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                    {{ __('Pendentes:') }} {{ $data['error'] }}
                </div>
            @endif
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Conexão de fila') }}</dt>
                    <dd class="mt-0.5 font-mono text-gray-900 dark:text-gray-100">{{ $data['connection'] }}</dd>
                </div>
                <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Jobs pendentes') }}</dt>
                    <dd class="mt-0.5 font-mono text-gray-900 dark:text-gray-100">
                        @if ($data['pending'] !== null)
                            {{ number_format($data['pending']) }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30 sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('failed_jobs (tabela)') }}</dt>
                    <dd class="mt-0.5 font-mono text-gray-900 dark:text-gray-100">
                        @if ($data['failed'] !== null)
                            {{ number_format($data['failed']) }}
                        @else
                            {{ __('Tabela inexistente ou indisponível.') }}
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
