<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Services\Inep\SaebHistoricoDatabase;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;

/**
 * Séries históricas do SAEB na aba Desempenho.
 * Os dados vêm da tabela PostgreSQL `saeb_indicator_points` (Sincronizações → Pedagógicas).
 * Cada ponto deve ter «city_ids» (importação oficial por IBGE ou CSV/JSON); sem isso o ponto é ignorado.
 */
final class PerformanceSaebSeries
{
    /**
     * @return array{
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string, footnote?: string}>,
     *   extra_charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string, footnote?: string}>,
     *   summary: ?array<string, mixed>,
     *   school_table: list<array<string, mixed>>,
     *   notes: list<string>,
     *   error: ?string,
     *   source_hint: ?string,
     *   explicacao_modal: ?array<string, mixed>
     * }
     */
    public static function build(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $empty = [
            'charts' => [],
            'extra_charts' => [],
            'summary' => null,
            'school_table' => [],
            'notes' => [],
            'error' => null,
            'source_hint' => null,
            'explicacao_modal' => null,
        ];

        if (! filter_var(config('ieducar.saeb.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return $empty;
        }

        try {
            $bundle = self::loadSaebBundle();
        } catch (\Throwable $e) {
            return [
                'charts' => [],
                'extra_charts' => [],
                'summary' => null,
                'school_table' => [],
                'notes' => [],
                'error' => __('Não foi possível carregar séries SAEB: :msg', ['msg' => $e->getMessage()]),
                'source_hint' => null,
                'explicacao_modal' => null,
            ];
        }

        $points = $bundle['points'];
        $fileMeta = $bundle['meta'];
        $explicacaoModal = $bundle['explicacao_modal'] ?? null;
        $footnoteBase = self::formatFootnoteFromMeta($fileMeta, $bundle['path']);

        if ($points === []) {
            return [
                'charts' => [],
                'extra_charts' => [],
                'summary' => null,
                'school_table' => [],
                'notes' => [
                    __(
                        'Sem dados SAEB utilizáveis na base (:path). Importe em Admin → Sincronizações → Pedagógicas ou confirme que cada ponto tem «city_ids» com o id interno da cidade.',
                        ['path' => $bundle['path']]
                    ),
                ],
                'error' => null,
                'source_hint' => __('Importação pedagógica (tabela saeb_indicator_points) — ver Sincronizações Pedagógicas.'),
                'explicacao_modal' => $explicacaoModal,
            ];
        }

        $points = self::filterPointsForCity($points, $city);
        if ($points === []) {
            return [
                'charts' => [],
                'extra_charts' => [],
                'summary' => null,
                'school_table' => [],
                'notes' => [__('Não há dados SAEB para esta cidade no ficheiro importado (cada ponto deve incluir o id desta cidade em «city_ids»).')],
                'error' => null,
                'source_hint' => $footnoteBase,
                'explicacao_modal' => $explicacaoModal,
            ];
        }

        $maxYear = self::maxYearFilter($filters);
        $pointsYear = array_values(array_filter($points, static fn (array $p): bool => (int) ($p['year'] ?? 0) <= $maxYear));

        if ($pointsYear === []) {
            return [
                'charts' => [],
                'extra_charts' => [],
                'summary' => null,
                'school_table' => [],
                'notes' => [__('Não há resultados SAEB até ao ano seleccionado no filtro.')],
                'error' => null,
                'source_hint' => $footnoteBase,
                'explicacao_modal' => $explicacaoModal,
            ];
        }

        $pointsForCharts = self::filterPointsForSchoolFilter($pointsYear, $filters);
        if ($pointsForCharts === []) {
            $msg = $filters->escola_id !== null
                ? __('Não há pontos SAEB para a escola seleccionada nos dados importados (use escola_id / escola_ids alinhados ao cod_escola do i-Educar).')
                : __('Não há pontos de rede municipal importados (pontos sem escola_id). Importe dados agregados por município ou seleccione uma escola com série própria.');

            return [
                'charts' => [],
                'extra_charts' => [],
                'summary' => self::buildSummaryBlock($fileMeta, $pointsYear, $maxYear, $city),
                'school_table' => self::buildSchoolTableRows($pointsYear, $maxYear, self::resolveEscolaNames($db, $city, $pointsYear)),
                'notes' => [$msg],
                'error' => null,
                'source_hint' => $footnoteBase,
                'explicacao_modal' => $explicacaoModal,
            ];
        }

        $escolaNomes = self::resolveEscolaNames($db, $city, $pointsYear);

        $grouped = [];
        foreach ($pointsForCharts as $p) {
            $key = (string) ($p['series_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $grouped[$key][] = $p;
        }

        $charts = [];
        ksort($grouped);

        foreach ($grouped as $seriesKey => $rows) {
            $chart = self::chartForSeries($seriesKey, $rows, $maxYear, $footnoteBase, $escolaNomes);
            if ($chart !== null) {
                $charts[] = $chart;
            }
        }

        $extraCharts = [];
        if ($filters->escola_id === null) {
            $cmp = self::buildSchoolComparisonChart($pointsYear, $maxYear, $escolaNomes, $footnoteBase);
            if ($cmp !== null) {
                $extraCharts[] = $cmp;
            }
        }
        $schoolTable = self::buildSchoolTableRows(
            $filters->escola_id === null ? $pointsYear : $pointsForCharts,
            $maxYear,
            $escolaNomes
        );

        $hint = __('Círculos verdes — resultado final oficial; triângulos laranja tracejados — preliminar.');
        $yearNote = $filters->hasYearSelected() && ! $filters->isAllSchoolYears()
            ? __('A série mostra todos os anos disponíveis até :ano (inclusive), conforme o filtro de ano letivo.', ['ano' => (string) $filters->ano_letivo])
            : __('A série mostra todos os anos disponíveis na fonte.');
        $schoolNote = $filters->escola_id !== null
            ? __('Filtro de escola activo: séries por cod_escola :id (i-Educar).', ['id' => (string) $filters->escola_id])
            : __('Sem filtro de escola: linhas temporais = rede municipal; quadro e gráfico comparativo usam escolas com pontos importados.');

        return [
            'charts' => $charts,
            'extra_charts' => $extraCharts,
            'summary' => self::buildSummaryBlock($fileMeta, $pointsYear, $maxYear, $city),
            'school_table' => $schoolTable,
            'notes' => [$yearNote, $schoolNote, $hint],
            'error' => null,
            'source_hint' => $footnoteBase,
            'explicacao_modal' => $explicacaoModal,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $fileMeta
     * @param  list<array<string, mixed>>  $pointsYear
     * @return array<string, mixed>
     */
    private static function buildSummaryBlock(?array $fileMeta, array $pointsYear, int $maxYear, City $city): array
    {
        $nMun = 0;
        $nEsc = 0;
        $ids = [];
        foreach ($pointsYear as $p) {
            $eid = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : null;
            $has = ($eid !== null && $eid > 0) || (isset($p['escola_ids']) && is_array($p['escola_ids']) && $p['escola_ids'] !== []);
            if ($has) {
                $nEsc++;
                if ($eid !== null && $eid > 0) {
                    $ids[$eid] = true;
                }
            } else {
                $nMun++;
            }
        }

        $ibge = is_array($fileMeta) && isset($fileMeta['municipio_ibge']) ? trim((string) $fileMeta['municipio_ibge']) : '';
        $munNome = is_array($fileMeta) && isset($fileMeta['municipio_nome']) ? trim((string) $fileMeta['municipio_nome']) : '';

        $lp = self::latestMunicipalDisciplineValue($pointsYear, $maxYear, 'lp');
        $mat = self::latestMunicipalDisciplineValue($pointsYear, $maxYear, 'mat');
        $gap = ($lp !== null && $mat !== null) ? round($lp - $mat, 2) : null;

        $years = [];
        foreach ($pointsYear as $p) {
            $y = (int) ($p['year'] ?? 0);
            if ($y > 0) {
                $years[$y] = true;
            }
        }
        $yMin = $years !== [] ? min(array_keys($years)) : null;
        $yMax = $years !== [] ? min(max(array_keys($years)), $maxYear) : null;

        return [
            'municipio_ibge' => $ibge !== '' ? $ibge : null,
            'municipio_nome' => $munNome !== '' ? $munNome : null,
            'city_id_local' => (int) $city->id,
            'pontos_municipais' => $nMun,
            'pontos_escola' => $nEsc,
            'escolas_distintas' => count($ids),
            'ano_min' => $yMin,
            'ano_max' => $yMax,
            'rede_lp_ultimo' => $lp,
            'rede_mat_ultimo' => $mat,
            'rede_gap_lp_menos_mat' => $gap,
            'decisao_nota' => $gap !== null && $gap < 0
                ? __('Lacuna: Matemática abaixo de Língua Portuguesa no último ponto municipal — priorizar reforço em MAT.')
                : ($gap !== null && $gap > 3
                    ? __('Lacuna: LP abaixo de MAT no último ponto municipal — reforço em leitura/escrita.')
                    : __('Comparar séries e escolas no quadro abaixo para priorizar unidades ou etapas.')),
        ];
    }

    /**
     * Último valor «final» municipal para disciplina (lp|mat), qualquer etapa no JSON.
     *
     * @param  list<array<string, mixed>>  $pointsYear
     */
    private static function latestMunicipalDisciplineValue(array $pointsYear, int $maxYear, string $disc): ?float
    {
        $bestYear = null;
        $bestVal = null;
        foreach ($pointsYear as $p) {
            $sk = (string) ($p['series_key'] ?? '');
            if (! str_ends_with($sk, '|municipal')) {
                continue;
            }
            if (! str_starts_with($sk, strtolower($disc).'|')) {
                continue;
            }
            if (empty($p['is_final'])) {
                continue;
            }
            $y = (int) ($p['year'] ?? 0);
            if ($y <= 0 || $y > $maxYear) {
                continue;
            }
            $v = $p['value'] ?? null;
            if (! is_numeric($v)) {
                continue;
            }
            if ($bestYear === null || $y > $bestYear) {
                $bestYear = $y;
                $bestVal = (float) $v;
            }
        }

        return $bestVal;
    }

    /**
     * @param  list<array<string, mixed>>  $pointsYear
     * @param  array<int, string>  $escolaNomes
     * @return list<array<string, mixed>>
     */
    private static function buildSchoolTableRows(array $pointsYear, int $maxYear, array $escolaNomes): array
    {
        $bySchool = [];
        foreach ($pointsYear as $p) {
            $eid = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : null;
            if ($eid === null || $eid <= 0) {
                continue;
            }
            $y = (int) ($p['year'] ?? 0);
            if ($y <= 0 || $y > $maxYear) {
                continue;
            }
            $sk = (string) ($p['series_key'] ?? '');
            if (! str_starts_with($sk, 'lp|') && ! str_starts_with($sk, 'mat|')) {
                continue;
            }
            $disc = str_starts_with($sk, 'lp|') ? 'lp' : 'mat';
            if (empty($p['is_final'])) {
                continue;
            }
            $v = $p['value'] ?? null;
            if (! is_numeric($v)) {
                continue;
            }
            if (! isset($bySchool[$eid])) {
                $bySchool[$eid] = [];
            }
            if (! isset($bySchool[$eid][$disc]) || $y >= ($bySchool[$eid][$disc]['y'] ?? 0)) {
                $bySchool[$eid][$disc] = ['y' => $y, 'v' => (float) $v];
            }
        }

        $rows = [];
        foreach ($bySchool as $eid => $d) {
            $lp = $d['lp']['v'] ?? null;
            $mat = $d['mat']['v'] ?? null;
            $yLp = $d['lp']['y'] ?? null;
            $yMat = $d['mat']['y'] ?? null;
            $gap = ($lp !== null && $mat !== null) ? round($lp - $mat, 2) : null;
            $rows[] = [
                'escola_id' => $eid,
                'nome' => $escolaNomes[$eid] ?? __('Escola :id', ['id' => (string) $eid]),
                'lp_pct' => $lp,
                'lp_ano' => $yLp,
                'mat_pct' => $mat,
                'mat_ano' => $yMat,
                'gap_lp_menos_mat' => $gap,
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? '')));

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $pointsYear
     * @param  array<int, string>  $escolaNomes
     * @return array<string, mixed>|null
     */
    private static function buildSchoolComparisonChart(array $pointsYear, int $maxYear, array $escolaNomes, string $footnoteBase): ?array
    {
        $refYear = null;
        foreach ($pointsYear as $p) {
            $eid = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : null;
            if ($eid === null || $eid <= 0) {
                continue;
            }
            $y = (int) ($p['year'] ?? 0);
            if ($y > 0 && $y <= $maxYear) {
                $refYear = $refYear === null ? $y : max($refYear, $y);
            }
        }
        if ($refYear === null) {
            return null;
        }

        $schoolIds = [];
        foreach ($pointsYear as $p) {
            $eid = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : null;
            if ($eid !== null && $eid > 0) {
                $schoolIds[$eid] = true;
            }
        }
        $schoolIds = array_keys($schoolIds);
        if ($schoolIds === []) {
            return null;
        }
        sort($schoolIds);

        $labels = [];
        $lpData = [];
        $matData = [];
        foreach ($schoolIds as $eid) {
            $lp = self::schoolDiscAtYear($pointsYear, $eid, $refYear, 'lp', $maxYear);
            $mat = self::schoolDiscAtYear($pointsYear, $eid, $refYear, 'mat', $maxYear);
            if ($lp === null && $mat === null) {
                continue;
            }
            $nome = $escolaNomes[$eid] ?? __('Escola :id', ['id' => (string) $eid]);
            $short = mb_strlen($nome) > 42 ? mb_substr($nome, 0, 39).'…' : $nome;
            $labels[] = $short;
            $lpData[] = $lp;
            $matData[] = $mat;
        }

        if ($labels === []) {
            return null;
        }

        $chart = ChartPayload::barHorizontalGrouped(
            __('SAEB — comparativo por escola (LP e MAT, ano :ano)', ['ano' => (string) $refYear]),
            __('% proficientes (final)'),
            $labels,
            [
                ['label' => __('Língua Portuguesa'), 'data' => $lpData],
                ['label' => __('Matemática'), 'data' => $matData],
            ]
        );
        $chart['subtitle'] = __('Valores do último ano com dados por escola na importação (até :ano do filtro).', ['ano' => (string) $maxYear]);
        $chart['footnote'] = $footnoteBase;

        return $chart;
    }

    /**
     * @param  list<array<string, mixed>>  $pointsYear
     */
    private static function schoolDiscAtYear(array $pointsYear, int $escolaId, int $preferYear, string $disc, int $maxYear): ?float
    {
        $bestY = null;
        $bestV = null;
        foreach ($pointsYear as $p) {
            $eid = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : null;
            if ($eid !== $escolaId) {
                continue;
            }
            $sk = (string) ($p['series_key'] ?? '');
            if (! str_starts_with($sk, strtolower($disc).'|')) {
                continue;
            }
            if (empty($p['is_final'])) {
                continue;
            }
            $y = (int) ($p['year'] ?? 0);
            if ($y <= 0 || $y > $maxYear) {
                continue;
            }
            $v = $p['value'] ?? null;
            if (! is_numeric($v)) {
                continue;
            }
            if ($y !== $preferYear) {
                continue;
            }
            $bestV = (float) $v;
            $bestY = $y;
        }
        if ($bestV !== null) {
            return $bestV;
        }

        foreach ($pointsYear as $p) {
            $eid = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : null;
            if ($eid !== $escolaId) {
                continue;
            }
            $sk = (string) ($p['series_key'] ?? '');
            if (! str_starts_with($sk, strtolower($disc).'|')) {
                continue;
            }
            if (empty($p['is_final'])) {
                continue;
            }
            $y = (int) ($p['year'] ?? 0);
            if ($y <= 0 || $y > $maxYear) {
                continue;
            }
            $v = $p['value'] ?? null;
            if (! is_numeric($v)) {
                continue;
            }
            if ($bestY === null || $y > $bestY) {
                $bestY = $y;
                $bestV = (float) $v;
            }
        }

        return $bestV;
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return list<array<string, mixed>>
     */
    private static function filterPointsForSchoolFilter(array $points, IeducarFilterState $filters): array
    {
        $sel = $filters->escola_id !== null ? (int) $filters->escola_id : null;
        $out = [];
        foreach ($points as $p) {
            $eid = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : null;
            $eids = $p['escola_ids'] ?? null;
            $list = is_array($eids) ? array_values(array_filter(array_map(static fn ($x) => (int) $x, $eids), static fn (int $x): bool => $x > 0)) : [];
            $hasSchool = ($eid !== null && $eid > 0) || $list !== [];

            if ($sel === null) {
                if (! $hasSchool) {
                    $out[] = $p;
                }
            } elseif ($eid !== null && $eid === $sel) {
                $out[] = $p;
            } elseif ($list !== [] && in_array($sel, $list, true)) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return array<int, string>
     */
    private static function resolveEscolaNames(Connection $db, City $city, array $points): array
    {
        $ids = [];
        foreach ($points as $p) {
            if (isset($p['escola_id']) && is_numeric($p['escola_id'])) {
                $ids[(int) $p['escola_id']] = true;
            }
            if (isset($p['escola_ids']) && is_array($p['escola_ids'])) {
                foreach ($p['escola_ids'] as $x) {
                    if (is_numeric($x) && (int) $x > 0) {
                        $ids[(int) $x] = true;
                    }
                }
            }
        }
        $ids = array_keys($ids);
        if ($ids === []) {
            return [];
        }
        sort($ids);

        $out = [];
        $idCol = (string) config('ieducar.columns.escola.id', 'cod_escola');
        $nameCol = (string) config('ieducar.columns.escola.name', 'nome');

        try {
            $tbl = IeducarSchema::resolveTable('escola', $city);
        } catch (\Throwable) {
            return [];
        }

        foreach ($ids as $id) {
            try {
                $row = $db->table($tbl)->where($idCol, $id)->first([$nameCol]);
                if ($row !== null) {
                    $nome = is_object($row) ? ($row->{$nameCol} ?? null) : ($row[$nameCol] ?? null);
                    if (is_string($nome) && trim($nome) !== '') {
                        $out[$id] = trim($nome);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $out;
    }

    /**
     * @return array{points: list<array<string, mixed>>, meta: ?array<string, mixed>, explicacao_modal: ?array<string, mixed>, path: string}
     */
    private static function loadSaebBundle(): array
    {
        return app(SaebHistoricoDatabase::class)->loadBundleForCharts();
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private static function formatFootnoteFromMeta(?array $meta, string $relPath): string
    {
        $bits = [];
        if ($relPath === SaebHistoricoDatabase::STORAGE_LABEL) {
            $bits[] = __('Armazenamento: tabela PostgreSQL (:table).', ['table' => 'saeb_indicator_points']);
        } else {
            $bits[] = __('Ficheiro: :path', ['path' => 'storage/app/public/'.$relPath]);
        }

        if ($meta !== null) {
            if (! empty($meta['fonte_efetiva'])) {
                $bits[] = __('Fonte dos dados: :s', ['s' => (string) $meta['fonte_efetiva']]);
            } elseif (! empty($meta['fonte'])) {
                $bits[] = __('Fonte: :s', ['s' => (string) $meta['fonte']]);
            }
            if (! empty($meta['importado_em'])) {
                $bits[] = __('Importado em: :d', ['d' => (string) $meta['importado_em']]);
            }
            if (! empty($meta['descricao']) && empty($meta['fonte_efetiva'])) {
                $bits[] = (string) $meta['descricao'];
            }
        }

        return implode(' ', $bits);
    }

    private static function maxYearFilter(IeducarFilterState $filters): int
    {
        $y = $filters->yearFilterValue();

        return $y !== null ? $y : 9999;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, string>  $escolaNomes
     */
    private static function chartForSeries(string $seriesKey, array $rows, int $maxYear, string $footnoteBase, array $escolaNomes): ?array
    {
        /** @var array<int, array{final: ?float, prelim: ?float}> $byYear */
        $byYear = [];
        foreach ($rows as $r) {
            $y = (int) ($r['year'] ?? 0);
            if ($y <= 0 || $y > $maxYear) {
                continue;
            }
            $val = $r['value'] ?? null;
            if (! is_numeric($val)) {
                continue;
            }
            $v = (float) $val;
            $isFinal = (bool) ($r['is_final'] ?? true);
            if (! isset($byYear[$y])) {
                $byYear[$y] = ['final' => null, 'prelim' => null];
            }
            if ($isFinal) {
                $byYear[$y]['final'] = $v;
            } else {
                $byYear[$y]['prelim'] = $v;
            }
        }

        if ($byYear === []) {
            return null;
        }

        ksort($byYear, SORT_NUMERIC);
        $years = array_keys($byYear);
        $labels = array_map(static fn (int $y) => (string) $y, $years);

        $finalData = [];
        $prelimData = [];
        foreach ($years as $y) {
            $cell = $byYear[$y];
            $finalData[] = $cell['final'];
            $prelimData[] = $cell['prelim'];
        }

        [$disc, $etapa, $scope] = self::parseSeriesKey($seriesKey);
        $title = self::seriesTitle($disc, $etapa, $scope, $escolaNomes);
        $unit = (string) ($rows[0]['unidade'] ?? '%');
        $yLabel = $unit !== '' && $unit !== '%' ? $unit : __('Percentagem / escala');

        $subtitle = __(
            'Série histórica até ao ano do filtro. O INEP divulga resultados preliminares antes da versão final; use a legenda para distinguir.'
        );

        return ChartPayload::lineSaebHistory(
            $title,
            $yLabel,
            $labels,
            $finalData,
            $prelimData,
            $subtitle,
            $footnoteBase
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function parseSeriesKey(string $key): array
    {
        $parts = explode('|', $key);

        return [
            ($parts[0] ?? '') !== '' ? $parts[0] : 'lp',
            ($parts[1] ?? '') !== '' ? $parts[1] : 'geral',
            ($parts[2] ?? '') !== '' ? $parts[2] : 'municipal',
        ];
    }

    /**
     * @param  array<int, string>  $escolaNomes
     */
    private static function seriesTitle(string $disc, string $etapa, string $scope, array $escolaNomes): string
    {
        $d = match (strtolower($disc)) {
            'mat', 'matematica', 'matemática' => __('Matemática'),
            'lp', 'lingua', 'portugues', 'português' => __('Língua Portuguesa'),
            default => strtoupper($disc),
        };
        $e = match (strtolower($etapa)) {
            'efi', 'ef_iniciais', 'ef_i', 'anos_iniciais' => __('Anos iniciais do ensino fundamental'),
            'efaf', 'ef_finais', 'ef_ii', 'anos_finais' => __('Anos finais do ensino fundamental'),
            'em', 'ensino_medio', 'medio' => __('Ensino médio'),
            'ei', 'infantil', 'educacao_infantil' => __('Educação infantil'),
            'geral', 'general' => __('Rede municipal'),
            default => $etapa,
        };

        $base = __('SAEB — :disc (:etapa)', ['disc' => $d, 'etapa' => $e]);

        if ($scope === 'municipal') {
            return $base;
        }

        if (str_starts_with($scope, 'escola_')) {
            $id = (int) substr($scope, strlen('escola_'));
            if ($id <= 0) {
                return $base;
            }
            $nome = $escolaNomes[$id] ?? null;
            $label = ($nome !== null && $nome !== '') ? $nome : __('Escola :id', ['id' => (string) $id]);

            return $base.' — '.$label;
        }

        return $base.' — '.$scope;
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return list<array<string, mixed>>
     */
    private static function filterPointsForCity(array $points, City $city): array
    {
        $cid = (int) $city->id;
        $out = [];
        foreach ($points as $p) {
            $ids = $p['city_ids'] ?? null;
            if (! is_array($ids) || $ids === []) {
                continue;
            }
            $ids = array_map(static fn ($x) => (int) $x, $ids);
            if (! in_array($cid, $ids, true)) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }
}
