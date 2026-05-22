<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyticsPdfWorkCommand extends Command
{
    protected $signature = 'analytics-pdf:work
                            {--once : Processar apenas um job e terminar}
                            {--stop-when-empty : Parar quando a fila estiver vazia (usado pelo schedule:run)}
                            {--max-time=0 : Tempo máximo do worker em segundos (0 = sem limite)}
                            {--timeout= : Timeout por job em segundos (default: config analytics)}
                            {--tries= : Tentativas por job (default: config analytics)}';

    protected $description = 'Processa a fila de relatórios PDF do painel analítico';

    public function handle(): int
    {
        $queue = (string) config('analytics.pdf_report.queue', 'default');
        $connection = config('analytics.pdf_report.connection') ?? config('queue.default');
        $timeout = $this->option('timeout') !== null
            ? (int) $this->option('timeout')
            : max(120, (int) config('analytics.pdf_report.job_timeout', 900));
        $tries = $this->option('tries') !== null
            ? (int) $this->option('tries')
            : max(1, (int) config('analytics.pdf_report.tries', 2));

        $this->info(__('Worker da fila de PDF analítico'));
        $this->line(__('  Conexão: :conn', ['conn' => (string) $connection]));
        $this->line(__('  Fila: :queue', ['queue' => $queue]));
        $this->line(__('  Timeout: :s s · Tentativas: :t', ['s' => (string) $timeout, 't' => (string) $tries]));
        $this->newLine();

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
