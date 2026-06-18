<?php

namespace App\Services\Educacenso;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\Connection;

/**
 * Snapshot read-only do i-Educar para cruzamento com arquivo Educacenso.
 */
final class EducacensoIeducarSnapshot
{
    /**
     * @return array{
     *   ok: bool,
     *   note: ?string,
     *   total_matriculas: int,
     *   schools_by_inep: array<string, array{escola_id: int|string, nome: string, matriculas: int}>,
     *   school_ineps_with_matricula: list<string>
     * }
     */
    public function capture(Connection $db, City $city, IeducarFilterState $filters): array
    {
        if ($db->getDriverName() === 'pgsql') {
            $db->statement('SET TRANSACTION READ ONLY');
        }

        $total = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters) ?? 0;
        $schools = $this->schoolsWithInep($db, $city, $filters);

        return [
            'ok' => true,
            'note' => null,
            'total_matriculas' => max(0, $total),
            'schools_by_inep' => $schools['by_inep'],
            'school_ineps_with_matricula' => $schools['ineps_with_matricula'],
        ];
    }

    /**
     * @return array{
     *   by_inep: array<string, array{escola_id: int|string, nome: string, matriculas: int}>,
     *   ineps_with_matricula: list<string>
     * }
     */
    private function schoolsWithInep(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $inepTable = IeducarSchema::resolveTable('educacenso_cod_escola', $city);
        $escolaT = IeducarSchema::resolveTable('escola', $city);

        $eId = (string) config('ieducar.columns.escola.id', 'cod_escola');
        $eName = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
            (string) config('ieducar.columns.escola.name', 'nome'),
            'nm_escola',
            'nome',
        ], $city) ?? 'nome';

        $inepColEscola = (string) config('ieducar.columns.educacenso_cod_escola.cod_escola', 'cod_escola');
        $inepColInep = (string) config('ieducar.columns.educacenso_cod_escola.cod_escola_inep', 'cod_escola_inep');

        if (! IeducarColumnInspector::tableExists($db, $inepTable, $city)) {
            return ['by_inep' => [], 'ineps_with_matricula' => []];
        }

        $inepRows = $db->table($inepTable.' as ie')
            ->join($escolaT.' as e', 'ie.'.$inepColEscola, '=', 'e.'.$eId)
            ->select([
                'e.'.$eId.' as escola_id',
                'e.'.$eName.' as nome',
                'ie.'.$inepColInep.' as inep',
            ])
            ->get();

        $byInep = [];
        foreach ($inepRows as $row) {
            $inep = $this->normalizeInep((string) ($row->inep ?? ''));
            if ($inep === null) {
                continue;
            }
            $byInep[$inep] = [
                'escola_id' => $row->escola_id,
                'nome' => (string) ($row->nome ?? '—'),
                'matriculas' => 0,
            ];
        }

        if ($byInep === []) {
            return ['by_inep' => [], 'ineps_with_matricula' => []];
        }

        $escolaIds = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['escola_id'] ?? 0),
            $byInep,
        )));

        $countsByEscola = MatriculaChartQueries::matriculasCountByEscolaIds($db, $city, $filters, $escolaIds);

        $withMat = [];
        foreach ($byInep as $inep => &$row) {
            $eid = (int) ($row['escola_id'] ?? 0);
            $total = (int) ($countsByEscola[$eid] ?? 0);
            $row['matriculas'] = $total;
            if ($total > 0) {
                $withMat[] = $inep;
            }
        }
        unset($row);

        return ['by_inep' => $byInep, 'ineps_with_matricula' => $withMat];
    }

    private function normalizeInep(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === null || strlen($digits) < 8) {
            return null;
        }

        return substr($digits, 0, 8);
    }
}
