@props([
    'step' => null,
    'title',
    'subtitle' => null,
    'anchor' => null,
])

<section @if (filled($anchor)) id="{{ $anchor }}" @endif {{ $attributes->merge(['class' => 'serv-panel scroll-mt-6 px-4 py-4 sm:px-5 sm:py-5 space-y-4']) }}>
    <header class="border-b border-slate-200/80 dark:border-slate-700/80 pb-3">
        <div class="flex flex-wrap items-baseline gap-2">
            @if (filled($step))
                <span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-md bg-blue-100/90 px-1.5 text-[11px] font-bold tabular-nums text-blue-900 dark:bg-blue-950/50 dark:text-blue-100">{{ $step }}</span>
            @endif
            <h3 class="text-sm font-semibold font-display text-serv-navy dark:text-slate-100">{{ $title }}</h3>
        </div>
        @if (filled($subtitle))
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 leading-relaxed">{{ $subtitle }}</p>
        @endif
    </header>
    <div class="space-y-3">
        {{ $slot }}
    </div>
</section>
