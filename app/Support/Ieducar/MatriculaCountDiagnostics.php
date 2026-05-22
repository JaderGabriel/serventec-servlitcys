<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;

/**
 * Contagens auxiliares para diagnosticar municípios com KPI de matrículas em zero.
 */
final class MatriculaCountDiagnostics
{
    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $yearVal = $filters->yearFilterValue();
        $mat = IeducarSchema::resolveTable('matricula', $city);
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $mId = (string) config('ieducar.columns.matricula.id');
        $mAno = MatriculaTurmaJoin::matriculaAnoColumn($db, $city);
        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        $canTurma = DiscrepanciesAvailability::canJoinTurma($db, $city);

        $out = [
            'year_filter' => $yearVal,
            'matricula_ano_column' => $mAno,
            'turma_year_column' => $tc['year'] !== '' ? $tc['year'] : null,
            'uses_matricula_turma_pivot' => MatriculaTurmaJoin::usePivotTable($db, $city),
            'can_join_turma' => $canTurma,
            'counts' => [],
        ];

        try {
            $out['counts']['painel_total_ativas'] = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);
        } catch (\Throwable $e) {
            $out['counts']['painel_total_ativas'] = null;
            $out['counts']['painel_total_ativas_error'] = $e->getMessage();
        }

        if ($yearVal === null || $mAno === null) {
            return $out;
        }

        try {
            $qAtivo = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($qAtivo, $db, 'm.'.$mAtivo, $city);
            $out['counts']['ativas_matricula_ano'] = (int) $qAtivo->where('m.'.$mAno, $yearVal)
                ->distinct()
                ->count('m.'.$mId);
        } catch (\Throwable) {
            $out['counts']['ativas_matricula_ano'] = null;
        }

        if (! $canTurma || $tc['year'] === '') {
            return $out;
        }

        try {
            $qTurma = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($qTurma, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($qTurma, $db, $city, 'm', left: false);
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($qTurma, $db, $city, allowNullPivot: true);
            $qTurma->where('t_filter.'.$tc['year'], $yearVal);
            $out['counts']['ativas_somente_turma_ano_inner'] = (int) $qTurma->distinct()->count('m.'.$mId);
        } catch (\Throwable) {
            $out['counts']['ativas_somente_turma_ano_inner'] = null;
        }

        try {
            $qLegado = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($qLegado, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($qLegado, $db, $city, 'm', left: true);
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($qLegado, $db, $city, allowNullPivot: true);
            $grammar = $db->getQueryGrammar();
            $tYear = $grammar->wrap('t_filter').'.'.$grammar->wrap($tc['year']);
            $mYear = $grammar->wrap('m').'.'.$grammar->wrap($mAno);
            $tId = (string) config('ieducar.columns.turma.id');
            $tPk = $grammar->wrap('t_filter').'.'.$grammar->wrap($tId);
            $qLegado->where(function ($w) use ($yearVal, $tYear, $mYear, $tPk): void {
                $w->whereRaw($tYear.' = ?', [$yearVal])
                    ->orWhere(function ($w2) use ($yearVal, $mYear, $tPk): void {
                        $w2->whereRaw($mYear.' = ?', [$yearVal])
                            ->whereNull($tPk);
                    });
            });
            $out['counts']['ativas_filtro_ano_legado_turma_ou_matricula_sem_turma'] = (int) $qLegado->distinct()->count('m.'.$mId);
        } catch (\Throwable) {
            $out['counts']['ativas_filtro_ano_legado_turma_ou_matricula_sem_turma'] = null;
        }

        return $out;
    }
}
