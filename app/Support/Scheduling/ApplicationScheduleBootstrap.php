<?php

namespace App\Support\Scheduling;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

/**
 * Garante que os eventos definidos em bootstrap/app.php (withSchedule) estejam
 * registados também em pedidos HTTP.
 *
 * No Laravel 13, withSchedule() só regista callbacks dentro de Artisan::starting —
 * sem arrancar o console application, Schedule::events() devolve vazio na UI.
 */
final class ApplicationScheduleBootstrap
{
    private static bool $bootstrapped = false;

    public static function ensureRegistered(?Schedule $schedule = null): Schedule
    {
        $schedule ??= app(Schedule::class);

        if (count($schedule->events()) > 0) {
            self::$bootstrapped = true;

            return $schedule;
        }

        if (! self::$bootstrapped) {
            self::$bootstrapped = true;

            app(ConsoleKernel::class)->call('list', [
                '--quiet' => true,
                '--no-ansi' => true,
            ]);

            $schedule = app(Schedule::class);
        }

        return $schedule;
    }

    public static function resetForTests(): void
    {
        self::$bootstrapped = false;
    }
}
