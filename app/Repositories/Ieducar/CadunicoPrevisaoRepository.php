<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Repositories\CadunicoMunicipioSnapshotRepository;
use App\Repositories\InepCensoMunicipioMatriculaRepository;
use App\Services\Cadunico\CadunicoDemandaOfertaSlice;
use App\Services\Cadunico\CadunicoEscolarizacaoDecisionCardBuilder;
use App\Services\Cadunico\CadunicoRedeGapAnalyzer;
use App\Services\Cadunico\CadunicoTerritorialPressureBuilder;
use App\Services\CityDataConnection;
use App\Support\Analytics\CadunicoPrevisaoInformeBuilder;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Dashboard\PublicDataSourcesCatalog;
use App\Support\Ieducar\CadunicoFaixaEtariaCounts;
use App\Support\Ieducar\CadunicoIeducarEjaMatriculaCount;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use App\Support\Ieducar\InclusionDashboardQueries;
use App\Support\Ieducar\MatriculaChartQueries;
final class CadunicoPrevisaoRepository
{
    public function __construct(
        private CityDataConnection $cityData,
        private EnrollmentRepository $enrollmentRepository,
        private CadunicoMunicipioSnapshotRepository $cadunicoSnapshots,
        private InepCensoMunicipioMatriculaRepository $censoMunicipio,
        private CadunicoRedeGapAnalyzer $gapAnalyzer,
        private CadunicoEscolarizacaoDecisionCardBuilder $escolarizacaoCard,
        private CadunicoTerritorialPressureBuilder $territorialBuilder,
        private SchoolUnitsRepository $schoolUnits,
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
            'footnote' => __('Indicadores agregados CadÚnico (Cecad). Não utiliza CPF/NIS individuais. Valores FUNDEB são indicativos (base × VAAF).'),
            'gap' => [],
            'kpis' => [],
            'territorial' => [],
            'demanda_oferta' => [],
            'metodologia' => [],
            'informe' => ['available' => false, 'blocos' => []],
            'escolarizacao_card' => [],
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
            $volume = $this->cityData->run(
                $city,
                static fn ($db) => MatriculaChartQueries::volumeCounts($db, $city, $filters),
            );
            $matriculas = $volume['matriculas'];
            $alunos = ($volume['alunos_available'] ?? false) ? (int) ($volume['alunos'] ?? 0) : 0;

            $inclusionHints = $this->inclusionHints($city, $filters);
            $ieducarPorEtapa = $this->etapasFromEnrollment($enrollmentData, $matriculas);
            $faixaCounts = $this->cityData->run(
                $city,
                static fn ($db) => CadunicoFaixaEtariaCounts::count($db, $city, $filters),
            );

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
                $inclusionHints,
                $faixaCounts,
                $censoRow,
            );

            $schoolMarkers = $this->schoolMarkersForMap($city, $filters);
            $territorial = $this->territorialBuilder->build($city, $filters, $gap, $schoolMarkers);
            $demandaOferta = CadunicoDemandaOfertaSlice::build($gap, $territorial);

            $ieducarEja = $this->cityData->run(
                $city,
                static fn ($db) => CadunicoIeducarEjaMatriculaCount::count($db, $city, $filters),
            );

            $escolarizacaoCard = $this->escolarizacaoCard->build($gap, $censoRow, $ieducarEja);

            $report = array_merge($empty, [
                'available' => true,
                'city_name' => (string) $city->name,
                'intro' => __(
                    'Estimativa de crianças/jovens em idade escolar no CadÚnico do município que não aparecem na rede municipal filtrada, com lacuna por faixa etária, cenários financeiros (NEE/AEE) e mapa territorial quando houver importação por bairro/setor.'
                ),
                'gap' => $gap,
                'escolarizacao_card' => $escolarizacaoCard,
                'territorial' => $territorial,
                'demanda_oferta' => $demandaOferta,
                'kpis' => $this->buildKpis($gap, $matriculas, $alunos, $territorial),
                'metodologia' => $this->metodologiaSteps(),
                'alerts' => $this->buildAlerts($gap, $cadRow, $censoMat, $territorial),
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
     * @return array{nee_matriculas: int, alunos_nee: int, matriculas_aee_sem_cadastro: int, alunos_aee_sem_cadastro: int}
     */
    private function inclusionHints(City $city, IeducarFilterState $filters): array
    {
        return $this->cityData->run($city, function ($db) use ($city, $filters): array {
            $neeMat = InclusionDashboardQueries::countMatriculasComNee($db, $city, $filters);
            $neeAlunos = InclusionDashboardQueries::countAlunosComNee($db, $city, $filters);
            $aeeMat = InclusionDashboardQueries::countMatriculasTurmaAeeSemCadastroNee($db, $city, $filters);
            $aeeAlunos = InclusionDashboardQueries::countAlunosTurmaAeeSemCadastroNee($db, $city, $filters);

            return [
                'nee_matriculas' => $neeMat,
                'alunos_nee' => $neeAlunos,
                'matriculas_aee_sem_cadastro' => $aeeMat,
                'alunos_aee_sem_cadastro' => $aeeAlunos,
            ];
        });
    }

    /**
     * @return list<array{lat: float, lng: float, label?: string}>
     */
    private function schoolMarkersForMap(City $city, IeducarFilterState $filters): array
    {
        if (! filter_var(config('ieducar.cadunico.territorio.load_school_markers', true), FILTER_VALIDATE_BOOL)) {
            return [];
        }

        try {
            $snap = $this->schoolUnits->snapshot($city, $filters);
            $markers = $snap['tab']['markers'] ?? [];

            return is_array($markers) ? $markers : [];
        } catch (\Throwable) {
            return [];
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
     * @param  array<string, mixed>  $territorial
     * @return list<array<string, mixed>>
     */
    private function buildKpis(array $gap, int $matriculas, int $alunos, array $territorial): array
    {
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $impacto = is_array($gap['impacto_financeiro'] ?? null) ? $gap['impacto_financeiro'] : [];
        $vuln = is_array($gap['vulnerabilidade'] ?? null) ? $gap['vulnerabilidade'] : [];
        $base = (int) ($gap['ieducar_base_calculo'] ?? $matriculas);

        $kpis = [
            [
                'label' => __('CadÚnico (4-17 anos)'),
                'value' => isset($gap['cadunico_total_escolar'])
                    ? number_format((int) $gap['cadunico_total_escolar'], 0, ',', '.')
                    : '—',
                'tone' => 'indigo',
            ],
            [
                'label' => __('Base rede (cálculo)'),
                'value' => number_format($base, 0, ',', '.')
                    .($alunos > 0 && $alunos < $matriculas
                        ? ' ('.__(':alu alunos', ['alu' => number_format($alunos, 0, ',', '.')]).')'
                        : ''),
                'tone' => 'blue',
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

        if (($vuln['pct_criancas_pbf_label'] ?? null) !== null) {
            $kpis[] = [
                'label' => __('Crianças PBF (est.)'),
                'value' => (string) $vuln['pct_criancas_pbf_label'],
                'tone' => 'rose',
                'explicacao_resumo' => __('Agregado Misocial/Cecad — vulnerabilidade familiar.'),
            ];
        }

        if (($territorial['territorios_count'] ?? 0) > 0) {
            $kpis[] = [
                'label' => __('Territórios mapeados'),
                'value' => (string) (int) $territorial['territorios_count'],
                'tone' => 'sky',
            ];
        }

        return $kpis;
    }

    /**
     * @return list<array{step: string, text: string}>
     */
    private function metodologiaSteps(): array
    {
        return [
            ['step' => '1', 'text' => __('Importar agregados municipais Cecad/Misocial e, opcionalmente, território (bairro/setor) via CSV.')],
            ['step' => '2', 'text' => __('Contar população CadÚnico nas faixas 4–17 (pré 4–5 a médio 15–17); creche 0–3 excluída da lacuna principal.')],
            ['step' => '3', 'text' => __('Comparar com alunos/matrículas ativos da rede municipal (i-Educar) nos mesmos filtros; por faixa, idade na data de corte 31/03 quando houver nascimento cadastrado.')],
            ['step' => '4', 'text' => __('Descontar matrículas fora da rede municipal (Censo INEP) e calcular lacuna por faixa; cenários NEE/AEE/VAAR (VAAT/IEI não entram nesta aba).')],
            ['step' => '5', 'text' => __('Priorizar territórios no mapa (pressão = lacuna × vulnerabilidade × distância à escola).')],
            ['step' => '6', 'text' => __('Validar com Censo INEP e busca ativa — nem toda criança CadÚnico pertence à rede municipal.')],
        ];
    }

    /**
     * @param  array<string, mixed>  $territorial
     * @return list<array{tone: string, title: string, message: string}>
     */
    private function buildAlerts(array $gap, $cadRow, ?int $censoMat, array $territorial): array
    {
        $alerts = [];

        if (! ($gap['available'] ?? false)) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => __('CadÚnico não importado'),
                'message' => (string) ($gap['nota'] ?? __('Execute cadunico:sync-city ou importe Cecad.')),
            ];

            return $alerts;
        }

        if (($gap['gap_total'] ?? 0) > 50) {
            $alerts[] = [
                'tone' => 'amber',
                'title' => __('Potencial de busca ativa'),
                'message' => __('Há :n crianças/jovens no CadÚnico acima da base municipal — investigar EJA, transferências e escolas não municipais.', [
                    'n' => $gap['gap_total_fmt'] ?? '0',
                ]),
            ];
        }

        if (($territorial['territorios_count'] ?? 0) === 0) {
            $alerts[] = [
                'tone' => 'sky',
                'title' => __('Mapa territorial'),
                'message' => (string) ($territorial['nota'] ?? ''),
            ];
        }

        $cenarios = is_array($gap['cenarios_financeiros'] ?? null) ? $gap['cenarios_financeiros'] : [];
        if (($cenarios['available'] ?? false) && ($cenarios['total_cenarios_label'] ?? null) !== null) {
            $alerts[] = [
                'tone' => 'violet',
                'title' => __('Cenários financeiros (lacuna)'),
                'message' => __('Soma indicativa dos cenários: :v — ver tabela na aba.', [
                    'v' => (string) $cenarios['total_cenarios_label'],
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
