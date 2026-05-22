<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;

/**
 * Contagem de cadastros recentes no i-Educar por período e usuário (excl. admin).
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
     * @return array{turmas: int, matriculas: int, enturmacoes: int, ano: int}
     */
    public static function baselineFromPreviousYear(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): array {
        $year = $filters->yearFilterValue();
        if ($year === null || $year <= 1) {
            return ['turmas' => 0, 'matriculas' => 0, 'enturmacoes' => 0, 'ano' => 0];
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
        $enturmacoes = self::countEnturmacoesForYear($db, $city, $prevFilters);

        return [
            'turmas' => $turmas,
            'matriculas' => $matriculas,
            'enturmacoes' => $enturmacoes,
            'ano' => $prevYear,
        ];
    }

    /**
     * Vínculos matrícula ↔ turma (enturmações activas no filtro).
     */
    public static function countEnturmacoesForYear(Connection $db, City $city, IeducarFilterState $filters): int
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $turma = IeducarSchema::resolveTable('turma', $city);
            $tId = (string) config('ieducar.columns.turma.id');

            if (MatriculaTurmaJoin::usePivotTable($db, $city)) {
                $mt = IeducarSchema::resolveTable('matricula_turma', $city);
                $mtMat = (string) config('ieducar.columns.matricula_turma.matricula');
                $mtTurma = (string) config('ieducar.columns.matricula_turma.turma');
                $mtAtivo = (string) config('ieducar.columns.matricula_turma.ativo');

                $q = $db->table($mt.' as mt');
                $q->join($mat.' as m', 'mt.'.$mtMat, '=', 'm.'.$mId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
                if ($mtAtivo !== '' && IeducarColumnInspector::columnExists($db, $mt, $mtAtivo, $city)) {
                    MatriculaAtivoFilter::apply($q, $db, 'mt.'.$mtAtivo, $city);
                }
                $q->join($turma.' as t_ent', 'mt.'.$mtTurma, '=', 't_ent.'.$tId);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_ent');

                return (int) $q->count();
            }

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            return (int) $q->count();
        } catch (\Throwable) {
            return 0;
        }
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
     * Ritmo médio (matrículas com data de cadastro / dia) a partir do que o município registrou.
     *
     * @param  array{day: int, week: int, fortnight: int}  $periodCounts
     * @return array{pace: float, fonte: string, cadastros_dia: int, cadastros_semana: int, cadastros_quinzena: int}
     */
    public static function observedCadastroPace(array $periodCounts): array
    {
        $day = max(0, (int) ($periodCounts['day'] ?? 0));
        $week = max(0, (int) ($periodCounts['week'] ?? 0));
        $fortnight = max(0, (int) ($periodCounts['fortnight'] ?? 0));

        $daysDay = max(1, (int) config('ieducar.work_tracking.periods_days.day', 1));
        $daysWeek = max(1, (int) config('ieducar.work_tracking.periods_days.week', 7));
        $daysFortnight = max(1, (int) config('ieducar.work_tracking.periods_days.fortnight', 15));

        $paceDay = $day / $daysDay;
        $paceWeek = $week / $daysWeek;
        $paceFortnight = $fortnight / $daysFortnight;

        if ($fortnight >= 3) {
            $pace = round(0.65 * $paceFortnight + 0.35 * $paceWeek, 2);

            return [
                'pace' => $pace,
                'fonte' => 'quinzena_semana',
                'cadastros_dia' => $day,
                'cadastros_semana' => $week,
                'cadastros_quinzena' => $fortnight,
            ];
        }

        if ($week >= 2) {
            return [
                'pace' => round($paceWeek, 2),
                'fonte' => 'semana',
                'cadastros_dia' => $day,
                'cadastros_semana' => $week,
                'cadastros_quinzena' => $fortnight,
            ];
        }

        if ($day >= 1) {
            return [
                'pace' => round($paceDay, 2),
                'fonte' => 'dia',
                'cadastros_dia' => $day,
                'cadastros_semana' => $week,
                'cadastros_quinzena' => $fortnight,
            ];
        }

        return [
            'pace' => 0.0,
            'fonte' => 'sem_cadastro_recente',
            'cadastros_dia' => $day,
            'cadastros_semana' => $week,
            'cadastros_quinzena' => $fortnight,
        ];
    }

    /**
     * Minutos por matrícula implícitos num ritmo observado (1 pessoa, :h h/dia de capacidade).
     */
    public static function minutesPerMatriculaFromPace(float $pacePerDay, float $hoursPerDay): float
    {
        if ($pacePerDay <= 0) {
            return 0.0;
        }

        return round((60.0 * max(1.0, $hoursPerDay)) / $pacePerDay, 1);
    }

    /**
     * Pesos relativos turma/mat./enturmação a partir da config (só razão entre tipos, não tempo absoluto).
     *
     * @return array{turma: float, matricula: float, enturmacao: float}
     */
    public static function relativeTypeWeights(): array
    {
        $cfgMat = max(0.5, (float) config('ieducar.work_tracking.minutes_per_matricula', 3.5));
        $cfgTurma = max(0.5, (float) config('ieducar.work_tracking.minutes_per_turma', 8));
        $cfgEnt = max(0.5, (float) config('ieducar.work_tracking.minutes_per_enturmacao', 2.5));

        return [
            'turma' => round($cfgTurma / $cfgMat, 2),
            'matricula' => 1.0,
            'enturmacao' => round($cfgEnt / $cfgMat, 2),
        ];
    }

    /**
     * @param  array{turmas: int, matriculas: int, enturmacoes: int, ano: int}  $baseline
     * @param  array{day: int, week: int, fortnight: int}  $periodCounts
     * @param  list<array{total: int}>  $byUser
     * @return array<string, mixed>
     */
    public static function buildEstimate(
        array $baseline,
        array $periodCounts,
        int $currentTurmas,
        int $currentMatriculas,
        int $currentEnturmacoes,
        array $byUser = [],
    ): array {
        $metaTurmas = max(0, (int) $baseline['turmas']);
        $metaMatriculas = max(0, (int) $baseline['matriculas']);
        $metaEnturmacoes = max(0, (int) ($baseline['enturmacoes'] ?? $metaMatriculas));

        $restTurmas = max(0, $metaTurmas - $currentTurmas);
        $restMatriculas = max(0, $metaMatriculas - $currentMatriculas);
        $restEnturmacoes = max(0, $metaEnturmacoes - $currentEnturmacoes);
        $restTotal = $restTurmas + $restMatriculas + $restEnturmacoes;

        $hoursPerDay = (float) config('ieducar.work_tracking.working_hours_per_day', 6);
        $cfgMinTurma = (float) config('ieducar.work_tracking.minutes_per_turma', 8);
        $cfgMinMatricula = (float) config('ieducar.work_tracking.minutes_per_matricula', 3.5);
        $cfgMinEnturmacao = (float) config('ieducar.work_tracking.minutes_per_enturmacao', 2.5);
        $defaultMin = (float) config('ieducar.work_tracking.default_minutes_per_record', 3.5);

        $observed = self::observedCadastroPace($periodCounts);
        $pace = (float) $observed['pace'];
        $usaRitmo = $pace > 0;

        $weights = self::relativeTypeWeights();
        $activeUsers = count(array_filter($byUser, static fn (array $u): bool => (int) ($u['total'] ?? 0) > 0));
        $teamPace = $pace;
        if ($usaRitmo && $activeUsers > 1) {
            $teamPace = round($pace * min($activeUsers, 5), 2);
        }

        if ($usaRitmo) {
            $minMatricula = self::minutesPerMatriculaFromPace($pace, $hoursPerDay);
            $minTurma = round($minMatricula * $weights['turma'], 1);
            $minEnturmacao = round($minMatricula * $weights['enturmacao'], 1);
            $minutesPerRecord = $minMatricula;
        } else {
            $minTurma = $cfgMinTurma;
            $minMatricula = $cfgMinMatricula;
            $minEnturmacao = $cfgMinEnturmacao;
            $minutesPerRecord = $defaultMin;
        }

        $minutesTurmas = $restTurmas * $minTurma;
        $minutesMatriculas = $restMatriculas * $minMatricula;
        $minutesEnturmacoes = $restEnturmacoes * $minEnturmacao;
        $totalMinutes = $minutesTurmas + $minutesMatriculas + $minutesEnturmacoes;
        $totalHours = round($totalMinutes / 60.0, 1);
        $fteDays = $hoursPerDay > 0 ? round($totalHours / $hoursPerDay, 1) : null;

        $cadastroRestantes = $restMatriculas + $restEnturmacoes;
        $paceForDays = $teamPace > 0 ? $teamPace : $pace;

        $daysCadastroPace = $paceForDays > 0 && $cadastroRestantes > 0
            ? (int) ceil($cadastroRestantes / $paceForDays)
            : null;
        $daysTurmasPace = $restTurmas > 0 && $paceForDays > 0
            ? (int) ceil(($restTurmas * $weights['turma']) / max($paceForDays, 0.01))
            : null;
        $daysTurmasFixed = ! $usaRitmo && $restTurmas > 0 && $minTurma > 0
            ? (int) ceil(($restTurmas * $minTurma) / (60 * max($hoursPerDay, 1)))
            : 0;

        $daysToFinish = null;
        if ($usaRitmo) {
            $parts = array_filter([$daysCadastroPace, $daysTurmasPace], static fn (?int $d): bool => $d !== null && $d > 0);
            $daysToFinish = $parts !== [] ? max($parts) : null;
        } elseif ($daysCadastroPace !== null || $daysTurmasFixed > 0) {
            $daysToFinish = max($daysCadastroPace ?? 0, $daysTurmasFixed);
        }

        $progressTurmas = $metaTurmas > 0 ? round(100.0 * min($currentTurmas, $metaTurmas) / $metaTurmas, 1) : null;
        $progressMatriculas = $metaMatriculas > 0 ? round(100.0 * min($currentMatriculas, $metaMatriculas) / $metaMatriculas, 1) : null;
        $progressEnturmacoes = $metaEnturmacoes > 0 ? round(100.0 * min($currentEnturmacoes, $metaEnturmacoes) / $metaEnturmacoes, 1) : null;

        $formulaResumo = self::estimateFormulaSummary($usaRitmo, $observed, $baseline, $minMatricula, $minTurma, $minEnturmacao, $activeUsers, $cfgMinMatricula, $cfgMinTurma, $cfgMinEnturmacao);

        return [
            'ano_referencia' => (int) $baseline['ano'],
            'meta_turmas_ano_anterior' => $metaTurmas,
            'meta_matriculas_ano_anterior' => $metaMatriculas,
            'meta_enturmacoes_ano_anterior' => $metaEnturmacoes,
            'turmas_filtro_atual' => $currentTurmas,
            'matriculas_ativas_filtro' => $currentMatriculas,
            'enturmacoes_filtro_atual' => $currentEnturmacoes,
            'turmas_restantes' => $restTurmas,
            'matriculas_restantes' => $restMatriculas,
            'enturmacoes_restantes' => $restEnturmacoes,
            'registros_restantes_estimados' => $restTotal,
            'registros_restantes_cadastro' => $cadastroRestantes,
            'progresso_turmas_pct' => $progressTurmas,
            'progresso_matriculas_pct' => $progressMatriculas,
            'progresso_enturmacoes_pct' => $progressEnturmacoes,
            'turmas_ano_anterior' => $metaTurmas,
            'ritmo_por_dia' => $pace,
            'ritmo_equipe_por_dia' => $teamPace,
            'ritmo_fonte' => (string) $observed['fonte'],
            'cadastros_ultimo_dia' => (int) $observed['cadastros_dia'],
            'cadastros_ultima_semana' => (int) $observed['cadastros_semana'],
            'cadastros_ultima_quinzena' => (int) $observed['cadastros_quinzena'],
            'usuários_ativos_quinzena' => $activeUsers,
            'minutos_por_registro' => $minutesPerRecord,
            'minutos_por_turma' => $minTurma,
            'minutos_por_matricula' => $minMatricula,
            'minutos_por_enturmacao' => $minEnturmacao,
            'minutos_derivados_do_ritmo' => $usaRitmo,
            'horas_turmas_estimadas' => round($minutesTurmas / 60.0, 1),
            'horas_matriculas_estimadas' => round($minutesMatriculas / 60.0, 1),
            'horas_enturmacoes_estimadas' => round($minutesEnturmacoes / 60.0, 1),
            'horas_totais_estimadas' => $totalHours,
            'dias_para_concluir_ritmo_atual' => $daysToFinish,
            'dias_cadastro_ritmo_atual' => $daysCadastroPace,
            'dias_turmas_ritmo_atual' => $daysTurmasPace,
            'dias_pessoa_equivalente' => $fteDays,
            'usa_ritmo_observado' => $usaRitmo,
            'formula_resumo' => $formulaResumo,
        ];
    }

    /**
     * @param  array{pace: float, fonte: string, cadastros_dia: int, cadastros_semana: int, cadastros_quinzena: int}  $observed
     */
    private static function estimateFormulaSummary(
        bool $usaRitmo,
        array $observed,
        array $baseline,
        float $minMatricula,
        float $minTurma,
        float $minEnturmacao,
        int $activeUsers,
        float $cfgMinMatricula,
        float $cfgMinTurma,
        float $cfgMinEnturmacao,
    ): string {
        if ($usaRitmo) {
            $fonteLabel = match ($observed['fonte']) {
                'quinzena_semana' => __('última quinzena e semana de cadastro na base'),
                'semana' => __('última semana de cadastro'),
                'dia' => __('cadastro de ontem (amostra curta)'),
                default => __('cadastro recente'),
            };

            return __('Meta = ano :ano anterior. Tempo derivado do ritmo municipal (:fonte): :q matrículas na quinzena → :ritmo/dia; minutos por tipo calculados desse ritmo (turma × :wt, matrícula ×1, enturmação × :we). :users usuário(es) ativos na quinzena aceleram o prazo.', [
                'ano' => (int) $baseline['ano'],
                'fonte' => $fonteLabel,
                'q' => number_format((int) $observed['cadastros_quinzena'], 0, ',', '.'),
                'ritmo' => number_format((float) $observed['pace'], 1, ',', '.'),
                'wt' => number_format($minTurma / max(0.1, $minMatricula), 1, ',', '.'),
                'we' => number_format($minEnturmacao / max(0.1, $minMatricula), 1, ',', '.'),
                'users' => $activeUsers,
            ]);
        }

        return __('Meta = ano :ano anterior. Sem cadastro recente mensurável — tempo de referência usa valores padrão de configuração (:mt min/turma, :mm min/mat., :me min/enturmação). Cadastre matrículas no i-Educar para o sistema passar a usar o ritmo real do município.', [
            'ano' => (int) $baseline['ano'],
            'mt' => number_format($cfgMinTurma, 1, ',', '.'),
            'mm' => number_format($cfgMinMatricula, 1, ',', '.'),
            'me' => number_format($cfgMinEnturmacao, 1, ',', '.'),
        ]);
    }

    /**
     * @deprecated Use observedCadastroPace()
     */
    public static function pacePerDay(int $fortnightCount): float
    {
        return self::observedCadastroPace(['fortnight' => $fortnightCount])['pace'];
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
            $hints[] = __('Nenhuma matrícula com data de cadastro nos últimos 15 dias (equipe municipal, filtros aplicados).');
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
     * Anos letivos cadastrados na base com indicação fechado / em andamento (quando a coluna existir).
     *
     * @return list<array{year: int, state: string, state_label: string}>
     */
    public static function schoolYearsCatalog(Connection $db, City $city, array $fallbackYears = []): array
    {
        $fromTable = self::schoolYearsFromStatusTable($db, $city);
        if ($fromTable !== []) {
            return $fromTable;
        }

        $years = $fallbackYears !== [] ? $fallbackYears : self::schoolYearsFromTurma($db, $city);
        $current = (int) date('Y');
        $out = [];
        foreach ($years as $year) {
            $y = (int) $year;
            if ($y <= 0) {
                continue;
            }
            $filters = new IeducarFilterState((string) $y, null, null, null);
            $status = self::anoLetivoStatus($db, $city, $filters);
            if ($status !== null) {
                $out[] = [
                    'year' => $y,
                    'state' => $status['fechado'] ? 'closed' : 'open',
                    'state_label' => $status['fechado']
                        ? __('Fechado')
                        : __('Em andamento'),
                ];

                continue;
            }
            $inferredOpen = $y >= $current - 1;
            $out[] = [
                'year' => $y,
                'state' => $inferredOpen ? 'open' : 'closed',
                'state_label' => $inferredOpen
                    ? __('Em andamento (estimado)')
                    : __('Fechado (estimado)'),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['year'] <=> $a['year']);

        return $out;
    }

    /**
     * @return list<array{year: int, state: string, state_label: string}>
     */
    private static function schoolYearsFromStatusTable(Connection $db, City $city): array
    {
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
                'andamento', 'ativo', 'situacao', 'situacao_ano_letivo', 'status',
            ], $city);
            $boolCol = $statusCol === null
                ? IeducarColumnInspector::firstExistingColumn($db, $table, ['fechado', 'encerrado', 'finalizado'], $city)
                : null;

            if ($yearCol === null || ($statusCol === null && $boolCol === null)) {
                continue;
            }

            $col = $statusCol ?? $boolCol;
            $rows = $db->table($table)
                ->select($yearCol.' as y', $col.' as st')
                ->whereNotNull($yearCol)
                ->distinct()
                ->orderByDesc($yearCol)
                ->limit(40)
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $byYear = [];
            foreach ($rows as $row) {
                $y = (int) ($row->y ?? 0);
                if ($y <= 0) {
                    continue;
                }
                $fechado = self::isAnoLetivoFechado($row->st ?? null, $col);
                if (! isset($byYear[$y])) {
                    $byYear[$y] = ! $fechado;

                    continue;
                }
                if (! $fechado) {
                    $byYear[$y] = true;
                }
            }

            $out = [];
            foreach ($byYear as $y => $open) {
                $out[] = [
                    'year' => $y,
                    'state' => $open ? 'open' : 'closed',
                    'state_label' => $open
                        ? __('Em andamento')
                        : __('Fechado'),
                ];
            }
            usort($out, static fn (array $a, array $b): int => $b['year'] <=> $a['year']);

            return $out;
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private static function schoolYearsFromTurma(Connection $db, City $city): array
    {
        try {
            $table = IeducarSchema::resolveTable('turma', $city);
            $col = MatriculaTurmaJoin::turmaFilterColumns($db, $city)['year'];

            return $db->table($table)
                ->select($col)
                ->whereNotNull($col)
                ->distinct()
                ->orderByDesc($col)
                ->limit(40)
                ->pluck($col)
                ->map(fn ($v) => (int) $v)
                ->filter(fn (int $v) => $v > 0)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Indica se o valor da coluna de estado do ano letivo significa «fechado» no i-Educar.
     *
     * Convenções típicas: andamento 1 = em andamento, 2 = finalizado; ativo 1 = aberto;
     * colunas booleanas fechado/encerrado: 1 = fechado.
     */
    public static function isAnoLetivoFechado(mixed $value, ?string $columnName = null): bool
    {
        $col = strtolower(trim((string) $columnName));

        if ($col === 'andamento') {
            if (is_numeric($value)) {
                $n = (int) $value;

                return $n === 2;
            }

            return self::interpretSituacaoTextAsFechado($value);
        }

        if ($col === 'ativo') {
            if (is_numeric($value)) {
                return (int) $value !== 1;
            }

            return self::interpretSituacaoTextAsFechado($value);
        }

        if (in_array($col, ['fechado', 'encerrado', 'finalizado'], true)) {
            return self::interpretBooleanFlagAsFechado($value);
        }

        return self::interpretSituacaoTextAsFechado($value);
    }

    /**
     * @return array{fechado: bool, label: string}
     */
    public static function anoLetivoStateFromValue(mixed $value, ?string $columnName = null): array
    {
        $fechado = self::isAnoLetivoFechado($value, $columnName);
        $col = strtolower(trim((string) $columnName));

        $label = match ($col) {
            'andamento' => match (true) {
                is_numeric($value) && (int) $value === 1 => __('Em andamento'),
                is_numeric($value) && (int) $value === 2 => __('Finalizado'),
                is_numeric($value) && (int) $value === 3 => __('Em elaboração'),
                default => trim((string) $value) !== '' ? (string) $value : ($fechado ? __('Fechado') : __('Em andamento')),
            },
            'ativo' => is_numeric($value)
                ? ((int) $value === 1 ? __('Ativo (ano letivo)') : __('Inativo / encerrado'))
                : ($fechado ? __('Inativo / encerrado') : __('Ativo (ano letivo)')),
            default => trim((string) $value) !== ''
                ? (string) $value
                : ($fechado ? __('Fechado') : __('Em andamento')),
        };

        return ['fechado' => $fechado, 'label' => $label];
    }

    private static function interpretBooleanFlagAsFechado(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $st = strtolower(trim((string) $value));

        return in_array($st, ['1', 't', 'true', 'yes', 'sim', 's'], true)
            || $value === 1
            || $value === true;
    }

    private static function interpretSituacaoTextAsFechado(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            $n = (int) $value;

            return $n === 2 || $n === 0;
        }

        $st = strtolower(trim((string) $value));

        if ($st === '') {
            return false;
        }

        if (in_array($st, ['aberto', 'ativo', 'em andamento', 'andamento', 'corrente', 'nao', 'n', 'false', 'f', '0'], true)) {
            return false;
        }

        if (preg_match('/\b(em\s+)?andamento\b/u', $st) === 1) {
            return false;
        }

        if (in_array($st, ['fechado', 'encerrado', 'concluido', 'finalizado', 'inativo', 'sim', 's', 'true', 't', 'yes', '1'], true)) {
            return true;
        }

        return str_contains($st, 'fech')
            || str_contains($st, 'encerr')
            || str_contains($st, 'finaliz')
            || str_contains($st, 'inativ');
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
                'andamento', 'ativo', 'situacao', 'situacao_ano_letivo', 'status',
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

                return self::anoLetivoStateFromValue($row->st ?? null, $statusCol);
            }

            $boolCol = IeducarColumnInspector::firstExistingColumn($db, $table, ['fechado', 'encerrado', 'finalizado'], $city);
            if ($boolCol !== null) {
                $row = $q->select($boolCol.' as f')->limit(1)->first();
                if ($row !== null) {
                    return self::anoLetivoStateFromValue($row->f ?? null, $boolCol);
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
