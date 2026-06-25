<?php

namespace App\Support\Horizonte;

use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Fundeb\FundebFndeCsvTableReader;
use Illuminate\Support\Str;

/**
 * Lista oficial FNDE de entes habilitados/inabilitados ao VAAT (CSV publicado no portal Fundeb).
 */
final class FndeVaatInabilitadosCsvParser
{
    /**
     * @return array<string, array{ibge: string, uf: string, name: string, detail: string, items: list<array<string, mixed>>}>
     */
    public static function parse(string $body, int $exerciseYear, string $detailUrl): array
    {
        $rows = FundebFndeCsvTableReader::rowsFromBody($body);
        if ($rows === []) {
            return [];
        }

        $table = FundebFndeCsvTableReader::locateTable($rows, [
            'uf' => ['uf'],
            'entidade' => ['ente federado', 'entidade'],
            'ibge' => ['codigo ibge', 'ibge'],
            'status' => ['verificacao', 'art. 13', 'lei n'],
            'pendencia' => ['pendencia'],
        ]);

        $dataStart = $table['data_start'];
        if ($dataStart < 0) {
            return [];
        }

        $columns = $table['columns'];
        if (($columns['ibge'] ?? -1) < 0) {
            $columns = self::inferColumns($rows[$dataStart]);
        }

        $index = [];
        for ($i = $dataStart, $count = count($rows); $i < $count; $i++) {
            $row = $rows[$i];
            if (! FundebFndeCsvTableReader::isDataRow($row)) {
                continue;
            }

            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($row[$columns['ibge']] ?? '');
            if ($ibge === null || strlen($ibge) !== 7) {
                continue;
            }

            $uf = strtoupper(trim((string) ($row[$columns['uf']] ?? '')));
            $name = trim((string) ($row[$columns['entidade']] ?? ''));
            $status = trim((string) ($row[$columns['status']] ?? ''));
            $pendencia = trim((string) ($row[$columns['pendencia']] ?? ''));

            if (! self::isInabilitado($status, $pendencia)) {
                continue;
            }

            $detail = self::composeDetail($status, $pendencia);
            $index[$ibge] = FndeVaatInabilitadosParser::entryForMunicipality(
                $ibge,
                $uf,
                $name,
                $detail,
                $exerciseYear,
                $detailUrl,
            );
        }

        return $index;
    }

    /**
     * @param  list<string>  $row
     * @return array{uf: int, entidade: int, ibge: int, status: int, pendencia: int}
     */
    private static function inferColumns(array $row): array
    {
        if (count($row) >= 5) {
            return [
                'uf' => 0,
                'entidade' => 1,
                'ibge' => 2,
                'status' => 3,
                'pendencia' => 4,
            ];
        }

        return [
            'uf' => 0,
            'entidade' => 1,
            'ibge' => 2,
            'status' => 3,
            'pendencia' => 4,
        ];
    }

    private static function isInabilitado(string $status, string $pendencia): bool
    {
        $statusNorm = Str::lower(Str::ascii($status));
        if (str_contains($statusNorm, 'inobserv') || str_contains($statusNorm, 'inabilit')) {
            return true;
        }

        $pend = trim($pendencia);
        if ($pend === '') {
            return false;
        }

        return ! str_contains($statusNorm, 'habilitado para o calculo do vaat');
    }

    private static function composeDetail(string $status, string $pendencia): string
    {
        $status = trim($status);
        $pendencia = trim($pendencia);
        if ($status === '') {
            return $pendencia;
        }
        if ($pendencia === '' || str_contains($status, $pendencia)) {
            return $status;
        }

        return $status.' '.$pendencia;
    }
}
