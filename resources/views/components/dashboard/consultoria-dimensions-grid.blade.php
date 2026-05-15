@props([
    'dimensions' => [],
    'fmtBrl' => null,
    'columns' => '3',
])

@php
    $gridCols = match ((string) $columns) {
        '2' => 'lg:grid-cols-2',
        default => 'lg:grid-cols-3',
    };
    $formatBrl = $fmtBrl ?? static fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
@endphp

@if (count($dimensions) > 0)
    <div {{ $attributes->merge(['class' => 'grid grid-cols-1 sm:grid-cols-2 '.$gridCols.' gap-2']) }}>
        @foreach ($dimensions as $dim)
            @php
                $st = (string) ($dim['status'] ?? 'unavailable');
                $chip = match ($st) {
                    'danger' => 'border-red-400 bg-red-50 text-red-950 dark:bg-red-950/40 dark:text-red-100',
                    'warning' => 'border-amber-400 bg-amber-50 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100',
                    'ok' => 'border-emerald-400 bg-emerald-50 text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-100',
                    default => 'border-slate-300 bg-slate-100 text-slate-600 dark:bg-slate-800/50 dark:text-slate-300',
                };
                $icon = match ($st) {
                    'danger' => '✕',
                    'warning' => '!',
                    'ok' => '✓',
                    default => '—',
                };
            @endphp
            <div class="rounded-md border px-2.5 py-2 text-xs flex gap-2 {{ $chip }}">
                <span class="font-bold shrink-0" aria-hidden="true">{{ $icon }}</span>
                <div class="min-w-0">
                    <p class="font-medium leading-snug">{{ $dim['title'] ?? '' }}</p>
                    @if ($st === 'unavailable')
                        <p class="mt-0.5 opacity-90">{{ $dim['unavailable_reason'] ?? __('Rotina indisponível') }}</p>
                    @elseif ($dim['has_issue'] ?? $dim['detected'] ?? false)
                        <p class="mt-0.5 tabular-nums font-semibold">
                            {{ number_format((int) ($dim['total'] ?? 0)) }} {{ __('ocorr.') }}
                            @if (($dim['ganho_potencial_anual'] ?? 0) > 0)
                                · {{ $formatBrl((float) $dim['ganho_potencial_anual']) }}
                            @endif
                        </p>
                    @else
                        <p class="mt-0.5">{{ __('Sem pendência no filtro') }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
