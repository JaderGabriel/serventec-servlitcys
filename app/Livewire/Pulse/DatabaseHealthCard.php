<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use Throwable;

/**
 * Ligação à base de dados e latência de um ping simples.
 */
#[Lazy]
class DatabaseHealthCard extends Card
{
    public function render(): Renderable
    {
        [$data, $time, $runAt] = $this->remember(function () {
            $default = config('database.default');
            $conn = config("database.connections.{$default}", []);
            $driver = (string) ($conn['driver'] ?? '—');
            $database = (string) ($conn['database'] ?? ($conn['dbname'] ?? '—'));
            if (isset($conn['url']) && is_string($conn['url']) && $conn['url'] !== '') {
                $database = '(URL)';
            }

            $pingMs = null;
            $version = null;
            $error = null;

            try {
                $t0 = microtime(true);
                DB::connection()->getPdo();
                $pingMs = (int) round((microtime(true) - $t0) * 1000);
                try {
                    $driverName = DB::connection()->getDriverName();
                    $version = match ($driverName) {
                        'mysql', 'mariadb' => (string) (DB::selectOne('select version() as v')?->v ?? ''),
                        'pgsql' => (string) (DB::selectOne('select version() as v')?->v ?? ''),
                        'sqlite' => (string) (DB::selectOne('select sqlite_version() as v')?->v ?? ''),
                        default => null,
                    };
                    if ($version === '') {
                        $version = null;
                    }
                } catch (Throwable) {
                    $version = null;
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }

            return [
                'default' => $default,
                'driver' => $driver,
                'database' => $database,
                'ping_ms' => $pingMs,
                'version' => $version,
                'error' => $error,
            ];
        }, 'db');

        return View::make('livewire.pulse.database-health-card', [
            'data' => $data,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
