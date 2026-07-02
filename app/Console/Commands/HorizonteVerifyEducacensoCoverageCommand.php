<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteEducacensoWindowCoverageService;
use Illuminate\Console\Command;

class HorizonteVerifyEducacensoCoverageCommand extends Command
{
    protected $signature = 'horizonte:verify-educacenso-coverage
                            {--sample=50 : Número de municípios aleatórios}
                            {--seed= : Semente para amostra reproduzível}
                            {--json : Saída JSON}';

    protected $description = 'Audita cobertura da janela Educacenso em municípios aleatórios';

    public function handle(HorizonteEducacensoWindowCoverageService $coverage): int
    {
        $sample = max(1, (int) $this->option('sample'));
        $seed = $this->option('seed');
        $seed = is_numeric($seed) ? (int) $seed : null;

        $result = $coverage->auditRandomMunicipalities($sample, $seed);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        if (isset($result['message'])) {
            $this->error((string) $result['message']);

            return self::FAILURE;
        }

        $years = implode(', ', array_map('strval', $result['window_years'] ?? []));
        $this->info(__('Janela Educacenso: :anos', ['anos' => $years]));
        $this->newLine();

        $this->table(
            [__('Ano'), __('Municípios nacionais')],
            collect($result['national_years'] ?? [])
                ->map(static fn (int $count, int $year): array => [(string) $year, (string) $count])
                ->values()
                ->all(),
        );

        $this->newLine();
        $this->info(__('Amostra: :n municípios — :complete completos (:pct%), :incomplete incompletos', [
            'n' => (string) ($result['sample_size'] ?? 0),
            'complete' => (string) ($result['complete_count'] ?? 0),
            'pct' => (string) ($result['complete_pct'] ?? 0),
            'incomplete' => (string) ($result['incomplete_count'] ?? 0),
        ]));

        $rows = [];
        foreach ($result['municipalities'] ?? [] as $row) {
            $status = ($row['complete'] ?? false) ? 'OK' : 'FALTA';
            $missing = ($row['missing_years'] ?? []) !== []
                ? implode(', ', array_map('strval', $row['missing_years']))
                : '—';
            $rows[] = [
                (string) ($row['ibge'] ?? ''),
                $status,
                $missing,
            ];
        }

        $this->newLine();
        $this->table([__('IBGE'), __('Status'), __('Anos em falta')], $rows);

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
