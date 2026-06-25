<?php

namespace App\Support\Horizonte;

use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Fundeb\FundebFndeCsvTableReader;
use Illuminate\Support\Str;

/**
 * Lista oficial FNDE de entes beneficiários/não beneficiários da complementação VAAR do Fundeb.
 *
 * Sinaliza municípios com «Pendência Identificada» (não cumprimento das condicionalidades de
 * melhoria de gestão — art. 14 da Lei nº 14.113/2020), que ficam não habilitados ao VAAR.
 */
final class FndeVaarNaoHabilitadosCsvParser
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
            'ibge' => ['codigo ibge', 'ibge'],
            'entidade' => ['entidade', 'ente federado'],
            'habilitados' => ['habilitados'],
            'beneficiario' => ['beneficiario'],
            'pendencia' => ['pendencia identificada', 'pendencia'],
        ]);

        $dataStart = $table['data_start'];
        if ($dataStart < 0) {
            return [];
        }

        $columns = $table['columns'];
        $ufCol = ($columns['uf'] ?? -1) >= 0 ? $columns['uf'] : 0;
        $ibgeCol = ($columns['ibge'] ?? -1) >= 0 ? $columns['ibge'] : 1;
        $nameCol = ($columns['entidade'] ?? -1) >= 0 ? $columns['entidade'] : 2;
        $habCol = $columns['habilitados'] ?? -1;
        $pendCol = ($columns['pendencia'] ?? -1) >= 0 ? $columns['pendencia'] : -1;

        $index = [];
        for ($i = $dataStart, $count = count($rows); $i < $count; $i++) {
            $row = $rows[$i];
            if (! FundebFndeCsvTableReader::isDataRow($row)) {
                continue;
            }

            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($row[$ibgeCol] ?? '');
            if ($ibge === null || strlen($ibge) !== 7) {
                continue;
            }

            $pendencia = $pendCol >= 0 ? trim((string) ($row[$pendCol] ?? '')) : '';
            if ($pendencia === '' && $pendCol < 0) {
                $pendencia = trim((string) end($row));
            }
            $habilitados = $habCol >= 0 ? trim((string) ($row[$habCol] ?? '')) : '';

            if (! self::isNaoHabilitado($pendencia, $habilitados)) {
                continue;
            }

            $uf = strtoupper(trim((string) ($row[$ufCol] ?? '')));
            $name = trim((string) ($row[$nameCol] ?? ''));

            $index[$ibge] = self::entry($ibge, $uf, $name, $pendencia, $exerciseYear, $detailUrl);
        }

        return $index;
    }

    private static function isNaoHabilitado(string $pendencia, string $habilitados): bool
    {
        if ($pendencia !== '') {
            return true;
        }

        return str_contains(Str::lower(Str::ascii($habilitados)), 'nao habilitado');
    }

    /**
     * @return array{ibge: string, uf: string, name: string, detail: string, items: list<array<string, mixed>>}
     */
    private static function entry(
        string $ibge,
        string $uf,
        string $name,
        string $detail,
        int $exerciseYear,
        string $detailUrl,
    ): array {
        return [
            'ibge' => $ibge,
            'uf' => $uf,
            'name' => $name,
            'detail' => $detail,
            'items' => [
                [
                    'kind' => 'vaar_nao_habilitado',
                    'severity' => 'warning',
                    'title' => __('Não habilitado à complementação VAAR'),
                    'detail' => $detail !== ''
                        ? $detail
                        : __('Rede não habilitada às condicionalidades de gestão do VAAR.'),
                    'exercise_year' => $exerciseYear,
                    'source' => 'fnde_vaar_nao_habilitados',
                    'detail_url' => $detailUrl,
                ],
            ],
        ];
    }
}
