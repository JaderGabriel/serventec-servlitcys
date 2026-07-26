<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 1" :class="$class">
    <x-pulse::card-header
        name="{{ __('Clio — coletas e análise') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Operações `clio:*` (ingest, análise, cruzamento, BI, exports) e HTTP lento em /clio. Período:') }} {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.cloud-arrow-up />
        </x-slot:icon>
    </x-pulse::card-header>
    <x-pulse::scroll :expand="$expand" wire:poll.15s="">
        <div class="mb-3 flex flex-wrap gap-3 text-xs text-slate-600 dark:text-slate-300">
            <span>{{ __('Total ops') }}: <strong class="font-mono tabular-nums">{{ number_format($totalOps) }}</strong></span>
            <span class="{{ $totalErrors > 0 ? 'text-rose-700 dark:text-rose-300' : '' }}">
                {{ __('Erros') }}: <strong class="font-mono tabular-nums">{{ number_format($totalErrors) }}</strong>
            </span>
            <span>{{ __('HTTP lentos') }}: <strong class="font-mono tabular-nums">{{ number_format($httpSlow['count']) }}</strong>
                @if ($httpSlow['max'] !== null)
                    · {{ number_format($httpSlow['max']) }} ms
                @endif
            </span>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                'ingest' => ['label' => __('Ingestão'), 'tone' => 'violet'],
                'analyze' => ['label' => __('Análise'), 'tone' => 'blue'],
                'cross_check' => ['label' => __('Cruzamento'), 'tone' => 'cyan'],
                'bi' => ['label' => __('BI refresh'), 'tone' => 'emerald'],
                'export' => ['label' => __('Exports'), 'tone' => 'amber'],
            ] as $key => $meta)
                @php $row = $buckets[$key] ?? ['count' => 0, 'max_ms' => 0, 'slow' => 0, 'errors' => 0]; @endphp
                <div class="rounded-xl border border-slate-200/80 bg-white/70 p-3 dark:border-slate-700/80 dark:bg-slate-900/40">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ $meta['label'] }}</p>
                    <dl class="mt-2 space-y-1 text-xs">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">{{ __('Ops') }}</dt>
                            <dd class="font-mono tabular-nums font-semibold">{{ number_format($row['count']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">{{ __('Lentas') }}</dt>
                            <dd class="font-mono tabular-nums">{{ number_format($row['slow']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">{{ __('Erros') }}</dt>
                            <dd class="font-mono tabular-nums {{ $row['errors'] > 0 ? 'text-rose-700 dark:text-rose-300' : '' }}">{{ number_format($row['errors']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">{{ __('Pico') }}</dt>
                            <dd class="font-mono tabular-nums">{{ $row['max_ms'] > 0 ? number_format($row['max_ms']).' ms' : '—' }}</dd>
                        </div>
                    </dl>
                </div>
            @endforeach
        </div>
    </x-pulse::scroll>
</x-pulse::card>
