<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;

/**
 * Contagem de cadastros recentes no i-Educar por período e utilizador (excl. admin).
 */
final class IeducarWorkActivityQueries
{
    /**
     * @return array{
     *   available: bool,
     *   date_col: ?string,
     *   user_col: ?string,
     *   note: ?string
     * }
     */
    public static function matriculaActivityContext(Connection $db, City $city): array
    {
        $mat = IeducarSchema::resolveTable('matricula', $city);
        $dateCol = IeducarColumnInspector::firstExistingColumn(
            $db,
            $mat,
            config('ieducar.work_tracking.matricula_date_columns', []),
            $city
        );
        $userCol = IeducarColumnInspector::firstExistingColumn(
            $db,
            $mat,
            config('ieducar.work_tracking.matricula_user_columns', []),
            $city
        );

        if ($dateCol === null) {
            return [
                'available' => false,
                'date_col' => null,
                'user_col' => $userCol,
                'note' => __('Não foi encontrada coluna de data de cadastro em matrícula nesta base.'),
            ];
        }

        return [
            'available' => true,
            'date_col' => $dateCol,
            'user_col' => $userCol,
            'note' => null,
        ];
    }

    /**
     * @return array{day: int, week: int, fortnight: int}
     */
    public static function matriculaCountsByPeriod(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        string $dateCol,
        ?string $userCol = null,
    ): array {
        $periods = config('ieducar.work_tracking.periods_days', ['day' => 1, 'week' => 7, 'fortnight' => 15]);
        $out = ['day' => 0, 'week' => 0, 'fortnight' => 0];
        $usuario = IeducarUsuarioScope::resolve($db, $city);

        foreach ($periods as $key => $days) {
            if (! array_key_exists($key, $out)) {
                continue;
            }
            $out[$key] = self::countMatriculasSince(
                $db,
                $city,
                $filters,
                $dateCol,
                (int) $days,
                $userCol,
                $usuario
            );
        }

        return $out;
    }

    /**
     * @return list<array{usuario_id: int, login: string, nome: string, total: int}>
     */
    public static function matriculaByUserSince(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        string $dateCol,
        string $userCol,
        int $days,
        int $limit = 25,
    ): array {
        $usuario = IeducarUsuarioScope::resolve($db, $city);
        if ($usuario === null) {
            return [];
        }

        $mat = IeducarSchema::resolveTable('matricula', $city);
        $since = Carbon::now()->subDays($days)->startOfDay();

        $q = $db->table($mat.' as m');
        MatriculaAtivoFilter::apply($q, $db, 'm.'.(string) config('ieducar.columns.matricula.ativo'), $city);
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
        MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

        $q->join($usuario['table'].' as u', 'm.'.$userCol, '=', 'u.'.$usuario['id_col']);
        IeducarUsuarioScope::applyExclusions($q, $usuario, 'u');

        $q->where('m.'.$dateCol, '>=', $since->toDateString());

        $q->selectRaw('u.'.$usuario['id_col'].' as usuario_id');
        $groupBy = ['u.'.$usuario['id_col']];

        if (filled($usuario['login_col'] ?? null)) {
            $q->selectRaw('u.'.$usuario['login_col'].' as login');
            $groupBy[] = 'u.'.$usuario['login_col'];
        } else {
            $q->selectRaw("'' as login");
        }

        if (filled($usuario['name_col'] ?? null)) {
            $q->selectRaw('u.'.$usuario['name_col'].' as nome');
            $groupBy[] = 'u.'.$usuario['name_col'];
        } else {
            $q->selectRaw("'' as nome");
        }

        $rows = $q
            ->selectRaw('COUNT(*) as total')
            ->groupBy(...$groupBy)
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $list = [];
        foreach ($rows as $row) {
            $a = (array) $row;
            $list[] = [
                'usuario_id' => (int) ($a['usuario_id'] ?? 0),
                'login' => (string) ($a['login'] ?? ''),
                'nome' => (string) ($a['nome'] ?? ''),
                'total' => (int) ($a['total'] ?? 0),
            ];
        }

        return $list;
    }

    /**
     * @return array{turmas: int, matriculas: int, ano: int}
     */
    public static function baselineFromPreviousYear(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): array {
        $year = $filters->yearFilterValue();
        if ($year === null || $year <= 1) {
            return ['turmas' => 0, 'matriculas' => 0, 'ano' => 0];
        }

        $prevYear = $year - 1;
        $prevFilters = new IeducarFilterState(
            ano_letivo: (string) $prevYear,
            escola_id: $filters->escola_id,
            curso_id: $filters->curso_id,
            turno_id: $filters->turno_id,
        );

        $matriculas = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $prevFilters) ?? 0;
        $turmas = self::countTurmasForYear($db, $city, $prevFilters);

        return [
            'turmas' => $turmas,
            'matriculas' => $matriculas,
            'ano' => $prevYear,
        ];
    }

    public static function countTurmasForYear(Connection $db, City $city, IeducarFilterState $filters): int
    {
        try {
            $turma = IeducarSchema::resolveTable('turma', $city);
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $tId = (string) config('ieducar.columns.turma.id');

            $q = $db->table($turma.' as t');
            if ($filters->yearFilterValue() !== null && $tc['year'] !== '') {
                $q->where('t.'.$tc['year'], $filters->yearFilterValue());
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['turno'], $filters->turno_id);

            $row = $q->selectRaw('COUNT(DISTINCT t.'.$tId.') as c')->first();

            return (int) ($row->c ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Ritmo médio (registos/dia) com base no período de quinzena.
     */
    public static function pacePerDay(int $fortnightCount): float
    {
        $days = (int) (config('ieducar.work_tracking.periods_days.fortnight', 15));

        return $days > 0 ? round($fortnightCount / $days, 2) : 0.0;
    }

    /**
     * @param  array{turmas: int, matriculas: int, ano: int}  $baseline
     * @param  array{day: int, week: int, fortnight: int}  $periodCounts
     * @return array<string, mixed>
     */
    public static function buildEstimate(
        array $baseline,
        array $periodCounts,
        int $currentMatriculas,
    ): array {
        $target = max((int) $baseline['matriculas'], 1);
        $remaining = max(0, $target - $currentMatriculas);
        $pace = self::pacePerDay((int) ($periodCounts['fortnight'] ?? 0));
        $defaultMin = (float) config('ieducar.work_tracking.default_minutes_per_record', 3.5);
        $hoursPerDay = (float) config('ieducar.work_tracking.working_hours_per_day', 6);

        $minutesPerRecord = $defaultMin;
        if ($pace > 0) {
            $recordsPerHour = $pace / max($hoursPerDay, 0.5) * $hoursPerDay;
            if ($recordsPerHour > 0) {
                $minutesPerRecord = round(60.0 / $recordsPerHour, 1);
            }
        }

        $totalMinutes = $remaining * $minutesPerRecord;
        $totalHours = round($totalMinutes / 60.0, 1);
        $daysToFinish = $pace > 0 ? (int) ceil($remaining / $pace) : null;
        $fteDays = $hoursPerDay > 0 ? round($totalHours / $hoursPerDay, 1) : null;

        return [
            'meta_matriculas_ano_anterior' => $target,
            'matriculas_ativas_filtro' => $currentMatriculas,
            'registros_restantes_estimados' => $remaining,
            'turmas_ano_anterior' => (int) $baseline['turmas'],
            'ano_referencia' => (int) $baseline['ano'],
            'ritmo_por_dia' => $pace,
            'minutos_por_registro' => $minutesPerRecord,
            'horas_totais_estimadas' => $totalHours,
            'dias_para_concluir_ritmo_atual' => $daysToFinish,
            'dias_pessoa_equivalente' => $fteDays,
            'usa_ritmo_observado' => $pace > 0,
        ];
    }

    /**
     * Indica se o ano letivo filtrado parece consolidado (Censo fechado/exportado e sem cadastro recente).
     *
     * @param  array<string, mixed>  $censo
     * @param  array{day: int, week: int, fortnight: int}  $periods
     * @return ?array{consolidated: bool, title: string, message: string, hints: list<string>}
     */
    public static function yearClosureInsight(
        IeducarFilterState $filters,
        array $censo,
        array $periods,
        ?array $anoLetivoRow = null,
    ): ?array {
        if (! $filters->hasYearSelected() || $filters->isAllSchoolYears()) {
            return null;
        }

        $year = (string) $filters->ano_letivo;
        $summary = is_array($censo['summary'] ?? null) ? $censo['summary'] : [];
        $total = (int) ($summary['total_escolas'] ?? 0);
        $exportadas = (int) ($summary['exportadas'] ?? 0);
        $fechadas = (int) ($summary['fechadas'] ?? 0);
        $pendentes = (int) ($summary['pendentes'] ?? 0);
        $recent = (int) ($periods['day'] ?? 0) + (int) ($periods['week'] ?? 0) + (int) ($periods['fortnight'] ?? 0);

        $hints = [];
        $censoConsolidated = $total > 0 && $pendentes === 0 && ($exportadas + $fechadas) >= max(1, (int) ceil($total * 0.85));
        $noRecentCadastro = $recent === 0;

        if (is_array($anoLetivoRow) && ($anoLetivoRow['fechado'] ?? false)) {
            $hints[] = __('Situação do ano letivo na base i-Educar: :s', ['s' => (string) ($anoLetivoRow['label'] ?? __('fechado'))]);
        }

        if ($censoConsolidated) {
            $hints[] = __(':e escola(s) exportada(s) e :f fechada(s) no Educacenso — nenhuma pendente no filtro.', [
                'e' => number_format($exportadas, 0, ',', '.'),
                'f' => number_format($fechadas, 0, ',', '.'),
            ]);
        }

        if ($noRecentCadastro) {
            $hints[] = __('Nenhuma matrícula com data de cadastro nos últimos 15 dias (equipa municipal, filtros aplicados).');
        }

        if (! $censoConsolidated && ! ($anoLetivoRow['fechado'] ?? false)) {
            return null;
        }

        if (! $censoConsolidated && ($anoLetivoRow['fechado'] ?? false) && $recent > 0) {
            return [
                'consolidated' => false,
                'title' => __('Ano letivo marcado como fechado na base'),
                'message' => __(
                    'O ano :ano consta fechado no i-Educar, mas ainda há cadastros recentes de matrícula — verifique se o fecho é só do Censo ou se a secretaria reabriu alterações.',
                    ['ano' => $year]
                ),
                'hints' => $hints,
            ];
        }

        if ($censoConsolidated && $noRecentCadastro) {
            return [
                'consolidated' => true,
                'title' => __('Ano letivo consolidado'),
                'message' => __(
                    'O ano letivo :ano está fechado/consolidado no Educacenso e não há cadastros recentes na matrícula. Não se esperam mudanças de cadastro até nova abertura ou reexportação.',
                    ['ano' => $year]
                ),
                'hints' => $hints,
            ];
        }

        if (($anoLetivoRow['fechado'] ?? false) && $noRecentCadastro) {
            return [
                'consolidated' => true,
                'title' => __('Ano letivo fechado na base'),
                'message' => __(
                    'O ano :ano aparece como fechado no i-Educar e sem movimentação recente de cadastro. Trate o painel como leitura histórica/consolidada.',
                    ['ano' => $year]
                ),
                'hints' => $hints,
            ];
        }

        return null;
    }

    /**
     * Tenta ler situação do ano letivo na base (tabela ano_letivo ou escola_ano_letivo).
     *
     * @return ?array{fechado: bool, label: string}
     */
    public static function anoLetivoStatus(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        $year = $filters->yearFilterValue();
        if ($year === null) {
            return null;
        }

        $candidates = config('ieducar.work_tracking.ano_letivo_table_candidates', [
            'escola_ano_letivo',
            'ano_letivo',
            'educacenso_ano_letivo',
        ]);
        if (! is_array($candidates)) {
            $candidates = ['escola_ano_letivo', 'ano_letivo'];
        }

        foreach ($candidates as $name) {
            $table = IeducarColumnInspector::findQualifiedTableByNames($db, [(string) $name], $city);
            if ($table === null) {
                continue;
            }

            $yearCol = IeducarColumnInspector::firstExistingColumn($db, $table, [
                'ano', 'ano_letivo', 'ref_cod_ano_letivo', 'nu_ano',
            ], $city);
            $statusCol = IeducarColumnInspector::firstExistingColumn($db, $table, [
                'situacao', 'andamento', 'situacao_ano_letivo', 'status', 'fechado',
            ], $city);

            if ($yearCol === null) {
                continue;
            }

            $q = $db->table($table)->where($yearCol, $year);
            if ($statusCol !== null) {
                $row = $q->select($statusCol.' as st')->limit(1)->first();
                if ($row === null) {
                    continue;
                }
                $st = strtolower(trim((string) ($row->st ?? '')));
                $fechado = in_array($st, ['1', 't', 'true', 'fechado', 'encerrado', 'concluido', 'finalizado'], true)
                    || str_contains($st, 'fech')
                    || str_contains($st, 'encerr');

                return [
                    'fechado' => $fechado,
                    'label' => (string) ($row->st ?? $st),
                ];
            }

            $boolCol = IeducarColumnInspector::firstExistingColumn($db, $table, ['fechado', 'encerrado', 'finalizado'], $city);
            if ($boolCol !== null) {
                $row = $q->select($boolCol.' as f')->limit(1)->first();
                if ($row !== null) {
                    $v = $row->f ?? null;

                    return [
                        'fechado' => in_array((string) $v, ['1', 't', 'true'], true) || $v === 1 || $v === true,
                        'label' => $v ? __('fechado') : __('aberto'),
                    ];
                }
            }
        }

        return null;
    }

    private static function countMatriculasSince(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        string $dateCol,
        int $days,
        ?string $userCol,
        ?array $usuario,
    ): int {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $since = Carbon::now()->subDays($days)->startOfDay();

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.(string) config('ieducar.columns.matricula.ativo'), $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            $q->where('m.'.$dateCol, '>=', $since->toDateString());

            if ($userCol !== null && $usuario !== null) {
                $q->join($usuario['table'].' as u', 'm.'.$userCol, '=', 'u.'.$usuario['id_col']);
                IeducarUsuarioScope::applyExclusions($q, $usuario, 'u');
            }

            $row = $q->selectRaw('COUNT(*) as c')->first();

            return (int) ($row->c ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
