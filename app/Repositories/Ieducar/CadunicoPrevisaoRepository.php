<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Repositories\CadunicoMunicipioSnapshotRepository;
use App\Repositories\InepCensoMunicipioMatriculaRepository;
use App\Services\Cadunico\CadunicoRedeGapAnalyzer;
use App\Services\CityDataConnection;
use App\Support\Analytics\CadunicoPrevisaoInformeBuilder;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Dashboard\PublicDataSourcesCatalog;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use App\Support\Ieducar\IeducarWorkActivityQueries;
use App\Support\Ieducar\MatriculaChartQueries;

final class CadunicoPrevisaoRepository
{
    public function __construct(
        private CityDataConnection $cityData,
        private EnrollmentRepository $enrollmentRepository,
        private CadunicoMunicipioSnapshotRepository $cadunicoSnapshots,
        private InepCensoMunicipioMatriculaRepository $censoMunicipio,
        private CadunicoRedeGapAnalyzer $gapAnalyzer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildReport(?City $city, IeducarFilterState $filters): array
    {
        $empty = [
            'available' => false,
            'city_name' => (string) ($city->name ?? ''),
            'year_label' => '',
            'intro' => '',
            'footnote' => __('Indicadores agregados CadÚnico (Cecad). Não utiliza CPF/NIS individuais. Valores FUNDEB são indicativos (matrículas × VAAF).'),
            'gap' => [],
            'kpis' => [],
            'metodologia' => [],
            'informe' => ['available' => false, 'blocos' => []],
            'alerts' => [],
            'public_data_sources' => $this->publicSourcesCatalog(),
            'error' => null,
        ];

        if ($city === null) {
            return array_merge($empty, ['error' => __('Selecione um município.')]);
        }

        if (! $filters->hasYearSelected() || $filters->isAllSchoolYears()) {
            return array_merge($empty, [
                'error' => __('Selecione um ano letivo específico para a previsão CadÚnico.'),
                'metodologia' => $this->metodologiaSteps(),
            ]);
        }

        if (! $city->hasDataSetup()) {
            return array_merge($empty, [
                'error' => __('Base i-Educar não configurada para este município.'),
                'metodologia' => $this->metodologiaSteps(),
            ]);
        }

        $year = (int) $filters->ano_letivo;
        $empty['year_label'] = (string) $year;

        try {
            $enrollmentData = $this->enrollmentRepository->sample($city, $filters);
            $matriculas = (int) $this->cityData->run(
                $city,
                static fn ($db) => MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters) ?? 0,
            );
            $alunos = (int) $this->cityData->run(
                $city,
                static fn ($db) => IeducarWorkActivityQueries::countAlunosAtivosForYear($db, $city, $filters),
            );

            $ieducarPorEtapa = $this->etapasFromEnrollment($enrollmentData, $matriculas);

            $cadRow = $this->cadunicoSnapshots->findForCityYear($city, $year);
            $censoRow = $this->censoMunicipio->findForCityYear($city, $year);
            $censoMat = $censoRow !== null ? (int) $censoRow->matriculas_total : null;

            $vaaf = (float) FundebMunicipalReferenceResolver::vaafParaCalculo($city, $filters)['vaaf'];

            $gap = $this->gapAnalyzer->analyze(
                $city,
                $filters,
                $matriculas,
                $alunos,
                $ieducarPorEtapa,
                $cadRow,
                $censoMat,
                $vaaf,
            );

            $report = array_merge($empty, [
                'available' => true,
                'city_name' => (string) $city->name,
                'intro' => __(
                    'Estimativa de crianças/jovens (4-17 anos) no CadÚnico do município que não aparecem como matrículas na rede municipal filtrada, com impacto FUNDEB indicativo por nível de ensino.'
                ),
                'gap' => $gap,
                'kpis' => $this->buildKpis($gap, $matriculas, $alunos),
                'metodologia' => $this->metodologiaSteps(),
                'alerts' => $this->buildAlerts($gap, $cadRow, $censoMat),
                'export_params' => [
                    'city_id' => $city->id,
                    'ano_letivo' => $filters->ano_letivo,
                    'escola_id' => $filters->escola_id,
                    'curso_id' => $filters->curso_id,
                    'turno_id' => $filters->turno_id,
                ],
            ]);

            $report['informe'] = CadunicoPrevisaoInformeBuilder::build($report);

            return $report;
        } catch (\Throwable $e) {
            return array_merge($empty, [
                'error' => $e->getMessage(),
                'metodologia' => $this->metodologiaSteps(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $enrollmentData
     * @return list<array{etapa: string, matriculas: int}>
     */
    private function etapasFromEnrollment(array $enrollmentData, int $matTotal): array
    {
        $out = [];
        foreach ($enrollmentData['charts'] ?? [] as $chart) {
            if (! is_array($chart)) {
                continue;
            }
            $t = mb_strtolower((string) ($chart['title'] ?? ''));
            if (! str_contains($t, 'nível de ensino') && ! str_contains($t, 'nivel de ensino')) {
                continue;
            }
            $labels = $chart['labels'] ?? [];
            $data = $chart['datasets'][0]['data'] ?? [];
            if (! is_array($labels) || ! is_array($data)) {
                continue;
            }
            foreach (array_values($labels) as $i => $label) {
                $mat = (int) ($data[$i] ?? 0);
                if ($mat > 0) {
                    $out[] = ['etapa' => (string) $label, 'matriculas' => $mat];
                }
            }
            break;
        }

        if ($out === [] && $matTotal > 0) {
            $out[] = ['etapa' => __('Rede municipal'), 'matriculas' => $matTotal];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $gap
     * @return list<array<string, mixed>>
     */
    private function buildKpis(array $gap, int $matriculas, int $alunos): array
    {
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $impacto = is_array($gap['impacto_financeiro'] ?? null) ? $gap['impacto_financeiro'] : [];

        return [
            [
                'label' => __('CadÚnico (4-17 anos)'),
                'value' => isset($gap['cadunico_total_escolar'])
                    ? number_format((int) $gap['cadunico_total_escolar'], 0, ',', '.')
                    : '—',
                'tone' => 'indigo',
            ],
            [
                'label' => __('Matrículas i-Educar'),
                'value' => number_format($matriculas, 0, ',', '.'),
                'tone' => 'teal',
            ],
            [
                'label' => __('Estimativa fora da rede'),
                'value' => $gap['gap_total_fmt'] ?? '—',
                'tone' => ($gap['status'] ?? '') === 'warning' ? 'amber' : 'slate',
                'explicacao_resumo' => $gap['nota'] ?? null,
            ],
            [
                'label' => __('Cobertura CadÚnico'),
                'value' => $gap['cobertura_label'] ?? '—',
                'tone' => ($gap['status'] ?? '') === 'success' ? 'emerald' : 'amber',
            ],
            [
                'label' => __('FUNDEB indicativo (lacuna)'),
                'value' => (string) ($impacto['gap_anual_label'] ?? '—'),
                'tone' => 'orange',
                'explicacao_resumo' => $impacto['formula'] ?? null,
            ],
        ];
    }

    /**
     * @return list<array{step: string, text: string}>
     */
    private function metodologiaSteps(): array
    {
        return [
            ['step' => '1', 'text' => __('Importar agregados municipais do Cecad (CSV) — sem dados pessoais.')],
            ['step' => '2', 'text' => __('Contar crianças/jovens 4-17 anos no CadÚnico no exercício de referência.')],
            ['step' => '3', 'text' => __('Comparar com matrículas ativas da rede municipal no i-Educar (mesmos filtros).')],
            ['step' => '4', 'text' => __('Distribuir lacuna por nível de ensino e aplicar VAAF municipal para impacto FUNDEB indicativo.')],
            ['step' => '5', 'text' => __('Validar com Censo INEP e busca ativa (nem toda criança CadÚnico pertence à rede municipal).')],
        ];
    }

    /**
     * @return list<array{tone: string, title: string, message: string}>
     */
    private function buildAlerts(array $gap, $cadRow, ?int $censoMat): array
    {
        $alerts = [];

        if (! ($gap['available'] ?? false)) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => __('CadÚnico não importado'),
                'message' => (string) ($gap['nota'] ?? __('Execute cadunico:import-cecad com exportação Cecad do município.')),
            ];

            return $alerts;
        }

        if (($gap['gap_total'] ?? 0) > 50) {
            $alerts[] = [
                'tone' => 'amber',
                'title' => __('Potencial de busca ativa'),
                'message' => __('Há :n crianças/jovens no CadÚnico acima das matrículas municipais registadas — investigar EJA, transferências e escolas não municipais.', [
                    'n' => $gap['gap_total_fmt'] ?? '0',
                ]),
            ];
        }

        if ($censoMat !== null && $censoMat > 0) {
            $alerts[] = [
                'tone' => 'sky',
                'title' => __('Referência Censo INEP'),
                'message' => __('O município tem :n matrículas no agregado Censo INEP (rede + outras esferas no território).', [
                    'n' => number_format($censoMat, 0, ',', '.'),
                ]),
            ];
        }

        if ($cadRow !== null && filled($cadRow->imported_at)) {
            $alerts[] = [
                'tone' => 'slate',
                'title' => __('Última importação CadÚnico'),
                'message' => __('Ano :ano · :data', [
                    'ano' => (string) $cadRow->ano_referencia,
                    'data' => $cadRow->imported_at->format('d/m/Y H:i'),
                ]),
            ];
        }

        return $alerts;
    }

    /**
     * @return array{intro: string, categories: list<array<string, mixed>>}
     */
    private function publicSourcesCatalog(): array
    {
        return PublicDataSourcesCatalog::build(null, 'cadastro');
    }
}
