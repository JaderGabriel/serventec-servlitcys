<?php

namespace App\Support\Cadunico;

use App\Repositories\FundebMunicipioReferenceRepository;

/**
 * O Solr Misocial (MDS) publica codigo_ibge com 6 dígitos (prefixo do código oficial);
 * o IBGE municipal usa 7 (inclui dígito verificador). Consultas devem tentar ambos.
 */
final class CadunicoMisocialIbgeNormalizer
{
    /**
     * Converte código vindo do Misocial para IBGE municipal de 7 dígitos.
     */
    public static function toOfficialSeven(string $misocialCode): ?string
    {
        $digits = preg_replace('/\D/', '', $misocialCode);
        if ($digits === null || $digits === '') {
            return null;
        }

        $len = strlen($digits);

        if ($len === 7) {
            return FundebMunicipioReferenceRepository::normalizeIbge($digits);
        }

        if ($len === 6) {
            return FundebMunicipioReferenceRepository::normalizeIbge($digits.'0');
        }

        if ($len === 5) {
            return FundebMunicipioReferenceRepository::normalizeIbge('0'.$digits.'0');
        }

        return FundebMunicipioReferenceRepository::normalizeIbge($digits);
    }

    /**
     * Variantes de consulta Solr para um IBGE oficial de 7 dígitos.
     *
     * @return list<string>
     */
    public static function solrQueryCodesForOfficialIbge(string $officialIbge): array
    {
        $official = FundebMunicipioReferenceRepository::normalizeIbge($officialIbge);
        if ($official === null) {
            return [];
        }

        $variants = [$official];
        if (strlen($official) === 7) {
            $six = substr($official, 0, 6);
            if ($six !== '' && $six !== $official) {
                $variants[] = $six;
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * Cláusula q= do Solr para um município (tenta 7 e 6 dígitos).
     */
    public static function solrQueryForOfficialIbge(string $officialIbge): string
    {
        $codes = self::solrQueryCodesForOfficialIbge($officialIbge);
        if ($codes === []) {
            return 'codigo_ibge:__invalid__';
        }

        if (count($codes) === 1) {
            return 'codigo_ibge:'.$codes[0];
        }

        return 'codigo_ibge:('.implode(' OR ', $codes).')';
    }
}
