<?php

namespace App\Services\Horizonte;

use App\Models\PortalVendorSanctionSnapshot;
use App\Repositories\PortalVendorSanctionSnapshotRepository;
use App\Support\Funding\PortalTransparenciaApiClient;
use App\Support\Horizonte\HorizonteMapCacheBuster;
use App\Support\Horizonte\PortalProcurementConfig;
use Illuminate\Support\Facades\Log;

/**
 * HOR-08g — due diligence leve CEIS/CNEP/CEPIM nos CNPJs curados (software vendors).
 */
final class HorizontePortalSanctionsSyncService
{
    public function __construct(
        private PortalTransparenciaApiClient $portal,
        private PortalVendorSanctionSnapshotRepository $sanctions,
    ) {}

    /**
     * @param  array{
     *   dry_run?: bool,
     *   max_pages?: int|null,
     *   cnpjs?: array<string, string>|list<string>|null
     * }  $options
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   message: string,
     *   cnpjs_checked: int,
     *   records_fetched: int,
     *   upserted: int,
     *   sanctioned_cnpjs: int,
     *   by_fonte: array{ceis: int, cnep: int, cepim: int}
     * }
     */
    public function sync(array $options = []): array
    {
        $empty = [
            'success' => true,
            'message' => '',
            'cnpjs_checked' => 0,
            'records_fetched' => 0,
            'upserted' => 0,
            'sanctioned_cnpjs' => 0,
            'by_fonte' => ['ceis' => 0, 'cnep' => 0, 'cepim' => 0],
        ];

        if (! filter_var(config('horizonte.transparency.procurement.enabled', true), FILTER_VALIDATE_BOOL)) {
            return array_merge($empty, [
                'skipped' => true,
                'message' => __('Procurement desactivado (HORIZONTE_PROCUREMENT_ENABLED).'),
            ]);
        }

        $apiKey = trim((string) config('ieducar.other_funding.public_queries.portal_transparencia.api_key', ''));
        if ($apiKey === '') {
            return array_merge($empty, [
                'skipped' => true,
                'message' => __('Defina PORTAL_TRANSPARENCIA_API_KEY para consultar CEIS/CNEP/CEPIM.'),
            ]);
        }

        $vendors = $this->resolveVendors($options['cnpjs'] ?? null);
        if ($vendors === []) {
            return array_merge($empty, [
                'skipped' => true,
                'message' => __('Nenhum CNPJ curado (HORIZONTE_PROCUREMENT_SOFTWARE_VENDORS).'),
            ]);
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $timeout = max(15, (int) config('horizonte.transparency.procurement.http_timeout', 30));
        $maxPages = max(1, min(10, (int) ($options['max_pages'] ?? config('horizonte.transparency.procurement.sanctions_max_pages', 2))));

        $rows = [];
        $fetched = 0;
        $byFonte = ['ceis' => 0, 'cnep' => 0, 'cepim' => 0];
        $sanctioned = [];

        foreach ($vendors as $cnpj => $label) {
            foreach ([
                PortalVendorSanctionSnapshot::FONTE_CEIS => fn () => $this->portal->ceis($cnpj, $apiKey, $timeout, $maxPages),
                PortalVendorSanctionSnapshot::FONTE_CNEP => fn () => $this->portal->cnep($cnpj, $apiKey, $timeout, $maxPages),
                PortalVendorSanctionSnapshot::FONTE_CEPIM => fn () => $this->portal->cepim($cnpj, $apiKey, $timeout, $maxPages),
            ] as $fonte => $fetch) {
                $items = $fetch();
                $fetched += count($items);
                usleep(70_000);

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $mapped = $this->mapItem($fonte, $cnpj, $label, $item);
                    if ($mapped === null) {
                        continue;
                    }
                    $rows[] = $mapped;
                    $byFonte[$fonte]++;
                    $sanctioned[$cnpj] = true;
                }
            }
        }

        $upserted = 0;
        if (! $dryRun && $rows !== []) {
            $upserted = $this->sanctions->upsertBatch($rows);
            HorizonteMapCacheBuster::bust();
        } elseif ($dryRun) {
            $upserted = count($rows);
        }

        Log::info('horizonte.procurement_sanctions_synced', [
            'cnpjs' => count($vendors),
            'fetched' => $fetched,
            'upserted' => $upserted,
            'sanctioned' => count($sanctioned),
            'dry_run' => $dryRun,
        ]);

        return [
            'success' => true,
            'message' => $dryRun
                ? __('Dry-run sanções: :c CNPJ(s); :f registo(s); :s com sanção.', [
                    'c' => (string) count($vendors),
                    'f' => (string) $fetched,
                    's' => (string) count($sanctioned),
                ])
                : __('Sanções: :u linha(s) (:c CNPJ(s); :s com sanção CEIS/CNEP/CEPIM).', [
                    'u' => (string) $upserted,
                    'c' => (string) count($vendors),
                    's' => (string) count($sanctioned),
                ]),
            'cnpjs_checked' => count($vendors),
            'records_fetched' => $fetched,
            'upserted' => $upserted,
            'sanctioned_cnpjs' => count($sanctioned),
            'by_fonte' => $byFonte,
        ];
    }

    /**
     * @param  array<string, string>|list<string>|null  $override
     * @return array<string, string>
     */
    private function resolveVendors(array|null $override): array
    {
        if (is_array($override) && $override !== []) {
            $out = [];
            $isList = array_is_list($override);
            foreach ($override as $key => $value) {
                if ($isList) {
                    $cnpj = preg_replace('/\D/', '', (string) $value) ?: '';
                    $label = $cnpj;
                } else {
                    $cnpj = preg_replace('/\D/', '', (string) $key) ?: '';
                    $label = trim((string) $value);
                }
                if (strlen($cnpj) === 14) {
                    $out[$cnpj] = $label !== '' ? $label : $cnpj;
                }
            }

            return $out;
        }

        return PortalProcurementConfig::softwareVendors();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function mapItem(string $fonte, string $cnpj, string $label, array $item): ?array
    {
        $id = $item['id'] ?? null;
        if ($id === null || $id === '') {
            return null;
        }

        $nome = null;
        $categoria = null;
        $orgao = null;
        $dataInicio = null;
        $dataFim = null;

        if ($fonte === PortalVendorSanctionSnapshot::FONTE_CEPIM) {
            $pessoa = is_array($item['pessoaJuridica'] ?? null) ? $item['pessoaJuridica'] : [];
            $nome = trim((string) ($pessoa['nome'] ?? $pessoa['razaoSocialReceita'] ?? $pessoa['nomeFantasiaReceita'] ?? ''));
            $categoria = trim((string) ($item['motivo'] ?? ''));
            $orgaoObj = is_array($item['orgaoSuperior'] ?? null) ? $item['orgaoSuperior'] : [];
            $orgao = trim((string) ($orgaoObj['nome'] ?? $orgaoObj['nomeOrgao'] ?? ''));
        } else {
            $sancionado = is_array($item['sancionado'] ?? null) ? $item['sancionado'] : [];
            $pessoa = is_array($item['pessoa'] ?? null) ? $item['pessoa'] : [];
            $nome = trim((string) ($sancionado['nome'] ?? $pessoa['nome'] ?? $pessoa['razaoSocialReceita'] ?? ''));
            $tipo = is_array($item['tipoSancao'] ?? null) ? $item['tipoSancao'] : [];
            $categoria = trim((string) ($tipo['descricaoResumida'] ?? $tipo['descricaoPortal'] ?? ''));
            $orgaoObj = is_array($item['orgaoSancionador'] ?? null) ? $item['orgaoSancionador'] : [];
            $orgao = trim((string) ($orgaoObj['nome'] ?? ''));
            $dataInicio = isset($item['dataInicioSancao']) ? (string) $item['dataInicioSancao'] : null;
            $dataFim = isset($item['dataFimSancao']) ? (string) $item['dataFimSancao'] : null;
        }

        return [
            'fonte' => $fonte,
            'cnpj' => $cnpj,
            'external_id' => (string) $id,
            'nome' => $nome !== '' ? $nome : null,
            'categoria' => $categoria !== '' ? $categoria : null,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'orgao' => $orgao !== '' ? $orgao : null,
            'vendor_label' => $label !== '' ? $label : null,
            'payload' => $item,
            'fonte_api' => 'portal_transparencia',
        ];
    }
}
