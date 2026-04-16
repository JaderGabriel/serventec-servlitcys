<?php

use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RecordPulseInstitutionContext;
use Illuminate\Console\Scheduling\Schedule;
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
        $mutexExpires = min(120, $minutes + 2);

        // Uma fotografia por intervalo; executa no mesmo processo do schedule:run (sem runInBackground).
        $schedule->command('pulse:check', ['--once' => true])
            ->cron(sprintf('*/%d * * * *', $minutes))
            ->withoutOverlapping($mutexExpires)
            ->name('pulse:check-once');

        // Uma passagem de digest por intervalo (alternativa ao daemon `pulse:work` em Supervisor).
        if (config('pulse.schedule.run_digest_tick', true)) {
            $schedule->command('pulse:work', ['--stop-when-empty' => true])
                ->cron(sprintf('*/%d * * * *', $minutes))
                ->withoutOverlapping($mutexExpires)
                ->name('pulse:work-tick');
        }
    })
    ->create();
