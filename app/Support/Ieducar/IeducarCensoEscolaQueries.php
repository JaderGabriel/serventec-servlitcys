<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;

/**
 * Deteta escolas com Censo/Educacenso exportado ou fechado na base i-Educar (schema variável por instalação).
 */
final class IeducarCensoEscolaQueries
{
    /**
     * @return array{
     *   available: bool,
     *   source_label: ?string,
     *   note: ?string,
     *   exported: list<array{escola_id: int|string, nome: string, inep: ?string, detalhe: ?string}>,
     *   closed: list<array{escola_id: int|string, nome: string, inep: ?string, detalhe: ?string}>,
     *   pending: list<array{escola_id: int|string, nome: string, inep: ?string, detalhe: ?string}>,
     *   summary: array{total_escolas: int, exportadas: int, fechadas: int, pendentes: int}
     * }
     */
    public static function schoolStatuses(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $empty = [
            'available' => false,
            'source_label' => null,
            'note' => null,
            'exported' => [],
            'closed' => [],
            'pending' => [],
            'summary' => ['total_escolas' => 0, 'exportadas' => 0, 'fechadas' => 0, 'pendentes' => 0],
        ];

        $ctx = self::resolveStatusSource($db, $city);
        if ($ctx === null) {
            return array_merge($empty, [
                'note' => __('Não foi encontrada tabela ou coluna de exportação/fecho do Educacenso nesta base. Configure IEDUCAR_CENSO_* ou consulte o módulo Censo no i-Educar.'),
            ]);
        }

        $escolaT = IeducarSchema::resolveTable('escola', $city);
        $eId = (string) config('ieducar.columns.escola.id', 'cod_escola');
        $eName = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
            (string) config('ieducar.columns.escola.name', 'nome'),
            'nm_escola',
            'nome',
        ], $city) ?? 'nome';

        $inepSub = self::inepSubquery($db, $city, $escolaT, $eId);

        $rows = self::fetchStatusRows($db, $city, $filters, $ctx, $escolaT, $eId, $eName, $inepSub);
        if ($rows === []) {
            return array_merge($empty, [
                'available' => true,
                'source_label' => $ctx['label'],
                'note' => __('Nenhuma escola no filtro actual ou sem registos de Censo para o ano.'),
            ]);
        }

        $exported = [];
        $closed = [];
        $pending = [];

        foreach ($rows as $row) {
            $item = [
                'escola_id' => $row['escola_id'],
                'nome' => (string) ($row['nome'] ?? '—'),
                'inep' => filled($row['inep'] ?? null) ? (string) $row['inep'] : null,
                'detalhe' => filled($row['detalhe'] ?? null) ? (string) $row['detalhe'] : null,
            ];
            $kind = (string) ($row['kind'] ?? 'pending');
            if ($kind === 'exported') {
                $exported[] = $item;
            } elseif ($kind === 'closed') {
                $closed[] = $item;
            } else {
                $pending[] = $item;
            }
        }

        usort($exported, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
        usort($closed, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
        usort($pending, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

        return [
            'available' => true,
            'source_label' => $ctx['label'],
            'note' => null,
            'exported' => $exported,
            'closed' => $closed,
            'pending' => $pending,
            'summary' => [
                'total_escolas' => count($rows),
                'exportadas' => count($exported),
                'fechadas' => count($closed),
                'pendentes' => count($pending),
            ],
        ];
    }

    /**
     * @return ?array{
     *   label: string,
     *   mode: 'table'|'escola_col',
     *   table: string,
     *   escola_col: string,
     *   year_col: ?string,
     *   export_col: ?string,
     *   closed_col: ?string,
     *   status_col: ?string,
     *   export_kind: 'bool'|'date'|'text',
     *   closed_kind: 'bool'|'date'|'text',
     *   status_kind: 'text'
     * }
     */
    private static function resolveStatusSource(Connection $db, City $city): ?array
    {
        $configured = trim((string) config('ieducar.censo_tracking.status_table', ''));
        if ($configured !== '' && IeducarColumnInspector::tableExists($db, $configured, $city)) {
            $spec = self::buildTableSpec($db, $city, $configured);
            if ($spec !== null) {
                return $spec;
            }
        }

        $candidates = config('ieducar.censo_tracking.table_candidates', []);
        if (! is_array($candidates)) {
            $candidates = [];
        }
        foreach ($candidates as $name) {
            $table = IeducarColumnInspector::findQualifiedTableByNames($db, [(string) $name], $city);
            if ($table === null) {
                continue;
            }
            $spec = self::buildTableSpec($db, $city, $table);
            if ($spec !== null) {
                return $spec;
            }
        }

        if ($db->getDriverName() === 'pgsql') {
            $rows = $db->select(
                "select table_schema, table_name from information_schema.tables
                where table_type = 'BASE TABLE'
                and table_schema not in ('pg_catalog', 'information_schema')
                and (lower(table_name) like '%educacenso%' or lower(table_name) like '%censo%export%')
                order by table_schema, table_name
                limit 40"
            );
            foreach ($rows as $r) {
                $q = $r->table_schema.'.'.$r->table_name;
                $spec = self::buildTableSpec($db, $city, $q);
                if ($spec !== null) {
                    return $spec;
                }
            }
        }

        return self::resolveEscolaColumnSource($db, $city);
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function buildTableSpec(Connection $db, City $city, string $table): ?array
    {
        $escolaCol = IeducarColumnInspector::firstExistingColumn($db, $table, self::escolaFkCandidates(), $city);
        if ($escolaCol === null) {
            return null;
        }

        $yearCol = IeducarColumnInspector::firstExistingColumn($db, $table, self::yearColCandidates(), $city);
        $exportCol = IeducarColumnInspector::firstExistingColumn($db, $table, self::exportColCandidates(), $city);
        $closedCol = IeducarColumnInspector::firstExistingColumn($db, $table, self::closedColCandidates(), $city);
        $statusCol = IeducarColumnInspector::firstExistingColumn($db, $table, self::statusColCandidates(), $city);

        if ($exportCol === null && $closedCol === null && $statusCol === null) {
            return null;
        }

        return [
            'label' => $table,
            'mode' => 'table',
            'table' => $table,
            'escola_col' => $escolaCol,
            'year_col' => $yearCol,
            'export_col' => $exportCol,
            'closed_col' => $closedCol,
            'status_col' => $statusCol,
            'export_kind' => self::columnKind($db, $table, $exportCol, $city),
            'closed_kind' => self::columnKind($db, $table, $closedCol, $city),
            'status_kind' => 'text',
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function resolveEscolaColumnSource(Connection $db, City $city): ?array
    {
        $escolaT = IeducarSchema::resolveTable('escola', $city);
        $exportCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, self::exportColCandidates(), $city);
        $closedCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, self::closedColCandidates(), $city);
        $statusCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, self::statusColCandidates(), $city);

        if ($exportCol === null && $closedCol === null && $statusCol === null) {
            return null;
        }

        return [
            'label' => $escolaT,
            'mode' => 'escola_col',
            'table' => $escolaT,
            'escola_col' => (string) config('ieducar.columns.escola.id', 'cod_escola'),
            'year_col' => null,
            'export_col' => $exportCol,
            'closed_col' => $closedCol,
            'status_col' => $statusCol,
            'export_kind' => self::columnKind($db, $escolaT, $exportCol, $city),
            'closed_kind' => self::columnKind($db, $escolaT, $closedCol, $city),
            'status_kind' => 'text',
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return list<array{escola_id: int|string, nome: string, inep: ?string, detalhe: ?string, kind: string}>
     */
    private static function fetchStatusRows(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        array $ctx,
        string $escolaT,
        string $eId,
        string $eName,
        ?string $inepSelect,
    ): array {
        $eActive = IeducarColumnInspector::firstExistingColumn($db, $escolaT, array_filter([
            (string) config('ieducar.columns.escola.active', 'ativo'),
            'ativo',
        ]), $city);

        $q = $db->table($escolaT.' as e');
        $q->selectRaw('e.'.$eId.' as escola_id');
        $q->selectRaw('e.'.$eName.' as nome');
        if ($inepSelect !== null) {
            $q->selectRaw($inepSelect.' as inep');
        } else {
            $q->selectRaw('null as inep');
        }

        if ($eActive !== null && filter_var(config('ieducar.filters.escola_only_active', true), FILTER_VALIDATE_BOOL)) {
            $q->where('e.'.$eActive, 1);
        }

        if ($filters->escola_id !== null) {
            $q->where('e.'.$eId, $filters->escola_id);
        }

        if ($ctx['mode'] === 'table') {
            $censo = $ctx['table'];
            $joinCol = $ctx['escola_col'];
            $q->leftJoin($censo.' as cen', 'cen.'.$joinCol, '=', 'e.'.$eId);
            if ($ctx['year_col'] !== null && $filters->hasYearSelected() && ! $filters->isAllSchoolYears()) {
                $q->where('cen.'.$ctx['year_col'], (int) $filters->ano_letivo);
            }
            $exportExpr = self::statusExpression('cen', $ctx['export_col'], $ctx['export_kind'], 'exported');
            $closedExpr = self::statusExpression('cen', $ctx['closed_col'], $ctx['closed_kind'], 'closed');
            $statusExpr = self::statusExpression('cen', $ctx['status_col'], 'text', 'status');
            $q->selectRaw('coalesce('.$exportExpr.', '.$closedExpr.', '.$statusExpr.", 'pending') as kind");
            $detalheParts = array_filter([
                $ctx['export_col'] !== null ? 'cen.'.$ctx['export_col'] : null,
                $ctx['closed_col'] !== null ? 'cen.'.$ctx['closed_col'] : null,
                $ctx['status_col'] !== null ? 'cen.'.$ctx['status_col'] : null,
            ]);
            if ($detalheParts !== []) {
                $q->selectRaw('concat_ws(\' · \', '.implode(', ', $detalheParts).') as detalhe');
            } else {
                $q->selectRaw('null as detalhe');
            }
        } else {
            $exportExpr = self::statusExpression('e', $ctx['export_col'], $ctx['export_kind'], 'exported');
            $closedExpr = self::statusExpression('e', $ctx['closed_col'], $ctx['closed_kind'], 'closed');
            $statusExpr = self::statusExpression('e', $ctx['status_col'], 'text', 'status');
            $q->selectRaw('coalesce('.$exportExpr.', '.$closedExpr.', '.$statusExpr.", 'pending') as kind");
            $detalheParts = array_filter([
                $ctx['export_col'] !== null ? 'e.'.$ctx['export_col'] : null,
                $ctx['closed_col'] !== null ? 'e.'.$ctx['closed_col'] : null,
                $ctx['status_col'] !== null ? 'e.'.$ctx['status_col'] : null,
            ]);
            if ($detalheParts !== []) {
                $q->selectRaw('concat_ws(\' · \', '.implode(', ', $detalheParts).') as detalhe');
            } else {
                $q->selectRaw('null as detalhe');
            }
        }

        $bySchool = [];
        foreach ($q->get() as $row) {
            $id = $row->escola_id;
            $kind = self::normalizeKind((string) ($row->kind ?? 'pending'));
            $item = [
                'escola_id' => $id,
                'nome' => (string) ($row->nome ?? ''),
                'inep' => $row->inep ?? null,
                'detalhe' => $row->detalhe ?? null,
                'kind' => $kind,
            ];
            if (! isset($bySchool[$id]) || self::kindPriority($kind) > self::kindPriority((string) $bySchool[$id]['kind'])) {
                $bySchool[$id] = $item;
            }
        }

        return array_values($bySchool);
    }

    private static function normalizeKind(string $kind): string
    {
        if ($kind === 'exported' || $kind === 'closed') {
            return $kind;
        }

        return 'pending';
    }

    private static function statusExpression(string $alias, ?string $col, string $kind, string $role): string
    {
        if ($col === null) {
            return 'null';
        }

        $ref = $alias.'.'.$col;
        $exportedTokens = self::sqlTokenList(config('ieducar.censo_tracking.exported_text_values', []));
        $closedTokens = self::sqlTokenList(config('ieducar.censo_tracking.closed_text_values', []));

        if ($role === 'exported') {
            if ($kind === 'date') {
                return "case when {$ref} is not null then 'exported' else null end";
            }
            if ($kind === 'text') {
                return self::textMatchCase($ref, $exportedTokens, 'exported');
            }

            return "case when {$ref} in (true, 1, '1', 't', 'true', 'sim', 's') then 'exported' else null end";
        }

        if ($role === 'closed') {
            if ($kind === 'date') {
                return "case when {$ref} is not null then 'closed' else null end";
            }
            if ($kind === 'text') {
                return self::textMatchCase($ref, $closedTokens, 'closed');
            }

            return "case when {$ref} in (true, 1, '1', 't', 'true', 'sim', 's') then 'closed' else null end";
        }

        $exportedWhen = self::textMatchCase($ref, $exportedTokens, 'exported');
        $closedWhen = self::textMatchCase($ref, $closedTokens, 'closed');

        return 'coalesce('.$exportedWhen.', '.$closedWhen.', null)';
    }

    private static function kindPriority(string $kind): int
    {
        return match ($kind) {
            'exported' => 3,
            'closed' => 2,
            default => 1,
        };
    }

    /**
     * @param  list<string>  $tokens
     */
    private static function textMatchCase(string $ref, array $tokens, string $result): string
    {
        if ($tokens === []) {
            return 'null';
        }
        $parts = [];
        foreach ($tokens as $tok) {
            $lit = str_replace("'", "''", mb_strtolower($tok));
            $parts[] = "lower(cast({$ref} as text)) like '%{$lit}%'";
        }

        return 'case when ('.implode(' or ', $parts).") then '{$result}' else null end";
    }

    /**
     * @return list<string>
     */
    private static function sqlTokenList(mixed $cfg): array
    {
        if (! is_array($cfg)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $cfg), static fn (string $s): bool => $s !== ''));
    }

    private static function columnKind(Connection $db, string $table, ?string $col, City $city): string
    {
        if ($col === null) {
            return 'bool';
        }
        if ($db->getDriverName() !== 'pgsql') {
            return 'bool';
        }
        [$schema, $tname] = self::parseTable($table, $city);
        $row = $db->selectOne(
            'select data_type from information_schema.columns
            where table_schema = ? and table_name = ? and column_name = ?',
            [$schema, $tname, $col]
        );
        $type = strtolower((string) ($row->data_type ?? ''));
        if (str_contains($type, 'timestamp') || $type === 'date') {
            return 'date';
        }
        if (in_array($type, ['boolean'], true)) {
            return 'bool';
        }
        if (in_array($type, ['integer', 'smallint', 'bigint', 'numeric'], true)) {
            return 'bool';
        }

        return 'text';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function parseTable(string $qualified, City $city): array
    {
        if (str_contains($qualified, '.')) {
            [$s, $t] = explode('.', $qualified, 2);

            return [trim($s), trim($t)];
        }

        return [IeducarSchema::effectiveSchema($city) ?: 'pmieducar', $qualified];
    }

    private static function inepSubquery(Connection $db, City $city, string $escolaT, string $eId): ?string
    {
        $educTable = trim((string) config('ieducar.tables.educacenso_cod_escola', ''));
        if ($educTable === '' || ! IeducarColumnInspector::tableExists($db, $educTable, $city)) {
            $inepCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, array_filter([
                (string) config('ieducar.columns.escola.inep', ''),
                'codigo_inep',
                'inep',
            ]), $city);
            if ($inepCol !== null) {
                return 'e.'.$inepCol;
            }

            return null;
        }

        $fk = (string) config('ieducar.columns.educacenso_cod_escola.cod_escola', 'cod_escola');
        $inepCol = (string) config('ieducar.columns.educacenso_cod_escola.cod_escola_inep', 'cod_escola_inep');

        return '(select ed.'.$inepCol.' from '.$educTable.' ed where ed.'.$fk.' = e.'.$eId.' limit 1)';
    }

    /**
     * @return list<string>
     */
    private static function escolaFkCandidates(): array
    {
        return [
            'ref_cod_escola',
            'cod_escola',
            'escola_id',
            'id_escola',
        ];
    }

    /**
     * @return list<string>
     */
    private static function yearColCandidates(): array
    {
        return [
            'ano',
            'ano_letivo',
            'ref_cod_ano_letivo',
            'nu_ano',
            'ano_referencia',
        ];
    }

    /**
     * @return list<string>
     */
    private static function exportColCandidates(): array
    {
        $cfg = config('ieducar.censo_tracking.export_columns', []);
        if (! is_array($cfg)) {
            $cfg = [];
        }

        return array_values(array_unique(array_merge($cfg, [
            'exportado',
            'foi_exportado',
            'data_exportacao',
            'dt_exportacao',
            'data_exportacao_educacenso',
            'educacenso_exportado',
        ])));
    }

    /**
     * @return list<string>
     */
    private static function closedColCandidates(): array
    {
        $cfg = config('ieducar.censo_tracking.closed_columns', []);
        if (! is_array($cfg)) {
            $cfg = [];
        }

        return array_values(array_unique(array_merge($cfg, [
            'fechado',
            'situacao_fechamento',
            'data_fechamento',
            'dt_fechamento',
            'censo_fechado',
            'educacenso_fechado',
        ])));
    }

    /**
     * @return list<string>
     */
    private static function statusColCandidates(): array
    {
        $cfg = config('ieducar.censo_tracking.status_columns', []);
        if (! is_array($cfg)) {
            $cfg = [];
        }

        return array_values(array_unique(array_merge($cfg, [
            'situacao',
            'situacao_exportacao',
            'status',
            'status_exportacao',
            'situacao_educacenso',
            'situacao_censo',
        ])));
    }
}
