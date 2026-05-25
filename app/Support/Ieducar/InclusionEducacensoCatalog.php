<?php

namespace App\Support\Ieducar;

use App\Models\City;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

/**
 * Catálogos Educacenso/MEC (raça/cor e deficiência) unidos ao cadastro i-Educar,
 * para gráficos com todas as opções mesmo quando a contagem é zero.
 */
final class InclusionEducacensoCatalog
{
    /**
     * @return list<string>
     */
    public static function racaMecLabels(): array
    {
        return self::stringListFromConfig(self::configStringList('ieducar.inclusion.raca_mec_catalog'));
    }

    /**
     * @return list<string>
     */
    public static function deficienciaMecLabels(): array
    {
        $raw = self::configStringList('ieducar.inclusion.deficiencia_mec_catalog');
        if ($raw === []) {
            $raw = [
                'Cegueira', 'Baixa visão', 'Surdez', 'Deficiência auditiva', 'Surdocegueira',
                'Deficiência física', 'Deficiência intelectual', 'Deficiência múltipla',
                'Transtorno do espectro autista', 'Altas habilidades/Superdotação', 'Síndrome de Down',
                'Discalculia', 'Disgrafia', 'Dislalia', 'Dislexia', 'TDAH', 'TPAC',
            ];
        }

        return self::stringListFromConfig($raw);
    }

    /**
     * Raça/cor: MEC primeiro, depois linhas do cadastro.raca (ordem estável por rótulo).
     *
     * @return list<array{id: ?string, label: string, norm: string}>
     */
    public static function mergedRacaEntries(Connection $db, City $city): array
    {
        $seen = [];
        $out = [];

        foreach (self::racaMecLabels() as $label) {
            $norm = self::normalizeLabel($label);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = ['id' => null, 'label' => $label, 'norm' => $norm];
        }

        foreach (self::loadRacaCatalogRows($db, $city) as $row) {
            $norm = self::normalizeLabel($row['label']);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = ['id' => $row['id'], 'label' => $row['label'], 'norm' => $norm];
        }

        return $out;
    }

    /**
     * Deficiência/NEE: MEC primeiro, depois cadastro.deficiencia.
     *
     * @return list<array{id: ?string, label: string, norm: string}>
     */
    public static function mergedDeficienciaEntries(Connection $db, City $city): array
    {
        $seen = [];
        $out = [];

        foreach (self::deficienciaMecLabels() as $label) {
            $norm = self::normalizeLabel($label);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = ['id' => null, 'label' => $label, 'norm' => $norm];
        }

        foreach (self::loadDeficienciaCatalogRows($db, $city) as $row) {
            $norm = self::normalizeLabel($row['label']);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = ['id' => $row['id'], 'label' => $row['label'], 'norm' => $norm];
        }

        return $out;
    }

    /**
     * Catálogo para gráficos: cadastro i-Educar primeiro (com ID), depois rótulos MEC sem duplicar norm.
     *
     * @return list<array{id: ?string, label: string, norm: string}>
     */
    public static function mergedDeficienciaEntriesForChart(Connection $db, City $city): array
    {
        $seen = [];
        $out = [];

        foreach (self::loadDeficienciaCatalogRows($db, $city) as $row) {
            $norm = self::normalizeLabel($row['label']);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $entry = ['id' => $row['id'], 'label' => $row['label'], 'norm' => $norm];
            $entry['kind'] = self::classifyDeficienciaKind($entry);
            $out[] = $entry;
        }

        foreach (self::deficienciaMecLabels() as $label) {
            $norm = self::normalizeLabel($label);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $entry = ['id' => null, 'label' => $label, 'norm' => $norm];
            $entry['kind'] = self::classifyDeficienciaKind($entry);
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * Classificação para legenda do gráfico de catálogo NEE.
     *
     * @param  array{id: ?string, label: string, norm: string}  $entry
     * @return 'inep'|'complementar'|'ieducar'
     */
    public static function classifyDeficienciaKind(array $entry): string
    {
        $norm = (string) ($entry['norm'] ?? self::normalizeLabel((string) ($entry['label'] ?? '')));
        $complementar = self::complementarNormSet();

        if ($norm !== '' && isset($complementar[$norm])) {
            return 'complementar';
        }

        foreach (self::deficienciaMecLabels() as $mecLabel) {
            if (self::normalizeLabel($mecLabel) === $norm) {
                return 'inep';
            }
        }

        $id = $entry['id'] ?? null;
        if ($id !== null && $id !== '' && $id !== '0') {
            return 'ieducar';
        }

        return 'inep';
    }

    /**
     * @param  array{id: ?string, label: string, norm: string, kind?: string}  $entry
     */
    public static function deficienciaChartLabel(array $entry): string
    {
        $base = trim((string) ($entry['label'] ?? ''));
        if ($base === '') {
            $base = (string) __('Não informado');
        }

        return match ($entry['kind'] ?? self::classifyDeficienciaKind($entry)) {
            'complementar' => $base.' — '.__('complementar (mapear no Censo)'),
            'ieducar' => $base.' — '.__('cadastro i-Educar'),
            default => $base.' — '.__('INEP/Censo'),
        };
    }

    /**
     * @return array<string, true>
     */
    public static function complementarNormSet(): array
    {
        static $set = null;
        if ($set !== null) {
            return $set;
        }

        $raw = self::configStringList('ieducar.inclusion.deficiencia_complementar_catalog');
        if ($raw === []) {
            $raw = ['Discalculia', 'Disgrafia', 'Dislalia', 'Dislexia', 'TDAH', 'TPAC'];
        }

        $set = [];
        foreach (self::stringListFromConfig($raw) as $label) {
            $norm = self::normalizeLabel($label);
            if ($norm !== '') {
                $set[$norm] = true;
            }
        }

        return $set;
    }

    /**
     * @param  list<array{label: string, value: float, kind: string}>  $rows
     * @return array{labels: list<string>, values: list<float>, colors: list<string>}
     */
    public static function neeCatalogChartSeries(array $rows): array
    {
        $colorInep = '#4f46e5';
        $colorComplementar = '#7c3aed';
        $colorIeducar = '#d97706';

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($rows as $row) {
            $labels[] = (string) $row['label'];
            $values[] = (float) $row['value'];
            $colors[] = match ($row['kind'] ?? 'inep') {
                'complementar' => $colorComplementar,
                'ieducar' => $colorIeducar,
                default => $colorInep,
            };
        }

        return ['labels' => $labels, 'values' => $values, 'colors' => $colors];
    }

    /**
     * @param  list<array{id: ?string, label: string, norm: string}>  $entries
     * @param  array<string, int|float>  $countsByNorm
     * @return array{0: list<string>, 1: list<float>}
     */
    public static function mergeLabelsWithCounts(array $entries, array $countsByNorm): array
    {
        $labels = [];
        $values = [];
        foreach ($entries as $entry) {
            $labels[] = $entry['label'];
            $values[] = (float) ($countsByNorm[$entry['norm']] ?? 0);
        }

        return [$labels, $values];
    }

    /**
     * @param  iterable<mixed>  $rows
     * @return array<string, int>
     */
    public static function countsByNormFromRows(iterable $rows, callable $labelResolver, callable $countResolver): array
    {
        $map = [];
        foreach ($rows as $row) {
            $rawLabel = trim((string) $labelResolver($row));
            if ($rawLabel === '') {
                $rawLabel = (string) __('Não declarada');
            }
            $norm = self::normalizeLabel($rawLabel);
            $map[$norm] = ($map[$norm] ?? 0) + (int) $countResolver($row);
        }

        return $map;
    }

    /**
     * Agrega contagens por ID do catálogo (cadastro.deficiencia) e por rótulo normalizado.
     *
     * @param  iterable<mixed>  $rows
     * @return array{by_id: array<string, int>, by_norm: array<string, int>}
     */
    public static function deficienciaCountMapsFromRows(
        iterable $rows,
        callable $labelResolver,
        callable $countResolver,
        ?callable $idResolver = null,
    ): array {
        $byId = [];
        $byNorm = [];

        foreach ($rows as $row) {
            $count = (int) $countResolver($row);
            if ($count <= 0) {
                continue;
            }

            $rawLabel = trim((string) $labelResolver($row));
            if ($rawLabel === '') {
                $rawLabel = (string) __('Não informado');
            }
            $norm = self::resolveCatalogNorm($rawLabel);
            if ($norm !== '') {
                $byNorm[$norm] = ($byNorm[$norm] ?? 0) + $count;
            }

            if ($idResolver !== null) {
                $id = trim((string) $idResolver($row));
                if ($id !== '' && $id !== '0') {
                    $byId[$id] = ($byId[$id] ?? 0) + $count;
                }
            }
        }

        return ['by_id' => $byId, 'by_norm' => $byNorm];
    }

    /**
     * Resolve contagem de uma entrada do catálogo (ID do i-Educar, rótulo exacto ou aproximado).
     *
     * @param  array{id: ?string, label: string, norm: string}  $entry
     * @param  array{by_id: array<string, int>, by_norm: array<string, int>}  $maps
     */
    public static function countForDeficienciaEntry(array $entry, array $maps): int
    {
        $byId = $maps['by_id'] ?? [];
        $byNorm = $maps['by_norm'] ?? [];

        $id = $entry['id'] ?? null;
        if ($id !== null && $id !== '' && isset($byId[(string) $id])) {
            return (int) $byId[(string) $id];
        }

        $norm = (string) ($entry['norm'] ?? self::normalizeLabel((string) ($entry['label'] ?? '')));
        if ($norm !== '' && isset($byNorm[$norm])) {
            return (int) $byNorm[$norm];
        }

        return 0;
    }

    /**
     * Atribui contagens do mapa a entradas do catálogo sem duplicar a mesma matrícula em várias barras.
     *
     * @param  list<array{id: ?string, label: string, norm: string, kind?: string}>  $entries
     * @param  array{by_id: array<string, int>, by_norm: array<string, int>}  $maps
     * @return array{0: list<array{label: string, value: float, kind: string, norm: string}>, 1: array{by_id: array<string, int>, by_norm: array<string, int>}}
     */
    public static function assignDeficienciaCountsExclusive(array $entries, array $maps): array
    {
        $byId = $maps['by_id'] ?? [];
        $byNorm = $maps['by_norm'] ?? [];
        $rows = [];

        foreach ($entries as $entry) {
            $norm = (string) ($entry['norm'] ?? self::normalizeLabel((string) ($entry['label'] ?? '')));
            $id = $entry['id'] ?? null;
            $idKey = ($id !== null && $id !== '') ? (string) $id : '';
            $value = 0;

            if ($idKey !== '' && isset($byId[$idKey])) {
                $value = (int) $byId[$idKey];
                unset($byId[$idKey]);
                if ($norm !== '' && isset($byNorm[$norm])) {
                    unset($byNorm[$norm]);
                }
            } elseif ($norm !== '' && isset($byNorm[$norm])) {
                $value = (int) $byNorm[$norm];
                unset($byNorm[$norm]);
            }

            $entry['kind'] = (string) ($entry['kind'] ?? self::classifyDeficienciaKind($entry));
            $rows[] = [
                'label' => self::deficienciaChartLabel($entry),
                'value' => (float) $value,
                'kind' => (string) $entry['kind'],
                'norm' => $norm,
                'grupo' => InclusionDashboardQueries::classificarDesignacaoNeeGrupo((string) ($entry['label'] ?? '')),
            ];
        }

        return [$rows, ['by_id' => $byId, 'by_norm' => $byNorm]];
    }

    public static function normalizeLabel(string $label): string
    {
        $t = trim($label);
        if ($t === '') {
            return '';
        }

        return Str::ascii(mb_strtolower($t));
    }

    /**
     * Alias configurável (município → rótulo MEC/INEP) para cruzamento no catálogo unificado.
     */
    public static function resolveCatalogNorm(string $label): string
    {
        $norm = self::normalizeLabel($label);
        if ($norm === '') {
            return '';
        }

        $aliases = config('ieducar.inclusion.deficiencia_label_aliases', []);
        if (! is_array($aliases)) {
            return $norm;
        }

        foreach ($aliases as $from => $to) {
            if (! is_string($from) || ! is_string($to)) {
                continue;
            }
            if ($norm === self::normalizeLabel($from)) {
                return self::normalizeLabel($to);
            }
        }

        return $norm;
    }

    /**
     * @return list<mixed>
     */
    private static function configStringList(string $key): array
    {
        try {
            $raw = config($key, []);

            return is_array($raw) ? $raw : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private static function stringListFromConfig($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $s = trim($item);
                if ($s !== '') {
                    $out[] = $s;
                }

                continue;
            }
            if (is_array($item) && isset($item['label']) && is_string($item['label'])) {
                $s = trim($item['label']);
                if ($s !== '') {
                    $out[] = $s;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private static function loadRacaCatalogRows(Connection $db, City $city): array
    {
        foreach (IeducarSchema::racaTableCandidates($city) as $qualified) {
            if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
                continue;
            }

            $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
                (string) config('ieducar.columns.raca.id'),
                'cod_raca',
                'id',
                'id_raca',
                'codigo',
            ]), $city);
            $nameCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
                (string) config('ieducar.columns.raca.name'),
                'nm_raca',
                'nome',
                'nm_cor',
                'descricao',
                'ds_raca',
                'rac_nome',
            ]), $city);

            if ($idCol === null) {
                continue;
            }

            $nameCol = $nameCol ?? $idCol;

            try {
                $rows = $db->table($qualified)
                    ->select($idCol.' as rid', $nameCol.' as rname')
                    ->orderBy($nameCol)
                    ->get();

                $out = [];
                foreach ($rows as $row) {
                    $label = trim((string) ($row->rname ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $out[] = [
                        'id' => (string) ($row->rid ?? ''),
                        'label' => $label,
                    ];
                }

                return $out;
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private static function loadDeficienciaCatalogRows(Connection $db, City $city): array
    {
        $defTable = self::resolveDeficienciaCatalogTable($db, $city);
        if ($defTable === null) {
            return [];
        }

        $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.id'),
            'cod_deficiencia',
        ]), $city);
        $nmCol = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.name'),
            'nm_deficiencia',
            'nome',
            'descricao',
        ]), $city);

        if ($defPk === null || $nmCol === null) {
            return [];
        }

        try {
            $rows = $db->table($defTable)
                ->select($defPk.' as did', $nmCol.' as dname')
                ->orderBy($nmCol)
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $label = trim((string) ($row->dname ?? ''));
                if ($label === '') {
                    continue;
                }
                $out[] = [
                    'id' => (string) ($row->did ?? ''),
                    'label' => $label,
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function resolveDeficienciaCatalogTable(Connection $db, City $city): ?string
    {
        foreach (self::deficienciaCatalogTableCandidates($city) as $t) {
            if (IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return IeducarColumnInspector::findQualifiedTableByNames($db, [
            'deficiencia',
            'deficiencias',
        ], $city);
    }

    /**
     * @return list<string>
     */
    private static function deficienciaCatalogTableCandidates(City $city): array
    {
        $primary = IeducarSchema::resolveTable('deficiencia', $city);

        return array_values(array_unique(array_filter([
            $primary,
            trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.deficiencia',
            'public.deficiencia',
        ])));
    }
}
