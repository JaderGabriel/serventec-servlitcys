<?php

namespace App\Console\Commands;

use App\Services\Admin\ModuleMonitorProbeService;
use App\Services\Notifications\ModuleMonitorOperationalNotifier;
use App\Support\Admin\ModuleMonitorCatalog;
use Illuminate\Console\Command;

class ModuleMonitorCollectCommand extends Command
{
    protected $signature = 'module-monitor:collect
                            {--dry-run : Lista módulos sem gravar cache}';

    protected $description = 'Recolhe sinais de saúde por módulo e atualiza o cache do monitor admin';

    public function handle(
        ModuleMonitorProbeService $probes,
        ModuleMonitorOperationalNotifier $notifier,
    ): int {
        if (! (bool) config('module_monitor.enabled', true)) {
            $this->comment(__('Monitor de módulos desactivado (MODULE_MONITOR_ENABLED).'));

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(__('Modo dry-run — módulos monitorizados:'));
            foreach (ModuleMonitorCatalog::modules() as $module) {
                $this->line('  · '.($module['label'] ?? $module['id']));
            }

            return self::SUCCESS;
        }

        $snapshot = $probes->collect();
        $modules = is_array($snapshot['modules'] ?? null) ? $snapshot['modules'] : [];

        $rows = [];
        $failed = 0;
        $degraded = 0;
        foreach ($modules as $moduleId => $probe) {
            if (! is_array($probe)) {
                continue;
            }
            $signal = (string) ($probe['signal'] ?? '—');
            if ($signal === 'failed') {
                $failed++;
            } elseif ($signal === 'degraded') {
                $degraded++;
            }
            $rows[] = [
                (string) $moduleId,
                $signal,
                \Illuminate\Support\Str::limit((string) ($probe['detail'] ?? ''), 72),
            ];
        }

        $this->table([__('Módulo'), __('Sinal'), __('Resumo')], $rows);
        $this->newLine();
        $this->info(__('Snapshot guardado — :n módulo(s).', ['n' => count($rows)]));
        if ($failed > 0 || $degraded > 0) {
            $this->warn(__('Sinais: :f falha(s), :d degradado(s).', [
                'f' => (string) $failed,
                'd' => (string) $degraded,
            ]));
        }
        $this->line(__('Monitor: :url', ['url' => route('admin.module-monitor.index')]));

        $notifier->afterCollect($snapshot);

        return self::SUCCESS;
    }
}
