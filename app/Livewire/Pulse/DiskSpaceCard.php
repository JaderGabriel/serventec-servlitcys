<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Espaço em disco do volume onde reside a aplicação (base_path).
 */
#[Lazy]
class DiskSpaceCard extends Card
{
    public function render(): Renderable
    {
        [$data, $time, $runAt] = $this->remember(function () {
            $path = base_path();
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            $ok = $free !== false && $total !== false && (float) $total > 0.0;
            $pctFree = $ok ? round(100.0 * ($free / $total), 1) : null;

            return [
                'path' => $path,
                'free_bytes' => $free !== false ? (int) $free : null,
                'total_bytes' => $total !== false ? (int) $total : null,
                'pct_free' => $pctFree,
                'ok' => $ok,
            ];
        }, 'disk');

        return View::make('livewire.pulse.disk-space-card', [
            'data' => $data,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
