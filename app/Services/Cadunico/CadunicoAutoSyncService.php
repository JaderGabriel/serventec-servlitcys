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

        $national = $this->remoteCsv->ensureNationalCsv($ano);
        $log[] = $national['message'];
        if (! $national['ok']) {
            $discover = $this->remoteCsv->tryDiscoverFromDadosGov($ano);
            $log[] = $discover['message'];
        }

        $storageImport = $this->import->importFromStorageForYear($ano);
        $log[] = $storageImport['message'];

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

        $imported = (int) ($storageImport['imported'] ?? 0);
        $success = $imported > 0 || $gapOk > 0;

        return [
            'success' => $success,
            'message' => $success
                ? __('CadÚnico: :n registo(s) nacional(is); :ok município(s) via API/CSV local.', [
                    'n' => (string) $imported,
                    'ok' => (string) $gapOk,
                ])
                : __('CadÚnico automático sem novos dados — configure IEDUCAR_CADUNICO_NACIONAL_CSV_URL ou API.'),
            'ano' => $ano,
            'imported_nacional' => $imported,
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
