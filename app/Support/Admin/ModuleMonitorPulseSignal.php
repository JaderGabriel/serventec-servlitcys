<?php

namespace App\Support\Admin;

use App\Support\Pulse\PulseAggregateBridge;

/** Contagens Pulse por prefixo de chave — usadas nas sondas do Module Monitor. */
final class ModuleMonitorPulseSignal
{
    public static function operationCount(string $prefix, string $period = '7d'): int
    {
        return self::sumType('app_operation', $prefix, $period);
    }

    public static function errorCount(string $prefix, string $period = '7d'): int
    {
        return self::sumType('app_operation_error', $prefix, $period);
    }

    public static function slowCount(string $prefix, string $period = '7d'): int
    {
        return self::sumType('app_operation_slow', $prefix, $period);
    }

    public static function databaseSlowCount(string $period = '7d'): int
    {
        if (! PulseAggregateBridge::isAvailable()) {
            return 0;
        }

        $total = 0;
        foreach (PulseAggregateBridge::aggregate('db_slow_scope', 'count', $period, 'count', 'desc', 80) as $row) {
            $total += (int) ($row->count ?? 0);
        }

        return $total;
    }

    private static function sumType(string $type, string $prefix, string $period): int
    {
        if ($prefix === '' || ! PulseAggregateBridge::isAvailable()) {
            return 0;
        }

        $total = 0;
        foreach (PulseAggregateBridge::aggregate($type, 'count', $period, 'count', 'desc', 120) as $row) {
            $key = (string) ($row->key ?? '');
            if ($key !== '' && str_starts_with($key, $prefix)) {
                $total += (int) ($row->count ?? 0);
            }
        }

        return $total;
    }
}
