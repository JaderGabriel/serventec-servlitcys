@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 focus:border-teal-600 dark:focus:border-teal-500 focus:ring-teal-500/40 dark:focus:ring-teal-500/30 rounded-lg shadow-sm']) }}>
