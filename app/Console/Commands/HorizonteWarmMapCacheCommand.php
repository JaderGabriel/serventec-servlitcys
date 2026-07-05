<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteMapService;
use App\Support\Brazil\BrazilUfNames;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Console\Command;

class HorizonteWarmMapCacheCommand extends Command
{
    protected $signature = 'horizonte:warm-map-cache
                            {--uf= : Aquecer apenas uma UF (ex.: MG)}
                            {--skip-overview : Ignorar painel nacional}';

    protected $description = 'Aquece o cache JSON do mapa Horizonte (overview + UFs) para evitar 503 na primeira visita';

    public function handle(HorizonteMapService $map): int
    {
        if (! (bool) config('horizonte.enabled', true)) {
            $this->error(__('Horizonte desactivado (HORIZONTE_ENABLED=false).'));

            return self::FAILURE;
        }

        $overviewLimit = max(60, (int) config('horizonte.map_display.overview_time_limit', 180));
        $regionalLimit = max(60, (int) config('horizonte.map_display.regional_time_limit', 120));
        @ini_set('memory_limit', trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M')));

        $ufFilter = HorizonteUfScope::normalize($this->option('uf'));
        if ($this->option('uf') && $ufFilter === null) {
            $this->error(__('UF inválida.'));

            return self::FAILURE;
        }

        if (! $this->option('skip-overview') && $ufFilter === null) {
            $this->info(__('Overview…'));
            set_time_limit($overviewLimit);
            $overview = $map->buildForRequest('overview', null, warmCache: true);
            $count = count($overview['uf_rankings'] ?? []);
            $this->line(__('  OK — :count UFs no ranking', ['count' => $count]));
        }

        $ufs = $ufFilter !== null
            ? [$ufFilter]
            : array_keys(BrazilUfNames::all());

        $total = count($ufs);
        foreach ($ufs as $index => $uf) {
            $n = $index + 1;
            $this->info("[{$n}/{$total}] {$uf}…");
            set_time_limit($regionalLimit);
            $payload = $map->buildForRequest('regional', $uf, warmCache: true);
            $markers = count($payload['markers'] ?? []);
            $this->line(__('  OK — :count marcadores', ['count' => $markers]));
        }

        $this->info(__('Cache do mapa Horizonte aquecido.'));

        return self::SUCCESS;
    }
}
