@props([
    'title' => '',
    'hint' => null,
    'variant' => 'default',
    'submitLabel' => null,
    'hideSubmit' => false,
    'step' => null,
    'command' => null,
    'titleTooltip' => null,
    'showQueueHint' => true,
    'submitDisabled' => false,
])

@php
    $variants = [
        'primary' => 'border-2 border-emerald-400/80 dark:border-emerald-700/60 bg-emerald-50/40 dark:bg-emerald-950/20',
        'accent' => 'border-2 border-violet-300/80 dark:border-violet-700/60 bg-violet-50/30 dark:bg-violet-950/20',
        'warning' => 'border-2 border-amber-300/80 dark:border-amber-700/60 bg-amber-50/40 dark:bg-amber-950/20',
        'default' => 'border border-gray-200/90 dark:border-gray-700 bg-white dark:bg-gray-900/40',
    ];
    $box = $variants[$variant] ?? $variants['default'];
    $submit = $submitLabel ?? __('Enfileirar na fila');
    $tags = $tags ?? [];
    if (! is_array($tags)) {
        $tags = filled($tags) ? [(string) $tags] : [];
    }
@endphp

<form {{ $attributes->merge(['class' => 'rounded-xl p-5 space-y-4 '.$box]) }}>
    @if (filled($step) || $tags !== [])
        <div class="flex flex-wrap items-center gap-2">
            @if (filled($step))
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ $step }}</span>
            @endif
            @foreach ($tags as $tag)
                <span class="inline-flex items-center rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-700 ring-1 ring-gray-200/80 dark:bg-gray-800/60 dark:text-gray-300 dark:ring-gray-600">{{ $tag }}</span>
            @endforeach
        </div>
    @endif
    @if (filled($title))
        <div>
            <h3
                @class(['text-base font-semibold text-gray-900 dark:text-gray-100', 'border-b border-dashed border-gray-300 dark:border-gray-600 pb-1 inline-block' => filled($titleTooltip)])
                @if (filled($titleTooltip)) title="{{ $titleTooltip }}" @endif
            >{{ $title }}</h3>
            @if (filled($hint))
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ $hint }}</p>
            @endif
            @if (filled($command))
                <p class="mt-2 text-[11px] font-mono text-gray-600 dark:text-gray-400 leading-relaxed break-all">{{ $command }}</p>
            @endif
        </div>
    @endif
    {{ $slot }}
    @isset($actions)
        <div class="flex flex-wrap items-center gap-3 pt-1">{{ $actions }}</div>
        @if ($showQueueHint)
            <x-admin.queue-submit-hint />
        @endif
    @endisset
    @if (! $hideSubmit && ! isset($actions))
        <div class="flex flex-wrap items-center gap-3">
            <button
                type="submit"
                @disabled($submitDisabled)
                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-gray-900"
            >
                {{ $submit }}
            </button>
            @if ($showQueueHint)
                <x-admin.queue-submit-hint />
            @endif
        </div>
    @endif
</form>
