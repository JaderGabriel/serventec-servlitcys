<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 1" :class="$class">
    <x-pulse::card-header
        name="{{ __('Horizonte — mapa e abastecimento') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Operações `horizonte:*` (mapa GIS, fases do feed bimestral) e pedidos HTTP lentos em rotas Horizonte. Período:') }} {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.cloud-arrow-up />
        </x-slot:icon>
    </x-pulse::card-header>
    <x-pulse::scroll :expand="$expand" wire:poll.15s="">
        <div class="grid gap-4 lg:grid-cols-3 sm:grid-cols-2">
            <div class="rounded-xl border border-teal-100/90 bg-teal-50/40 p-4 dark:border-teal-900/50 dark:bg-teal-950/25">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-800 dark:text-teal-200">{{ __('Mapa — overview') }}</p>
                <p class="mt-1 text-[11px] italic text-teal-700/90 dark:text-teal-300/90">/dashboard/horizonte</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ops') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($map['overview']['count']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Lentas') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($map['overview']['slow']) }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pico (ms)') }}</dt>
                        <dd class="mt-0.5 font-mono text-sm">{{ $map['overview']['max_ms'] > 0 ? number_format($map['overview']['max_ms']) : '—' }}</dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-xl border border-cyan-100/90 bg-cyan-50/40 p-4 dark:border-cyan-900/50 dark:bg-cyan-950/25">
                <p class="text-xs font-semibold uppercase tracking-wide text-cyan-800 dark:text-cyan-200">{{ __('Mapa — regional (UF)') }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ops') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($map['regional']['count']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Lentas') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($map['regional']['slow']) }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pico (ms)') }}</dt>
                        <dd class="mt-0.5 font-mono text-sm">{{ $map['regional']['max_ms'] > 0 ? number_format($map['regional']['max_ms']) : '—' }}</dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-xl border border-emerald-100/90 bg-emerald-50/40 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/25 lg:col-span-1 sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ __('HTTP Horizonte (lentos)') }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pedidos') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($httpSlow['count']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Erros feed') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($feedErrors) }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pior HTTP (ms)') }}</dt>
                        <dd class="mt-0.5 font-mono text-sm">{{ $httpSlow['max'] !== null ? number_format($httpSlow['max']) : '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        @if (count($feedPhases) > 0)
            <div class="mt-4 rounded-xl border border-slate-200/80 bg-white/60 p-4 dark:border-slate-700/80 dark:bg-slate-900/40">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('Fases do feed — tempo máximo') }}</p>
                <ul class="mt-2 space-y-1.5 text-xs">
                    @foreach ($feedPhases as $phase => $row)
                        <li class="flex items-center justify-between gap-2 font-mono tabular-nums">
                            <span class="truncate text-slate-700 dark:text-slate-300">{{ $phase }}</span>
                            <span class="shrink-0 text-amber-800 dark:text-amber-200">{{ number_format((int) ($row['max_ms'] ?? 0)) }} ms · {{ number_format((int) ($row['count'] ?? 0)) }}×</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
