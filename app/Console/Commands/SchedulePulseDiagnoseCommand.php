<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Recorders\Servers as ServersRecorder;

#[Signature('schedule:pulse-diagnose')]
#[Description('Diagnóstico: Pulse Servers offline com cron (snapshots, mutex, crontab recomendado)')]
class SchedulePulseDiagnoseCommand extends Command
{
    public function handle(): int
    {
        $pulseEnabled = (bool) config('pulse.enabled', true);
        $scheduleEnabled = (bool) config('pulse.schedule.enabled', true);
        $intervalMin = max(1, (int) config('pulse.schedule.interval_minutes', 3));
        $runnerMin = max(1, (int) config('schedule.runner_interval_minutes', 3));
        $freshWindow = max(120, min(3600, ($intervalMin * 60) + 90));
        $serverName = (string) config('pulse.recorders.'.ServersRecorder::class.'.server_name', (string) gethostname());
        $targetSlug = Str::slug($serverName);

        $this->info(__('Diagnóstico Pulse / scheduler'));
        $this->newLine();
        $this->line(__('Utilizador PHP: :u', ['u' => get_current_user()]));
        $this->line(__('APP_ENV: :e · timezone: :tz', [
            'e' => config('app.env'),
            'tz' => config('app.timezone'),
        ]));
        $this->line(__('PULSE_ENABLED: :p · PULSE_SCHEDULE_ENABLED: :s', [
            'p' => $pulseEnabled ? 'true' : 'false',
            's' => $scheduleEnabled ? 'true' : 'false',
        ]));
        $this->line(__('PULSE_SERVER_NAME: :n (slug: :slug)', ['n' => $serverName, 'slug' => $targetSlug]));
        $this->line(__('PULSE_SCHEDULE_INTERVAL_MINUTES: :i · janela «online»: ~:s s', [
            'i' => $intervalMin,
            's' => $freshWindow,
        ]));
        $this->line(__('SCHEDULE_RUN_INTERVAL_MINUTES: :r', ['r' => $runnerMin]));
        $this->line(__('CACHE_STORE (mutex schedule): :c', ['c' => config('cache.default')]));
        $this->newLine();

        $this->inspectPulseSnapshot($targetSlug, $serverName, $freshWindow);
        $this->inspectScheduleMutex();
        $this->printCronRecommendations($runnerMin);

        return self::SUCCESS;
    }

    private function inspectPulseSnapshot(string $targetSlug, string $serverName, int $freshWindow): void
    {
        $this->comment(__('Último snapshot «system» (pulse_values):'));

        try {
            $connection = config('pulse.storage.database.connection') ?: config('database.default');
            $row = DB::connection($connection)
                ->table('pulse_values')
                ->where('type', 'system')
                ->orderByDesc('timestamp')
                ->first();

            if ($row === null) {
                $this->warn(__('  Nenhum registro. Rode: php artisan schedule:run  ou  php artisan pulse:check --once'));

                return;
            }

            $updatedAt = CarbonImmutable::createFromTimestamp((int) $row->timestamp);
            $ageSec = (int) now()->diffInSeconds($updatedAt, absolute: true);
            $online = $updatedAt->isAfter(now()->subSeconds($freshWindow));
            $slugMatch = (string) $row->key === $targetSlug;

            $this->line(__('  key=:k (esperado :e) · idade=:a s · Pulse UI: :st', [
                'k' => $row->key,
                'e' => $targetSlug,
                'a' => $ageSec,
                'st' => $online ? 'online' : 'offline',
            ]));

            if (! $slugMatch) {
                $this->warn(__('  Slug diferente de PULSE_SERVER_NAME — defina PULSE_SERVER_NAME=:k no .env e php artisan config:cache', [
                    'k' => $row->key,
                ]));
            }

            if (! $online) {
                $this->warn(__('  Snapshot antigo (> :s s). O cron provavelmente não executa pulse:check a tempo.', ['s' => $freshWindow]));
            }
        } catch (\Throwable $e) {
            $this->error(__('  Erro ao ler pulse_values: :m', ['m' => $e->getMessage()]));
        }

        $this->newLine();
    }

    private function inspectScheduleMutex(): void
    {
        $this->comment(__('Mutex / permissões (withoutOverlapping):'));
        $cacheDir = storage_path('framework/cache/data');
        $writable = is_dir($cacheDir) && is_writable($cacheDir);
        $this->line(__('  storage/framework/cache gravável: :w', ['w' => $writable ? 'sim' : 'não']));
        $this->line(__('  Se pulse:check não corre no cron mas corre no SSH: mesmo usuário no crontab,'));
        $this->line(__('  evite >> /dev/null, rode php artisan schedule:clear-cache e confira storage/logs/scheduler.log'));
        $this->newLine();
    }

    private function printCronRecommendations(int $runnerMin): void
    {
        $base = base_path();
        $php = PHP_BINARY;

        $this->comment(__('Crontab recomendado (mesmo usuário do PHP-FPM / deploy):'));
        $this->newLine();
        $this->line(__('  # Invocar o scheduler a cada minuto (Laravel decide o que está due):'));
        $this->line("  * * * * * cd {$base} && {$php} artisan schedule:run >> {$base}/storage/logs/scheduler.log 2>&1");
        $this->newLine();
        $this->line(__('  Evite só `*/{$runnerMin} * * * *` se outras tarefas usam cadências diferentes;'));
        $this->line(__('  e não use `>> /dev/null` até confirmar que pulse:check corre.'));
        $this->newLine();
        $this->line(__('  Ative log no .env: SCHEDULE_LOG_TO_FILE=true'));
        $this->line(__('  Teste como o cron: sudo -u www-data php artisan schedule:run -v'));
    }
}
