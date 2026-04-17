<x-pulse::card :cols="$cols ?? 6" :rows="$rows ?? 1" :class="$class">
    <x-pulse::card-header
        name="{{ __('Aplicação & runtime') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Contexto da instância Laravel. Período global do Pulse:') }} {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.command-line />
        </x-slot:icon>
    </x-pulse::card-header>
    <x-pulse::scroll :expand="$expand" wire:poll.30s="">
        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Laravel') }}</dt>
                <dd class="mt-0.5 font-mono text-gray-900 dark:text-gray-100">{{ $data['laravel'] }}</dd>
            </div>
            <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('PHP') }}</dt>
                <dd class="mt-0.5 font-mono text-gray-900 dark:text-gray-100">{{ $data['php'] }}</dd>
            </div>
            <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Ambiente') }}</dt>
                <dd class="mt-0.5">
                    <span class="font-mono uppercase text-gray-900 dark:text-gray-100">{{ $data['env'] }}</span>
                    @if ($data['debug'])
                        <span class="ms-2 rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">{{ __('debug on') }}</span>
                    @endif
                </dd>
            </div>
            <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Timezone / locale') }}</dt>
                <dd class="mt-0.5 font-mono text-xs text-gray-900 dark:text-gray-100">{{ $data['timezone'] }} · {{ $data['locale'] }}</dd>
            </div>
            <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Cache / sessão / fila (config)') }}</dt>
                <dd class="mt-0.5 font-mono text-xs leading-relaxed text-gray-900 dark:text-gray-100">
                    {{ $data['cache'] }} · {{ $data['session'] }} · {{ $data['queue'] }}
                </dd>
            </div>
            <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30 sm:col-span-2">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('APP_URL') }}</dt>
                <dd class="mt-0.5 break-all font-mono text-xs text-gray-800 dark:text-gray-200">{{ $data['url'] }}</dd>
            </div>
        </dl>
    </x-pulse::scroll>
</x-pulse::card>
