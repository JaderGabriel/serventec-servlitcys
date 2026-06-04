<div {{ $attributes->merge(['class' => 'rounded-xl border border-indigo-200/90 bg-indigo-50/50 dark:border-indigo-900/60 dark:bg-indigo-950/20 p-5']) }}>
    <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">{{ __('Atalhos') }}</h3>
    <div class="mt-3 flex flex-wrap gap-2 text-sm">
        {{ $slot }}
    </div>
</div>
