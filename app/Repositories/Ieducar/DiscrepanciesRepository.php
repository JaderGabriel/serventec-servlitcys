<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Dashboard\PublicDataSourcesCatalog;
use App\Support\Ieducar\ConsultoriaOperationalSignals;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;
use App\Support\Ieducar\DiscrepanciesCheckRunner;
use App\Support\Ieducar\DiscrepanciesCsvRowsBuilder;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\DiscrepanciesQueries;
use App\Support\Ieducar\DiscrepanciesRoutineStatus;
use App\Support\Ieducar\InclusionDashboardQueries;
use App\Support\Ieducar\InclusionRecursoProvaQueries;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\Connection;

/**
 * Aba «Discrepâncias e Erros»: inconsistências de cadastro com impacto em Censo, VAAR/FUNDEB e repasses.
 */
class DiscrepanciesRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?City $city, IeducarFilterState $filters): array
    {
        $empty = [
            'intro' => '',
            'footnote' => '',
            'funding_aviso' => '',
            'year_label' => '',
            'city_name' => '',
            'total_matriculas' => null,
            'funding_reference' => null,
            'summary' => [
                'com_problema' => 0,
                'corrigiveis' => 0,
                'escolas_afetadas' => 0,
                'perda_estimada_anual' => 0.0,
                'ganho_potencial_anual' => 0.0,
            ],
            'chart_resumo' => null,
            'chart_financeiro' => null,
            'funding_pillars' => [],
            'active_check_ids' => [],
            'dimensions' => [],
            'checks' => [],
            'notes' => [],
            'public_data_sources' => PublicDataSourcesCatalog::build($city, 'financeiro'),
            'export_params' => [],
            'error' => null,
        ];

        if ($city === null) {
            return $empty;
        }

        try {
            return $this->cityData->run($city, function (Connection $db) use ($city, $filters) {
                $totalMat = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters) ?? 0;
                $checks = [];
                $dimensions = [];
                $notes = [];
                $catalog = DiscrepanciesCheckCatalog::definitions();
                $queryMap = DiscrepanciesCheckRunner::queryMap();

                foreach ($catalog as $id => $meta) {
                    if ($id === 'nee_subnotificacao') {
                        continue;
                    }
                    $spec = $queryMap[$id] ?? null;
                    if ($spec === null) {
                        $dimensions[] = $this->buildDimension($meta, [
                            'availability' => 'unavailable',
                            'has_issue' => false,
                            'rows' => [],
                            'unavailable_reason' => __('Rotina não implementada.'),
                        ], $totalMat, $city, $filters);

                        continue;
                    }

                    $eval = DiscrepanciesCheckRunner::evaluate(
                        $db,
                        $city,
                        $filters,
                        $spec['fn'],
                        $spec['probe'],
                        isset($spec['hint']) ? (string) $spec['hint'] : null,
                    );
                    $dimensions[] = $this->buildDimension($meta, $eval, $totalMat, $city, $filters);

                    if ($eval['has_issue']) {
                        $rows = $eval['rows'];
                        if ($id === 'recurso_prova_sem_nee' && $rows !== []) {
                            $rows = InclusionRecursoProvaQueries::enriquecerLinhasEscolaComTiposRecurso(
                                $db,
                                $city,
                                $filters,
                                $rows,
                            );
                        }
                        $checks[] = $this->buildCheck($meta, $rows, $totalMat, $city, $filters);
                    }
                }

                $neeRow = DiscrepanciesQueries::neeSubnotificacaoEstimativaPorRede($db, $city, $filters, $totalMat);
                if ($neeRow !== null && isset($catalog['nee_subnotificacao'])) {
                    $meta = $catalog['nee_subnotificacao'];
                    $m = is_array($neeRow['meta'] ?? null) ? $neeRow['meta'] : [];
                    $meta['explanation'] = __(
                        'A rede tem :nee matrícula(s) NEE (:pct% do total), abaixo do patamar de referência de :bench% (configurável). Estimativa de :gap registo(s) possivelmente omitidos — indicador de subnotificação no Censo e no VAAR de inclusão.',
                        [
                            'nee' => number_format((int) ($m['nee_matriculas'] ?? 0)),
                            'pct' => number_format((float) ($m['pct_atual'] ?? 0), 1, ',', '.'),
                            'bench' => number_format((float) ($m['benchmark_pct'] ?? 0), 1, ',', '.'),
                            'gap' => number_format((int) ($neeRow['total'] ?? 0)),
                        ]
                    );
                    $neeEval = [
                        'availability' => 'available',
                        'has_issue' => true,
                        'rows' => [$neeRow],
                        'unavailable_reason' => null,
                    ];
                    $dimensions[] = $this->buildDimension($meta, $neeEval, $totalMat, $city, $filters);
                    $checks[] = $this->buildCheck($meta, [$neeRow], $totalMat, $city, $filters);
                }

                $networkKpis = null;
                try {
                    $networkKpis = MatriculaChartQueries::redeVagasResumoKpis($db, $city, $filters);
                } catch (\Throwable) {
                    $networkKpis = null;
                }

                $dimensions = ConsultoriaOperationalSignals::append($dimensions, $networkKpis, $totalMat);

                $checks = $this->sortChecksForConsultoria($checks);
                $checks = ConsultoriaOperationalSignals::enrichChecksFromDimensions($dimensions, $checks);
                $checks = $this->sortChecksForConsultoria($checks);

                $aee = InclusionDashboardQueries::buildAeeCrossEnrollment($db, $city, $filters);
                if (is_array($aee) && (int) ($aee['nee_matriculas_total'] ?? 0) > 0) {
                    $neeTotal = (int) $aee['nee_matriculas_total'];
                    $emAee = (int) ($aee['matriculas_em_turmas_aee'] ?? 0);
                    $semAee = max(0, $neeTotal - $emAee);
                    if ($semAee > 0 && ! $this->hasCheckId($checks, 'nee_sem_aee')) {
                        $notes[] = __('Cruzamento AEE (rede): :n matrícula(s) NEE sem turma AEE identificada.', ['n' => number_format($semAee)]);
                    }
                }

                $summary = $dimensions !== []
                    ? $this->buildSummaryFromDimensions($dimensions)
                    : $this->buildSummary($checks);
                if ($checks !== [] && $dimensions !== []) {
                    $fromChecks = $this->buildSummary($checks);
                    $summary['escolas_afetadas'] = max(
                        (int) ($summary['escolas_afetadas'] ?? 0),
                        (int) ($fromChecks['escolas_afetadas'] ?? 0)
                    );
                    $summary['corrigiveis'] = max(
                        (int) ($summary['corrigiveis'] ?? 0),
                        (int) ($fromChecks['corrigiveis'] ?? 0)
                    );
                }
                $fundingRefPayload = DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);
                $tiposComProblema = count(array_filter($dimensions, static fn (array $d): bool => (bool) ($d['has_issue'] ?? false)));

                return [
                    'intro' => __(
                        'Rotinas automáticas sobre a base i-Educar (ano, escola, curso e turno). Cada bloco explica o problema, o impacto em Censo/VAAR/FUNDEB, localiza por escola e compara situação atual com o cenário após correção. Valores financeiros são estimativas indicativas (VAAF de referência × peso por tipo).'
                    ),
                    'footnote' => __(
                        'Correções assumem ajuste no i-Educar antes da exportação ao Censo. VAAR/FUNDEB oficiais: Simec/MEC. Heurística AEE: IEDUCAR_INCLUSION_AEE_KEYWORDS. VAAF referência: IEDUCAR_DISC_VAA_REFERENCIA.'
                    ),
                    'funding_aviso' => (string) config('ieducar.discrepancies.aviso_financeiro', ''),
                    'year_label' => $this->yearLabel($filters),
                    'city_name' => $city->name,
                    'total_matriculas' => $totalMat > 0 ? $totalMat : null,
                    'funding_reference' => $fundingRefPayload,
                    'funding_metodologia' => DiscrepanciesFundingImpact::metodologiaResumo($city, $filters),
                    'funding_resumo_explicacao' => DiscrepanciesFundingImpact::explicacaoResumoAgregado(
                        (int) ($summary['com_problema'] ?? 0),
                        (float) ($summary['perda_estimada_anual'] ?? 0),
                        (float) ($summary['ganho_potencial_anual'] ?? 0),
                        $tiposComProblema,
                    ),
                    'summary' => $summary,
                    'chart_resumo' => $this->chartResumoRede($checks),
                    'chart_financeiro' => $this->chartFinanceiro($checks),
                    'funding_pillars' => DiscrepanciesFundingImpact::pillarsWithMunicipioSummary(
                        DiscrepanciesFundingImpact::fundingPillars(),
                        $dimensions,
                        $city->name,
                        $this->yearLabel($filters),
                    ),
                    'active_check_ids' => array_values(array_map(static fn (array $c): string => (string) ($c['id'] ?? ''), $checks)),
                    'dimensions' => $dimensions,
                    'checks' => $checks,
                    'notes' => $notes,
                    'public_data_sources' => PublicDataSourcesCatalog::build($city, 'financeiro'),
                    'export_params' => $filters->toQueryParamsWithCity((int) $city->id),
                    'error' => null,
                ];
            });
        } catch (\Throwable $e) {
            return array_merge($empty, [
                'city_name' => $city->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{availability: string, has_issue: bool, rows: list<array<string, mixed>>, unavailable_reason: ?string}  $eval
     * @return array<string, mixed>
     */
    private function buildDimension(array $meta, array $eval, int $totalMat, ?City $city = null, ?IeducarFilterState $filters = null): array
    {
        $id = (string) ($meta['id'] ?? '');
        $hasIssue = (bool) ($eval['has_issue'] ?? false);
        $total = $hasIssue ? array_sum(array_column($eval['rows'], 'total')) : 0;
        $severity = (string) ($meta['severity'] ?? 'warning');

        $resolved = ($city !== null && $filters !== null)
            ? DiscrepanciesRoutineStatus::resolve($id, $eval, $totalMat, $city, $filters, $severity)
            : self::legacyResolveStatus($eval, $severity);

        $status = (string) $resolved['status'];
        $availability = (string) $resolved['availability'];
        $analyzed = $status === DiscrepanciesRoutineStatus::OK
            || $status === 'warning'
            || $status === 'danger';

        $pct = $totalMat > 0 && $hasIssue ? round(100.0 * $total / $totalMat, 1) : null;
        $funding = $hasIssue ? DiscrepanciesFundingImpact::estimate($id, $total, $city, $filters) : null;
        $ganho = (float) ($funding['ganho_potencial_anual'] ?? 0);
        $perda = (float) ($funding['perda_anual'] ?? 0);

        return [
            'id' => $id,
            'title' => (string) ($meta['title'] ?? ''),
            'vaar_refs' => is_array($meta['vaar_refs'] ?? null) ? $meta['vaar_refs'] : [],
            'availability' => $availability,
            'has_issue' => $hasIssue,
            'detected' => $hasIssue,
            'analyzed' => $analyzed,
            'total' => $total,
            'pct_rede' => $pct,
            'ganho_potencial_anual' => $ganho,
            'perda_estimada_anual' => $perda,
            'funding_formula' => $funding['formula'] ?? null,
            'funding_explicacao' => $funding['explicacao'] ?? null,
            'status' => $status,
            'status_label' => (string) $resolved['status_label'],
            'status_hint' => $resolved['status_hint'] ?? null,
            'unavailable_reason' => $status === DiscrepanciesRoutineStatus::UNAVAILABLE
                ? ($eval['unavailable_reason'] ?? $resolved['status_hint'])
                : null,
            'severity' => $severity,
        ];
    }

    /**
     * @param  array{availability: string, has_issue: bool, unavailable_reason?: ?string}  $eval
     * @return array{status: string, status_label: string, status_hint: ?string, availability: string}
     */
    private static function legacyResolveStatus(array $eval, string $severity): array
    {
        $availability = (string) ($eval['availability'] ?? DiscrepanciesRoutineStatus::UNAVAILABLE);
        $hasIssue = (bool) ($eval['has_issue'] ?? false);

        if ($availability === DiscrepanciesRoutineStatus::UNAVAILABLE) {
            return [
                'status' => DiscrepanciesRoutineStatus::UNAVAILABLE,
                'status_label' => __('Indisponível'),
                'status_hint' => $eval['unavailable_reason'] ?? null,
                'availability' => DiscrepanciesRoutineStatus::UNAVAILABLE,
            ];
        }

        if ($hasIssue) {
            return [
                'status' => $severity === 'danger' ? 'danger' : 'warning',
                'status_label' => __('Pendência'),
                'status_hint' => null,
                'availability' => 'available',
            ];
        }

        return [
            'status' => DiscrepanciesRoutineStatus::OK,
            'status_label' => __('Sem pendência'),
            'status_hint' => null,
            'availability' => 'available',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @return list<array<string, mixed>>
     */
    private function sortChecksForConsultoria(array $checks): array
    {
        usort($checks, static function (array $a, array $b): int {
            $order = static fn (array $c): int => match ((string) ($c['severity'] ?? '')) {
                'danger' => 0,
                'warning' => 1,
                default => 2,
            };
            $oa = $order($a);
            $ob = $order($b);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return ((float) ($b['ganho_potencial_anual'] ?? 0)) <=> ((float) ($a['ganho_potencial_anual'] ?? 0));
        });

        return $checks;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<array{escola_id: string, escola: string, total: int}>  $schoolRows
     * @return array<string, mixed>
     */
    private function buildCheck(
        array $meta,
        array $schoolRows,
        int $totalMat,
        ?City $city = null,
        ?IeducarFilterState $filters = null,
    ): array {
        $id = (string) ($meta['id'] ?? '');
        $total = array_sum(array_column($schoolRows, 'total'));
        $corrigivel = $total;
        $pct = $totalMat > 0 ? round(100.0 * $total / $totalMat, 1) : null;
        $funding = DiscrepanciesFundingImpact::estimate($id, $total, $city, $filters);

        $top = array_slice($schoolRows, 0, 6);
        $labelsEsc = array_map(
            fn (array $r): string => $this->truncateChartLabel((string) $r['escola'], 26),
            $top
        );
        $valsAtual = array_map(static fn (array $r): int => (int) $r['total'], $top);

        $chartEscolas = $this->applyConsultoriaChartOptions(
            ChartPayload::barHorizontalGrouped(
                __('Top escolas'),
                __('Ocorrências'),
                $labelsEsc,
                [
                    ['label' => __('Atual'), 'data' => $valsAtual],
                    ['label' => __('Após correção'), 'data' => array_fill(0, count($valsAtual), 0)],
                ]
            ),
            horizontal: true,
            panelHeight: 'sm',
        );

        $chartRede = $this->applyConsultoriaChartOptions(
            ChartPayload::bar(
                __('Rede'),
                __('Matrículas'),
                [__('Com discrepância'), __('Após correção')],
                [(float) $total, 0.0]
            ),
            panelHeight: 'xs',
        );
        $chartRede['datasets'][0]['backgroundColor'] = ['#ef4444', '#22c55e'];
        $chartRede['datasets'][0]['borderColor'] = ['#ef4444', '#22c55e'];

        $chartFinanceiro = $this->applyConsultoriaChartOptions(
            ChartPayload::bar(
                __('Impacto (R$/ano)'),
                __('Estimativa'),
                [__('Perda'), __('Ganho pot.')],
                [(float) $funding['perda_anual'], (float) $funding['ganho_potencial_anual']]
            ),
            panelHeight: 'xs',
        );
        $chartFinanceiro['datasets'][0]['backgroundColor'] = ['#f97316', '#10b981'];
        $chartFinanceiro['datasets'][0]['borderColor'] = ['#f97316', '#10b981'];

        $severity = (string) ($meta['severity'] ?? 'warning');
        $status = match ($severity) {
            'danger' => 'danger',
            'warning' => 'warning',
            default => 'neutral',
        };

        $unitGain = $total > 0 ? ((float) $funding['ganho_potencial_anual']) / $total : 0.0;
        $enrichedRows = [];
        foreach ($schoolRows as $row) {
            $cnt = (int) ($row['total'] ?? 0);
            $enrichedRows[] = array_merge($row, [
                'ganho_potencial_anual' => round($unitGain * $cnt, 2),
            ]);
        }

        return [
            'id' => $id,
            'title' => (string) ($meta['title'] ?? ''),
            'explanation' => (string) ($meta['explanation'] ?? ''),
            'impact' => (string) ($meta['impact'] ?? ''),
            'correction' => (string) ($meta['correction'] ?? ''),
            'severity' => $severity,
            'status' => $status,
            'is_erro' => $severity === 'danger',
            'consultoria_prioridade' => $severity === 'danger' ? __('Erro crítico') : __('Atenção'),
            'vaar_refs' => is_array($meta['vaar_refs'] ?? null) ? $meta['vaar_refs'] : [],
            'total' => $total,
            'corrigivel' => $corrigivel,
            'pct_rede' => $pct,
            'perda_estimada_anual' => $funding['perda_anual'],
            'ganho_potencial_anual' => $funding['ganho_potencial_anual'],
            'funding_formula' => $funding['formula'],
            'funding_explicacao' => $funding['explicacao'],
            'funding' => $funding,
            'school_rows' => $enrichedRows,
            'chart_rede' => $chartRede,
            'chart_escolas' => $chartEscolas,
            'chart_financeiro' => $chartFinanceiro,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @return array{
     *   com_problema: int,
     *   corrigiveis: int,
     *   escolas_afetadas: int,
     *   perda_estimada_anual: float,
     *   ganho_potencial_anual: float
     * }
     */
    private function buildSummary(array $checks): array
    {
        $comProblema = 0;
        $corrigiveis = 0;
        $perda = 0.0;
        $ganho = 0.0;
        $escolas = [];
        foreach ($checks as $c) {
            $comProblema += (int) ($c['total'] ?? 0);
            $corrigiveis += (int) ($c['corrigivel'] ?? 0);
            $perda += (float) ($c['perda_estimada_anual'] ?? 0);
            $ganho += (float) ($c['ganho_potencial_anual'] ?? 0);
            foreach ($c['school_rows'] ?? [] as $row) {
                $eid = (string) ($row['escola_id'] ?? '');
                if ($eid !== '') {
                    $escolas[$eid] = true;
                }
            }
        }

        return [
            'com_problema' => $comProblema,
            'corrigiveis' => $corrigiveis,
            'escolas_afetadas' => count($escolas),
            'perda_estimada_anual' => round($perda, 2),
            'ganho_potencial_anual' => round($ganho, 2),
        ];
    }

    /**
     * Resumo financeiro a partir do mapa de dimensões quando não há checks detalhados.
     *
     * @param  list<array<string, mixed>>  $dimensions
     * @return array{
     *   com_problema: int,
     *   corrigiveis: int,
     *   escolas_afetadas: int,
     *   perda_estimada_anual: float,
     *   ganho_potencial_anual: float
     * }
     */
    private function buildSummaryFromDimensions(array $dimensions): array
    {
        $comProblema = 0;
        $perda = 0.0;
        $ganho = 0.0;

        foreach ($dimensions as $d) {
            if (! ($d['has_issue'] ?? false)) {
                continue;
            }
            $total = (int) ($d['total'] ?? 0);
            $comProblema += $total;
            $perda += (float) ($d['perda_estimada_anual'] ?? 0);
            $ganho += (float) ($d['ganho_potencial_anual'] ?? 0);
        }

        return [
            'com_problema' => $comProblema,
            'corrigiveis' => $comProblema,
            'escolas_afetadas' => 0,
            'perda_estimada_anual' => round($perda, 2),
            'ganho_potencial_anual' => round($ganho, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @return ?array<string, mixed>
     */
    private function chartResumoRede(array $checks): ?array
    {
        if ($checks === []) {
            return null;
        }
        $sorted = $checks;
        usort($sorted, static fn (array $a, array $b): int => ((int) ($b['total'] ?? 0)) <=> ((int) ($a['total'] ?? 0)));
        $sorted = array_slice($sorted, 0, 8);

        $labels = [];
        $atual = [];
        foreach ($sorted as $c) {
            $labels[] = $this->truncateChartLabel((string) ($c['title'] ?? ''), 36);
            $atual[] = (float) ($c['total'] ?? 0);
        }

        return $this->applyConsultoriaChartOptions(
            ChartPayload::barHorizontalGrouped(
                __('Ocorrências por rotina'),
                __('Quantidade'),
                $labels,
                [
                    ['label' => __('Atual'), 'data' => $atual],
                    ['label' => __('Após correção'), 'data' => array_fill(0, count($atual), 0.0)],
                ]
            ),
            horizontal: true,
            panelHeight: 'sm',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @return ?array<string, mixed>
     */
    private function chartFinanceiro(array $checks): ?array
    {
        if ($checks === []) {
            return null;
        }
        $sorted = $checks;
        usort($sorted, static fn (array $a, array $b): int => ((float) ($b['perda_estimada_anual'] ?? 0)) <=> ((float) ($a['perda_estimada_anual'] ?? 0)));
        $sorted = array_slice($sorted, 0, 8);

        $labels = [];
        $perda = [];
        $ganho = [];
        foreach ($sorted as $c) {
            $labels[] = $this->truncateChartLabel((string) ($c['title'] ?? ''), 36);
            $perda[] = (float) ($c['perda_estimada_anual'] ?? 0);
            $ganho[] = (float) ($c['ganho_potencial_anual'] ?? 0);
        }

        return $this->applyConsultoriaChartOptions(
            ChartPayload::barHorizontalGrouped(
                __('Impacto por rotina (R$/ano)'),
                __('Estimativa'),
                $labels,
                [
                    ['label' => __('Perda'), 'data' => $perda],
                    ['label' => __('Ganho pot.'), 'data' => $ganho],
                ]
            ),
            horizontal: true,
            panelHeight: 'sm',
        );
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array<string, mixed>
     */
    private function applyConsultoriaChartOptions(
        array $chart,
        bool $horizontal = false,
        string $panelHeight = 'sm',
    ): array {
        $chart['options'] = array_merge(
            is_array($chart['options'] ?? null) ? $chart['options'] : [],
            [
                'panelHeight' => $panelHeight,
                'skipHorizontalBarAutoHeight' => $horizontal,
            ],
        );

        if ($horizontal) {
            $n = count($chart['labels'] ?? []);
            $chart['options']['layout'] = array_merge(
                is_array($chart['options']['layout'] ?? null) ? $chart['options']['layout'] : [],
                [
                    'padding' => ['left' => 4, 'right' => 28, 'top' => 6, 'bottom' => 6],
                ],
            );
            if ($n > 0 && $n <= 8) {
                $chart['options']['minChartHeight'] = max(140, 56 + $n * 36);
            }
        }

        return $chart;
    }

    private function truncateChartLabel(string $label, int $max = 32): string
    {
        $label = trim($label);
        if ($label === '') {
            return '—';
        }

        return mb_strlen($label) > $max ? mb_substr($label, 0, $max - 1).'…' : $label;
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     */
    private function hasCheckId(array $checks, string $id): bool
    {
        foreach ($checks as $c) {
            if (($c['id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }

    private function yearLabel(IeducarFilterState $filters): string
    {
        if (! $filters->hasYearSelected()) {
            return '';
        }
        if ($filters->isAllSchoolYears()) {
            return __('Todos os anos (consolidado)');
        }

        return __('Ano letivo :year', ['year' => (string) $filters->ano_letivo]);
    }
}
