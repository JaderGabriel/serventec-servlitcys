<?php

namespace App\Services\Clio\Bi;

use App\Models\Bi\BiClioCampaign;
use App\Models\Bi\BiClioEnrollmentStage;
use App\Models\Bi\BiClioInclusion;
use App\Models\Bi\BiClioQuality;
use App\Models\Bi\BiClioSchool;
use App\Services\Clio\Analysis\EtapaLabelOrder;
use App\Support\Dashboard\ChartPayload;

/**
 * Painel visual nativo (Chart.js) a partir de bi_clio_* — zero PII.
 */
final class ClioBiDashboardComposer
{
    private const ETAPAS_TOP = 20;

    private const ESCOLAS_TOP = 12;

    public function __construct(
        private readonly EtapaLabelOrder $etapaOrder,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function charts(int $campaignId, BiClioCampaign $bi): array
    {
        $out = [];

        if (is_numeric($bi->triade_pct)) {
            $gauge = ChartPayload::gaugePercent(
                __('Cobertura da tríade'),
                (float) $bi->triade_pct,
            );
            $gauge['labels'] = [__('Tríade completa'), __('Restante')];
            $gauge['subtitle'] = __(':a escolas ativas · :t total na coleta', [
                'a' => (int) $bi->schools_active,
                't' => (int) $bi->schools_total,
            ]);
            $gauge['footnote'] = __('Percentagem de unidades ativas com alunos, turmas e profissionais.');
            $out['triade'] = $gauge;
        }

        $matLabels = [];
        $matValues = [];
        foreach ([
            [__('Curricular'), (int) $bi->mat_curricular],
            [__('AEE'), (int) $bi->mat_aee],
            [__('Ativ. complementar'), (int) $bi->mat_ac],
        ] as [$label, $n]) {
            if ($n > 0) {
                $matLabels[] = $label;
                $matValues[] = $n;
            }
        }
        if ($matValues !== []) {
            $chart = ChartPayload::doughnut(__('Matrículas (Acompanhamento)'), $matLabels, $matValues);
            $chart['subtitle'] = __('Totais do arquivo geral — sem dados pessoais.');
            $chart['kpi_total'] = array_sum($matValues);
            $chart['kpi_total_label'] = __('Soma das categorias');
            $out['matriculas'] = $chart;
        }

        $etapas = $this->etapasChart($campaignId);
        if ($etapas !== null) {
            $out['etapas'] = $etapas;
        }

        $inclusao = $this->inclusaoChart($campaignId);
        if ($inclusao !== null) {
            $out['inclusao'] = $inclusao;
        }

        $aeeGap = $this->aeeGapChart($campaignId);
        if ($aeeGap !== null) {
            $out['aee_gap'] = $aeeGap;
        }

        $qualidade = $this->qualidadeChart($campaignId);
        if ($qualidade !== null) {
            $out['qualidade'] = $qualidade;
        }

        $escolas = $this->escolasChart($campaignId);
        if ($escolas !== null) {
            $out['escolas'] = $escolas;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function etapasChart(int $campaignId): ?array
    {
        $rows = BiClioEnrollmentStage::query()
            ->where('campaign_id', $campaignId)
            ->whereNull('inep')
            ->get(['etapa', 'qt_alunos', 'qt_turmas']);

        if ($rows->isEmpty()) {
            return null;
        }

        $sorted = $rows->sort(function ($a, $b) {
            return $this->etapaOrder->compare((string) $a->etapa, (string) $b->etapa);
        })->values();

        $top = $sorted
            ->sortByDesc(fn ($r) => (int) $r->qt_alunos)
            ->take(self::ETAPAS_TOP)
            ->values();

        $ordered = $top->sort(function ($a, $b) {
            return $this->etapaOrder->compare((string) $a->etapa, (string) $b->etapa);
        })->values();

        $labels = $ordered->map(fn ($r) => (string) $r->etapa)->all();
        $alunos = $ordered->map(fn ($r) => (int) $r->qt_alunos)->all();
        $turmas = $ordered->map(fn ($r) => (int) $r->qt_turmas)->all();

        if (array_sum($alunos) + array_sum($turmas) <= 0) {
            return null;
        }

        $chart = ChartPayload::barHorizontalGrouped(
            __('Alunos e turmas por etapa'),
            __('Quantidade'),
            $labels,
            [
                ['label' => __('Alunos'), 'data' => $alunos],
                ['label' => __('Turmas'), 'data' => $turmas],
            ],
        );
        $chart['subtitle'] = __('Até :n etapas com mais alunos · ordem pedagógica.', ['n' => self::ETAPAS_TOP]);
        $chart['footnote'] = __('Fonte: bi_clio_enrollment_stage (agregado municipal).');
        $chart['kpi_total'] = (int) array_sum($alunos);
        $chart['kpi_total_label'] = __('Alunos nas etapas exibidas');
        $chart['kpi_total_secondary'] = (int) array_sum($turmas);
        $chart['kpi_total_secondary_label'] = __('Turmas');

        return $chart;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function inclusaoChart(int $campaignId): ?array
    {
        $agg = BiClioInclusion::query()
            ->where('campaign_id', $campaignId)
            ->selectRaw('SUM(qt_deficiency) as def, SUM(qt_disorder) as trs, SUM(qt_ah) as ah')
            ->first();

        $def = (int) ($agg->def ?? 0);
        $trs = (int) ($agg->trs ?? 0);
        $ah = (int) ($agg->ah ?? 0);
        if ($def + $trs + $ah <= 0) {
            return null;
        }

        $chart = ChartPayload::doughnut(__('Inclusão — tipificação NEE'), [
            __('Deficiência'),
            __('TEA / transtornos'),
            __('Altas habilidades'),
        ], [$def, $trs, $ah]);
        $chart['subtitle'] = __('Contagem por pessoa · sem identificação.');
        $chart['kpi_total'] = $def + $trs + $ah;
        $chart['kpi_total_label'] = __('Marcações tipificadas');
        $chart['footnote'] = __('Uma pessoa pode contribuir em mais de uma categoria conforme o CSV.');

        return $chart;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function aeeGapChart(int $campaignId): ?array
    {
        $agg = BiClioInclusion::query()
            ->where('campaign_id', $campaignId)
            ->selectRaw('SUM(qt_without_aee) as sem, SUM(qt_aee_without_nee) as aee')
            ->first();

        $sem = (int) ($agg->sem ?? 0);
        $aee = (int) ($agg->aee ?? 0);
        if ($sem + $aee <= 0) {
            return null;
        }

        $chart = ChartPayload::bar(
            __('Lacunas AEE × tipificação'),
            __('Pessoas / matrículas'),
            [__('NEE sem AEE'), __('AEE sem NEE tipificada')],
            [$sem, $aee],
        );
        $chart['subtitle'] = __('Priorize revisão de oferta e tipificação.');
        $chart['footnote'] = __('Fonte: bi_clio_inclusion.');

        return $chart;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function qualidadeChart(int $campaignId): ?array
    {
        $active = BiClioSchool::query()
            ->where('campaign_id', $campaignId)
            ->where('is_active', true)
            ->count();
        if ($active <= 0) {
            return null;
        }

        $activeIneps = BiClioSchool::query()
            ->where('campaign_id', $campaignId)
            ->where('is_active', true)
            ->pluck('inep');

        $missing = BiClioQuality::query()
            ->where('campaign_id', $campaignId)
            ->where('missing_triad', true)
            ->whereIn('inep', $activeIneps)
            ->count();

        $ok = max(0, $active - $missing);
        $chart = ChartPayload::doughnut(__('Qualidade — tríade nas ativas'), [
            __('Tríade completa'),
            __('Incompleta'),
        ], [$ok, $missing]);
        $chart['subtitle'] = __(':n escola(s) ativa(s) no recorte.', ['n' => $active]);
        $chart['kpi_total'] = $active;
        $chart['kpi_total_label'] = __('Escolas ativas');

        return $chart;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function escolasChart(int $campaignId): ?array
    {
        $schools = BiClioSchool::query()
            ->where('campaign_id', $campaignId)
            ->where('is_active', true)
            ->get(['name', 'delta_curricular', 'findings_errors', 'triade_parts']);

        if ($schools->isEmpty()) {
            return null;
        }

        $ranked = $schools
            ->map(function ($s) {
                $delta = abs((int) ($s->delta_curricular ?? 0));
                $errors = (int) $s->findings_errors;
                $score = max($delta, $errors * 10);

                return [
                    'name' => (string) $s->name,
                    'score' => $score,
                    'delta' => $delta,
                    'errors' => $errors,
                    'incomplete' => (int) $s->triade_parts < 3,
                ];
            })
            ->filter(fn (array $r) => $r['score'] > 0 || $r['incomplete'])
            ->sortByDesc('score')
            ->take(self::ESCOLAS_TOP)
            ->values();

        if ($ranked->isEmpty()) {
            return null;
        }

        $labels = $ranked->map(function (array $r) {
            $name = $r['name'];
            if (mb_strlen($name) > 42) {
                $name = mb_substr($name, 0, 41).'…';
            }

            return $name;
        })->all();

        $values = $ranked->map(fn (array $r) => (float) max($r['score'], $r['incomplete'] ? 1 : 0))->all();

        $chart = ChartPayload::barHorizontal(
            __('Escolas a priorizar'),
            __('Score (delta / erros)'),
            $labels,
            $values,
        );
        $chart['subtitle'] = __('Maior |delta Acomp×alunos| ou erros · até :n unidades.', ['n' => self::ESCOLAS_TOP]);
        $chart['footnote'] = __('Sem PII de alunos — apenas nome da escola e indicadores agregados.');

        return $chart;
    }
}
