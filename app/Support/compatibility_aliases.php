<?php

declare(strict_types=1);

/**
 * Aliases de compatibilidade carregados antes do bootstrap Laravel (composer autoload files).
 * Evita falha em workers com bytecode antigo que referencia App\Support\Admin\WeeklyMassSyncCheckpoint.
 */
if (! class_exists(\App\Support\Admin\WeeklyMassSyncCheckpoint::class, false)) {
    class_alias(
        \App\Support\AdminSync\WeeklyMassSyncCheckpoint::class,
        \App\Support\Admin\WeeklyMassSyncCheckpoint::class,
    );
}

if (! class_exists(\App\Services\Ieducar\IeducarCityDataService::class, false)) {
    class_alias(
        \App\Services\CityDataConnection::class,
        \App\Services\Ieducar\IeducarCityDataService::class,
    );
}
