<?php

namespace App\Support;

/**
 * Leitura comum do CSV de escolas do Censo (microdados_ed_basica / cadastro INEP).
 */
final class InepMicrodadosEscolasCsv
{
    /** @var list<string> */
    public const INEP_HEADER_ALIASES = ['co_entidade', 'codigo_inep', 'nu_inep', 'inep', 'cod_inep', 'cod_inep_escola'];

    /** @var list<string> */
    public const LAT_HEADER_ALIASES = ['nu_latitude', 'latitude', 'lat', 'vl_latitude', 'y'];

    /** @var list<string> */
    public const LNG_HEADER_ALIASES = ['nu_longitude', 'longitude', 'lng', 'vl_longitude', 'x'];

    public static function delimiterFromFirstLine(string $firstLine): string
    {
        $semi = substr_count($firstLine, ';');
        $comma = substr_count($firstLine, ',');

        return $semi >= $comma ? ';' : ',';
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    public static function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $i => $h) {
            $map[mb_strtolower(trim((string) $h))] = $i;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $map
     */
    public static function inepColumnIndex(array $map): ?int
    {
        foreach (self::INEP_HEADER_ALIASES as $a) {
            if (isset($map[$a])) {
                return $map[$a];
            }
        }

        return null;
    }

    /**
     * @param  array<string, int>  $map
     * @return array{lat: ?int, lng: ?int}
     */
    public static function latLngColumnIndices(array $map): array
    {
        $lat = null;
        $lng = null;
        foreach (self::LAT_HEADER_ALIASES as $a) {
            if (isset($map[$a])) {
                $lat = $map[$a];
                break;
            }
        }
        foreach (self::LNG_HEADER_ALIASES as $a) {
            if (isset($map[$a])) {
                $lng = $map[$a];
                break;
            }
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * @param  array<string, int>  $map
     */
    public static function headerHasGeoColumns(array $map): bool
    {
        $ll = self::latLngColumnIndices($map);

        return $ll['lat'] !== null && $ll['lng'] !== null;
    }

    public static function parseInepCode(mixed $raw): int
    {
        $s = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($s === '') {
            return 0;
        }
        if (strlen($s) > 8) {
            $s = substr($s, -8);
        }

        return (int) $s;
    }

    public static function parseCoordinate(mixed $raw): ?float
    {
        $v = trim((string) $raw);
        if ($v === '' || $v === 'null') {
            return null;
        }
        $v = str_replace(',', '.', str_replace(' ', '', $v));
        if (! is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return is_finite($f) ? $f : null;
    }
}
