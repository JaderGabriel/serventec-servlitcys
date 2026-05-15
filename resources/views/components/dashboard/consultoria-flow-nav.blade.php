@props([
    'steps' => [],
    'tone' => 'slate',
])

@php
    $toneRing = match ($tone) {
        'teal' => 'border-teal-300 dark:border-teal-700 text-teal-800 dark:text-teal-200',
        'rose' => 'border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200',
        default => 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300',
    };
@endphp

@if (count($steps) > 0)
    <nav class="flex flex-wrap gap-2 text-xs" aria-label="{{ __('Fluxo de consultoria') }}">
        @foreach ($steps as $step)
            <a
                href="#{{ $step['anchor'] ?? '' }}"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 font-medium transition-colors hover:bg-white/80 dark:hover:bg-gray-900/50 {{ $toneRing }}"
            >
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-current/10 text-[10px] font-bold tabular-nums">{{ $step['num'] ?? '' }}</span>
                {{ $step['label'] ?? '' }}
            </a>
        @endforeach
    </nav>
@endif
