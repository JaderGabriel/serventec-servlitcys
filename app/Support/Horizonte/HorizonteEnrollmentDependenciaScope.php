<?php

namespace App\Support\Horizonte;

/**
 * Âmbito de dependência administrativa (Educacenso INEP) no gráfico de matrículas Horizonte — v1.
 *
 * v2 (futuro): federal, estadual, municipal e privada em séries comparáveis.
 */
final class HorizonteEnrollmentDependenciaScope
{
    public const TOTAL = 'total';

    public const MUNICIPAL = 'municipal';

    public const NAO_MUNICIPAL = 'nao_municipal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::TOTAL, self::MUNICIPAL, self::NAO_MUNICIPAL];
    }

    public static function normalize(?string $raw): string
    {
        $key = mb_strtolower(trim((string) $raw));

        return match ($key) {
            self::MUNICIPAL, 'rede_municipal' => self::MUNICIPAL,
            self::NAO_MUNICIPAL, 'nao-municipal', 'não_municipal', 'rede_nao_municipal' => self::NAO_MUNICIPAL,
            default => self::TOTAL,
        };
    }

    public static function label(string $scope): string
    {
        return match (self::normalize($scope)) {
            self::MUNICIPAL => __('Rede municipal'),
            self::NAO_MUNICIPAL => __('Não municipal (estadual, federal e privada)'),
            default => __('Todas as redes no território'),
        };
    }

    public static function shortLabel(string $scope): string
    {
        return match (self::normalize($scope)) {
            self::MUNICIPAL => __('Municipal'),
            self::NAO_MUNICIPAL => __('Não municipal'),
            default => __('Total'),
        };
    }

    /**
     * Coluna em inep_censo_municipio_matriculas para a métrica base no âmbito pedido.
     */
    public static function column(string $baseColumn, string $scope): string
    {
        $scope = self::normalize($scope);

        if ($scope === self::TOTAL) {
            return $baseColumn;
        }

        if ($baseColumn === 'matriculas_total') {
            return $scope === self::MUNICIPAL ? 'matriculas_municipal' : 'matriculas_nao_municipal';
        }

        return $baseColumn.'_'.$scope;
    }

    /**
     * @return list<array{key: string, label: string, base_column: string}>
     */
    public static function seriesDefinitions(): array
    {
        return [
            ['key' => 'total', 'label' => __('Total'), 'base_column' => 'matriculas_total'],
            ['key' => 'regular', 'label' => __('Regular'), 'base_column' => 'matriculas_regular'],
            ['key' => 'eja', 'label' => __('EJA'), 'base_column' => 'matriculas_eja'],
            ['key' => 'especial', 'label' => __('Educação especial'), 'base_column' => 'matriculas_especial'],
            ['key' => 'complementar', 'label' => __('Complementar / integral'), 'base_column' => 'matriculas_complementar'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, base_column: string}>
     */
    public static function stageDefinitions(): array
    {
        return [
            ['key' => 'infantil', 'label' => __('Educação infantil'), 'base_column' => 'matriculas_infantil'],
            ['key' => 'fundamental_1', 'label' => __('Fundamental I'), 'base_column' => 'matriculas_fundamental_1'],
            ['key' => 'fundamental_2', 'label' => __('Fundamental II'), 'base_column' => 'matriculas_fundamental_2'],
            ['key' => 'medio', 'label' => __('Ensino médio'), 'base_column' => 'matriculas_medio'],
            ['key' => 'profissional', 'label' => __('Educação profissional'), 'base_column' => 'matriculas_profissional'],
        ];
    }
}
