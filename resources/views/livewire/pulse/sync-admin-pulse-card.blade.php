<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 1" :class="$class">
    <x-pulse::card-header
        name="{{ __('Sincronização — admin') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Volume (`sync_admin_endpoint`) e pedidos lentos (`slow_request`) nas rotas de geo e pedagógico. Período:') }} {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.cloud-arrow-up />
        </x-slot:icon>
    </x-pulse::card-header>
    <x-pulse::scroll :expand="$expand" wire:poll.15s="">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-teal-100/90 bg-teal-50/40 p-4 dark:border-teal-900/50 dark:bg-teal-950/25">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-800 dark:text-teal-200">{{ __('Geo (unidades)') }}</p>
                <p class="mt-1 text-[11px] italic text-teal-700/90 dark:text-teal-300/90">/admin/geo-sync</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pedidos (Pulse)') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($geoHits) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Lentos (contagem)') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($geoSlow['count']) }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pior tempo (ms)') }}</dt>
                        <dd class="mt-0.5 font-mono text-sm text-gray-800 dark:text-gray-200">
                            @if ($geoSlow['max'] !== null)
                                {{ number_format($geoSlow['max']) }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-xl border border-cyan-100/90 bg-cyan-50/40 p-4 dark:border-cyan-900/50 dark:bg-cyan-950/25">
                <p class="text-xs font-semibold uppercase tracking-wide text-cyan-800 dark:text-cyan-200">{{ __('Pedagógico (SAEB / INEP)') }}</p>
                <p class="mt-1 text-[11px] italic text-cyan-700/90 dark:text-cyan-300/90">/admin/pedagogical-sync</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pedidos (Pulse)') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($pedHits) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Lentos (contagem)') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($pedSlow['count']) }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pior tempo (ms)') }}</dt>
                        <dd class="mt-0.5 font-mono text-sm text-gray-800 dark:text-gray-200">
                            @if ($pedSlow['max'] !== null)
                                {{ number_format($pedSlow['max']) }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
