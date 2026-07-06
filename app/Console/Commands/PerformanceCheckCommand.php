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
        $diag = RedisProbe::diagnose();

        $this->info('Configuração actual');
        $this->table(
            ['Variável', 'Valor'],
            [
                ['CACHE_STORE', (string) config('cache.default')],
                ['SESSION_DRIVER', (string) config('session.driver')],
                ['QUEUE_CONNECTION', (string) config('queue.default')],
                ['PULSE_CACHE_DRIVER', (string) (config('pulse.cache') ?: '(default cache)')],
                ['PULSE_INGEST_DRIVER', (string) config('pulse.ingest.driver')],
                ['REDIS_CLIENT (.env)', $diag['client_env']],
                ['REDIS_CLIENT (efetivo)', $diag['client_effective']],
                ['REDIS_HOST:PORT', $diag['host'].':'.$diag['port']],
                ['Ext. phpredis', RedisProbe::phpredisExtensionAvailable() ? 'sim' : 'não'],
                ['Pacote predis', RedisProbe::predisPackageAvailable() ? 'sim' : 'não'],
            ],
        );

        $this->printLoginHints($diag);

        if ($diag['ok']) {
            $this->info('Redis: PONG — disponível ('.$diag['client_effective'].' em '.$diag['host'].':'.$diag['port'].').');
            $this->newLine();
            $this->line('Variáveis já recomendadas para produção com Redis:');
            foreach (RedisProbe::recommendedEnvWhenAvailable() as $line) {
                $this->line("  {$line}");
            }
        } else {
            if ($diag['uses_redis_drivers']) {
                $this->error('Redis: configurado na aplicação, mas o servidor não respondeu.');
            } else {
                $this->warn('Redis: indisponível ou não configurado.');
            }

            if (filled($diag['error'])) {
                $this->line('Erro: '.$diag['error']);
            }

            foreach ($diag['hints'] as $hint) {
                $this->line('→ '.$hint);
            }

            if (! $diag['uses_redis_drivers']) {
                $this->newLine();
                $this->line('Sem Redis, sessão/cache em `database` geram mais I/O MySQL no login e rate-limit.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $diag
     */
    private function printLoginHints(array $diag): void
    {
        $this->newLine();
        $this->info('Login (código + .env)');
        $this->table(
            ['Opção', 'Valor'],
            [
                ['PERFORMANCE_DEFER_LOGIN_AUDIT', config('performance.defer_login_audit') ? 'true' : 'false'],
                ['PERFORMANCE_SKIP_MAIL_ON_AUTH', config('performance.skip_mail_on_auth_routes') ? 'true' : 'false'],
                ['PERFORMANCE_PULSE_SKIP_AUTH', config('performance.pulse_skip_auth_routes') ? 'true' : 'false'],
                ['PERFORMANCE_USER_CITY_IDS_CACHE', (string) config('performance.user_city_ids_cache')],
            ],
        );

        $session = (string) config('session.driver');
        $cache = (string) config('cache.default');
        if ($session === 'database' || $cache === 'database') {
            $this->warn('Sessão ou cache ainda em `database` — cada login gera vários I/O MySQL (sessão, rate-limit, cache).');
            if (! ($diag['ok'] ?? false)) {
                $this->line('→ Active Redis e use SESSION_DRIVER=redis + CACHE_STORE=redis (ver acima).');
            }
        }
    }
}
