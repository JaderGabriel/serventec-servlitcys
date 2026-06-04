<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Contagens de volume no filtro: matrículas (registos distintos) e alunos (pessoas distintas).
 */
final class MatriculaVolumeCounts
{
    /**
     * @return array{matriculas: int, alunos: ?int, alunos_available: bool}
     */
    public static function count(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $q = DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters);
            $grammar = $db->getQueryGrammar();
            $mId = (string) config('ieducar.columns.matricula.id');
            $matExpr = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';
            $matriculas = (int) ($q->selectRaw($matExpr.' as c')->value('c') ?? 0);

            $mAluno = self::matriculaAlunoColumn($db, $city);
            if ($mAluno === null) {
                return [
                    'matriculas' => $matriculas,
                    'alunos' => null,
                    'alunos_available' => false,
                ];
            }

            $alunoExpr = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mAluno).')';
            $alunos = (int) ($q->selectRaw($alunoExpr.' as c')->value('c') ?? 0);

            return [
                'matriculas' => $matriculas,
                'alunos' => $alunos,
                'alunos_available' => true,
            ];
        } catch (QueryException|\InvalidArgumentException) {
            return [
                'matriculas' => 0,
                'alunos' => null,
                'alunos_available' => false,
            ];
        }
    }

    /**
     * Base indicativa FUNDEB/Censo per capita: alunos distintos quando disponível (evita inflar por matrícula duplicada).
     */
    public static function fundebCalculationBase(array $counts): int
    {
        $matriculas = max(0, (int) ($counts['matriculas'] ?? 0));
        if (! ($counts['alunos_available'] ?? false)) {
            return $matriculas;
        }

        $alunos = max(0, (int) ($counts['alunos'] ?? 0));
        if ($alunos <= 0) {
            return $matriculas;
        }

        if ($matriculas <= 0) {
            return $alunos;
        }

        return min($matriculas, $alunos);
    }

    /**
     * @param  array{matriculas: int, alunos: ?int, alunos_available: bool}  $counts
     * @return array{matriculas: int, alunos: ?int, label_matriculas: string, label_alunos: ?string, hint: ?string}
     */
    public static function presentation(array $counts): array
    {
        $mat = max(0, (int) ($counts['matriculas'] ?? 0));
        $alunos = ($counts['alunos_available'] ?? false) ? max(0, (int) ($counts['alunos'] ?? 0)) : null;

        $hint = null;
        if ($alunos !== null && $mat > $alunos) {
            $hint = __(':diff matrícula(s) a mais que alunos distintos — verifique transferências não encerradas (Discrepâncias → matrícula duplicada).', [
                'diff' => number_format($mat - $alunos, 0, ',', '.'),
            ]);
        }

        return [
            'matriculas' => $mat,
            'alunos' => $alunos,
            'label_matriculas' => __(':n matrícula(s) ativa(s)', ['n' => number_format($mat, 0, ',', '.')]),
            'label_alunos' => $alunos !== null
                ? __(':n aluno(s) distinto(s)', ['n' => number_format($alunos, 0, ',', '.')])
                : null,
            'hint' => $hint,
        ];
    }

    public static function matriculaAlunoColumn(Connection $db, City $city): ?string
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $col = (string) config('ieducar.columns.matricula.aluno');
        if ($col === '' || ! IeducarColumnInspector::columnExists($db, $mat, $col, $city)) {
            return null;
        }

        return $col;
    }
}
