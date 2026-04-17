<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use Throwable;

/**
 * Jobs pendentes na fila configurada e contagem de failed_jobs (se existir tabela).
 */
#[Lazy]
class QueueAndFailuresCard extends Card
{
    public function render(): Renderable
    {
        [$data, $time, $runAt] = $this->remember(function () {
            $connection = (string) config('queue.default', 'sync');
            $pending = null;
            $failed = null;
            $error = null;

            try {
                if ($connection !== 'sync' && $connection !== 'null') {
                    $pending = Queue::connection($connection)->size();
                } else {
                    $pending = 0;
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }

            try {
                if (Schema::hasTable('failed_jobs')) {
                    $failed = (int) DB::table('failed_jobs')->count();
                }
            } catch (Throwable) {
                $failed = null;
            }

            return [
                'connection' => $connection,
                'pending' => $pending,
                'failed' => $failed,
                'error' => $error,
            ];
        }, 'queue');

        return View::make('livewire.pulse.queue-and-failures-card', [
            'data' => $data,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
