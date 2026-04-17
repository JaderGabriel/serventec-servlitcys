<x-pulse::card :cols="$cols ?? 6" :rows="$rows ?? 1" :class="$class">
    <x-pulse::card-header
        name="{{ __('Base de dados') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Ligação predefinida e latência PDO. Período:') }} {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.circle-stack />
        </x-slot:icon>
    </x-pulse::card-header>
    <x-pulse::scroll :expand="$expand" wire:poll.15s="">
        <div class="space-y-3 text-sm">
            @if ($data['error'])
                <div class="rounded-xl border border-red-200/80 bg-red-50/70 px-3 py-2 text-xs text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                    {{ $data['error'] }}
                </div>
            @endif
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Conexão') }}</dt>
                    <dd class="mt-0.5 font-mono text-gray-900 dark:text-gray-100">{{ $data['default'] }}</dd>
                </div>
                <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Driver') }}</dt>
                    <dd class="mt-0.5 font-mono text-gray-900 dark:text-gray-100">{{ $data['driver'] }}</dd>
                </div>
                <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30 sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Base / recurso') }}</dt>
                    <dd class="mt-0.5 break-all font-mono text-xs text-gray-900 dark:text-gray-100">{{ $data['database'] }}</dd>
                </div>
                @if ($data['ping_ms'] !== null)
                    <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Ping PDO') }}</dt>
                        <dd class="mt-0.5 font-mono text-gray-900 dark:text-gray-100">{{ number_format($data['ping_ms']) }} ms</dd>
                    </div>
                @endif
                @if ($data['version'] ?? null)
                    <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30 sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Versão do motor') }}</dt>
                        <dd class="mt-0.5 break-all font-mono text-xs text-gray-800 dark:text-gray-200">{{ Str::limit($data['version'], 220) }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
