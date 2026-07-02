<?php

namespace App\Support\Inep;

/**
 * Extrai matrículas por escola dos microdados Educacenso INEP sem dupla contagem.
 *
 * Regra INEP: qt_mat_bas já agrega infantil+fundamental+médio; não somar com qt_mat_inf/fund/med.
 */
final class InepEducacensoMatriculaColumns
{
    /**
     * @param  array<string, int>  $map
     * @return array{
     *     infantil: int,
     *     fundamental_1: int,
     *     fundamental_2: int,
     *     medio: int,
     *     profissional: int
     * }
     */
    public static function etapasFromRow(array $row, array $map): array
    {
        return [
            'infantil' => self::int($row, $map, 'qt_mat_inf'),
            'fundamental_1' => self::intColumn($row, $map, ['qt_mat_fund_ai', 'qt_mat_fund_1']),
            'fundamental_2' => self::intColumn($row, $map, ['qt_mat_fund_af', 'qt_mat_fund_2']),
            'medio' => self::int($row, $map, 'qt_mat_med'),
            'profissional' => self::int($row, $map, 'qt_mat_prof'),
        ];
    }

    /**
     * @param  array<string, int>  $map
     */
    public static function hasEtapaColumns(array $map): bool
    {
        foreach ([
            'qt_mat_inf',
            'qt_mat_fund_ai', 'qt_mat_fund_1',
            'qt_mat_fund_af', 'qt_mat_fund_2',
            'qt_mat_med',
            'qt_mat_prof',
        ] as $column) {
            if (isset($map[mb_strtolower($column)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $map
     * @return array{total: int, regular: int, eja: int, especial: int, complementar: int}
     */
    public static function fromRow(array $row, array $map): array
    {
        $regular = self::basica($row, $map);
        $eja = self::int($row, $map, 'qt_mat_eja');
        $especial = self::int($row, $map, 'qt_mat_esp');
        $prof = self::int($row, $map, 'qt_mat_prof');
        $complementar = self::int($row, $map, 'qt_mat_ativ_comp')
            + self::int($row, $map, 'qt_mat_ativ_comp_esp')
            + $prof;

        $total = $regular + $eja + $especial + $prof;
        if ($total <= 0) {
            $total = max(
                self::int($row, $map, 'qt_mat'),
                self::int($row, $map, 'quantidade_matricula'),
                self::int($row, $map, 'quant_matriculas'),
            );
        }

        return [
            'total' => $total,
            'regular' => $regular,
            'eja' => $eja,
            'especial' => $especial,
            'complementar' => $complementar,
        ];
    }

    /**
     * @param  array<string, int>  $map
     */
    public static function hasMatriculaColumns(array $map): bool
    {
        foreach ([
            'qt_mat_bas', 'qt_mat_inf', 'qt_mat_fund', 'qt_mat_med',
            'qt_mat_eja', 'qt_mat_esp', 'qt_mat_prof', 'qt_mat',
            'quantidade_matricula', 'quant_matriculas',
        ] as $column) {
            if (isset($map[mb_strtolower($column)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $map
     */
    private static function basica(array $row, array $map): int
    {
        $bas = self::int($row, $map, 'qt_mat_bas');
        if ($bas > 0) {
            return $bas;
        }

        return self::int($row, $map, 'qt_mat_inf')
            + self::int($row, $map, 'qt_mat_fund')
            + self::int($row, $map, 'qt_mat_med');
    }

    /**
     * @param  array<string, int>  $map
     */
    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $columns
     */
    private static function intColumn(array $row, array $map, array $columns): int
    {
        foreach ($columns as $column) {
            if (isset($map[mb_strtolower($column)])) {
                return self::int($row, $map, $column);
            }
        }

        return 0;
    }

    /**
     * @param  array<string, int>  $map
     */
    private static function int(array $row, array $map, string $column): int
    {
        $idx = $map[mb_strtolower($column)] ?? null;
        if ($idx === null || ! isset($row[$idx]) || ! is_numeric($row[$idx])) {
            return 0;
        }

        return max(0, (int) $row[$idx]);
    }
}
