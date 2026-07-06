<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteMunicipalTransparencySyncService;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Console\Command;

class HorizonteSyncTransparencyCommand extends Command
{
    protected $signature = 'horizonte:sync-transparency
                            {--uf= : Restringir a uma UF (ex.: BA)}
                            {--year= : Ano de referência (default: horizonte.reference_year)}
                            {--limit= : Municípios por lote}
                            {--ibge=* : IBGE(s) específicos}
                            {--dry-run : Simular sem gravar}';

    protected $description = 'Importa convênios MEC/FNDE e empenhos educacionais do Portal da Transparência para o Horizonte';

    public function handle(HorizonteMunicipalTransparencySyncService $sync): int
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
        $ibgeCodes = array_values(array_filter(array_map('strval', (array) $this->option('ibge'))));

        $this->info(__('Horizonte — Portal da Transparência (convênios / empenhos educação)'));
        if ($ufRaw !== '') {
            $this->line(__('Âmbito: UF :uf', ['uf' => (string) HorizonteUfScope::normalize($ufRaw)]));
        }

        if ((bool) $this->option('dry-run')) {
            $this->warn(__('Dry-run — requer PORTAL_TRANSPARENCIA_API_KEY para importação real.'));

            return self::SUCCESS;
        }

        $options = [
            'year' => $year,
            'uf' => $ufRaw !== '' ? HorizonteUfScope::normalize($ufRaw) : null,
        ];
        if ($ibgeCodes !== []) {
            $options['ibge_codes'] = $ibgeCodes;
        }
        if ($this->option('limit') !== null) {
            $options['municipios_per_step'] = (int) $this->option('limit');
        }

        $result = $sync->syncBatch($options);
        if ($result['skipped'] ?? false) {
            $this->warn((string) ($result['message'] ?? ''));
        } else {
            $this->info((string) ($result['message'] ?? ''));
        }

        if ($result['partial'] ?? false) {
            $this->line(__('Lote parcial — execute novamente para continuar a cobertura nacional.'));
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
