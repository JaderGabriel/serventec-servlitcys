<div {{ $attributes->merge(['class' => 'rounded-xl border border-sky-200/90 bg-sky-50/50 dark:border-sky-900/60 dark:bg-sky-950/20 p-5']) }}>
    <h3 class="text-sm font-semibold text-sky-900 dark:text-sky-100">{{ __('Atalhos') }}</h3>
    <div class="mt-3 flex flex-wrap gap-2 text-sm">
        {{ $slot }}
    </div>
</div>
