<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Storage;

/**
 * Séries históricas do SAEB na aba Desempenho.
 * Os gráficos leem **apenas** o ficheiro JSON importado (Sincronizações → Pedagógicas), p.ex. storage/app/public/saeb/historico.json.
 */
final class PerformanceSaebSeries
{
    /**
     * @return array{
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string, footnote?: string}>,
     *   notes: list<string>,
     *   error: ?string,
     *   source_hint: ?string,
     *   explicacao_modal: ?array<string, mixed>
     * }
     */
    public static function build(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $empty = ['charts' => [], 'notes' => [], 'error' => null, 'source_hint' => null, 'explicacao_modal' => null];

        if (! filter_var(config('ieducar.saeb.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return $empty;
        }

        try {
            $bundle = self::loadPublicSaebFile();
        } catch (\Throwable $e) {
            return [
                'charts' => [],
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
                'notes' => [
                    __(
                        'Sem dados SAEB no ficheiro local. Importe em Admin → Sincronizações → Pedagógicas (o gráfico usa apenas :path).',
                        ['path' => $bundle['path']]
                    ),
                ],
                'error' => null,
                'source_hint' => __('Importação pedagógica (JSON) — ver Sincronizações Pedagógicas.'),
                'explicacao_modal' => $explicacaoModal,
            ];
        }

        $points = self::filterPointsForCity($points, $city);
        if ($points === []) {
            return [
                'charts' => [],
                'notes' => [__('Não há pontos SAEB para este município no ficheiro (city_ids).')],
                'error' => null,
                'source_hint' => $footnoteBase,
                'explicacao_modal' => $explicacaoModal,
            ];
        }

        $points = self::filterPointsForSchoolFilter($points, $filters);
        if ($points === []) {
            $msg = $filters->escola_id !== null
                ? __('Não há pontos SAEB para a escola seleccionada no ficheiro (use escola_id / escola_ids no JSON alinhados ao cod_escola do i-Educar).')
                : __('Não há pontos de rede municipal no ficheiro (pontos sem escola_id). Importe dados agregados por município ou seleccione uma escola com série própria.');

            return [
                'charts' => [],
                'notes' => [$msg],
                'error' => null,
                'source_hint' => $footnoteBase,
                'explicacao_modal' => $explicacaoModal,
            ];
        }

        $maxYear = self::maxYearFilter($filters);
        $points = array_values(array_filter($points, static fn (array $p): bool => (int) ($p['year'] ?? 0) <= $maxYear));

        if ($points === []) {
            return [
                'charts' => [],
                'notes' => [__('Não há resultados SAEB até ao ano seleccionado no filtro.')],
                'error' => null,
                'source_hint' => $footnoteBase,
                'explicacao_modal' => $explicacaoModal,
            ];
        }

        $escolaNomes = self::resolveEscolaNames($db, $city, $points);

        $grouped = [];
        foreach ($points as $p) {
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

        $hint = __('Círculos verdes — resultado final oficial; triângulos laranja tracejados — preliminar.');
        $yearNote = $filters->hasYearSelected() && ! $filters->isAllSchoolYears()
            ? __('A série mostra todos os anos disponíveis até :ano (inclusive), conforme o filtro de ano letivo.', ['ano' => (string) $filters->ano_letivo])
            : __('A série mostra todos os anos disponíveis na fonte.');
        $schoolNote = $filters->escola_id !== null
            ? __('Filtro de escola activo: séries por cod_escola :id (i-Educar).', ['id' => (string) $filters->escola_id])
            : __('Sem filtro de escola: mostram-se apenas indicadores da rede municipal (pontos sem escola_id no JSON).');

        return [
            'charts' => $charts,
            'notes' => [$yearNote, $schoolNote, $hint],
            'error' => null,
            'source_hint' => $footnoteBase,
            'explicacao_modal' => $explicacaoModal,
        ];
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
    private static function loadPublicSaebFile(): array
    {
        $rel = trim((string) config('ieducar.saeb.json_path', 'saeb/historico.json'));
        if ($rel === '') {
            return ['points' => [], 'meta' => null, 'explicacao_modal' => null, 'path' => 'saeb/historico.json'];
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($rel)) {
            return ['points' => [], 'meta' => null, 'explicacao_modal' => null, 'path' => $rel];
        }

        $raw = $disk->get($rel);
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return ['points' => [], 'meta' => null, 'explicacao_modal' => null, 'path' => $rel];
        }

        $meta = isset($decoded['meta']) && is_array($decoded['meta']) ? $decoded['meta'] : null;
        $explicacaoModal = null;
        if ($meta !== null && isset($meta['explicacao_modal']) && is_array($meta['explicacao_modal'])) {
            $explicacaoModal = $meta['explicacao_modal'];
        }
        $points = self::normalizeJsonPayload($decoded);

        return ['points' => $points, 'meta' => $meta, 'explicacao_modal' => $explicacaoModal, 'path' => $rel];
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private static function formatFootnoteFromMeta(?array $meta, string $relPath): string
    {
        $bits = [];
        $bits[] = __('Ficheiro: :path', ['path' => 'storage/app/public/'.$relPath]);

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
            if (is_array($ids) && $ids !== []) {
                $ids = array_map(static fn ($x) => (int) $x, $ids);
                if (! in_array($cid, $ids, true)) {
                    continue;
                }
            }
            $out[] = $p;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private static function normalizeJsonPayload(array $decoded): array
    {
        $pontos = $decoded['pontos'] ?? $decoded['points'] ?? $decoded['series'] ?? null;
        if (! is_array($pontos)) {
            return [];
        }

        $cityIds = null;
        if (isset($decoded['city_ids']) && is_array($decoded['city_ids'])) {
            $cityIds = array_map(static fn ($x) => (int) $x, $decoded['city_ids']);
        }

        $out = [];
        foreach ($pontos as $p) {
            if (! is_array($p)) {
                continue;
            }
            $year = self::intish(self::pick($p, ['ano', 'year', 'ano_aplicacao'], null));
            if ($year === null || $year <= 0) {
                continue;
            }
            $val = self::pick($p, ['valor', 'value', 'v'], null);
            if (! is_numeric($val)) {
                continue;
            }
            $statusRaw = strtolower((string) self::pick($p, ['status', 'tipo'], 'final'));
            $isFinal = self::statusIsFinal($statusRaw);
            $disc = strtolower((string) self::pick($p, ['disciplina', 'disc'], 'lp'));
            $etapa = strtolower((string) self::pick($p, ['etapa', 'etapa_ensino'], 'geral'));

            $pointEscolaId = self::intish(self::pick($p, ['escola_id', 'cod_escola'], null));
            $rawEids = $p['escola_ids'] ?? null;
            $escolaIdsList = [];
            if (is_array($rawEids)) {
                foreach ($rawEids as $x) {
                    if (is_numeric($x) && (int) $x > 0) {
                        $escolaIdsList[] = (int) $x;
                    }
                }
                $escolaIdsList = array_values(array_unique($escolaIdsList));
            }

            if ($pointEscolaId !== null && $pointEscolaId > 0) {
                $scope = 'escola_'.$pointEscolaId;
            } elseif ($escolaIdsList !== []) {
                sort($escolaIdsList);
                $scope = 'escola_'.$escolaIdsList[0];
            } else {
                $scope = 'municipal';
            }

            $row = [
                'year' => $year,
                'series_key' => $disc.'|'.$etapa.'|'.$scope,
                'value' => (float) $val,
                'is_final' => $isFinal,
                'unidade' => (string) self::pick($p, ['unidade', 'unit'], '%'),
            ];
            if ($pointEscolaId !== null && $pointEscolaId > 0) {
                $row['escola_id'] = $pointEscolaId;
            }
            if ($escolaIdsList !== []) {
                $row['escola_ids'] = $escolaIdsList;
            }
            if ($cityIds !== null) {
                $row['city_ids'] = $cityIds;
            }
            $out[] = $row;
        }

        return $out;
    }

    private static function statusIsFinal(string $s): bool
    {
        if (str_contains($s, 'prelim')) {
            return false;
        }
        if (str_contains($s, 'prel')) {
            return false;
        }
        if (str_contains($s, 'prov')) {
            return false;
        }
        if ($s === 'p') {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $arr
     */
    private static function pick(array $arr, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') {
                return $arr[$k];
            }
        }

        return $default;
    }

    private static function intish(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (int) $v;
        }

        return null;
    }
}
