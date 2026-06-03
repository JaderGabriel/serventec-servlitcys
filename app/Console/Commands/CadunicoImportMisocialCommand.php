<?php

namespace App\Console\Commands;

use App\Services\Cadunico\CadunicoMisocialBulkImportService;
use Illuminate\Console\Command;

class CadunicoImportMisocialCommand extends Command
{
    protected $signature = 'cadunico:import-misocial
        {--from=2020 : Ano inicial (inclusivo)}
        {--to= : Ano final (inclusivo; omite = ano civil actual)}
        {--years= : Anos separados por vírgula (substitui --from/--to)}';

    protected $description = 'Importa agregados nacionais CadÚnico via SAGI/Misocial (MDS) para vários anos';

    public function handle(CadunicoMisocialBulkImportService $service): int
    {
        $toOpt = $this->option('to');
        $years = CadunicoMisocialBulkImportService::parseYearsOption(
            $this->option('years'),
            is_numeric($this->option('from')) ? (int) $this->option('from') : null,
            $toOpt !== null && $toOpt !== '' && is_numeric($toOpt) ? (int) $toOpt : null,
        );

        if ($years === []) {
            $this->error(__('Nenhum ano válido.'));

            return self::FAILURE;
        }

        $this->info(__('A importar Misocial para os anos: :y', ['y' => implode(', ', array_map('strval', $years))]));

        $result = $service->importYears($years, function (int $index, int $ano, int $total): void {
            $this->line(__('[:i/:t] Ano :ano…', ['i' => (string) $index, 't' => (string) $total, 'ano' => (string) $ano]));
        });

        foreach ($result['per_year'] ?? [] as $ano => $stats) {
            if (! is_array($stats)) {
                continue;
            }
            $status = ($stats['success'] ?? false) ? 'OK' : '—';
            $this->line(sprintf(
                '  %s %s — %d município(s), mês %s',
                $status,
                (string) $ano,
                (int) ($stats['imported'] ?? 0),
                (string) ($stats['month'] ?? '—'),
            ));
            $msg = trim((string) ($stats['message'] ?? ''));
            if ($msg !== '') {
                $this->comment('      '.$msg);
            }
        }

        if (! ($result['ok'] ?? false)) {
            $this->error((string) ($result['message'] ?? __('Falha na importação Misocial.')));

            return self::FAILURE;
        }

        $this->info((string) ($result['message'] ?? ''));

        return self::SUCCESS;
    }
}
