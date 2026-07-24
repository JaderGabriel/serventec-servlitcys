<?php

namespace App\Console\Commands;

use App\Models\Clio\ClioCampaignArtifact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Remove artefactos Clio mais antigos que a retenção configurada (CLI-IND-05).
 */
class ClioPruneArtifactsCommand extends Command
{
    protected $signature = 'clio:prune-artifacts
                            {--days= : Dias de retenção (default: config clio.retention_days)}
                            {--dry-run : Só listar o que seria apagado}';

    protected $description = 'Apaga artefactos Clio (ficheiros + registos) além da retenção';

    public function handle(): int
    {
        if (! filter_var(config('clio.enabled', true), FILTER_VALIDATE_BOOL)) {
            $this->error(__('Clio está desativado (CLIO_ENABLED).'));

            return self::FAILURE;
        }

        $days = max(1, (int) ($this->option('days') ?: config('clio.retention_days', 90)));
        $cutoff = now()->subDays($days);
        $dry = (bool) $this->option('dry-run');
        $disk = (string) config('clio.disk', 'local');

        $query = ClioCampaignArtifact::query()->where('created_at', '<', $cutoff);
        $total = (clone $query)->count();
        $this->info(__('Retenção :d dias · corte :c · candidatos :n', [
            'd' => $days,
            'c' => $cutoff->toDateTimeString(),
            'n' => $total,
        ]));

        if ($total === 0) {
            return self::SUCCESS;
        }

        $deletedFiles = 0;
        $deletedRows = 0;
        $query->orderBy('id')->chunkById(100, function ($artifacts) use ($dry, $disk, &$deletedFiles, &$deletedRows): void {
            foreach ($artifacts as $artifact) {
                $path = (string) $artifact->storage_path;
                if ($path !== '' && Storage::disk($disk)->exists($path)) {
                    if (! $dry) {
                        Storage::disk($disk)->delete($path);
                    }
                    $deletedFiles++;
                }
                if (! $dry) {
                    $artifact->delete();
                }
                $deletedRows++;
            }
        });

        $this->info($dry
            ? __('Dry-run: :r registo(s) / :f ficheiro(s) seriam removidos.', ['r' => $deletedRows, 'f' => $deletedFiles])
            : __('Removidos :r registo(s) e :f ficheiro(s).', ['r' => $deletedRows, 'f' => $deletedFiles]));

        return self::SUCCESS;
    }
}
