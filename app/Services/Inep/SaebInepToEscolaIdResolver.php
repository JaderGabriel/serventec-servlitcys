<?php

namespace App\Services\Inep;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Liga o código INEP da escola (divulgação SAEB) ao cod_escola interno do i-Educar.
 */
final class SaebInepToEscolaIdResolver
{
    public function __construct(
        private CityDataConnection $cityData,
    ) {}

    public function resolve(City $city, int $inep): ?int
    {
        if ($inep <= 0) {
            return null;
        }

        try {
            return $this->cityData->run($city, function (Connection $db) use ($city, $inep): ?int {
                return $this->resolveInConnection($db, $city, $inep);
            });
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveInConnection(Connection $db, City $city, int $inep): ?int
    {
        $edTable = trim((string) config('ieducar.tables.educacenso_cod_escola', ''));
        if ($edTable !== '') {
            try {
                $fk = (string) config('ieducar.columns.educacenso_cod_escola.cod_escola', 'cod_escola');
                $inepCol = (string) config('ieducar.columns.educacenso_cod_escola.cod_escola_inep', 'cod_escola_inep');
                if (IeducarColumnInspector::tableExists($db, $edTable, $city)
                    && IeducarColumnInspector::columnExists($db, $edTable, $fk, $city)
                    && IeducarColumnInspector::columnExists($db, $edTable, $inepCol, $city)) {
                    $row = $db->table($edTable)->where($inepCol, $inep)->orderBy($fk)->first([$fk]);
                    if ($row !== null) {
                        $v = is_object($row) ? ($row->{$fk} ?? null) : ($row[$fk] ?? null);
                        if (is_numeric($v)) {
                            return (int) $v;
                        }
                    }
                }
            } catch (QueryException) {
                // continua para escola.*
            }
        }

        try {
            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eId = (string) config('ieducar.columns.escola.id', 'cod_escola');
            $inepCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'codigo_inep', 'cod_escola_inep', 'inep', 'cod_inep', 'codigo_escola_inep', 'inep_escola', 'ref_cod_escola_inep',
            ], $city);
            if ($inepCol === null) {
                return null;
            }
            $row = $db->table($escolaT)->where($inepCol, $inep)->orderBy($eId)->first([$eId]);
            if ($row !== null) {
                $v = is_object($row) ? ($row->{$eId} ?? null) : ($row[$eId] ?? null);
                if (is_numeric($v)) {
                    return (int) $v;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
