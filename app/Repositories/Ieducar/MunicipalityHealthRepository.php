<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Dashboard\PublicDataSourcesCatalog;
use App\Support\Ieducar\ConsultoriaThematicBridge;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebReferenceDisplay;

/**
 * Diagnóstico Geral: conformidade consolidada (cadastro/Censo + eixos FUNDEB/VAAR) no ano filtrado.
 */
class MunicipalityHealthRepository
{
    public function __construct(
        private DiscrepanciesRepository $discrepancies,
        private FundebRepository $fundeb,
        private OtherFundingRepository $otherFunding,
        private WorkDoneRepository $workDone,
        private OverviewRepository $overview,
        private EnrollmentRepository $enrollment,
        private PerformanceRepository $performance,
        private AttendanceRepository $attendance,
        private InclusionRepository $inclusion,
        private NetworkRepository $network,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?City $city, IeducarFilterState $filters): array
    {
        $empty = [
            'intro' => '',
            'footnote' => '',
            'year_label' => '',
            'city_name' => '',
            'compliance_score' => null,
            'compliance_status' => 'neutral',
            'compliance_label' => '',
            'summary' => [
                'pendencias_cadastro' => 0,
                'modulos_fundeb_alerta' => 0,
                'perda_estimada_anual' => 0.0,
                'ganho_potencial_anual' => 0.0,
            ],
            'cadastro_dimensions' => [],
            'thematic_blocks' => [],
            'active_check_ids' => [],
            'public_data_sources' => PublicDataSourcesCatalog::build(null, 'all'),
            'fundeb_modules' => [],
            'top_problems' => [],
            'chart_pendencias' => null,
            'error' => null,
        ];

        if ($city === null) {
            return $empty;
        }

        try {
            $overview = $this->overview->summary($city, $filters);
            $enrollment = $this->enrollment->sample($city, $filters);
            $performance = $this->performance->snapshot($city, $filters);
            $attendance = $this->attendance->snapshot($city, $filters);
            $inclusion = $this->inclusion->snapshot($city, $filters);
            $network = $this->network->snapshot($city, $filters);
            $disc = $this->discrepancies->snapshot($city, $filters);
            $fundeb = $this->fundeb->buildReport(
                $city,
                $filters,
                $overview,
                $enrollment,
                $performance,
                $attendance,
                $inclusion,
                $network,
                $disc,
            );
            $otherFunding = $this->otherFunding->buildReport($city, $filters);
            $workDone = $this->workDone->buildReport($city, $filters);

            $totalMat = (int) ($disc['total_matriculas'] ?? $overview['kpis']['matriculas'] ?? 0);

            return $this->assemble($city, $filters, $disc, $fundeb, $otherFunding, $workDone, $inclusion, $performance, $network, $totalMat);
        } catch (\Throwable $e) {
            return array_merge($empty, [
                'city_name' => $city->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $disc
     * @param  array<string, mixed>  $fundeb
     * @param  array<string, mixed>  $otherFunding
     * @param  array<string, mixed>  $workDone
     * @param  array<string, mixed>  $inclusion
     * @param  array<string, mixed>  $performance
     * @return array<string, mixed>
     */
    private function assemble(
        City $city,
        IeducarFilterState $filters,
        array $disc,
        array $fundeb,
        array $otherFunding,
        array $workDone,
        array $inclusion,
        array $performance,
        array $network,
        int $totalMat,
    ): array {
        $checks = is_array($disc['checks'] ?? null) ? $disc['checks'] : [];
        $dimensions = is_array($disc['dimensions'] ?? null) ? $disc['dimensions'] : [];
        $discSummary = is_array($disc['summary'] ?? null) ? $disc['summary'] : [];
        $modules = is_array($fundeb['modules'] ?? null) ? $fundeb['modules'] : [];

        $cadastroDimensions = $dimensions !== [] ? $dimensions : $this->legacyDimensionsFromChecks($checks);

        $score = $this->computeComplianceScore($cadastroDimensions, $modules);

        [$status, $label] = match (true) {
            $score >= 80 => ['success', __('Boa conformidade')],
            $score >= 55 => ['warning', __('Atenção — pendências relevantes')],
            default => ['danger', __('Situação crítica')],
        };

        $topProblems = self::buildTopProblems($checks, $cadastroDimensions);

        $pendencias = count(array_filter(
            $cadastroDimensions,
            static fn (array $d): bool => ($d['has_issue'] ?? false) === true
        ));

        $modulosAlerta = 0;
        foreach ($modules as $m) {
            if (in_array((string) ($m['status'] ?? ''), ['danger', 'warning'], true)) {
                $modulosAlerta++;
            }
        }

        $proj = is_array($fundeb['resource_projection'] ?? null) ? $fundeb['resource_projection'] : [];
        $fundebRef = is_array($fundeb['fundeb_reference'] ?? null)
            ? $fundeb['fundeb_reference']
            : DiscrepanciesFundingImpact::resolveReference($city, $filters);
        $fundingDisplay = is_array($disc['funding_reference'] ?? null)
            ? $disc['funding_reference']
            : DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);
        $vaafComparacao = is_array($proj['vaaf_comparacao'] ?? null)
            ? $proj['vaaf_comparacao']
            : FundebReferenceDisplay::vaafComparacao($fundebRef);
        $previsaoComparacao = is_array($proj['previsao_comparacao'] ?? null)
            ? $proj['previsao_comparacao']
            : ($totalMat > 0 ? FundebReferenceDisplay::previsaoComparacao($totalMat, $fundebRef, $city, $filters) : null);
        $divergenciaVaaf = is_array($proj['divergencia_vaaf'] ?? null)
            ? $proj['divergencia_vaaf']
            : (is_array($fundingDisplay['divergencia_vaaf'] ?? null)
                ? $fundingDisplay['divergencia_vaaf']
                : (is_array($fundebRef['divergencia'] ?? null) ? $fundebRef['divergencia'] : null));

        $workPeriods = is_array($workDone['periods'] ?? null) ? $workDone['periods'] : [];
        $cadastrosQuinzena = (int) ($workPeriods['fortnight'] ?? 0);
        $complementaryPrograms = self::summarizeComplementaryPrograms($otherFunding);
        $activeProgramIds = array_values(array_filter(
            array_map(
                static fn (array $p): string => (string) ($p['id'] ?? ''),
                array_filter($complementaryPrograms, static fn (array $p): bool => in_array((string) ($p['status'] ?? ''), ['warning', 'danger'], true))
            ),
            static fn (string $id): bool => $id !== ''
        ));
        $publicQueries = is_array($otherFunding['public_municipal'] ?? null) ? $otherFunding['public_municipal'] : [];
        $publicQueriesOk = (int) count(array_filter(
            is_array($publicQueries['queries'] ?? null) ? $publicQueries['queries'] : [],
            static fn ($q): bool => is_array($q) && ($q['status'] ?? '') === 'success'
        ));

        return [
            'intro' => __(
                'Painel de consultoria municipal: consolida cadastro (Discrepâncias), VAAF municipal × prévia federal, programas complementares (PNAE/PNATE), ritmo de cadastro no i-Educar, FUNDEB/VAAR e indicadores INEP quando disponíveis.'
            ),
            'footnote' => __(
                'Índice 0–100: mesma base das discrepâncias (volume + gravidade) e alertas FUNDEB. Verde = rotina executada sem pendências; cinza = rotina indisponível nesta base; amarelo/vermelho = pendência detectada.'
            ),
            'year_label' => $this->yearLabel($filters),
            'city_name' => $city->name,
            'compliance_score' => $score,
            'compliance_status' => $status,
            'compliance_label' => $label,
            'summary' => [
                'pendencias_cadastro' => $pendencias,
                'modulos_fundeb_alerta' => $modulosAlerta,
                'com_problema' => (int) ($discSummary['com_problema'] ?? 0),
                'corrigiveis' => (int) ($discSummary['corrigiveis'] ?? 0),
                'perda_estimada_anual' => (float) ($discSummary['perda_estimada_anual'] ?? 0),
                'ganho_potencial_anual' => (float) ($discSummary['ganho_potencial_anual'] ?? 0),
                'escolas_afetadas' => (int) ($discSummary['escolas_afetadas'] ?? 0),
                'total_matriculas' => $totalMat > 0 ? $totalMat : ($disc['total_matriculas'] ?? null),
                'recurso_prova_sem_nee' => (int) data_get($inclusion, 'recurso_prova.sem_nee', 0),
                'cadastros_quinzena' => $cadastrosQuinzena,
                'ritmo_cadastro_dia' => (float) ($workDone['estimativa']['ritmo_por_dia'] ?? 0),
            ],
            'funding_reference' => $fundebRef,
            'funding_display' => $fundingDisplay,
            'vaaf_comparacao' => $vaafComparacao,
            'previsao_comparacao' => $previsaoComparacao,
            'divergencia_vaaf' => $divergenciaVaaf,
            'other_funding_programs' => count($complementaryPrograms),
            'programas_alerta' => count($activeProgramIds),
            'complementary_programs' => $complementaryPrograms,
            'active_program_ids' => $activeProgramIds,
            'public_queries_success' => $publicQueriesOk,
            'work_done_available' => (bool) ($workDone['activity_available'] ?? false),
            'cadastro_dimensions' => $cadastroDimensions,
            'active_check_ids' => is_array($disc['active_check_ids'] ?? null) ? $disc['active_check_ids'] : [],
            'thematic_blocks' => ConsultoriaThematicBridge::buildBlocks(
                $inclusion,
                $fundeb,
                $performance,
                $disc,
                $totalMat,
                is_array($network['kpis'] ?? null) ? $network['kpis'] : null,
                $otherFunding,
                $workDone,
            ),
            'public_data_sources' => PublicDataSourcesCatalog::build($city, 'all'),
            'fundeb_modules' => array_map(static fn (array $m): array => [
                'id' => (string) ($m['id'] ?? ''),
                'title' => (string) ($m['title'] ?? ''),
                'status' => (string) ($m['status'] ?? 'neutral'),
                'reference' => (string) ($m['reference'] ?? ''),
                'situacao' => (string) ($m['situacao'] ?? ''),
            ], $modules),
            'top_problems' => $topProblems,
            'funding_metodologia' => is_array($disc['funding_metodologia'] ?? null)
                ? $disc['funding_metodologia']
                : DiscrepanciesFundingImpact::metodologiaResumo($city, $filters),
            'funding_resumo_explicacao' => is_array($disc['funding_resumo_explicacao'] ?? null)
                ? $disc['funding_resumo_explicacao']
                : DiscrepanciesFundingImpact::explicacaoResumoAgregado(
                    (int) ($discSummary['com_problema'] ?? $pendencias),
                    (float) ($discSummary['perda_estimada_anual'] ?? 0),
                    (float) ($discSummary['ganho_potencial_anual'] ?? 0),
                    count(array_filter($cadastroDimensions, static fn (array $d): bool => (bool) ($d['has_issue'] ?? false))),
                ),
            'error' => $disc['error'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $dimensions
     * @param  list<array<string, mixed>>  $modules
     */
    private function computeComplianceScore(array $dimensions, array $modules): int
    {
        $score = 100.0;
        foreach ($dimensions as $d) {
            $avail = (string) ($d['availability'] ?? '');
            if ($avail === 'unavailable' || $avail === 'no_data' || ($d['status'] ?? '') === 'no_data') {
                continue;
            }
            if (! ($d['has_issue'] ?? false)) {
                continue;
            }
            $pct = (float) ($d['pct_rede'] ?? 0);
            $severity = (string) ($d['severity'] ?? 'warning');
            $score -= match ($severity) {
                'danger' => min(35.0, $pct * 1.15),
                'warning' => min(22.0, $pct * 0.75),
                default => min(12.0, $pct * 0.35),
            };
        }
        foreach ($modules as $m) {
            $score -= match ((string) ($m['status'] ?? 'neutral')) {
                'danger' => 8.0,
                'warning' => 4.0,
                default => 0.0,
            };
        }

        return (int) max(0, min(100, round($score)));
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @return list<array<string, mixed>>
     */
    private function legacyDimensionsFromChecks(array $checks): array
    {
        $byId = [];
        foreach ($checks as $c) {
            $byId[(string) ($c['id'] ?? '')] = $c;
        }
        $out = [];
        foreach (\App\Support\Ieducar\DiscrepanciesCheckCatalog::definitions() as $id => $def) {
            $found = $byId[$id] ?? null;
            $out[] = [
                'id' => $id,
                'title' => (string) ($def['title'] ?? ''),
                'vaar_refs' => $def['vaar_refs'] ?? [],
                'availability' => $found !== null ? 'available' : 'unavailable',
                'has_issue' => $found !== null,
                'detected' => $found !== null,
                'total' => (int) ($found['total'] ?? 0),
                'pct_rede' => $found['pct_rede'] ?? null,
                'ganho_potencial_anual' => (float) ($found['ganho_potencial_anual'] ?? 0),
                'status' => $found === null ? 'unavailable' : ((string) ($found['severity'] ?? '') === 'danger' ? 'danger' : 'warning'),
                'unavailable_reason' => null,
                'severity' => (string) ($def['severity'] ?? 'warning'),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @param  list<array<string, mixed>>  $dimensions
     * @return list<array<string, mixed>>
     */
    private function buildTopProblems(array $checks, array $dimensions): array
    {
        $byId = [];
        foreach ($checks as $c) {
            if (! ($c['has_issue'] ?? $c['detected'] ?? false)) {
                continue;
            }
            $byId[(string) ($c['id'] ?? '')] = $c;
        }
        foreach ($dimensions as $d) {
            if (! ($d['has_issue'] ?? false)) {
                continue;
            }
            $id = (string) ($d['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (isset($byId[$id])) {
                if (($d['pct_rede'] ?? null) !== null && ! isset($byId[$id]['pct_rede'])) {
                    $byId[$id]['pct_rede'] = $d['pct_rede'];
                }

                continue;
            }
            $total = (int) ($d['total'] ?? 0);
            $byId[$id] = [
                'id' => $id,
                'title' => (string) ($d['title'] ?? ''),
                'total' => $total,
                'pct_rede' => $d['pct_rede'] ?? null,
                'perda_estimada_anual' => (float) ($d['perda_estimada_anual'] ?? 0),
                'ganho_potencial_anual' => (float) ($d['ganho_potencial_anual'] ?? 0),
                'funding_explicacao' => is_array($d['funding_explicacao'] ?? null) ? $d['funding_explicacao'] : null,
                'severity' => (string) ($d['severity'] ?? 'warning'),
                'is_erro' => ($d['severity'] ?? '') === 'danger',
            ];
        }

        $top = array_values(array_filter(
            $byId,
            static fn (array $row): bool => (int) ($row['total'] ?? 0) > 0
                || (float) ($row['perda_estimada_anual'] ?? 0) > 0
                || (float) ($row['ganho_potencial_anual'] ?? 0) > 0,
        ));
        usort($top, static fn (array $a, array $b): int => ((float) ($b['perda_estimada_anual'] ?? $b['ganho_potencial_anual'] ?? 0))
            <=> ((float) ($a['perda_estimada_anual'] ?? $a['ganho_potencial_anual'] ?? 0)));

        return array_slice($top, 0, 8);
    }

    /**
     * @param  array<string, mixed>  $otherFunding
     * @return list<array<string, mixed>>
     */
    private static function summarizeComplementaryPrograms(array $otherFunding): array
    {
        $programs = is_array($otherFunding['programs'] ?? null) ? $otherFunding['programs'] : [];
        $out = [];
        foreach ($programs as $prog) {
            if (! is_array($prog)) {
                continue;
            }
            $kpis = is_array($prog['kpis'] ?? null) ? $prog['kpis'] : [];
            $status = (string) ($prog['status'] ?? 'neutral');
            $cobertura = null;
            foreach ($kpis as $k) {
                if (is_array($k) && ($k['label'] ?? '') === __('Preenchimento indicativo')) {
                    $v = (string) ($k['value'] ?? '');
                    $cobertura = str_ends_with($v, '%') ? (float) rtrim($v, '%') : null;
                    break;
                }
            }
            $resumo = match ($status) {
                'success' => __('Cobertura de cadastro adequada no i-Educar.'),
                'warning' => __('Cobertura parcial — rever campos antes do Censo.'),
                'danger' => __('Cobertura baixa ou campo não detectado na base.'),
                default => filled($prog['descricao'] ?? null) ? (string) $prog['descricao'] : __('Sem colunas configuradas detectadas.'),
            };
            if ($cobertura !== null) {
                $resumo = __('Cobertura indicativa :pct% das matrículas no filtro.', ['pct' => number_format($cobertura, 1, ',', '.')]);
            }
            $out[] = [
                'id' => (string) ($prog['id'] ?? ''),
                'titulo' => (string) ($prog['titulo'] ?? ''),
                'status' => $status,
                'status_label' => match ($status) {
                    'success' => __('Adequado'),
                    'warning' => __('Atenção'),
                    'danger' => __('Crítico'),
                    default => __('Neutro'),
                },
                'resumo' => $resumo,
                'cobertura_pct' => $cobertura,
            ];
        }

        return $out;
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
