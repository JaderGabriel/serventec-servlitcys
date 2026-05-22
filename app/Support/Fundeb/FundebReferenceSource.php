<?php

namespace App\Support\Fundeb;

/**
 * Classificação da fonte gravada em fundeb_municipio_references.
 */
final class FundebReferenceSource
{
    public const FONTE_NACIONAL = 'referencia_nacional_config';

    public const FONTE_FNDE_RECEITA_IEDUCAR = 'fnde_portaria_receita_ieducar';

    public const FONTE_API_CKAN = 'api_ckan_fnde';

    /** @var list<string> */
    public const PLACEHOLDER_FONTES = [
        self::FONTE_NACIONAL,
        'benchmark_db_only',
        'referencia_nacional',
    ];

    public static function isPlaceholder(?string $fonte): bool
    {
        $fonte = trim((string) $fonte);

        return $fonte !== '' && in_array($fonte, self::PLACEHOLDER_FONTES, true);
    }

    public static function isMunicipalOfficial(?string $fonte): bool
    {
        $fonte = trim((string) $fonte);

        return $fonte !== '' && ! self::isPlaceholder($fonte);
    }

    public static function tipoFromFonte(?string $fonte): string
    {
        $fonte = trim((string) $fonte);
        if (self::isPlaceholder($fonte)) {
            return 'placeholder';
        }
        if ($fonte === self::FONTE_FNDE_RECEITA_IEDUCAR) {
            return 'estimativa';
        }
        if ($fonte === self::FONTE_API_CKAN) {
            return 'oficial';
        }

        return 'oficial';
    }
}
