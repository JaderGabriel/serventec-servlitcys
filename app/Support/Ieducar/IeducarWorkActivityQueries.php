<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
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

        $loginExpr = filled($usuario['login_col'] ?? null)
            ? 'u.'.$usuario['login_col']
            : "''";
        $nameExpr = filled($usuario['name_col'] ?? null)
            ? 'u.'.$usuario['name_col']
            : "''";

        $rows = $q
            ->selectRaw('u.'.$usuario['id_col'].' as usuario_id')
            ->selectRaw($loginExpr.' as login')
            ->selectRaw($nameExpr.' as nome')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('u.'.$usuario['id_col'], $loginExpr, $nameExpr)
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
