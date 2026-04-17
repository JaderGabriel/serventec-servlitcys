@php
    $fmt = function (?int $bytes): string {
        if ($bytes === null || $bytes < 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            $i++;
        }

        return ($i === 0 ? number_format($v, 0) : number_format($v, 2)).' '.$units[$i];
    };
@endphp
<x-pulse::card :cols="$cols ?? 6" :rows="$rows ?? 1" :class="$class">
    <x-pulse::card-header
        name="{{ __('Disco (volume da app)') }}"
        x-bind:title="`{{ __('Consulta') }}: {{ number_format($time) }}ms @ {{ $runAt }}`"
        details="{{ __('Espaço livre no filesystem de base_path(). Período:') }} {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.computer-desktop />
        </x-slot:icon>
    </x-pulse::card-header>
    <x-pulse::scroll :expand="$expand" wire:poll.60s="">
        <div class="space-y-3 text-sm">
            @if (! $data['ok'])
                <div class="rounded-xl border border-gray-200/80 bg-gray-50/70 px-3 py-2 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-300">
                    {{ __('Não foi possível ler o espaço em disco deste volume.') }}
                </div>
            @else
                <div class="rounded-xl border border-emerald-200/60 bg-emerald-50/40 px-3 py-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                    <p class="text-2xl font-semibold tabular-nums text-emerald-900 dark:text-emerald-100">{{ $data['pct_free'] }}%</p>
                    <p class="mt-1 text-xs text-emerald-800/90 dark:text-emerald-200/90">{{ __('livres no volume') }}</p>
                </div>
                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Livre') }}</dt>
                        <dd class="mt-0.5 font-mono text-gray-900 dark:text-gray-100">{{ $fmt($data['free_bytes']) }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-100/90 bg-gray-50/50 px-3 py-2 dark:border-gray-700/80 dark:bg-gray-900/30">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Total') }}</dt>
                        <dd class="mt-0.5 font-mono text-gray-900 dark:text-gray-100">{{ $fmt($data['total_bytes']) }}</dd>
                    </div>
                </dl>
            @endif
            <p class="break-all font-mono text-[11px] text-gray-500 dark:text-gray-400" title="{{ $data['path'] }}">{{ Str::limit($data['path'], 96) }}</p>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
