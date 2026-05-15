<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Escopo opcional da aba Inclusão: só matrículas NEE ou só inconsistências recurso × NEE.
 */
final class InclusionMatriculaScope
{
    public static function isActive(IeducarFilterState $filters): bool
    {
        return $filters->inclusionSomenteNee() || $filters->inclusionSomenteInconsistencias();
    }

    /**
     * Aplica filtro à query que já tem matrícula `m` e aluno `a` (ou alias indicados).
     */
    public static function apply(
        Builder $q,
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        string $alunoAlias = 'a',
    ): void {
        $aId = (string) config('ieducar.columns.aluno.id');
        $col = $alunoAlias.'.'.$aId;

        if ($filters->inclusionSomenteNee()) {
            $neeSub = DiscrepanciesQueries::alunosComNeeSubqueryPublic($db, $city);
            if ($neeSub !== null) {
                $q->whereIn($col, $neeSub);
            }

            return;
        }

        if ($filters->inclusionSomenteInconsistencias()) {
            $sub = self::alunosComInconsistenciaSubquery($db, $city, $filters);
            if ($sub !== null) {
                $q->whereIn($col, $sub);
            }
        }
    }

    /**
     * @return \Closure(Builder): void|null
     */
    public static function alunosComInconsistenciaSubquery(Connection $db, City $city, IeducarFilterState $filters): ?\Closure
    {
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $aId = (string) config('ieducar.columns.aluno.id');

        $recursoSub = InclusionRecursoProvaQueries::alunosComRecursoProvaSubquery($db, $city);
        $neeSub = DiscrepanciesQueries::alunosComNeeSubqueryPublic($db, $city);

        if ($recursoSub === null || $neeSub === null) {
            return null;
        }

        $exigirRecurso = (bool) config('ieducar.inclusion.recurso_prova_exigir_com_nee', false);

        return static function ($sub) use ($aluno, $aId, $recursoSub, $neeSub, $exigirRecurso): void {
            $sub->select($aId)
                ->from($aluno)
                ->where(function ($q) use ($aId, $recursoSub, $neeSub, $exigirRecurso): void {
                    $q->where(function ($q1) use ($aId, $recursoSub, $neeSub): void {
                        $q1->whereIn($aId, $recursoSub)->whereNotIn($aId, $neeSub);
                    });
                    if ($exigirRecurso) {
                        $q->orWhere(function ($q2) use ($aId, $neeSub, $recursoSub): void {
                            $q2->whereIn($aId, $neeSub)->whereNotIn($aId, $recursoSub);
                        });
                    }
                });
        };
    }
}
