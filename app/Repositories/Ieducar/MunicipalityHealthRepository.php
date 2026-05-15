<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;

/**
 * Diagnóstico Geral: conformidade consolidada (cadastro/Censo + eixos FUNDEB/VAAR) no ano filtrado.
 */
class MunicipalityHealthRepository
{
    public function __construct(
        private DiscrepanciesRepository $discrepancies,
        private FundebRepository $fundeb,
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
            'fundeb_modules' => [],
            'top_problems' => [],
            'chart_score' => null,
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
            );

            return $this->assemble($city, $filters, $disc, $fundeb);
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
     * @return array<string, mixed>
     */
    private function assemble(City $city, IeducarFilterState $filters, array $disc, array $fundeb): array
    {
        $checks = is_array($disc['checks'] ?? null) ? $disc['checks'] : [];
        $discSummary = is_array($disc['summary'] ?? null) ? $disc['summary'] : [];
        $modules = is_array($fundeb['modules'] ?? null) ? $fundeb['modules'] : [];

        $score = 100.0;
        foreach ($checks as $c) {
            $pct = (float) ($c['pct_rede'] ?? 0);
            $score -= match ((string) ($c['severity'] ?? 'warning')) {
                'danger' => min(35.0, $pct * 1.15),
                'warning' => min(22.0, $pct * 0.75),
                default => min(12.0, $pct * 0.35),
            };
        }
        $modulosAlerta = 0;
        foreach ($modules as $m) {
            $st = (string) ($m['status'] ?? 'neutral');
            if ($st === 'danger') {
                $score -= 10.0;
                $modulosAlerta++;
            } elseif ($st === 'warning') {
                $score -= 5.0;
                $modulosAlerta++;
            }
        }
        $score = (int) max(0, min(100, round($score)));

        [$status, $label] = match (true) {
            $score >= 80 => ['success', __('Boa conformidade')],
            $score >= 55 => ['warning', __('Atenção — pendências relevantes')],
            default => ['danger', __('Situação crítica')],
        };

        $checksById = [];
        foreach ($checks as $c) {
            $checksById[(string) ($c['id'] ?? '')] = $c;
        }

        $cadastroDimensions = [];
        foreach (DiscrepanciesCheckCatalog::definitions() as $id => $def) {
            $found = $checksById[$id] ?? null;
            $cadastroDimensions[] = [
                'id' => $id,
                'title' => (string) ($def['title'] ?? ''),
                'vaar_refs' => is_array($def['vaar_refs'] ?? null) ? $def['vaar_refs'] : [],
                'detected' => $found !== null,
                'total' => (int) ($found['total'] ?? 0),
                'pct_rede' => $found['pct_rede'] ?? null,
                'ganho_potencial_anual' => (float) ($found['ganho_potencial_anual'] ?? 0),
                'status' => $found === null
                    ? 'success'
                    : ((string) ($found['severity'] ?? 'warning') === 'danger' ? 'danger' : 'warning'),
            ];
        }

        $topProblems = $checks;
        usort($topProblems, static fn (array $a, array $b): int => ((float) ($b['ganho_potencial_anual'] ?? 0)) <=> ((float) ($a['ganho_potencial_anual'] ?? 0)));
        $topProblems = array_slice($topProblems, 0, 8);

        $pendencias = count(array_filter($cadastroDimensions, static fn (array $d): bool => ($d['detected'] ?? false) === true));
        $okCadastro = count($cadastroDimensions) - $pendencias;

        $chartPendencias = null;
        if ($pendencias > 0) {
            $labels = [];
            $vals = [];
            foreach ($cadastroDimensions as $d) {
                if (! ($d['detected'] ?? false)) {
                    continue;
                }
                $labels[] = (string) ($d['title'] ?? '');
                $vals[] = (float) ($d['total'] ?? 0);
            }
            if ($labels !== []) {
                $chartPendencias = ChartPayload::barHorizontal(
                    __('Principais pendências de cadastro (ocorrências)'),
                    __('Quantidade'),
                    $labels,
                    $vals
                );
            }
        }

        return [
            'intro' => __(
                'Visão consolidada da conformidade municipal no ano letivo e filtros seleccionados. Cruza rotinas automáticas de cadastro (Censo / VAAR) com o roteiro FUNDEB. Use as abas «Discrepâncias e Erros» e «FUNDEB» para detalhar cada eixo.'
            ),
            'footnote' => __(
                'O índice de conformidade é indicativo (0–100), baseado na gravidade e volume das pendências detectadas na base i-Educar e nos módulos FUNDEB com alerta. Não substitui parecer do Simec/MEC.'
            ),
            'year_label' => $this->yearLabel($filters),
            'city_name' => $city->name,
            'compliance_score' => $score,
            'compliance_status' => $status,
            'compliance_label' => $label,
            'summary' => [
                'pendencias_cadastro' => $pendencias,
                'modulos_fundeb_alerta' => $modulosAlerta,
                'perda_estimada_anual' => (float) ($discSummary['perda_estimada_anual'] ?? 0),
                'ganho_potencial_anual' => (float) ($discSummary['ganho_potencial_anual'] ?? 0),
                'escolas_afetadas' => (int) ($discSummary['escolas_afetadas'] ?? 0),
                'total_matriculas' => $disc['total_matriculas'] ?? null,
            ],
            'cadastro_dimensions' => $cadastroDimensions,
            'fundeb_modules' => array_map(static fn (array $m): array => [
                'id' => (string) ($m['id'] ?? ''),
                'title' => (string) ($m['title'] ?? ''),
                'status' => (string) ($m['status'] ?? 'neutral'),
                'reference' => (string) ($m['reference'] ?? ''),
                'situacao' => (string) ($m['situacao'] ?? ''),
            ], $modules),
            'top_problems' => $topProblems,
            'chart_pendencias' => $chartPendencias,
            'error' => $disc['error'] ?? null,
        ];
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
