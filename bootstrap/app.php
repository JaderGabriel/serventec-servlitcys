<?php

use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RecordPulseInstitutionContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

        $minutes = (int) config('pulse.schedule.interval_minutes', 5);
        // Mutex maior que o intervalo para locks órfãos; evita picos se `schedule:run` se sobrepor.
        $mutexExpires = min(120, max(10, $minutes * 2 + 2));

        // Um único agendamento: mesma cadência do cron (`*/N`), sequencial no mesmo processo,
        // sem `runInBackground`; se uma execução anterior ainda estiver ativa, esta é ignorada.
        $schedule->call(function (): void {
            Artisan::call('pulse:check', ['--once' => true]);

            if (config('pulse.schedule.run_digest_tick', true)) {
                Artisan::call('pulse:work', ['--stop-when-empty' => true]);
            }
        })
            ->name('pulse-scheduled-tick')
            ->cron(sprintf('*/%d * * * *', $minutes))
            ->withoutOverlapping($mutexExpires);
    })
    ->create();
