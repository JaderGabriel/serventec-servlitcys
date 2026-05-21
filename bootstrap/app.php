<?php

use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RecordPulseInstitutionContext;
use App\Support\Scheduling\ScheduleIntervals;
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
        $runnerMinutes = max(1, min(59, (int) config('schedule.runner_interval_minutes', 3)));

        if (config('pulse.enabled', true) && config('pulse.schedule.enabled', true)) {
            $pulseMinutes = max(1, min(59, (int) config('pulse.schedule.interval_minutes', $runnerMinutes)));

            /*
             * Separar `pulse:check` e `pulse:work`: se ficarem na mesma closure com um único
             * `withoutOverlapping`, um digest longo pode atrasar snapshots de sistema
             * e o cartão Servers mostra “offline” apesar do schedule:list estar correcto.
             */
            $pulseCheck = $schedule->call(function (): void {
                Artisan::call('pulse:check', ['--once' => true]);
            })
                ->name('pulse-scheduled-check')
                ->withoutOverlapping(max(90, $pulseMinutes * 60 - 30));

            ScheduleIntervals::everyMinutes($pulseCheck, $pulseMinutes);

            if (config('pulse.schedule.run_digest_tick', true)) {
                $pulseWork = $schedule->call(function (): void {
                    Artisan::call('pulse:work', ['--stop-when-empty' => true]);
                })
                    ->name('pulse-scheduled-work')
                    ->withoutOverlapping(max(300, $pulseMinutes * 60 * 2));

                ScheduleIntervals::everyMinutes($pulseWork, $pulseMinutes);
            }
        }

        if (config('ieducar.admin_sync.schedule.enabled', true)) {
            $syncIntervalMinutes = max(1, (int) config('ieducar.admin_sync.schedule.interval_minutes', 60));
            $maxSeconds = max(10, (int) config('ieducar.admin_sync.schedule.max_seconds', 3300));
            $overlapMinutes = max(1, (int) config('ieducar.admin_sync.schedule.overlap_minutes', 65));

            $adminSync = $schedule->command('admin-sync:work', [
                '--stop-when-empty' => true,
                '--max-time' => $maxSeconds,
            ])
                ->name('admin-sync-scheduled-work')
                ->withoutOverlapping($overlapMinutes)
                ->runInBackground();

            if ($syncIntervalMinutes >= 60) {
                ScheduleIntervals::everyHours($adminSync, (int) ceil($syncIntervalMinutes / 60));
            } else {
                ScheduleIntervals::everyMinutes($adminSync, min(59, $syncIntervalMinutes));
            }
        }
    })
    ->create();
