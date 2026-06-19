<?php

namespace App\Console\Commands;

use App\Services\Admin\ModuleMonitorProbeService;
use App\Support\Admin\ModuleMonitorCatalog;
use Illuminate\Console\Command;

class ModuleMonitorCollectCommand extends Command
{
    protected $signature = 'module-monitor:collect
                            {--dry-run : Lista módulos sem gravar cache}';

    protected $description = 'Recolhe sinais de saúde por módulo e actualiza o cache do monitor admin';

    public function handle(ModuleMonitorProbeService $probes): int
    {
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
        foreach ($modules as $moduleId => $probe) {
            if (! is_array($probe)) {
                continue;
            }
            $rows[] = [
                (string) $moduleId,
                (string) ($probe['signal'] ?? '—'),
                \Illuminate\Support\Str::limit((string) ($probe['detail'] ?? ''), 72),
            ];
        }

        $this->table([__('Módulo'), __('Sinal'), __('Resumo')], $rows);
        $this->newLine();
        $this->info(__('Snapshot guardado — :n módulo(s).', ['n' => count($rows)]));
        $this->line(__('Monitor: :url', ['url' => route('admin.module-monitor.index')]));

        return self::SUCCESS;
    }
}
