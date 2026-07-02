<?php

namespace App\Services\Horizonte;

use App\Models\City;
use App\Models\InepCensoMunicipioMatricula;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Dashboard\ChartPayload;
use App\Support\Horizonte\HorizonteEducacensoYearWindow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Série histórica de matrículas (Censo INEP) para o modal Horizonte — só municípios sem Consultoria activa.
 */
final class HorizonteMunicipioEnrollmentSeriesService
{
    /**
     * @return array{
     *     ok: bool,
     *     status?: int,
     *     message?: string,
     *     ibge?: string,
     *     fonte?: string,
     *     has_segments?: bool,
     *     footnote?: string,
     *     stage_counters?: array{ano: int, items: list<array{key: string, label: string, value: int|null}>}|null,
     *     chart?: array<string, mixed>
     * }
     */
    public function forIbge(string $ibgeRaw, ?int $years = null): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($ibgeRaw);
        if ($ibge === null) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => __('IBGE inválido.'),
            ];
        }

        if ($this->isConsultoriaActive($ibge)) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => __('Série disponível apenas para municípios sem Consultoria activa.'),
            ];
        }

        if (! Schema::hasTable('inep_censo_municipio_matriculas')) {
            return [
                'ok' => false,
                'status' => 503,
                'message' => __('Dados do Censo ainda não indexados.'),
            ];
        }

        $limit = max(2, min(10, $years ?? (int) config('horizonte.enrollment_series.years', 5)));
        $targetYears = HorizonteEducacensoYearWindow::years($limit);

        $rowsByYear = InepCensoMunicipioMatricula::query()
            ->where('ibge_municipio', $ibge)
            ->whereIn('ano', $targetYears)
            ->get()
            ->keyBy(static fn (InepCensoMunicipioMatricula $row): int => (int) $row->ano);

        if (! $this->municipalityHasAnyEnrollmentInWindow($rowsByYear, $targetYears)) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => __('Sem matrículas Censo indexadas para este município.'),
            ];
        }

        $labels = array_map(static fn (int $year): string => (string) $year, $targetYears);

        $seriesDefs = [
            ['key' => 'total', 'label' => __('Total'), 'column' => 'matriculas_total'],
            ['key' => 'regular', 'label' => __('Regular'), 'column' => 'matriculas_regular'],
            ['key' => 'eja', 'label' => __('EJA'), 'column' => 'matriculas_eja'],
            ['key' => 'especial', 'label' => __('Educação especial'), 'column' => 'matriculas_especial'],
            ['key' => 'complementar', 'label' => __('Complementar / integral'), 'column' => 'matriculas_complementar'],
        ];

        $series = [];
        $hasSegments = false;
        $missingYears = [];

        foreach ($seriesDefs as $def) {
            $values = [];
            $hasAny = false;
            foreach ($targetYears as $year) {
                /** @var InepCensoMunicipioMatricula|null $row */
                $row = $rowsByYear->get($year);
                if ($row === null && $def['key'] === 'total') {
                    $missingYears[$year] = true;
                }
                $val = $row !== null ? (int) ($row->{$def['column']} ?? 0) : 0;
                if ($def['key'] !== 'total' && $val > 0) {
                    $hasSegments = true;
                }
                if ($val > 0) {
                    $hasAny = true;
                }
                $values[] = $val > 0 ? $val : null;
            }
            if ($def['key'] === 'total' || $hasAny) {
                $series[] = [
                    'label' => $def['label'],
                    'data' => $values,
                ];
            }
        }

        $chart = ChartPayload::lineMulti(
            __('Matrículas — Censo INEP'),
            $labels,
            $series,
            [
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
            preserveNull: true,
        );

        $footnote = $this->buildFootnote($hasSegments, $missingYears, $targetYears);
        $stageCounters = $this->buildStageCounters($rowsByYear, $targetYears);

        return [
            'ok' => true,
            'ibge' => $ibge,
            'fonte' => 'censo_inep',
            'has_segments' => $hasSegments,
            'footnote' => $footnote,
            'stage_counters' => $stageCounters,
            'chart' => $chart,
        ];
    }

    /**
     * @param  Collection<int, InepCensoMunicipioMatricula>  $rowsByYear
     * @param  list<int>  $targetYears
     */
    private function municipalityHasAnyEnrollmentInWindow(Collection $rowsByYear, array $targetYears): bool
    {
        foreach ($targetYears as $year) {
            $row = $rowsByYear->get($year);
            if ($row !== null && (int) $row->matriculas_total > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, true>  $missingYears
     * @param  list<int>  $targetYears
     */
    private function buildFootnote(bool $hasSegments, array $missingYears, array $targetYears): string
    {
        $parts = [];

        if ($hasSegments) {
            $parts[] = __('Fonte: microdados Censo INEP (Educacenso), agregado por município.');
        } else {
            $parts[] = __('Fonte: Censo INEP (total municipal). Reimporte o Censo para ver EJA, educação especial, regular e complementar.');
        }

        if ($missingYears !== []) {
            ksort($missingYears);
            $missingList = implode(', ', array_map(static fn (int $year): string => (string) $year, array_keys($missingYears)));
            $parts[] = __('Anos sem dados indexados para este município: :anos (reimporte com horizonte:fortnightly-feed --phase=educacenso).', [
                'anos' => $missingList,
            ]);
        }

        return implode(' ', $parts);
    }

    /**
     * @param  Collection<int, InepCensoMunicipioMatricula>  $rowsByYear
     * @param  list<int>  $targetYears
     * @return array{ano: int, items: list<array{key: string, label: string, value: int|null}>}|null
     */
    private function buildStageCounters(Collection $rowsByYear, array $targetYears): ?array
    {
        $latestRow = null;
        foreach (array_reverse($targetYears) as $year) {
            /** @var InepCensoMunicipioMatricula|null $row */
            $row = $rowsByYear->get($year);
            if ($row !== null && $this->rowHasStageCounters($row)) {
                $latestRow = $row;
                break;
            }
        }

        if ($latestRow === null) {
            return null;
        }

        $defs = [
            ['key' => 'infantil', 'label' => __('Educação infantil'), 'column' => 'matriculas_infantil'],
            ['key' => 'fundamental_1', 'label' => __('Fundamental I'), 'column' => 'matriculas_fundamental_1'],
            ['key' => 'fundamental_2', 'label' => __('Fundamental II'), 'column' => 'matriculas_fundamental_2'],
            ['key' => 'medio', 'label' => __('Ensino médio'), 'column' => 'matriculas_medio'],
            ['key' => 'profissional', 'label' => __('Educação profissional'), 'column' => 'matriculas_profissional'],
        ];

        $items = [];
        foreach ($defs as $def) {
            $value = (int) ($latestRow->{$def['column']} ?? 0);
            $items[] = [
                'key' => $def['key'],
                'label' => $def['label'],
                'value' => $value > 0 ? $value : null,
            ];
        }

        return [
            'ano' => (int) $latestRow->ano,
            'items' => $items,
        ];
    }

    private function rowHasStageCounters(InepCensoMunicipioMatricula $row): bool
    {
        foreach ([
            'matriculas_infantil',
            'matriculas_fundamental_1',
            'matriculas_fundamental_2',
            'matriculas_medio',
            'matriculas_profissional',
        ] as $column) {
            if ((int) ($row->{$column} ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function isConsultoriaActive(string $ibge): bool
    {
        /** @var City|null $city */
        $city = City::query()
            ->where('is_active', true)
            ->whereNotNull('ibge_municipio')
            ->get(['id', 'ibge_municipio'])
            ->first(static function (City $candidate) use ($ibge): bool {
                return FundebMunicipioReferenceRepository::normalizeIbge((string) $candidate->ibge_municipio) === $ibge;
            });

        return $city !== null && $city->hasDataSetup();
    }
}
