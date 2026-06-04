@props([
    'title' => __('Fluxo recomendado'),
    'summary' => null,
    'open' => false,
])

<details
    {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200/90 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/40']) }}
    @if ($open) open @endif
>
    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</summary>
    <div class="border-t border-slate-200/80 dark:border-slate-700/80 px-4 py-4 space-y-4">
        @if (filled($summary))
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $summary }}</p>
        @endif
        {{ $slot }}
    </div>
</details>
