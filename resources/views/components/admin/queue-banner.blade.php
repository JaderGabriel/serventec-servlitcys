@props([
    'compact' => false,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-sky-200/90 bg-sky-50/80 dark:border-sky-800/60 dark:bg-sky-950/30 '.($compact ? 'px-3 py-2.5' : 'px-4 py-3')]) }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="{{ $compact ? 'text-xs' : 'text-sm' }} font-semibold text-sky-950 dark:text-sky-100">
                {{ __('Processamento em fila') }}
            </p>
            <p class="{{ $compact ? 'text-[11px] mt-0.5' : 'text-sm mt-1' }} text-sky-900/90 dark:text-sky-200/90 leading-relaxed">
                {{ __('Os envios desta página não correm no browser: cada botão cria uma tarefa em segundo plano. Acompanhe o estado, a cidade e o log em') }}
                <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="font-medium underline underline-offset-2 hover:text-sky-700 dark:hover:text-sky-100">{{ __('Fila de sincronização') }}</a>.
            </p>
            @if (! $compact)
                <p class="mt-1.5 text-xs text-sky-800/80 dark:text-sky-300/80">
                    @if (config('ieducar.admin_sync.schedule.enabled', true))
                        @php
                            $syncTimes = \App\Support\Scheduling\ScheduleIntervals::normalizeDailyTimes(
                                config('ieducar.admin_sync.schedule.times', ['06:00', '18:00']),
                            );
                        @endphp
                        {{ __('Cron:') }} <span class="font-mono">*/{{ config('schedule.runner_interval_minutes', 3) }} * * * * schedule:run</span>
                        {{ __('— admin-sync :times (:tz); se houver tarefas na fila, o run também dispara o worker.', [
                            'times' => $syncTimes !== [] ? implode(', ', $syncTimes) : __('horário configurado'),
                            'tz' => config('app.timezone'),
                        ]) }}
                        {{ __('Pulse a cada :p min.', ['p' => (string) config('pulse.schedule.interval_minutes', 3)]) }}
                    @else
                        <span class="font-mono">php artisan admin-sync:work</span>
                    @endif
                </p>
            @endif
        </div>
        <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="shrink-0 inline-flex items-center rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-500">
            {{ __('Ver fila') }}
        </a>
    </div>
</div>
