@props([
    'steps' => [],
    'tone' => 'slate',
])

@php
    $toneRing = match ($tone) {
        'blue' => 'border-blue-200/90 dark:border-blue-800 text-blue-800 dark:text-blue-200 hover:bg-blue-50/80 dark:hover:bg-blue-950/30',
        'rose' => 'border-rose-200/90 dark:border-rose-800 text-rose-800 dark:text-rose-200 hover:bg-rose-50/80 dark:hover:bg-rose-950/30',
        default => 'border-slate-200/90 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50',
    };
@endphp

@if (count($steps) > 0)
    <nav class="serv-panel px-3 py-2.5 flex flex-wrap gap-2 text-xs" aria-label="{{ __('Fluxo de consultoria') }}">
        @foreach ($steps as $step)
            <a
                href="#{{ $step['anchor'] ?? '' }}"
                class="inline-flex items-center gap-1.5 rounded-full border bg-white/70 dark:bg-slate-900/50 px-3 py-1 font-medium transition-colors {{ $toneRing }}"
            >
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-current/10 text-[10px] font-bold tabular-nums">{{ $step['num'] ?? '' }}</span>
                {{ $step['label'] ?? '' }}
            </a>
        @endforeach
    </nav>
@endif
