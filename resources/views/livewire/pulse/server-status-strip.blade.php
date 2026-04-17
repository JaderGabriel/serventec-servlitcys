@php
    $mbLabel = function (?int $mb, int $precision = 1): string {
        if ($mb === null || $mb < 0) {
            return '—';
        }
        if ($mb >= 1024 * 1024) {
            return round($mb / 1024 / 1024, $precision).' TB';
        }
        if ($mb >= 1024) {
            return round($mb / 1024, $precision).' GB';
        }

        return round($mb, $precision).' MB';
    };
@endphp
<div
    wire:poll.5s
    class="rounded-2xl border border-indigo-200/70 bg-white/95 px-4 py-3 shadow-md ring-1 ring-indigo-950/[0.05] backdrop-blur-sm dark:border-indigo-800/50 dark:bg-gray-800/90 dark:ring-indigo-400/10 sm:px-5"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
            {{-- Estado --}}
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Servidor') }}</span>
                @if ($payload['ok'] && ($payload['online'] ?? false))
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_0_3px_rgba(16,185,129,0.25)] dark:shadow-[0_0_0_3px_rgba(16,185,129,0.2)]" aria-hidden="true"></span>
                        {{ __('Online') }}
                    </span>
                @elseif ($payload['ok'])
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800 dark:bg-red-900/35 dark:text-red-200">
                        <x-pulse::icons.signal-slash class="h-4 w-4 shrink-0 text-red-600 dark:text-red-300" />
                        {{ __('Offline') }}
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {{ __('Sem dados') }}
                    </span>
                @endif
                <span class="max-w-[14rem] truncate text-sm font-medium text-gray-700 dark:text-gray-200" title="{{ $payload['name'] ?? '' }}">
                    {{ $payload['name'] ?? '—' }}
                </span>
            </div>

            <div class="hidden h-8 w-px bg-gray-200 dark:bg-gray-600 sm:block" aria-hidden="true"></div>

            {{-- Métricas --}}
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('CPU') }}</span>
                    <span class="tabular-nums text-base font-semibold text-gray-900 dark:text-gray-100">
                        @if (($payload['cpu'] ?? null) !== null)
                            {{ $payload['cpu'] }}%
                        @else
                            —
                        @endif
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Memória') }}</span>
                    <span class="tabular-nums text-base font-semibold text-gray-900 dark:text-gray-100">
                        @if (($payload['memory_used_mb'] ?? null) !== null && ($payload['memory_total_mb'] ?? null) !== null)
                            {{ $mbLabel($payload['memory_used_mb']) }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400"> / {{ $mbLabel($payload['memory_total_mb']) }}</span>
                        @else
                            —
                        @endif
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Disco') }}</span>
                    <span class="tabular-nums text-base font-semibold text-gray-900 dark:text-gray-100">
                        @if (($payload['disk_used_pct'] ?? null) !== null)
                            {{ $payload['disk_used_pct'] }}%
                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ __('usado') }}</span>
                        @else
                            —
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 flex-col items-start gap-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:items-end">
            @if ($payload['updated_human'] ?? null)
                <span title="{{ __('Último snapshot de sistema (Pulse)') }}">{{ __('Atualizado') }} {{ $payload['updated_human'] }}</span>
            @endif
            @if (isset($payload['fresh_window_s']) && ($payload['ok'] ?? false))
                <span class="opacity-80">{{ __('Janela online (~:seconds s)', ['seconds' => $payload['fresh_window_s']]) }}</span>
            @endif
        </div>
    </div>
    @if (! empty($payload['message']))
        <p class="mt-2 border-t border-gray-100 pt-2 text-xs text-amber-700 dark:border-gray-600 dark:text-amber-300/90">
            {{ $payload['message'] }}
        </p>
    @endif
</div>
