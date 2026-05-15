<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AdminSyncWorkCommand extends Command
{
    protected $signature = 'admin-sync:work
                            {--once : Processar apenas um job e terminar}
                            {--stop-when-empty : Parar quando a fila estiver vazia (usado pelo schedule:run)}
                            {--max-time=0 : Tempo máximo do worker em segundos (0 = sem limite)}
                            {--timeout= : Timeout por job em segundos (default: config ieducar)}
                            {--tries= : Tentativas por job (default: config ieducar)}';

    protected $description = 'Processa a fila de sincronização administrativa (geo, pedagógico, FUNDEB, i-Educar)';

    public function handle(): int
    {
        $queue = (string) config('ieducar.admin_sync.queue', 'admin-sync');
        $connection = config('ieducar.admin_sync.connection') ?? config('queue.default');
        $timeout = $this->option('timeout') !== null
            ? (int) $this->option('timeout')
            : max(60, (int) config('ieducar.admin_sync.job_timeout', 3600));
        $tries = $this->option('tries') !== null
            ? (int) $this->option('tries')
            : max(1, (int) config('ieducar.admin_sync.tries', 1));

        $this->info(__('Worker da fila administrativa'));
        $this->line(__('  Ligação: :conn', ['conn' => (string) $connection]));
        $this->line(__('  Fila: :queue', ['queue' => $queue]));
        $this->line(__('  Timeout: :s s · Tentativas: :t', ['s' => (string) $timeout, 't' => (string) $tries]));
        $this->newLine();
        if (! $this->option('once') && ! $this->option('stop-when-empty')) {
            $this->comment(__('Em produção contínua, use Supervisor. Com cron, active ADMIN_SYNC_SCHEDULE_ENABLED e schedule:run cada minuto.'));
            $this->newLine();
        }

        $maxTime = $this->option('max-time');
        $maxTime = $maxTime !== null && $maxTime !== '' && $maxTime !== '0'
            ? (int) $maxTime
            : 0;

        $params = [
            'connection' => $connection,
            '--queue' => $queue,
            '--timeout' => $timeout,
            '--tries' => $tries,
            '--sleep' => 3,
            '--max-time' => $maxTime,
        ];

        if ($this->option('once')) {
            $params['--once'] = true;
        }

        if ($this->option('stop-when-empty')) {
            $params['--stop-when-empty'] = true;
        }

        return $this->call('queue:work', $params);
    }
}
