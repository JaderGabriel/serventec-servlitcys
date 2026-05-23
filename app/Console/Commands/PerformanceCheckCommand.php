<?php

namespace App\Console\Commands;

use App\Support\Performance\RedisProbe;
use Illuminate\Console\Command;

class PerformanceCheckCommand extends Command
{
    protected $signature = 'performance:check';

    protected $description = 'Diagnóstico rápido de cache/sessão/filas e disponibilidade do Redis';

    public function handle(): int
    {
        $this->info('Configuração actual');
        $this->table(
            ['Variável', 'Valor'],
            [
                ['CACHE_STORE', (string) config('cache.default')],
                ['SESSION_DRIVER', (string) config('session.driver')],
                ['QUEUE_CONNECTION', (string) config('queue.default')],
                ['PULSE_CACHE_DRIVER', (string) (config('pulse.cache') ?: '(default cache)')],
                ['PULSE_INGEST_DRIVER', (string) config('pulse.ingest.driver')],
            ],
        );

        if (RedisProbe::isReachable()) {
            $this->info('Redis: PONG — disponível.');
            $this->newLine();
            $this->line('Recomendações para produção (adicione ao .env se ainda não estiverem activas):');
            foreach (RedisProbe::recommendedEnvWhenAvailable() as $line) {
                $this->line("  {$line}");
            }
        } else {
            $this->warn('Redis: indisponível ou não configurado.');
            $this->line('Com sessão/cache em `database`, cada login e rate-limit geram várias escritas/leituras MySQL.');
            $this->line('Instale Redis e ajuste REDIS_* no .env, depois execute novamente este comando.');
        }

        return self::SUCCESS;
    }
}
