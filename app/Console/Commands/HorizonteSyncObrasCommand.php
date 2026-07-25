<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteMunicipalObrasSyncService;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Console\Command;

class HorizonteSyncObrasCommand extends Command
{
    protected $signature = 'horizonte:sync-obras
                            {--uf= : Restringir a uma UF (ex.: BA)}
                            {--situacao= : Filtrar por situação específica}
                            {--ibge= : IBGE específico (não implementado no MVP)}
                            {--limit-pages= : Limitar número de páginas por situação}
                            {--enrich-finance : Forçar enriquecimento financeiro}
                            {--no-enrich-finance : Desabilitar enriquecimento financeiro}
                            {--dry-run : Simular sem gravar}
                            {--reset : Reiniciar progresso nacional}
                            {--continue : Continuar ciclo nacional UF por UF}';

    protected $description = 'Importa obras de educação (FNDE/SIMEC) do Obrasgov por UF para o Horizonte';

    public function handle(HorizonteMunicipalObrasSyncService $sync): int
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

        $this->info(__('Horizonte — Obras de educação (Obrasgov FNDE/SIMEC)'));
        if ($ufRaw !== '') {
            $this->line(__('Âmbito: UF :uf', ['uf' => (string) HorizonteUfScope::normalize($ufRaw)]));
        }

        if ((bool) $this->option('dry-run')) {
            $this->warn(__('Dry-run — simulação sem gravação no banco de dados.'));
        }

        $options = [
            'uf' => $ufRaw !== '' ? HorizonteUfScope::normalize($ufRaw) : null,
            'reset' => (bool) $this->option('reset'),
            'continue' => (bool) $this->option('continue'),
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        if ($this->option('situacao') !== null) {
            $options['situacao'] = $this->option('situacao');
        }

        if ($this->option('limit-pages') !== null) {
            $options['limit_pages'] = (int) $this->option('limit-pages');
        }

        if ((bool) $this->option('enrich-finance')) {
            $options['enrich_finance'] = true;
        }

        if ((bool) $this->option('no-enrich-finance')) {
            $options['no_enrich_finance'] = true;
        }

        $result = $sync->syncBatch($options);

        if ($result['skipped'] ?? false) {
            $this->warn((string) ($result['message'] ?? ''));
        } else {
            $this->info((string) ($result['message'] ?? ''));
            if (($result['imported'] ?? 0) > 0) {
                $this->line(__(':n obra(s) importadas.', ['n' => (string) ($result['imported'] ?? 0)]));
            }
        }

        if ($result['partial'] ?? false) {
            $this->line(__('Lote parcial — execute novamente com --continue para prosseguir.'));
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
