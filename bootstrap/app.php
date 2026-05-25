<?php

use App\Http\Middleware\EnsureAnalyticsDiagnostics;
use App\Http\Middleware\EnsureCanManageUsers;
use App\Http\Middleware\EnsureLegalConsentAccepted;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RecordPulseInstitutionContext;
use App\Http\Middleware\RecordPulseOperations;
use App\Support\Scheduling\AdminSyncScheduleGate;
use App\Support\Scheduling\AnalyticsPdfScheduleGate;
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
            'analytics.diagnostics' => EnsureAnalyticsDiagnostics::class,
            'manage.users' => EnsureCanManageUsers::class,
            'profile.complete' => EnsureProfileComplete::class,
            'legal.consent' => EnsureLegalConsentAccepted::class,
        ]);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            RecordPulseInstitutionContext::class,
            RecordPulseOperations::class,
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
            $scheduleLog = config('schedule.log_to_file', false)
                ? (string) config('schedule.log_path', storage_path('logs/scheduler.log'))
                : null;

            $pulseCheck = $schedule->command('pulse:check', ['--once' => true])
                ->name('pulse-scheduled-check')
                ->withoutOverlapping(max(90, $pulseMinutes * 60 - 30));

            if (is_string($scheduleLog) && $scheduleLog !== '') {
                $pulseCheck->appendOutputTo($scheduleLog);
            }

            ScheduleIntervals::everyMinutes($pulseCheck, $pulseMinutes);

            if (config('pulse.schedule.run_digest_tick', true)) {
                $pulseWork = $schedule->command('pulse:work', ['--stop-when-empty' => true])
                    ->name('pulse-scheduled-work')
                    ->withoutOverlapping(max(300, $pulseMinutes * 60 * 2));

                if (is_string($scheduleLog) && $scheduleLog !== '') {
                    $pulseWork->appendOutputTo($scheduleLog);
                }

                ScheduleIntervals::everyMinutes($pulseWork, $pulseMinutes);
            }
        }

        if (config('ieducar.admin_sync.schedule.enabled', true)) {
            $timezone = (string) config('app.timezone', 'UTC');
            $maxSeconds = max(10, (int) config('ieducar.admin_sync.schedule.max_seconds', 3300));
            $overlapMinutes = max(1, (int) config('ieducar.admin_sync.schedule.overlap_minutes', 720));
            $times = ScheduleIntervals::normalizeDailyTimes(
                config('ieducar.admin_sync.schedule.times', ['06:00', '18:00']),
            );

            $defaultJobTimeout = max(60, (int) config('ieducar.admin_sync.job_timeout', 3600));
            $adminSync = $schedule->command('admin-sync:work', [
                '--stop-when-empty' => true,
                '--max-time' => $maxSeconds,
                '--timeout' => $defaultJobTimeout,
            ])
                ->name('admin-sync-scheduled-work')
                ->withoutOverlapping($overlapMinutes)
                ->timezone($timezone)
                ->runInBackground();

            if ($times !== []) {
                ScheduleIntervals::dailyAtTimes($adminSync, $times);
            } else {
                $syncIntervalMinutes = max(1, (int) config('ieducar.admin_sync.schedule.interval_minutes', 60));
                if ($syncIntervalMinutes >= 60) {
                    ScheduleIntervals::everyHours($adminSync, (int) ceil($syncIntervalMinutes / 60));
                } else {
                    ScheduleIntervals::everyMinutes($adminSync, min(59, $syncIntervalMinutes));
                }
            }

            if (config('ieducar.admin_sync.schedule.on_demand', true)) {
                $onDemandMax = max(60, (int) config('ieducar.admin_sync.schedule.on_demand_max_seconds', 900));
                $defaultJobTimeout = max(60, (int) config('ieducar.admin_sync.job_timeout', 3600));
                $onDemand = $schedule->call(function () use ($onDemandMax, $defaultJobTimeout): void {
                    if (! AdminSyncScheduleGate::hasPendingWork()) {
                        return;
                    }

                    Artisan::call('admin-sync:work', [
                        '--stop-when-empty' => true,
                        '--max-time' => $onDemandMax,
                        '--timeout' => $defaultJobTimeout,
                    ]);
                })
                    ->name('admin-sync-on-demand')
                    ->withoutOverlapping(max(10, min(30, $runnerMinutes * 2)))
                    ->timezone($timezone);

                ScheduleIntervals::everyMinutes($onDemand, $runnerMinutes);
            }
        }

        if (config('analytics.pdf_report.schedule.enabled', true)
            && config('analytics.pdf_report.schedule.on_demand', true)) {
            $timezone = (string) config('app.timezone', 'UTC');
            $pdfOnDemandMax = max(60, (int) config('analytics.pdf_report.schedule.on_demand_max_seconds', 900));
            $pdfOnDemand = $schedule->call(function () use ($pdfOnDemandMax): void {
                if (! AnalyticsPdfScheduleGate::hasPendingWork()) {
                    return;
                }

                Artisan::call('analytics-pdf:work', [
                    '--stop-when-empty' => true,
                    '--max-time' => $pdfOnDemandMax,
                ]);
            })
                ->name('analytics-pdf-on-demand')
                ->withoutOverlapping(max(10, min(30, $runnerMinutes * 2)))
                ->timezone($timezone);

            ScheduleIntervals::everyMinutes($pdfOnDemand, $runnerMinutes);
        }

        if ((bool) config('notifications.operational_alerts.enabled', true)
            && (bool) config('notifications.operational_alerts.schedule.enabled', true)) {
            $opsMinutes = max(5, min(120, (int) config('notifications.operational_alerts.schedule.interval_minutes', 15)));
            $timezone = (string) config('app.timezone', 'UTC');

            $opsAlerts = $schedule->command('notifications:operational-alerts')
                ->name('operational-alerts-check')
                ->withoutOverlapping(max(5, $opsMinutes - 1))
                ->timezone($timezone);

            ScheduleIntervals::everyMinutes($opsAlerts, $opsMinutes);
        }

        if (filter_var(config('ieducar.weekly_mass_sync.enabled', true), FILTER_VALIDATE_BOOLEAN)
            && filter_var(config('ieducar.weekly_mass_sync.schedule.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $timezone = (string) config('app.timezone', 'UTC');
            $day = max(0, min(6, (int) config('ieducar.weekly_mass_sync.schedule.day_of_week', 0)));
            $time = trim((string) config('ieducar.weekly_mass_sync.schedule.time', '02:00')) ?: '02:00';
            $overlap = max(60, (int) config('ieducar.weekly_mass_sync.schedule.overlap_minutes', 10080));

            $schedule->command('weekly-mass-sync:run')
                ->weeklyOn($day, $time)
                ->name('weekly-mass-sync-enqueue')
                ->withoutOverlapping($overlap)
                ->timezone($timezone)
                ->runInBackground();
        }
    })
    ->create();
