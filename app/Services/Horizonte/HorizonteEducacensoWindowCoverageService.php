<?php

namespace App\Services\Horizonte;

use App\Models\InepCensoMunicipioMatricula;
use App\Support\Horizonte\HorizonteEducacensoYearWindow;
use Illuminate\Support\Facades\Schema;

/**
 * Audita se municípios têm todos os anos da janela Educacenso indexados.
 */
final class HorizonteEducacensoWindowCoverageService
{
    /**
     * @return array{
     *     ok: bool,
     *     window_years: list<int>,
     *     sample_size: int,
     *     complete_count: int,
     *     incomplete_count: int,
     *     complete_pct: float,
     *     national_years: array<int, int>,
     *     municipalities: list<array{
     *         ibge: string,
     *         complete: bool,
     *         missing_years: list<int>,
     *         years: array<int, ?int>
     *     }>
     * }
     */
    public function auditRandomMunicipalities(int $sampleSize = 50, ?int $seed = null): array
    {
        $windowYears = HorizonteEducacensoYearWindow::years();
        $sampleSize = max(1, min(500, $sampleSize));

        if (! Schema::hasTable('inep_censo_municipio_matriculas')) {
            return $this->emptyResult($windowYears, $sampleSize, __('Tabela inep_censo_municipio_matriculas indisponível.'));
        }

        $nationalYears = $this->nationalYearCounts($windowYears);
        $poolIbge = $this->candidateIbgeCodes($windowYears);

        if ($poolIbge === []) {
            return $this->emptyResult($windowYears, $sampleSize, __('Nenhum município com matrículas na janela.'));
        }

        $sampleSize = min($sampleSize, count($poolIbge));
        $selected = $this->pickRandom($poolIbge, $sampleSize, $seed);

        $rowsByIbge = InepCensoMunicipioMatricula::query()
            ->whereIn('ibge_municipio', $selected)
            ->whereIn('ano', $windowYears)
            ->where('matriculas_total', '>', 0)
            ->get(['ibge_municipio', 'ano', 'matriculas_total'])
            ->groupBy('ibge_municipio');

        $municipalities = [];
        $completeCount = 0;

        foreach ($selected as $ibge) {
            $byYear = [];
            foreach ($windowYears as $year) {
                $byYear[$year] = null;
            }

            foreach ($rowsByIbge->get($ibge, collect()) as $row) {
                $byYear[(int) $row->ano] = (int) $row->matriculas_total;
            }

            $missingYears = array_values(array_filter(
                $windowYears,
                static fn (int $year): bool => ($byYear[$year] ?? null) === null,
            ));

            $complete = $missingYears === [];
            if ($complete) {
                $completeCount++;
            }

            $municipalities[] = [
                'ibge' => $ibge,
                'complete' => $complete,
                'missing_years' => $missingYears,
                'years' => $byYear,
            ];
        }

        $incompleteCount = count($municipalities) - $completeCount;

        return [
            'ok' => $incompleteCount === 0 && $this->nationalWindowComplete($nationalYears, $windowYears),
            'window_years' => $windowYears,
            'sample_size' => $sampleSize,
            'complete_count' => $completeCount,
            'incomplete_count' => $incompleteCount,
            'complete_pct' => round(100 * $completeCount / max(1, $sampleSize), 1),
            'national_years' => $nationalYears,
            'municipalities' => $municipalities,
        ];
    }

    /**
     * @param  list<int>  $windowYears
     * @return array<int, int>
     */
    private function nationalYearCounts(array $windowYears): array
    {
        $counts = [];
        foreach ($windowYears as $year) {
            $counts[$year] = (int) InepCensoMunicipioMatricula::query()
                ->where('ano', $year)
                ->where('matriculas_total', '>', 0)
                ->distinct()
                ->count('ibge_municipio');
        }

        return $counts;
    }

    /**
     * @param  list<int>  $windowYears
     * @return list<string>
     */
    private function candidateIbgeCodes(array $windowYears): array
    {
        $anchor = max($windowYears);

        return InepCensoMunicipioMatricula::query()
            ->where('ano', $anchor)
            ->where('matriculas_total', '>', 0)
            ->distinct()
            ->orderBy('ibge_municipio')
            ->pluck('ibge_municipio')
            ->map(static fn ($v): string => (string) $v)
            ->all();
    }

    /**
     * @param  list<string>  $pool
     * @return list<string>
     */
    private function pickRandom(array $pool, int $count, ?int $seed): array
    {
        $items = array_values($pool);
        if ($seed !== null) {
            mt_srand($seed);
        }
        shuffle($items);
        if ($seed !== null) {
            mt_srand();
        }

        return array_slice($items, 0, $count);
    }

    /**
     * @param  array<int, int>  $nationalYears
     * @param  list<int>  $windowYears
     */
    private function nationalWindowComplete(array $nationalYears, array $windowYears): bool
    {
        $max = max(array_values($nationalYears) ?: [0]);
        if ($max === 0) {
            return false;
        }

        foreach ($windowYears as $year) {
            if (($nationalYears[$year] ?? 0) < (int) round($max * 0.95)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<int>  $windowYears
     * @return array<string, mixed>
     */
    private function emptyResult(array $windowYears, int $sampleSize, string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'window_years' => $windowYears,
            'sample_size' => $sampleSize,
            'complete_count' => 0,
            'incomplete_count' => 0,
            'complete_pct' => 0.0,
            'national_years' => [],
            'municipalities' => [],
        ];
    }
}
