<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteSiconfiMunicipalSyncService;
use App\Support\Horizonte\HorizonteSiconfiSyncProgress;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Console\Command;

class HorizonteSyncSiconfiCommand extends Command
{
    protected $signature = 'horizonte:sync-siconfi
                            {--uf= : Restringir a uma UF (ex.: BA)}
                            {--year= : Ano de referência (default: horizonte.reference_year)}
                            {--period= : Período RREO 1–6 (default: config horizonte.siconfi.period)}
                            {--limit= : Municípios por lote}
                            {--ibge=* : IBGE(s) específicos}
                            {--continue : Retomar sincronização nacional em curso (ignorado com --uf ou --ibge)}
                            {--reset : Reiniciar sincronização (apaga snapshots do ano no âmbito e inicia progresso nacional)}
                            {--refresh : Reimportar municípios com período RREO inferior ao alvo}
                            {--dry-run : Simular sem gravar}';

    protected $description = 'Importa indicadores fiscais municipais via API SICONFI (RREO) para o Horizonte';

    public function handle(HorizonteSiconfiMunicipalSyncService $sync): int
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $ufRaw = trim((string) $this->option('uf'));
        if ($ufRaw !== '' && HorizonteUfScope::normalize($ufRaw) === null) {
            $this->error(__('UF inválida: :uf', ['uf' => $ufRaw]));

            return self::FAILURE;
        }

        $year = (int) ($this->option('year') ?: config('horizonte.reference_year', (int) date('Y') - 1));
        $period = $this->option('period') !== null
            ? max(1, min(6, (int) $this->option('period')))
            : max(1, min(6, (int) config('horizonte.siconfi.period', 6)));
        $ibgeCodes = array_values(array_filter(array_map('strval', (array) $this->option('ibge'))));
        $nationalScope = $ufRaw === '' && $ibgeCodes === [];

        $this->info(__('Horizonte — SICONFI / RREO (capacidade fiscal municipal)'));
        $this->line(__('Ano :ano · período RREO :periodo', [
            'ano' => (string) $year,
            'periodo' => (string) $period,
        ]));

        if ($ufRaw !== '') {
            $this->line(__('Âmbito: UF :uf', ['uf' => (string) HorizonteUfScope::normalize($ufRaw)]));
        } elseif ($nationalScope) {
            $this->renderProgressHeader($year, $period);
            if (filter_var(config('horizonte.siconfi_sync.by_uf', true), FILTER_VALIDATE_BOOLEAN)) {
                $next = HorizonteSiconfiSyncProgress::remainingUfs($year, $period)[0] ?? null;
                $this->line(__('Modo: 1 UF por execução (menor → maior por nº de municípios). Próxima: :uf', [
                    'uf' => $next ?? __('concluído'),
                ]));
            }
        }

        if ((bool) $this->option('reset')) {
            if ($ufRaw !== '') {
                $this->warn(__('Snapshots SICONFI da UF :uf (ano :ano) serão repostos.', [
                    'uf' => (string) HorizonteUfScope::normalize($ufRaw),
                    'ano' => (string) $year,
                ]));
            } else {
                $this->warn(__('Snapshots SICONFI nacionais (ano :ano) serão repostos.', ['ano' => (string) $year]));
            }
        }

        if ((bool) $this->option('dry-run')) {
            $this->warn(__('Dry-run — nenhum registo gravado.'));

            return self::SUCCESS;
        }

        $options = [
            'year' => $year,
            'period' => $period,
            'uf' => $ufRaw !== '' ? HorizonteUfScope::normalize($ufRaw) : null,
            'reset' => (bool) $this->option('reset'),
            'continue' => (bool) $this->option('continue'),
            'refresh' => (bool) $this->option('refresh'),
        ];
        if ($ibgeCodes !== []) {
            $options['ibge_codes'] = $ibgeCodes;
        }
        if ($this->option('limit') !== null) {
            $options['municipios_per_step'] = (int) $this->option('limit');
        }

        $byUf = filter_var(config('horizonte.siconfi_sync.by_uf', true), FILTER_VALIDATE_BOOLEAN);
        if ($nationalScope && $byUf) {
            $options['by_uf'] = true;
            $options['ufs_per_step'] = max(1, (int) config('horizonte.siconfi_sync.ufs_per_step', 1));
        }

        $result = $sync->syncBatch($options);

        if ($result['skipped'] ?? false) {
            $this->warn((string) ($result['message'] ?? ''));
        } elseif ($result['success'] ?? false) {
            $this->info((string) ($result['message'] ?? ''));
        } else {
            $this->error((string) ($result['message'] ?? ''));
        }

        $this->renderImportDetails($result);

        if (isset($result['siconfi_done'], $result['siconfi_total']) && ($options['by_uf'] ?? false)) {
            $this->line(__('UFs: :done/:total', [
                'done' => (string) $result['siconfi_done'],
                'total' => (string) $result['siconfi_total'],
            ]));
        } elseif (isset($result['pending'], $result['total']) && $nationalScope) {
            $done = max(0, (int) $result['total'] - (int) $result['pending']);
            $this->line(__('Cobertura: :done/:total municípios', [
                'done' => (string) $done,
                'total' => (string) $result['total'],
            ]));
        }

        if ($result['complete'] ?? false) {
            $this->info(__('Sincronização nacional concluída.'));
        } elseif (($result['partial'] ?? false) && $nationalScope) {
            $next = is_array($result['remaining_ufs'] ?? null) ? ($result['remaining_ufs'][0] ?? null) : null;
            $this->line($next !== null
                ? __('Retomar: php artisan horizonte:sync-siconfi --continue (próxima UF: :uf)', ['uf' => $next])
                : __('Retomar: php artisan horizonte:sync-siconfi --continue'));
        } elseif (($result['partial'] ?? false) && ! $nationalScope) {
            $this->line(__('Lote parcial — execute novamente para continuar a cobertura.'));
        }

        return ($result['success'] ?? false) || ($result['skipped'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function renderProgressHeader(int $year, int $period): void
    {
        $state = HorizonteSiconfiSyncProgress::get($year, $period);
        if ($state === null) {
            $this->line(__('Nenhum progresso anterior — fila nacional por UF (menor → maior nº municípios).'));

            return;
        }

        $status = (string) ($state['status'] ?? '');
        if ($status === 'running') {
            $this->line(__('Sincronização nacional em curso (desde :data).', [
                'data' => (string) ($state['started_at'] ?? '—'),
            ]));
        } elseif ($status === 'complete') {
            $this->line(__('Última sincronização nacional concluída em :data.', [
                'data' => (string) ($state['completed_at'] ?? '—'),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderImportDetails(array $result): void
    {
        $importedLines = is_array($result['imported_lines'] ?? null) ? $result['imported_lines'] : [];
        $failedLines = is_array($result['failed_lines'] ?? null) ? $result['failed_lines'] : [];

        if ($importedLines === [] && $failedLines === []) {
            return;
        }

        if ($importedLines !== []) {
            $this->newLine();
            $this->line(__('Importados (:n):', ['n' => (string) count($importedLines)]));
            foreach ($importedLines as $line) {
                $this->line($line);
            }
        }

        if ($failedLines !== []) {
            $this->newLine();
            $this->warn(__('Sem importação (:n):', ['n' => (string) count($failedLines)]));
            foreach ($failedLines as $line) {
                $this->line($line);
            }
        }
    }
}
