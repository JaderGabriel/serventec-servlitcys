@props([
    'step' => null,
    'title',
    'subtitle' => null,
    'anchor' => null,
])

<section @if (filled($anchor)) id="{{ $anchor }}" @endif {{ $attributes->merge(['class' => 'scroll-mt-6 space-y-3']) }}>
    <header>
        <div class="flex flex-wrap items-baseline gap-2">
            @if (filled($step))
                <span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-md bg-slate-200/90 px-1.5 text-[11px] font-bold tabular-nums text-slate-800 dark:bg-slate-700 dark:text-slate-100">{{ $step }}</span>
            @endif
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        </div>
        @if (filled($subtitle))
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">{{ $subtitle }}</p>
        @endif
    </header>
    {{ $slot }}
</section>
