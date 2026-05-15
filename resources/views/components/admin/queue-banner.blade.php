@props([
    'compact' => false,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-indigo-200/90 bg-indigo-50/80 dark:border-indigo-800/60 dark:bg-indigo-950/30 '.($compact ? 'px-3 py-2.5' : 'px-4 py-3')]) }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="{{ $compact ? 'text-xs' : 'text-sm' }} font-semibold text-indigo-950 dark:text-indigo-100">
                {{ __('Processamento em fila') }}
            </p>
            <p class="{{ $compact ? 'text-[11px] mt-0.5' : 'text-sm mt-1' }} text-indigo-900/90 dark:text-indigo-200/90 leading-relaxed">
                {{ __('Os envios desta página não correm no browser: cada botão cria uma tarefa em segundo plano. Acompanhe o estado, a cidade e o log em') }}
                <a href="{{ route('admin.sync-queue.index') }}" class="font-medium underline underline-offset-2 hover:text-indigo-700 dark:hover:text-indigo-100">{{ __('Fila de sincronização') }}</a>.
            </p>
            @if (! $compact)
                <p class="mt-1.5 text-xs text-indigo-800/80 dark:text-indigo-300/80">
                    @if (config('ieducar.admin_sync.schedule.enabled', true))
                        {{ __('Cron:') }} <span class="font-mono">php artisan schedule:run</span>
                        {{ __('(cada minuto) processa a fila automaticamente.') }}
                    @else
                        <span class="font-mono">php artisan admin-sync:work</span>
                    @endif
                </p>
            @endif
        </div>
        <a href="{{ route('admin.sync-queue.index') }}" class="shrink-0 inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
            {{ __('Ver fila') }}
        </a>
    </div>
</div>
