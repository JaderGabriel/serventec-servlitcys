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
 * Painel visual nativo (Chart.js) a partir de bi_clio_* + payloads INF-* (zero PII).
 */
final class ClioBiDashboardComposer
{
    private const ETAPAS_TOP = 20;

    private const ESCOLAS_TOP = 12;

    private const DEM_TOP = 12;

    public function __construct(
        private readonly EtapaLabelOrder $etapaOrder,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $inferences  código INF-* => payload
     * @return array<string, array<string, mixed>>
     */
    public function charts(int $campaignId, BiClioCampaign $bi, array $inferences = []): array
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

        $findings = $this->findingsChart($bi);
        if ($findings !== null) {
            $out['findings'] = $findings;
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

        $location = $this->locationChart($campaignId);
        if ($location !== null) {
            $out['localizacao'] = $location;
        }

        $triadeParts = $this->triadePartsChart($campaignId);
        if ($triadeParts !== null) {
            $out['triade_parts'] = $triadeParts;
        }

        $qualidade = $this->qualidadeChart($campaignId);
        if ($qualidade !== null) {
            $out['qualidade'] = $qualidade;
        }

        $this->mergeInferenceCharts($out, $inferences);

        $inclusao = $this->inclusaoChart($campaignId);
        if ($inclusao !== null) {
            $out['inclusao'] = $inclusao;
        }

        $aeeGap = $this->aeeGapChart($campaignId);
        if ($aeeGap !== null) {
            $out['aee_gap'] = $aeeGap;
        }

        $under = $this->underreportingChart($campaignId);
        if ($under !== null) {
            $out['subnotificacao'] = $under;
        }

        $neeEscolas = $this->neeEscolasChart($campaignId);
        if ($neeEscolas !== null) {
            $out['nee_escolas'] = $neeEscolas;
        }

        $deltas = $this->deltasChart($campaignId);
        if ($deltas !== null) {
            $out['deltas'] = $deltas;
        }

        $escolas = $this->escolasChart($campaignId);
        if ($escolas !== null) {
            $out['escolas'] = $escolas;
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $out
     * @param  array<string, array<string, mixed>>  $inferences
     */
    private function mergeInferenceCharts(array &$out, array $inferences): void
    {
        $dis = $inferences['INF-DIS'] ?? [];
        $den = $inferences['INF-DEN'] ?? [];
        $doc = $inferences['INF-DOC'] ?? [];
        $dem = $inferences['INF-DEM'] ?? [];
        $tra = $inferences['INF-TRA'] ?? [];
        $jor = $inferences['INF-JOR'] ?? [];
        $tur = $inferences['INF-TUR'] ?? [];
        $gap = $inferences['INF-GAP'] ?? [];

        $stack = $this->distortionStackChart($dis);
        if ($stack !== null) {
            $out['distorcao_stack'] = $stack;
        }
        $byEtapa = $this->distortionByEtapaChart($dis);
        if ($byEtapa !== null) {
            $out['distorcao_etapas'] = $byEtapa;
        }

        $dens = $this->densityChart($den);
        if ($dens !== null) {
            $out['densidade'] = $dens;
        }

        $docentes = $this->docentesChart($doc);
        if ($docentes !== null) {
            $out['docentes'] = $docentes;
        }

        $turmasTipo = $this->turmasTipoChart($tur);
        if ($turmasTipo !== null) {
            $out['turmas_tipo'] = $turmasTipo;
        }

        $cor = $this->assocDoughnutOrBar(
            __('Cor/Raça'),
            is_array($dem['by_cor_raca'] ?? null) ? $dem['by_cor_raca'] : [],
            __('Matrículas nas Relações'),
            __('Fonte: INF-DEM · sem identificação pessoal.'),
        );
        if ($cor !== null) {
            $out['dem_cor'] = $cor;
        }

        $sexo = $this->assocDoughnutOrBar(
            __('Sexo'),
            is_array($dem['by_sexo'] ?? null) ? $dem['by_sexo'] : [],
            __('Matrículas nas Relações'),
            __('Fonte: INF-DEM.'),
            true,
        );
        if ($sexo !== null) {
            $out['dem_sexo'] = $sexo;
        }

        $idade = $this->assocBarHorizontal(
            __('Faixa etária'),
            is_array($dem['by_faixa_etaria'] ?? null) ? $dem['by_faixa_etaria'] : [],
            __('Alunos'),
            __('Estimativa a partir da data de nascimento · INF-DEM.'),
            false,
        );
        if ($idade !== null) {
            $out['dem_idade'] = $idade;
        }

        $activeTra = is_array($tra['active'] ?? null) ? $tra['active'] : $tra;
        $traLoc = $this->assocDoughnutOrBar(
            __('Transporte — localização'),
            is_array($activeTra['by_location_users'] ?? null)
                ? $activeTra['by_location_users']
                : (is_array($tra['by_location_users'] ?? null) ? $tra['by_location_users'] : []),
            __('Usuários de transporte (escolas ativas)'),
            __('Fonte: INF-TRA.'),
        );
        if ($traLoc !== null) {
            $out['tra_local'] = $traLoc;
        }

        $traVeic = $this->assocBarHorizontal(
            __('Transporte — tipo de veículo'),
            is_array($activeTra['by_veiculo'] ?? null)
                ? $activeTra['by_veiculo']
                : (is_array($tra['by_veiculo'] ?? null) ? $tra['by_veiculo'] : []),
            __('Usuários'),
            __('Fonte: INF-TRA · escolas ativas quando disponível.'),
            true,
        );
        if ($traVeic !== null) {
            $out['tra_veiculo'] = $traVeic;
        }

        $rebucketed = (new \App\Services\Clio\Analysis\RelationCsvAggregator)->rebucketTurnoCounts(
            is_array($jor['by_turno'] ?? null) ? $jor['by_turno'] : [],
            is_array($jor['by_turno_outros'] ?? null) ? $jor['by_turno_outros'] : [],
        );
        $turno = $this->assocBarHorizontal(
            __('Turmas por turno'),
            $rebucketed['by_turno'],
            __('Turmas'),
            __('Fonte: INF-JOR · períodos canónicos (Outros detalhados na Análise).'),
            true,
        );
        if ($turno !== null) {
            $out['jornada_turno'] = $turno;
        }

        $jorPad = $this->jornadaPadroesChart($jor);
        if ($jorPad !== null) {
            $out['jornada_padroes'] = $jorPad;
        }

        $gapChart = $this->gapChart($gap);
        if ($gapChart !== null) {
            $out['gap'] = $gapChart;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findingsChart(BiClioCampaign $bi): ?array
    {
        $e = (int) $bi->findings_errors;
        $w = (int) $bi->findings_warnings;
        if ($e + $w <= 0) {
            return null;
        }
        $chart = ChartPayload::doughnut(__('Achados da coleta'), [
            __('Erros'),
            __('Atenções'),
        ], [$e, $w]);
        $chart['subtitle'] = __('Classificação dos apontamentos na análise.');
        $chart['kpi_total'] = $e + $w;
        $chart['kpi_total_label'] = __('Total de achados');

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $dis
     * @return array<string, mixed>|null
     */
    private function distortionStackChart(array $dis): ?array
    {
        $rows = [
            [__('Adequados'), (int) ($dis['adequado'] ?? 0)],
            [__('Atraso 1 ano'), (int) ($dis['atraso_1'] ?? 0)],
            [__('Distorção'), (int) ($dis['distorcao'] ?? 0)],
            [__('Adiantados'), (int) ($dis['adiantado'] ?? 0)],
        ];
        $labels = [];
        $values = [];
        foreach ($rows as [$l, $n]) {
            if ($n > 0) {
                $labels[] = $l;
                $values[] = $n;
            }
        }
        if ($values === []) {
            return null;
        }
        $chart = ChartPayload::doughnut(__('Distorção idade-série'), $labels, $values);
        $pct = $dis['pct_distorcao'] ?? null;
        $chart['subtitle'] = is_numeric($pct)
            ? __('Taxa de distorção: :p% · critério INEP (≥ margem em 31/03).', [
                'p' => number_format((float) $pct, 1, ',', '.'),
            ])
            : __('Classificação no escopo EF/EM seriado.');
        $chart['kpi_total'] = (int) array_sum($values);
        $chart['kpi_total_label'] = __('Alunos no escopo');
        $chart['footnote'] = __('Fonte: INF-DIS · EJA/AEE/AC fora do denominador.');

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $dis
     * @return array<string, mixed>|null
     */
    private function distortionByEtapaChart(array $dis): ?array
    {
        $by = is_array($dis['by_etapa'] ?? null) ? $dis['by_etapa'] : [];
        if ($by === []) {
            return null;
        }

        $rows = [];
        foreach ($by as $etapa => $row) {
            if (! is_array($row)) {
                continue;
            }
            $eligible = (int) ($row['eligible'] ?? 0);
            if ($eligible <= 0) {
                continue;
            }
            $pct = isset($row['pct']) && is_numeric($row['pct'])
                ? (float) $row['pct']
                : (isset($row['distorcao']) ? round(100 * (int) $row['distorcao'] / $eligible, 1) : null);
            if ($pct === null) {
                continue;
            }
            $rows[] = ['etapa' => (string) $etapa, 'pct' => $pct, 'eligible' => $eligible];
        }
        if ($rows === []) {
            return null;
        }

        usort($rows, fn (array $a, array $b): int => $this->etapaOrder->compare($a['etapa'], $b['etapa']));
        $rows = array_slice($rows, 0, self::ETAPAS_TOP);

        $chart = ChartPayload::barHorizontal(
            __('Distorção % por etapa'),
            __('% distorção'),
            array_column($rows, 'etapa'),
            array_column($rows, 'pct'),
        );
        $chart['subtitle'] = __('Até :n etapas com base elegível.', ['n' => self::ETAPAS_TOP]);
        $chart['footnote'] = __('Fonte: INF-DIS.by_etapa.');

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $den
     * @return array<string, mixed>|null
     */
    private function densityChart(array $den): ?array
    {
        $com = (int) ($den['turmas_com_aluno'] ?? 0);
        $sem = (int) ($den['turmas_sem_aluno'] ?? 0);
        $ge40 = (int) ($den['turmas_ge_40'] ?? 0);
        if ($com + $sem + $ge40 <= 0) {
            return null;
        }
        $chart = ChartPayload::bar(
            __('Densidade das turmas curriculares'),
            __('Turmas'),
            [__('Com aluno'), __('Sem aluno'), __('≥ 40 alunos')],
            [$com, $sem, $ge40],
        );
        $media = $den['media_alunos_por_turma'] ?? null;
        $chart['subtitle'] = is_numeric($media)
            ? __('Média :m alunos/turma curricular.', ['m' => number_format((float) $media, 1, ',', '.')])
            : __('AEE/AC fora do denominador.');
        $chart['footnote'] = __('Fonte: INF-DEN.');

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>|null
     */
    private function docentesChart(array $doc): ?array
    {
        $com = (int) ($doc['turmas_com_docente'] ?? 0);
        $sem = (int) ($doc['turmas_sem_docente'] ?? 0);
        if ($com + $sem <= 0) {
            return null;
        }
        $chart = ChartPayload::doughnut(__('Turmas × vínculo de profissional'), [
            __('Com vínculo'),
            __('Sem vínculo'),
        ], [$com, $sem]);
        $chart['subtitle'] = __('Relação de profissionais × códigos de turma.');
        $chart['footnote'] = __('Fonte: INF-DOC.');

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $tur
     * @return array<string, mixed>|null
     */
    private function turmasTipoChart(array $tur): ?array
    {
        $b = is_array($tur['by_tipo_bucket'] ?? null) ? $tur['by_tipo_bucket'] : [];
        $map = [
            __('Curricular') => (int) ($b['curricular'] ?? 0),
            __('AEE') => (int) ($b['aee'] ?? 0),
            __('Ativ. complementar') => (int) ($b['atividade_complementar'] ?? 0),
            __('Outras') => (int) ($b['outra'] ?? 0),
        ];
        $labels = [];
        $values = [];
        foreach ($map as $l => $n) {
            if ($n > 0) {
                $labels[] = $l;
                $values[] = $n;
            }
        }
        if ($values === []) {
            return null;
        }
        $chart = ChartPayload::doughnut(__('Turmas por tipo'), $labels, $values);
        $chart['subtitle'] = __('Classificação da Relação de turmas.');
        $chart['kpi_total'] = (int) array_sum($values);
        $chart['kpi_total_label'] = __('Turmas');
        $chart['footnote'] = __('Fonte: INF-TUR.');

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $jor
     * @return array<string, mixed>|null
     */
    private function jornadaPadroesChart(array $jor): ?array
    {
        $rows = [
            [__('Fund. + AEE'), (int) ($jor['fund_aee_contraturno'] ?? 0)],
            [__('Curricular + AC'), (int) ($jor['curricular_ac'] ?? 0)],
            [__('Infantil estendido'), (int) ($jor['infantil_turma_estendida'] ?? 0)],
            [__('Multi-matrícula'), (int) ($jor['multi_enrollment'] ?? 0)],
        ];
        $labels = [];
        $values = [];
        foreach ($rows as [$l, $n]) {
            if ($n > 0) {
                $labels[] = $l;
                $values[] = $n;
            }
        }
        if ($values === []) {
            return null;
        }
        $chart = ChartPayload::bar(
            __('Padrões de jornada'),
            __('Alunos / ocorrências'),
            $labels,
            $values,
        );
        $chart['subtitle'] = __('Indicadores de organização do tempo escolar.');
        $chart['footnote'] = __('Fonte: INF-JOR.');

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $gap
     * @return array<string, mixed>|null
     */
    private function gapChart(array $gap): ?array
    {
        $clio = (int) ($gap['only_in_clio'] ?? $gap['only_clio'] ?? $gap['clio_only'] ?? 0);
        $ie = (int) ($gap['only_in_ieducar'] ?? $gap['only_ieducar'] ?? $gap['ieducar_only'] ?? 0);
        $matched = (int) ($gap['matched'] ?? 0);
        if ($clio + $ie + $matched <= 0) {
            return null;
        }
        $labels = [];
        $values = [];
        foreach ([
            [__('Só no Clio'), $clio],
            [__('Só no i-Educar'), $ie],
            [__('Em ambos'), $matched],
        ] as [$l, $n]) {
            if ($n > 0) {
                $labels[] = $l;
                $values[] = $n;
            }
        }
        $chart = ChartPayload::doughnut(__('Cruzamento Clio × i-Educar'), $labels, $values);
        $chart['subtitle'] = __('Escolas pelo código INEP.');
        $chart['footnote'] = __('Fonte: INF-GAP.');

        return $chart;
    }

    /**
     * @param  array<string, int|float>  $assoc
     * @return array<string, mixed>|null
     */
    private function assocDoughnutOrBar(
        string $title,
        array $assoc,
        string $subtitle,
        string $footnote,
        bool $forceBar = false,
    ): ?array {
        $clean = $this->normalizeCounts($assoc);
        if ($clean === []) {
            return null;
        }
        arsort($clean);
        if (count($clean) > self::DEM_TOP) {
            $clean = array_slice($clean, 0, self::DEM_TOP, true);
        }
        $labels = array_map('strval', array_keys($clean));
        $values = array_map('intval', array_values($clean));
        $chart = ($forceBar || count($labels) > 6)
            ? ChartPayload::barHorizontal($title, __('Quantidade'), $labels, $values)
            : ChartPayload::doughnut($title, $labels, $values);
        $chart['subtitle'] = $subtitle;
        $chart['footnote'] = $footnote;
        $chart['kpi_total'] = (int) array_sum($values);
        $chart['kpi_total_label'] = __('Total exibido');

        return $chart;
    }

    /**
     * @param  array<string, int|float>  $assoc
     * @return array<string, mixed>|null
     */
    private function assocBarHorizontal(
        string $title,
        array $assoc,
        string $datasetLabel,
        string $footnote,
        bool $sortDesc,
    ): ?array {
        $clean = $this->normalizeCounts($assoc);
        if ($clean === []) {
            return null;
        }
        if ($sortDesc) {
            arsort($clean);
        }
        if (count($clean) > self::DEM_TOP) {
            $clean = array_slice($clean, 0, self::DEM_TOP, true);
        }
        $chart = ChartPayload::barHorizontal(
            $title,
            $datasetLabel,
            array_map('strval', array_keys($clean)),
            array_map('intval', array_values($clean)),
        );
        $chart['footnote'] = $footnote;
        $chart['kpi_total'] = (int) array_sum($clean);
        $chart['kpi_total_label'] = __('Total exibido');

        return $chart;
    }

    /**
     * @param  array<string, int|float|string>  $assoc
     * @return array<string, int>
     */
    private function normalizeCounts(array $assoc): array
    {
        $out = [];
        foreach ($assoc as $k => $v) {
            $n = is_numeric($v) ? (int) $v : 0;
            if ($n <= 0) {
                continue;
            }
            $label = trim((string) $k);
            if ($label === '') {
                continue;
            }
            $out[$label] = ($out[$label] ?? 0) + $n;
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

        $top = $rows
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
    private function locationChart(int $campaignId): ?array
    {
        $rows = BiClioSchool::query()
            ->where('campaign_id', $campaignId)
            ->where('is_active', true)
            ->get(['location']);

        if ($rows->isEmpty()) {
            return null;
        }

        $counts = [];
        foreach ($rows as $s) {
            $loc = trim((string) ($s->location ?? ''));
            if ($loc === '') {
                $loc = __('Não informado');
            }
            $counts[$loc] = ($counts[$loc] ?? 0) + 1;
        }

        return $this->assocDoughnutOrBar(
            __('Escolas ativas por localização'),
            $counts,
            __('Urbana / rural conforme cadastro da coleta.'),
            __('Fonte: bi_clio_school.location.'),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function triadePartsChart(int $campaignId): ?array
    {
        $rows = BiClioSchool::query()
            ->where('campaign_id', $campaignId)
            ->where('is_active', true)
            ->get(['triade_parts']);

        if ($rows->isEmpty()) {
            return null;
        }

        $bucket = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
        foreach ($rows as $s) {
            $p = max(0, min(3, (int) $s->triade_parts));
            $bucket[$p]++;
        }
        if (array_sum($bucket) <= 0) {
            return null;
        }

        $chart = ChartPayload::bar(
            __('Completude da tríade (0–3)'),
            __('Escolas ativas'),
            [__('0 ficheiros'), __('1 ficheiro'), __('2 ficheiros'), __('3 ficheiros')],
            [$bucket[0], $bucket[1], $bucket[2], $bucket[3]],
        );
        $chart['subtitle'] = __('Alunos + turmas + profissionais por unidade.');
        $chart['footnote'] = __('Fonte: bi_clio_school.triade_parts.');

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
    private function underreportingChart(int $campaignId): ?array
    {
        $total = (int) BiClioInclusion::query()
            ->where('campaign_id', $campaignId)
            ->sum('qt_underreporting');
        $nee = (int) BiClioInclusion::query()
            ->where('campaign_id', $campaignId)
            ->sum('qt_nee_people');
        if ($total <= 0) {
            return null;
        }

        $chart = ChartPayload::bar(
            __('Possível subnotificação NEE'),
            __('Pessoas'),
            [__('Com alerta'), __('Demais NEE')],
            [$total, max(0, $nee - $total)],
        );
        $chart['subtitle'] = __('Comorbidade / tipificação incompleta entre deficiências e transtornos.');
        $chart['footnote'] = __('Fonte: bi_clio_inclusion.qt_underreporting.');

        return $chart;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function neeEscolasChart(int $campaignId): ?array
    {
        $rows = BiClioInclusion::query()
            ->where('campaign_id', $campaignId)
            ->where('qt_nee_people', '>', 0)
            ->orderByDesc('qt_nee_people')
            ->limit(self::ESCOLAS_TOP)
            ->get(['inep', 'qt_nee_people']);

        if ($rows->isEmpty()) {
            return null;
        }

        $names = BiClioSchool::query()
            ->where('campaign_id', $campaignId)
            ->whereIn('inep', $rows->pluck('inep'))
            ->pluck('name', 'inep');

        $labels = [];
        $values = [];
        foreach ($rows as $r) {
            $name = (string) ($names[$r->inep] ?? $r->inep);
            if (mb_strlen($name) > 42) {
                $name = mb_substr($name, 0, 41).'…';
            }
            $labels[] = $name;
            $values[] = (int) $r->qt_nee_people;
        }

        $chart = ChartPayload::barHorizontal(
            __('NEE por escola'),
            __('Pessoas'),
            $labels,
            $values,
        );
        $chart['subtitle'] = __('Top :n unidades com mais pessoas tipificadas.', ['n' => self::ESCOLAS_TOP]);
        $chart['footnote'] = __('Fonte: bi_clio_inclusion · sem PII de alunos.');

        return $chart;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function deltasChart(int $campaignId): ?array
    {
        $schools = BiClioSchool::query()
            ->where('campaign_id', $campaignId)
            ->where('is_active', true)
            ->whereNotNull('delta_curricular')
            ->get(['name', 'delta_curricular']);

        $ranked = $schools
            ->filter(fn ($s) => (int) $s->delta_curricular !== 0)
            ->sortByDesc(fn ($s) => abs((int) $s->delta_curricular))
            ->take(self::ESCOLAS_TOP)
            ->values();

        if ($ranked->isEmpty()) {
            return null;
        }

        $labels = $ranked->map(function ($s) {
            $name = (string) $s->name;
            if (mb_strlen($name) > 42) {
                $name = mb_substr($name, 0, 41).'…';
            }

            return $name;
        })->all();

        $chart = ChartPayload::barHorizontal(
            __('Delta Acomp × Relação de alunos'),
            __('Diferença (linhas)'),
            $labels,
            $ranked->map(fn ($s) => (float) $s->delta_curricular)->all(),
        );
        $chart['subtitle'] = __('Valores positivos: mais linhas na Relação do que no Acomp.');
        $chart['footnote'] = __('Fonte: bi_clio_school.delta_curricular.');

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
