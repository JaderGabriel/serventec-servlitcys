<div {{ $attributes->merge(['class' => 'rounded-xl border border-sky-200 bg-sky-50 dark:border-sky-800 dark:bg-slate-800 p-5']) }}>
    <h3 class="text-sm font-semibold text-sky-900 dark:text-sky-100">{{ __('Atalhos') }}</h3>
    <div class="mt-3 flex flex-wrap gap-2 text-sm">
        {{ $slot }}
    </div>
</div>
