<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-teal-700 dark:bg-teal-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-800 dark:hover:bg-teal-500 focus:bg-teal-800 dark:focus:bg-teal-500 active:bg-teal-900 dark:active:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500/50 focus:ring-offset-2 dark:focus:ring-offset-slate-900 disabled:opacity-50 disabled:pointer-events-none transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
