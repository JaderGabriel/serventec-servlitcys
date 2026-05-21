@php
    $serverName = (string) config('pulse.recorders.'.Laravel\Pulse\Recorders\Servers::class.'.server_name', gethostname());
    $intervalMin = max(1, (int) config('pulse.schedule.interval_minutes', 3));
@endphp

<div class="rounded-xl border border-dashed border-amber-300/80 bg-amber-50/50 px-4 py-8 text-center dark:border-amber-700/60 dark:bg-amber-950/20 sm:px-6">
    <x-pulse::icons.server class="mx-auto h-10 w-10 stroke-amber-600/80 dark:stroke-amber-400/80" />
    <h4 class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100">
        {{ __('Sem histórico de servidor no Pulse') }}
    </h4>
    <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
        {{ __('Ainda não há snapshots de CPU, memória e disco para :name. O agendador deve correr `pulse:check` a cada :min min e, se configurado, `pulse:work` para processar a fila.', [
            'name' => $serverName,
            'min' => $intervalMin,
        ]) }}
    </p>
    <ul class="mx-auto mt-4 max-w-md space-y-1.5 text-left text-xs text-slate-600 dark:text-slate-400">
        <li class="flex gap-2">
            <span class="font-mono text-amber-800 dark:text-amber-300">1.</span>
            <span>{{ __('Confirme `PULSE_ENABLED=true` e o cron `php artisan schedule:run` (ver `SCHEDULE_RUN_INTERVAL_MINUTES`).') }}</span>
        </li>
        <li class="flex gap-2">
            <span class="font-mono text-amber-800 dark:text-amber-300">2.</span>
            <span>{{ __('No servidor: `php artisan pulse:check --once` e depois `php artisan pulse:work --stop-when-empty`.') }}</span>
        </li>
        <li class="flex gap-2">
            <span class="font-mono text-amber-800 dark:text-amber-300">3.</span>
            <span>{{ __('Alinhe `PULSE_SERVER_NAME` ao hostname registado (actual: :name).', ['name' => $serverName]) }}</span>
        </li>
    </ul>
    <p class="mt-4 text-[11px] text-slate-500 dark:text-slate-500">
        {{ __('A faixa superior mostra o último snapshot quando existir; os gráficos aparecem após alguns ciclos de recolha.') }}
    </p>
</div>
