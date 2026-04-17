<?php

use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RecordPulseInstitutionContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Artisan;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'profile.complete' => EnsureProfileComplete::class,
        ]);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            RecordPulseInstitutionContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        if (! config('pulse.enabled', true) || ! config('pulse.schedule.enabled', true)) {
            return;
        }

        // Mutex: evitar execuções em paralelo se `pulse:work` demorar mais de um minuto.
        $mutexExpires = min(180, max(60, (int) config('pulse.schedule.interval_minutes', 5) * 30));

        /*
         * `everyMinute()` + cron a cada minuto (`schedule:run`): em cada invocação há tarefa
         * “pronta” — evita “No scheduled commands are ready to run” causado por expressões
         * cron esparsas (ex. de cinco em cinco minutos) quando o relógio não coincide.
         * Alinhado ao guia oficial do Laravel Pulse.
         */
        $schedule->call(function (): void {
            Artisan::call('pulse:check', ['--once' => true]);

            if (config('pulse.schedule.run_digest_tick', true)) {
                Artisan::call('pulse:work', ['--stop-when-empty' => true]);
            }
        })
            ->name('pulse-scheduled-tick')
            ->everyMinute()
            ->withoutOverlapping($mutexExpires);
    })
    ->create();
