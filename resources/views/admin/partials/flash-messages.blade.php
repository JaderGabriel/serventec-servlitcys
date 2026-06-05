@if (session('status'))
    <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100" role="status">
        {{ session('status') }}
    </div>
@endif
@if (session('error') || session('public_data_error'))
    <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100" role="alert">
        {{ session('error') ?? session('public_data_error') }}
    </div>
@endif
