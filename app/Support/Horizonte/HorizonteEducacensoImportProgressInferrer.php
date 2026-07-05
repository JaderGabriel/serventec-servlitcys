<?php

namespace App\Support\Horizonte;

use App\Models\InepCensoMunicipioMatricula;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Brazil\IbgeUfFromCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Reconstrói passos Educacenso concluídos a partir da cobertura em inep_censo_municipio_matriculas. */
final class HorizonteEducacensoImportProgressInferrer
{
    /**
     * @param  list<int>  $years
     * @return list<string>
     */
    public static function inferDoneSteps(array $years): array
    {
        if ($years === []
            || ! Schema::hasTable('inep_censo_municipio_matriculas')
            || ! filter_var(
                config('horizonte.fortnightly_feed.educacenso_infer_progress_from_db', true),
                FILTER_VALIDATE_BOOLEAN,
            )) {
            return [];
        }

        $counts = self::indexedCountsByStep($years);
        if ($counts === []) {
            return [];
        }

        $expected = self::expectedMunicipalitiesPerUf();
        $ratio = max(0.5, min(1.0, (float) config('horizonte.fortnightly_feed.educacenso_infer_min_coverage_ratio', 0.7)));
        $done = [];

        foreach (array_map('intval', $years) as $year) {
            foreach (HorizonteEducacensoImportProgress::allUfs() as $uf) {
                $key = HorizonteEducacensoImportProgress::stepKey($year, $uf);
                $indexed = (int) ($counts[$key] ?? 0);
                if ($indexed <= 0) {
                    continue;
                }

                $expectedCount = (int) ($expected[$uf] ?? 0);
                $threshold = $expectedCount > 0
                    ? max(1, (int) floor($expectedCount * $ratio))
                    : 1;

                if ($indexed >= $threshold) {
                    $done[] = $key;
                }
            }
        }

        sort($done, SORT_STRING);

        if ($done !== []) {
            Log::info('horizonte.educacenso_progress_inferred_from_db', [
                'steps' => count($done),
                'years' => $years,
            ]);
        }

        return $done;
    }

    /**
     * @param  list<int>  $years
     * @return array<string, int>
     */
    private static function indexedCountsByStep(array $years): array
    {
        $distinctByStep = [];

        $rows = InepCensoMunicipioMatricula::query()
            ->whereIn('ano', array_map('intval', $years))
            ->where('matriculas_total', '>', 0)
            ->get(['ibge_municipio', 'ano']);

        foreach ($rows as $row) {
            $uf = IbgeUfFromCode::ufFromIbge((string) $row->ibge_municipio);
            if ($uf === null) {
                continue;
            }
            $key = HorizonteEducacensoImportProgress::stepKey((int) $row->ano, $uf);
            $ibge = (string) $row->ibge_municipio;
            $distinctByStep[$key][$ibge] = true;
        }

        $counts = [];
        foreach ($distinctByStep as $key => $ibgeSet) {
            $counts[$key] = count($ibgeSet);
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private static function expectedMunicipalitiesPerUf(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $cached = [];
        try {
            $catalog = app(IbgeMunicipalityCatalog::class);
            foreach (HorizonteEducacensoImportProgress::allUfs() as $uf) {
                $cached[$uf] = count($catalog->municipalitiesForUf($uf));
            }
        } catch (\Throwable $e) {
            Log::debug('horizonte.educacenso_progress_expected_uf_counts_failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return $cached;
    }
}
