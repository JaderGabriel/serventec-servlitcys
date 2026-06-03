<?php

namespace App\Services\Cadunico;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;

/**
 * Sincronização automática CadÚnico sem upload: download remoto → CSV nacional → API por lacunas.
 */
final class CadunicoAutoSyncService
{
    public function __construct(
        private CadunicoSagiMisocialClient $misocial,
        private CadunicoRemoteCsvFetcher $remoteCsv,
        private CadunicoOpenDataImportService $import,
    ) {}

    /**
     * @return list<int>
     */
    public static function yearsToSync(): array
    {
        $explicit = config('ieducar.cadunico.auto_sync.years', []);
        if (is_array($explicit) && $explicit !== []) {
            return array_values(array_unique(array_map('intval', $explicit)));
        }

        $y = CadunicoOpenDataImportService::suggestedImportYear();

        return [$y, $y - 1];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncYear(int $ano, bool $fillGapsPerCity = true): array
    {
        $log = [];

        $misocialImport = $this->misocial->importYear($ano);
        $log[] = $misocialImport['message'];
        $importedNacional = (int) ($misocialImport['imported'] ?? 0);

        if ($importedNacional === 0) {
            $national = $this->remoteCsv->ensureNationalCsv($ano);
            $log[] = $national['message'];
            if (! $national['ok'] && filter_var(config('ieducar.cadunico.auto_sync.dados_gov_search', true), FILTER_VALIDATE_BOOL)) {
                $discover = $this->remoteCsv->tryDiscoverFromDadosGov($ano);
                $log[] = $discover['message'];
            }

            $storageImport = $this->import->importFromStorageForYear($ano);
            $log[] = $storageImport['message'];
            $importedNacional = (int) ($storageImport['imported'] ?? 0);
        } else {
            $storageImport = ['imported' => 0, 'message' => __('CSV em storage ignorado — Misocial já importou o ano.')];
        }

        $gapOk = 0;
        $gapFail = 0;

        if ($fillGapsPerCity && filter_var(config('ieducar.cadunico.auto_sync.fill_api_gaps', true), FILTER_VALIDATE_BOOL)) {
            $cities = City::query()->forAnalytics()->get(['id', 'name', 'ibge_municipio']);
            foreach ($cities as $city) {
                $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
                if ($ibge === null) {
                    continue;
                }

                $has = CadunicoMunicipioSnapshot::query()
                    ->where('ibge_municipio', $ibge)
                    ->where('ano_referencia', $ano)
                    ->exists();

                if ($has) {
                    continue;
                }

                $this->remoteCsv->ensureMunicipalCsv($ibge, $ano);
                $result = $this->import->importForCity($city, $ano);
                $log[] = $city->name.': '.($result['message'] ?? '');
                if ($result['success'] ?? false) {
                    $gapOk++;
                } else {
                    $gapFail++;
                }
            }
        }

        $success = $importedNacional > 0 || $gapOk > 0;

        return [
            'success' => $success,
            'message' => $success
                ? __('CadÚnico: :n registo(s) nacional(is); :ok município(s) via fontes complementares.', [
                    'n' => (string) $importedNacional,
                    'ok' => (string) $gapOk,
                ])
                : __('CadÚnico automático sem novos dados — verifique conectividade SAGI/Misocial (MDS) ou fontes complementares.'),
            'ano' => $ano,
            'imported_nacional' => $importedNacional,
            'misocial_month' => $misocialImport['month'] ?? null,
            'gap_filled' => $gapOk,
            'gap_failed' => $gapFail,
            'log' => $log,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncAllConfiguredYears(): array
    {
        $years = self::yearsToSync();
        $results = [];
        $anySuccess = false;

        foreach ($years as $ano) {
            $r = $this->syncYear($ano);
            $results[$ano] = $r;
            if ($r['success'] ?? false) {
                $anySuccess = true;
            }
        }

        return [
            'success' => $anySuccess,
            'message' => $anySuccess
                ? __('Sincronização automática CadÚnico concluída para :anos.', ['anos' => implode(', ', $years)])
                : __('Nenhum ano sincronizado com sucesso.'),
            'years' => $years,
            'by_year' => $results,
        ];
    }
}
