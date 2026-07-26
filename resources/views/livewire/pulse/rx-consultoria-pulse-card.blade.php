<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 1" :class="$class">
    <x-pulse::card-header
        name="{{ __('Consultoria e RX') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Abas Analytics (`analytics:tab:*`), painel RX (`rx:*`) e mapas do início (`map:*`). Período:') }} {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.cloud-arrow-up />
        </x-slot:icon>
    </x-pulse::card-header>
    <x-pulse::scroll :expand="$expand" wire:poll.15s="">
        <div class="grid gap-4 lg:grid-cols-3 sm:grid-cols-2">
            <div class="rounded-xl border border-blue-100/90 bg-blue-50/40 p-4 dark:border-blue-900/50 dark:bg-blue-950/25">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-800 dark:text-blue-200">{{ __('Analytics — abas') }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ops') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($analytics['count']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Lentas') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($analytics['slow']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Erros') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums {{ $analytics['errors'] > 0 ? 'text-rose-700 dark:text-rose-300' : '' }}">{{ number_format($analytics['errors']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pico (ms)') }}</dt>
                        <dd class="mt-0.5 font-mono text-sm">{{ $analytics['max_ms'] > 0 ? number_format($analytics['max_ms']) : '—' }}</dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-xl border border-violet-100/90 bg-violet-50/40 p-4 dark:border-violet-900/50 dark:bg-violet-950/25">
                <p class="text-xs font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-200">{{ __('RX — overview') }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ops') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($rx['count']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Lentas') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($rx['slow']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Erros') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums {{ $rx['errors'] > 0 ? 'text-rose-700 dark:text-rose-300' : '' }}">{{ number_format($rx['errors']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pico (ms)') }}</dt>
                        <dd class="mt-0.5 font-mono text-sm">{{ $rx['max_ms'] > 0 ? number_format($rx['max_ms']) : '—' }}</dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-xl border border-fuchsia-100/90 bg-fuchsia-50/40 p-4 dark:border-fuchsia-900/50 dark:bg-fuchsia-950/25 lg:col-span-1 sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-fuchsia-800 dark:text-fuchsia-200">{{ __('Mapas início (`map:*`)') }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ops') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($map['count']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Lentas') }}</dt>
                        <dd class="mt-0.5 font-mono text-lg font-semibold tabular-nums">{{ number_format($map['slow']) }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pico (ms)') }}</dt>
                        <dd class="mt-0.5 font-mono text-sm">{{ $map['max_ms'] > 0 ? number_format($map['max_ms']) : '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        @if (count($topTabs) > 0)
            <div class="mt-4 rounded-xl border border-slate-200/80 bg-white/60 p-4 dark:border-slate-700/80 dark:bg-slate-900/40">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('Abas mais usadas') }}</p>
                <ul class="mt-2 space-y-1.5 text-xs">
                    @foreach ($topTabs as $tab => $row)
                        <li class="flex items-center justify-between gap-2 font-mono tabular-nums">
                            <span class="truncate text-slate-700 dark:text-slate-300">{{ $tab }}</span>
                            <span class="shrink-0 text-blue-800 dark:text-blue-200">{{ number_format((int) ($row['count'] ?? 0)) }}× · {{ number_format((int) ($row['max_ms'] ?? 0)) }} ms</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
