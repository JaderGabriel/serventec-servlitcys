<?php

namespace App\Services\Horizonte;

use App\Models\City;
use App\Models\InepCensoMunicipioMatricula;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Dashboard\ChartPayload;
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

        $rows = InepCensoMunicipioMatricula::query()
            ->where('ibge_municipio', $ibge)
            ->orderByDesc('ano')
            ->limit($limit)
            ->get()
            ->sortBy('ano')
            ->values();

        if ($rows->isEmpty()) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => __('Sem matrículas Censo indexadas para este município.'),
            ];
        }

        $labels = $rows->map(static fn (InepCensoMunicipioMatricula $row): string => (string) $row->ano)->all();

        $seriesDefs = [
            ['key' => 'total', 'label' => __('Total'), 'column' => 'matriculas_total'],
            ['key' => 'regular', 'label' => __('Regular'), 'column' => 'matriculas_regular'],
            ['key' => 'eja', 'label' => __('EJA'), 'column' => 'matriculas_eja'],
            ['key' => 'especial', 'label' => __('Educação especial'), 'column' => 'matriculas_especial'],
            ['key' => 'complementar', 'label' => __('Complementar / integral'), 'column' => 'matriculas_complementar'],
        ];

        $series = [];
        $hasSegments = false;

        foreach ($seriesDefs as $def) {
            $values = [];
            $hasAny = false;
            foreach ($rows as $row) {
                $val = (int) ($row->{$def['column']} ?? 0);
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
        );

        $footnote = $hasSegments
            ? __('Fonte: microdados Censo INEP (Educacenso), agregado por município. Segmentos dependem das colunas disponíveis na importação.')
            : __('Fonte: Censo INEP (total municipal). Reimporte o Censo para ver EJA, educação especial, regular e complementar.');

        return [
            'ok' => true,
            'ibge' => $ibge,
            'fonte' => 'censo_inep',
            'has_segments' => $hasSegments,
            'footnote' => $footnote,
            'chart' => $chart,
        ];
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
