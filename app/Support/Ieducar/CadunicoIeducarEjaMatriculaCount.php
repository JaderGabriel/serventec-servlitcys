<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/** Contagem de matrículas EJA na rede municipal (heurística curso/turma). */
final class CadunicoIeducarEjaMatriculaCount
{
    public static function count(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $turma = IeducarSchema::resolveTable('turma', $city);
            $curso = IeducarSchema::resolveTable('curso', $city);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $mId = (string) config('ieducar.columns.matricula.id');
        $tCurso = (string) config('ieducar.columns.turma.curso');
        $tName = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
            (string) config('ieducar.columns.turma.name'),
            'nm_turma',
        ]), $city) ?? 'nm_turma';
        $cName = IeducarColumnInspector::firstExistingColumn($db, $curso, array_filter([
            (string) config('ieducar.columns.curso.name'),
            'nm_curso',
        ]), $city) ?? 'nm_curso';
        $cId = (string) config('ieducar.columns.curso.id');

        $q = DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters);
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
        MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

        $q->leftJoin($curso.' as c', 't_filter.'.$tCurso, '=', 'c.'.$cId);

        try {
            $rows = $q->selectRaw('t_filter.'.$tName.' as nm_turma')
                ->selectRaw('c.'.$cName.' as nm_curso')
                ->selectRaw('m.'.$mId.' as matricula_id')
                ->get();
        } catch (QueryException) {
            return null;
        }

        $ids = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $matriculaId = (int) ($arr['matricula_id'] ?? 0);
            if ($matriculaId <= 0 || isset($ids[$matriculaId])) {
                continue;
            }
            $label = InclusionDashboardQueries::segmentLabelFromCursoTurma(
                (string) ($arr['nm_curso'] ?? ''),
                (string) ($arr['nm_turma'] ?? ''),
            );
            if (str_contains(mb_strtolower($label), 'eja') || str_contains(mb_strtolower($label), 'jovens e adultos')) {
                $ids[$matriculaId] = true;
            }
        }

        $count = count($ids);

        return $count > 0 ? $count : null;
    }
}
