<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizontePortalProcurementSyncService;
use App\Services\Horizonte\HorizontePortalSanctionsSyncService;
use App\Support\Horizonte\PortalProcurementConfig;
use Illuminate\Console\Command;

class HorizonteSyncProcurementCommand extends Command
{
    protected $signature = 'horizonte:sync-procurement
                            {--year= : Ano de referência (default: horizonte.reference_year)}
                            {--orgao= : Código SIAFI ou sigla (FNDE, MEC)}
                            {--tipos= : contratos,licitacoes (default: ambos)}
                            {--max-pages= : Páginas por órgão/mês}
                            {--licitacoes-months= : Meses a varrer em licitações (1–12)}
                            {--skip-orgaos : Só enrich por CNPJs curados (HOR-08f)}
                            {--skip-vendors : Não consultar /contratos/cpf-cnpj}
                            {--with-sanctions : Após vendors, consultar CEIS/CNEP/CEPIM (HOR-08g)}
                            {--dry-run : Simular sem gravar}';

    protected $description = 'Importa contratos e licitações MEC/FNDE (Portal da Transparência) para o Horizonte';

    public function handle(
        HorizontePortalProcurementSyncService $sync,
        HorizontePortalSanctionsSyncService $sanctions,
    ): int
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $year = (int) ($this->option('year') ?: config('horizonte.reference_year', (int) date('Y') - 1));
        $orgao = trim((string) $this->option('orgao'));
        $tipos = trim((string) $this->option('tipos'));
        $dryRun = (bool) $this->option('dry-run');
        $skipOrgaos = (bool) $this->option('skip-orgaos');
        $skipVendors = (bool) $this->option('skip-vendors');

        $this->info(__('Horizonte — Procurement Portal (contratos / licitações MEC·FNDE)'));
        $this->line(__('Ano: :ano', ['ano' => $year]));

        $orgaos = PortalProcurementConfig::orgaosSiafi();
        $vendors = PortalProcurementConfig::softwareVendors();
        if ($orgaos === [] && $vendors === []) {
            $this->warn(__('Configure órgãos SIAFI (HORIZONTE_PROCUREMENT_ORG_*) e/ou SOFTWARE_VENDORS.'));

            return self::SUCCESS;
        }

        if (! $skipOrgaos && $orgaos !== []) {
            $this->line(__('Órgãos configurados: :list', [
                'list' => implode(', ', array_map(
                    static fn (array $o): string => ($o['sigla'] !== '' ? $o['sigla'].'/' : '').$o['codigo'],
                    $orgaos,
                )),
            ]));
        }
        if (! $skipVendors) {
            $this->line(__('CNPJs curados: :n', ['n' => (string) count($vendors)]));
        }

        $options = [
            'year' => $year,
            'dry_run' => $dryRun,
            'skip_orgaos' => $skipOrgaos,
            'skip_vendors' => $skipVendors,
        ];
        if ($orgao !== '') {
            $options['orgao'] = $orgao;
        }
        if ($tipos !== '') {
            $options['tipos'] = $tipos;
        }
        if ($this->option('max-pages') !== null) {
            $options['max_pages'] = (int) $this->option('max-pages');
        }
        if ($this->option('licitacoes-months') !== null) {
            $options['licitacoes_max_months'] = (int) $this->option('licitacoes-months');
        }

        $result = $sync->sync($options);

        if ($result['skipped'] ?? false) {
            $this->warn((string) ($result['message'] ?? ''));

            return self::SUCCESS;
        }

        if (! ($result['success'] ?? false)) {
            $this->error((string) ($result['message'] ?? __('Falha no sync de procurement.')));

            return self::FAILURE;
        }

        $table = [];
        foreach ($result['by_orgao'] as $row) {
            $table[] = [
                $row['sigla'] ?: '—',
                $row['codigo'],
                (int) ($row['contratos'] ?? 0),
                (int) ($row['licitacoes'] ?? 0),
                (int) ($row['upserted'] ?? 0),
                (int) ($row['vendor_matched'] ?? 0),
            ];
        }

        if ($table !== []) {
            $this->table(
                [__('Sigla'), __('SIAFI'), __('Contratos'), __('Licitações'), __('Upsert'), __('Vendor')],
                $table,
            );
        }

        if (($result['vendor_cnpj_fetched'] ?? 0) > 0 || ($result['itens_software'] ?? 0) > 0) {
            $this->line(__('Enrich CNPJ: :n contrato(s) · itens software: :sw', [
                'n' => (string) ($result['vendor_cnpj_fetched'] ?? 0),
                'sw' => (string) ($result['itens_software'] ?? 0),
            ]));
        }

        if ($dryRun) {
            $this->comment((string) $result['message']);
            $this->comment(__('Dry-run: nenhuma alteração. Remova --dry-run para gravar.'));
        } else {
            $this->info((string) $result['message']);
        }

        if ($vendors === [] && ! $skipVendors) {
            $this->comment(__('HOR-08f: defina HORIZONTE_PROCUREMENT_SOFTWARE_VENDORS para cruzar incumbentes.'));
        }

        if ((bool) $this->option('with-sanctions')) {
            $this->newLine();
            $this->info(__('HOR-08g — due diligence CEIS/CNEP/CEPIM'));
            $sanctionResult = $sanctions->sync(['dry_run' => $dryRun]);
            if ($sanctionResult['skipped'] ?? false) {
                $this->warn((string) ($sanctionResult['message'] ?? ''));
            } elseif ($dryRun) {
                $this->comment((string) ($sanctionResult['message'] ?? ''));
            } else {
                $this->info((string) ($sanctionResult['message'] ?? ''));
            }
        }

        return self::SUCCESS;
    }
}
