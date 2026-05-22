<?php

namespace App\Support\Analytics;

use App\Models\City;
use App\Models\SaebIndicatorPoint;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\CityDataConnection;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Fundeb\FundebReferenceSource;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Support\Facades\DB;

/**
 * Quadros comparativos do PDF analítico (estilo portarias/cadernos FNDE–MEC).
 */
final class AnalyticsReportComparatives
{
    public function __construct(
        private FundebMunicipioReferenceRepository $fundebRefs,
        private FundebFndeReceitaCsvService $fndeReceita,
        private CityDataConnection $cityData,
    ) {}

    /**
     * @param  array<string, mixed>  $fundeb
     * @param  array<string, mixed>  $health
     * @return array{
     *   legal_notice: string,
     *   fundeb_years: array<string, mixed>,
     *   state_participation: array<string, mixed>,
     *   year_comparison_enriched: list<array<string, mixed>>,
     *   municipal_vs_state_enriched: array<string, mixed>
     * }
     */
    public function build(City $city, IeducarFilterState $filters, array $fundeb, array $health): array
    {
        $yearRows = $this->enrichYearComparison($city, $filters);
        $munState = $this->enrichMunicipalVsState($city, $filters);

        $vaafProfile = is_array($fundeb['vaaf_profile'] ?? null) ? $fundeb['vaaf_profile'] : [];
        $refTables = app(AnalyticsReportFundebReferenceTables::class)->build($vaafProfile, $fundeb);

        return [
            'legal_notice' => __(
                'Documento de apoio à gestão municipal, elaborado a partir do cadastro i-Educar, referências importadas (FNDE/dados abertos) e indicadores pedagógicos. Não substitui portaria de complementação, extrato Simec/VAAR nem prestação de contas ao FNDE. Consulte sempre o material oficial em gov.br/fnde (Fundeb) e inep.gov.br (Educacenso/SAEB).'
            ),
            'fundeb_years' => $this->fundebYearSeries($city, $filters, $fundeb, $health),
            'fundeb_reference_tables' => $refTables,
            'state_participation' => $this->stateFundebParticipation($city, $filters),
            'year_comparison_enriched' => $yearRows,
            'municipal_vs_state_enriched' => $munState,
        ];
    }

    /**
     * @param  array<string, mixed>  $fundeb
     * @param  array<string, mixed>  $health
     * @return array<string, mixed>
     */
    private function fundebYearSeries(City $city, IeducarFilterState $filters, array $fundeb, array $health): array
    {
        $refs = $this->fundebRefs->listForCity($city)
            ->filter(static fn ($r) => ! FundebReferenceSource::isPlaceholder($r->fonte))
            ->unique(static fn ($r) => (int) $r->ano)
            ->sortByDesc('ano')
            ->values();

        if ($refs->isEmpty()) {
            $refs = $this->fundebRefs->listForCity($city)->unique(static fn ($r) => (int) $r->ano)->sortByDesc('ano')->values();
        }

        $anchor = $filters->hasYearSelected() && ! $filters->isAllSchoolYears()
            ? (int) $filters->ano_letivo
            : FundebOpenDataImportService::suggestedImportYear();

        $rows = [];
        $prevVaaf = null;
        foreach ($refs->take(6) as $ref) {
            $vaaf = (float) $ref->vaaf;
            $delta = null;
            if ($prevVaaf !== null && $prevVaaf > 0) {
                $delta = round((($vaaf - $prevVaaf) / $prevVaaf) * 100, 1);
            }
            $complVaaf = $ref->complementacao_vaaf !== null
                ? (float) $ref->complementacao_vaaf
                : null;

            $rows[] = [
                'ano' => (string) $ref->ano,
                'vaaf' => DiscrepanciesFundingImpact::formatBrl($vaaf),
                'vaat' => $ref->vaat !== null ? DiscrepanciesFundingImpact::formatBrl((float) $ref->vaat) : '—',
                'complementacao_vaaf' => $complVaaf !== null ? DiscrepanciesFundingImpact::formatBrl($complVaaf) : '—',
                'complementacao_vaar' => $ref->complementacao_vaar !== null
                    ? DiscrepanciesFundingImpact::formatBrl((float) $ref->complementacao_vaar)
                    : '—',
                'fonte' => (string) ($ref->fonte ?? '—'),
                'variacao_vaaf_pct' => $delta !== null
                    ? (($delta >= 0 ? '+' : '').number_format($delta, 1, ',', '.').'%')
                    : '—',
                'is_anchor' => (int) $ref->ano === $anchor,
            ];
            $prevVaaf = $vaaf;
        }

        $vaafComp = is_array($health['vaaf_comparacao'] ?? null) ? $health['vaaf_comparacao'] : [];
        $previaFederal = is_array($vaafComp['previa'] ?? null) ? ($vaafComp['previa']['value'] ?? null) : null;

        return [
            'available' => $rows !== [],
            'title' => __('Quadro comparativo — VAAF/VAAT por exercício (referência municipal)'),
            'subtitle' => __('Alinhado ao conceito de Valor Aluno Ano do Fundeb (Lei nº 14.113/2020) e anexos de complementação publicados pelo FNDE.'),
            'rows' => $rows,
            'previa_federal' => is_string($previaFederal) ? $previaFederal : null,
            'previsao_label' => data_get($fundeb, 'resource_projection.previsao_referencia_label'),
            'note' => $rows === []
                ? __('Importe referências oficiais (Admin → Compatibilidade → FUNDEB) para preencher a série histórica.')
                : __('Valores gravados em fundeb_municipio_references. Placeholders nacionais aparecem apenas se não houver dado municipal.'),
        ];
    }

    /**
     * Participação do município na receita Fundeb da UF (CSV FNDE «Receita total por ente»).
     *
     * @return array<string, mixed>
     */
    private function stateFundebParticipation(City $city, IeducarFilterState $filters): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        $uf = strtoupper(trim((string) ($city->uf ?? '')));
        if ($ibge === null || $uf === '') {
            return [
                'available' => false,
                'title' => __('Quadro — Participação do município na receita Fundeb da UF'),
                'rows' => [],
                'note' => __('Configure IBGE e UF do município para calcular a participação estadual.'),
            ];
        }

        $ano = $filters->hasYearSelected() && ! $filters->isAllSchoolYears()
            ? (int) $filters->ano_letivo
            : FundebOpenDataImportService::suggestedImportYear();

        $munRow = $this->fndeReceita->rowForIbge($ibge, $ano);
        $pubYear = (int) ($munRow['ano_publicacao'] ?? $ano);
        $index = $this->fndeReceita->loadYearIndex($pubYear);

        $ufReceita = 0.0;
        $munReceita = (float) ($munRow['total_receita'] ?? 0);
        foreach ($index as $row) {
            if (strtoupper(trim((string) ($row['uf'] ?? ''))) === $uf) {
                $ufReceita += (float) ($row['total_receita'] ?? 0);
            }
        }

        $participacaoReceita = ($munReceita > 0 && $ufReceita > 0)
            ? round(($munReceita / $ufReceita) * 100, 2)
            : null;

        $matMun = $this->matriculasForCityYear($city, $ano);
        $matUfRede = $this->matriculasUfCadastradas($uf, $ano);
        $participacaoMat = ($matMun > 0 && $matUfRede > 0)
            ? round(($matMun / $matUfRede) * 100, 2)
            : null;

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $rows = [];

        if ($participacaoReceita !== null) {
            $rows[] = [
                'indicador' => __('Receita total prevista do Fundeb'),
                'municipio' => $fmt($munReceita),
                'referencia_uf' => $fmt($ufReceita),
                'participacao' => number_format($participacaoReceita, 2, ',', '.').'%',
                'fonte' => __('CSV FNDE (Portaria/complementação :ano)', ['ano' => (string) $pubYear]),
            ];
        }

        if ($participacaoMat !== null) {
            $rows[] = [
                'indicador' => __('Matrículas activas (cadastro i-Educar)'),
                'municipio' => number_format($matMun, 0, ',', '.'),
                'referencia_uf' => number_format($matUfRede, 0, ',', '.').' ('.__('soma municípios :uf na plataforma', ['uf' => $uf]).')',
                'participacao' => number_format($participacaoMat, 2, ',', '.').'%',
                'fonte' => __('i-Educar · ano :ano', ['ano' => (string) $ano]),
            ];
        }

        foreach ([
            'complementacao_vaaf' => __('Complementação VAAF (anexo FNDE)'),
            'complementacao_vaat' => __('Complementação VAAT (anexo FNDE)'),
            'complementacao_vaar' => __('Complementação VAAR (anexo FNDE)'),
        ] as $field => $label) {
            if ($munRow !== null && isset($munRow[$field]) && is_numeric($munRow[$field]) && (float) $munRow[$field] > 0) {
                $rows[] = [
                    'indicador' => $label,
                    'municipio' => $fmt((float) $munRow[$field]),
                    'referencia_uf' => '—',
                    'participacao' => '—',
                    'fonte' => __('CSV Portaria FNDE :ano', ['ano' => (string) $pubYear]),
                ];
            }
        }

        return [
            'available' => $rows !== [],
            'title' => __('Quadro — Participação do município no contexto da UF (:uf)', ['uf' => $uf]),
            'subtitle' => __(
                'Modelo inspirado nos anexos «Receita total do Fundeb por ente federado» e quadros de complementação (VAAF/VAAT/VAAR) das portarias FNDE/MEC.'
            ),
            'exercicio' => (string) $ano,
            'publicacao_fnde' => (string) $pubYear,
            'rows' => $rows,
            'note' => $participacaoReceita === null && $participacaoMat === null
                ? __('Baixe/cacheie o CSV FNDE (fundeb:import-api ou IEDUCAR_FUNDEB_FNDE_RECEITA) para a participação na receita estadual.')
                : __('A participação na receita usa todos os municípios do CSV da UF; matrículas usam apenas redes cadastradas nesta plataforma (proxy operacional).'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function enrichYearComparison(City $city, IeducarFilterState $filters): array
    {
        if (! $filters->hasYearSelected() || $filters->isAllSchoolYears()) {
            return [];
        }

        $current = (int) $filters->ano_letivo;
        $years = array_values(array_unique([$current, $current - 1, $current - 2]));
        sort($years);
        $rows = [];
        $prevMat = null;

        foreach ($years as $year) {
            if ($year < 2000) {
                continue;
            }
            $f = new IeducarFilterState(
                ano_letivo: (string) $year,
                escola_id: $filters->escola_id,
                curso_id: $filters->curso_id,
                turno_id: $filters->turno_id,
            );
            $mat = $this->matriculasForCityYear($city, $year);
            $ref = $this->fundebRefs->findForCityYear($city, $year);
            $vaaf = $ref !== null && ! FundebReferenceSource::isPlaceholder($ref->fonte)
                ? (float) $ref->vaaf
                : null;

            $variacao = null;
            if ($prevMat !== null && $prevMat > 0 && $mat > 0) {
                $variacao = round((($mat - $prevMat) / $prevMat) * 100, 1);
            }

            $rows[] = [
                'ano' => (string) $year,
                'matriculas' => $mat > 0 ? $mat : null,
                'matriculas_fmt' => $mat > 0 ? number_format($mat, 0, ',', '.') : '—',
                'vaaf' => $vaaf !== null ? DiscrepanciesFundingImpact::formatBrl($vaaf) : '—',
                'variacao_mat_pct' => $variacao !== null
                    ? (($variacao >= 0 ? '+' : '').number_format($variacao, 1, ',', '.').'%')
                    : '—',
                'label' => $year === $current ? __('Ano de referência do relatório') : __('Série histórica'),
            ];
            if ($mat > 0) {
                $prevMat = $mat;
            }
        }

        return array_reverse($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichMunicipalVsState(City $city, IeducarFilterState $filters): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return [
                'available' => false,
                'title' => __('Quadro — Desempenho SAEB: município × UF'),
                'rows' => [],
                'note' => __('Código IBGE do município não configurado.'),
            ];
        }

        $maxYear = $filters->hasYearSelected() && ! $filters->isAllSchoolYears()
            ? (int) $filters->ano_letivo
            : (int) date('Y');

        try {
            $munPoints = SaebIndicatorPoint::query()
                ->where('ibge_municipio', $ibge)
                ->where('ano', '<=', $maxYear)
                ->whereIn('disciplina', ['lp', 'mat', 'Língua Portuguesa', 'Matemática'])
                ->orderByDesc('ano')
                ->limit(20)
                ->get();

            $uf = strtoupper(trim((string) ($city->uf ?? '')));
            $stateQuery = SaebIndicatorPoint::query()
                ->where('ano', '<=', $maxYear)
                ->whereNull('city_id')
                ->orderByDesc('ano')
                ->limit(40);
            if ($uf !== '' && DB::connection()->getDriverName() === 'pgsql') {
                $stateQuery->whereRaw('raw_point::text ilike ?', ['%'.$uf.'%']);
            }
            $statePoints = $stateQuery->get();
        } catch (\Throwable) {
            return [
                'available' => false,
                'title' => __('Quadro — Desempenho SAEB: município × UF'),
                'rows' => [],
                'note' => __('Indicadores SAEB indisponíveis neste ambiente.'),
            ];
        }

        $rows = [];
        foreach (['lp' => 'Língua Portuguesa', 'mat' => 'Matemática'] as $disc => $label) {
            $m = $munPoints->first(fn ($p) => in_array((string) $p->disciplina, [$disc, $label], true));
            $s = $statePoints->first(fn ($p) => in_array((string) $p->disciplina, [$disc, $label], true)
                && (str_contains(strtolower((string) json_encode($p->raw_point)), 'uf')
                    || str_contains(strtolower((string) json_encode($p->raw_point)), 'estado')));
            if ($m === null && $s === null) {
                continue;
            }
            $valM = $m !== null && $m->valor !== null ? (float) $m->valor : null;
            $valS = $s !== null && $s->valor !== null ? (float) $s->valor : null;
            $gap = ($valM !== null && $valS !== null) ? round($valM - $valS, 1) : null;
            $rows[] = [
                'disciplina' => $label,
                'ano_municipio' => $m !== null ? (string) $m->ano : '—',
                'valor_municipio' => $valM !== null ? number_format($valM, 1, ',', '.') : '—',
                'ano_estado' => $s !== null ? (string) $s->ano : '—',
                'valor_estado' => $valS !== null ? number_format($valS, 1, ',', '.') : '—',
                'diferenca' => $gap !== null
                    ? (($gap >= 0 ? '+' : '').number_format($gap, 1, ',', '.'))
                    : '—',
                'leitura' => $gap === null
                    ? '—'
                    : ($gap >= 0 ? __('Município ≥ UF') : __('Município < UF')),
            ];
        }

        return [
            'available' => $rows !== [],
            'title' => __('Quadro — SAEB: rede municipal × referência da UF'),
            'subtitle' => __('Referência pedagógica (INEP/SAEB), em linha com cadernos de resultados — não confundir com indicadores financeiros do Fundeb.'),
            'rows' => $rows,
            'note' => $rows === []
                ? __('Importe séries SAEB em Admin → Sincronizações → Pedagógicas.')
                : __('Fonte: saeb_indicator_points. Compare sempre o mesmo ano/ciclo avaliativo.'),
        ];
    }

    private function matriculasForCityYear(City $city, int $year): int
    {
        $f = new IeducarFilterState(
            ano_letivo: (string) $year,
            escola_id: null,
            curso_id: null,
            turno_id: null,
        );

        try {
            return (int) $this->cityData->run(
                $city,
                static fn ($db) => MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $f) ?? 0,
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    private function matriculasUfCadastradas(string $uf, int $year): int
    {
        try {
            $total = 0;
            $cities = City::query()
                ->whereRaw('upper(trim(uf)) = ?', [$uf])
                ->get(['id', 'name', 'ibge_municipio', 'uf']);

            foreach ($cities as $city) {
                $total += $this->matriculasForCityYear($city, $year);
            }

            return $total;
        } catch (\Throwable) {
            return 0;
        }
    }
}
