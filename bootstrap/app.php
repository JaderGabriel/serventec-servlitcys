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
            'manage.users' => \App\Http\Middleware\EnsureCanManageUsers::class,
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

        /*
         * Separar `pulse:check` e `pulse:work`: se ficarem na mesma closure com um único
         * `withoutOverlapping`, um digest longo pode atrasar ou impedir snapshots de sistema
         * e o cartão Servers mostra “offline” apesar do schedule:list estar correcto.
         */
        $schedule->call(function (): void {
            Artisan::call('pulse:check', ['--once' => true]);
        })
            ->name('pulse-scheduled-check')
            ->everyMinute()
            ->withoutOverlapping(120);

        if (config('pulse.schedule.run_digest_tick', true)) {
            $schedule->call(function (): void {
                Artisan::call('pulse:work', ['--stop-when-empty' => true]);
            })
                ->name('pulse-scheduled-work')
                ->everyMinute()
                ->withoutOverlapping(300);
        }
    })
    ->create();
