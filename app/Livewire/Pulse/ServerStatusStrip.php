<?php

namespace App\Livewire\Pulse;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Recorders\Servers as ServersRecorder;
use Livewire\Attributes\Lazy;

/**
 * Faixa compacta: online/offline, CPU, memória e disco (dados do recorder Servidores do Pulse).
 */
#[Lazy]
class ServerStatusStrip extends Card
{
    /**
     * Quando true, remove o “cartão” exterior para encaixar no painel fundido com o cartão Servers.
     */
    public bool $embedded = false;

    /**
     * Placeholder fino (evita o skeleton em grelha do cartão Pulse por defeito).
     */
    public function placeholder(): Renderable
    {
        return View::make('livewire.pulse.server-status-strip-skeleton');
    }

    public function render(): Renderable
    {
        [$payload, $time, $runAt] = $this->remember(function () {
            return $this->snapshot();
        }, 'strip');

        return View::make('livewire.pulse.server-status-strip', [
            'payload' => $payload,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(): array
    {
        $serverName = (string) config('pulse.recorders.'.ServersRecorder::class.'.server_name', (string) gethostname());
        $targetSlug = Str::slug($serverName);
        $intervalMin = max(1, (int) config('pulse.schedule.interval_minutes', 5));
        $freshWindow = max(120, min(3600, ($intervalMin * 60) + 90));

        /** @var Collection<string, object{ timestamp: int, key: string, value: string }> $systems */
        $systems = $this->values('system');
        $row = $systems->get($targetSlug) ?? $systems->first();

        if ($row === null) {
            return [
                'ok' => false,
                'name' => $serverName,
                'online' => false,
                'cpu' => null,
                'memory_used_mb' => null,
                'memory_total_mb' => null,
                'disk_used_pct' => null,
                'updated_human' => null,
                'message' => __('Ainda sem snapshots de sistema. Corra o agendador (`pulse:check`) e o worker de digest.'),
            ];
        }

        $values = json_decode($row->value, false, 512, JSON_THROW_ON_ERROR);
        $updatedAt = CarbonImmutable::createFromTimestamp($row->timestamp);
        $online = $updatedAt->isAfter(now()->subSeconds($freshWindow));

        $storage = collect($values->storage ?? []);
        $diskUsedPct = null;
        if ($storage->isNotEmpty()) {
            $totalMb = (int) $storage->sum(function (mixed $s): int {
                return (int) (is_array($s) ? ($s['total'] ?? 0) : ($s->total ?? 0));
            });
            $usedMb = (int) $storage->sum(function (mixed $s): int {
                return (int) (is_array($s) ? ($s['used'] ?? 0) : ($s->used ?? 0));
            });
            $diskUsedPct = $totalMb > 0 ? round(100 * ($usedMb / $totalMb), 1) : null;
        }

        $memoryUsed = isset($values->memory_used) ? (int) $values->memory_used : null;
        $memoryTotal = isset($values->memory_total) ? (int) $values->memory_total : null;

        return [
            'ok' => true,
            'name' => (string) ($values->name ?? $serverName),
            'online' => $online,
            'cpu' => isset($values->cpu) ? (int) $values->cpu : null,
            'memory_used_mb' => $memoryUsed,
            'memory_total_mb' => $memoryTotal,
            'disk_used_pct' => $diskUsedPct,
            'updated_human' => $updatedAt->diffForHumans(),
            'fresh_window_s' => $freshWindow,
            'message' => null,
        ];
    }
}
