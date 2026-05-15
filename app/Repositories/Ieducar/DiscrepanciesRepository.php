<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;
use App\Support\Ieducar\DiscrepanciesCheckRunner;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\DiscrepanciesQueries;
use App\Support\Ieducar\InclusionDashboardQueries;
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
                        ], $totalMat);

                        continue;
                    }

                    $eval = DiscrepanciesCheckRunner::evaluate(
                        $db,
                        $city,
                        $filters,
                        $spec['fn'],
                        $spec['probe'],
                    );
                    $dimensions[] = $this->buildDimension($meta, $eval, $totalMat);

                    if ($eval['has_issue']) {
                        $checks[] = $this->buildCheck($meta, $eval['rows'], $totalMat);
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
                    $dimensions[] = $this->buildDimension($meta, $neeEval, $totalMat);
                    $checks[] = $this->buildCheck($meta, [$neeRow], $totalMat);
                }

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

                $summary = $this->buildSummary($checks);
                if ($checks === [] && $dimensions !== []) {
                    $summary = $this->buildSummaryFromDimensions($dimensions);
                }
                $vaaRef = (float) config('ieducar.discrepancies.vaa_referencia_anual', 4500);

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
                    'funding_reference' => [
                        'vaa_anual' => $vaaRef,
                        'vaa_label' => DiscrepanciesFundingImpact::formatBrl($vaaRef),
                    ],
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
    private function buildDimension(array $meta, array $eval, int $totalMat): array
    {
        $id = (string) ($meta['id'] ?? '');
        $availability = (string) ($eval['availability'] ?? 'unavailable');
        $hasIssue = (bool) ($eval['has_issue'] ?? false);
        $total = $hasIssue ? array_sum(array_column($eval['rows'], 'total')) : 0;
        $severity = (string) ($meta['severity'] ?? 'warning');

        $status = match (true) {
            $availability === 'unavailable' => 'unavailable',
            $hasIssue && $severity === 'danger' => 'danger',
            $hasIssue => 'warning',
            default => 'ok',
        };

        $pct = $totalMat > 0 && $hasIssue ? round(100.0 * $total / $totalMat, 1) : null;
        $ganho = $hasIssue ? DiscrepanciesFundingImpact::estimate($id, $total)['ganho_potencial_anual'] : 0.0;

        return [
            'id' => $id,
            'title' => (string) ($meta['title'] ?? ''),
            'vaar_refs' => is_array($meta['vaar_refs'] ?? null) ? $meta['vaar_refs'] : [],
            'availability' => $availability,
            'has_issue' => $hasIssue,
            'detected' => $hasIssue,
            'total' => $total,
            'pct_rede' => $pct,
            'ganho_potencial_anual' => $ganho,
            'status' => $status,
            'unavailable_reason' => $eval['unavailable_reason'] ?? null,
            'severity' => $severity,
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
    private function buildCheck(array $meta, array $schoolRows, int $totalMat): array
    {
        $id = (string) ($meta['id'] ?? '');
        $total = array_sum(array_column($schoolRows, 'total'));
        $corrigivel = $total;
        $pct = $totalMat > 0 ? round(100.0 * $total / $totalMat, 1) : null;
        $funding = DiscrepanciesFundingImpact::estimate($id, $total);

        $top = array_slice($schoolRows, 0, 12);
        $labelsEsc = array_map(static fn (array $r): string => (string) $r['escola'], $top);
        $valsAtual = array_map(static fn (array $r): int => (int) $r['total'], $top);

        $chartEscolas = ChartPayload::barHorizontalGrouped(
            __('Por escola — atual vs. após correção'),
            __('Matrículas / ocorrências'),
            $labelsEsc,
            [
                ['label' => __('Com discrepância (atual)'), 'data' => $valsAtual],
                ['label' => __('Após correção no cadastro'), 'data' => array_fill(0, count($valsAtual), 0)],
            ]
        );

        $chartRede = ChartPayload::bar(
            __('Rede — situação agregada'),
            __('Matrículas'),
            [__('Com discrepância'), __('Após correção')],
            [(float) $total, 0.0]
        );
        $chartRede['datasets'][0]['backgroundColor'] = ['#ef4444', '#22c55e'];
        $chartRede['datasets'][0]['borderColor'] = ['#ef4444', '#22c55e'];

        $chartFinanceiro = ChartPayload::bar(
            __('Impacto financeiro indicativo'),
            __('R$ / ano (estimativa)'),
            [__('Perda estimada'), __('Ganho potencial')],
            [(float) $funding['perda_anual'], (float) $funding['ganho_potencial_anual']]
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
            $id = (string) ($d['id'] ?? '');
            if ($id !== '') {
                $est = DiscrepanciesFundingImpact::estimate($id, $total);
                $perda += $est['perda_anual'];
                $ganho += $est['ganho_potencial_anual'];
            } else {
                $ganho += (float) ($d['ganho_potencial_anual'] ?? 0);
            }
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
        $labels = [];
        $atual = [];
        foreach ($checks as $c) {
            $labels[] = (string) ($c['title'] ?? '');
            $atual[] = (float) ($c['total'] ?? 0);
        }

        return ChartPayload::barHorizontalGrouped(
            __('Resumo — ocorrências por tipo de discrepância'),
            __('Quantidade'),
            $labels,
            [
                ['label' => __('Situação atual'), 'data' => $atual],
                ['label' => __('Após correções'), 'data' => array_fill(0, count($atual), 0.0)],
            ]
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
        $labels = [];
        $perda = [];
        $ganho = [];
        foreach ($checks as $c) {
            $labels[] = (string) ($c['title'] ?? '');
            $perda[] = (float) ($c['perda_estimada_anual'] ?? 0);
            $ganho[] = (float) ($c['ganho_potencial_anual'] ?? 0);
        }

        return ChartPayload::barHorizontalGrouped(
            __('Impacto financeiro indicativo por discrepância'),
            __('R$ / ano (estimativa)'),
            $labels,
            [
                ['label' => __('Perda estimada'), 'data' => $perda],
                ['label' => __('Ganho potencial após correção'), 'data' => $ganho],
            ]
        );
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
