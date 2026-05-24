<?php

namespace App\Listeners;

use App\Support\Pulse\PulseDatabaseRecorder;
use Illuminate\Database\Events\QueryExecuted;

final class RecordPulseDatabaseQueries
{
    public function handle(QueryExecuted $event): void
    {
        if (! PulseDatabaseRecorder::enabled()) {
            return;
        }

        PulseDatabaseRecorder::recordQuery(
            (string) $event->connectionName,
            (string) $event->sql,
            (float) $event->time,
        );
    }
}
