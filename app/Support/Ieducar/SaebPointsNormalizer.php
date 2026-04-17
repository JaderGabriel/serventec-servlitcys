<?php

namespace App\Support\Ieducar;

/**
 * Converte o payload bruto (pontos SAEB) no formato interno usado pelos gráficos.
 */
final class SaebPointsNormalizer
{
    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    public static function normalizeDecodedPayload(array $decoded): array
    {
        $pontos = $decoded['pontos'] ?? $decoded['points'] ?? $decoded['series'] ?? null;
        if (! is_array($pontos)) {
            return [];
        }

        $rootCityIds = null;
        if (isset($decoded['city_ids']) && is_array($decoded['city_ids'])) {
            $rootCityIds = array_values(array_unique(array_map(static fn ($x) => (int) $x, $decoded['city_ids'])));
            if ($rootCityIds === []) {
                $rootCityIds = null;
            }
        }

        $out = [];
        foreach ($pontos as $p) {
            if (! is_array($p)) {
                continue;
            }
            $year = self::intish(self::pick($p, ['ano', 'year', 'ano_aplicacao'], null));
            if ($year === null || $year <= 0) {
                continue;
            }
            $val = self::pick($p, ['valor', 'value', 'v'], null);
            if (! is_numeric($val)) {
                continue;
            }
            $statusRaw = strtolower((string) self::pick($p, ['status', 'tipo'], 'final'));
            $isFinal = self::statusIsFinal($statusRaw);
            $disc = strtolower((string) self::pick($p, ['disciplina', 'disc'], 'lp'));
            $etapa = strtolower((string) self::pick($p, ['etapa', 'etapa_ensino'], 'geral'));

            $pointEscolaId = self::intish(self::pick($p, ['escola_id', 'cod_escola'], null));
            $rawEids = $p['escola_ids'] ?? null;
            $escolaIdsList = [];
            if (is_array($rawEids)) {
                foreach ($rawEids as $x) {
                    if (is_numeric($x) && (int) $x > 0) {
                        $escolaIdsList[] = (int) $x;
                    }
                }
                $escolaIdsList = array_values(array_unique($escolaIdsList));
            }

            if ($pointEscolaId !== null && $pointEscolaId > 0) {
                $scope = 'escola_'.$pointEscolaId;
            } elseif ($escolaIdsList !== []) {
                sort($escolaIdsList);
                $scope = 'escola_'.$escolaIdsList[0];
            } else {
                $scope = 'municipal';
            }

            $row = [
                'year' => $year,
                'series_key' => $disc.'|'.$etapa.'|'.$scope,
                'value' => (float) $val,
                'is_final' => $isFinal,
                'unidade' => (string) self::pick($p, ['unidade', 'unit'], '%'),
            ];
            if ($pointEscolaId !== null && $pointEscolaId > 0) {
                $row['escola_id'] = $pointEscolaId;
            }
            if ($escolaIdsList !== []) {
                $row['escola_ids'] = $escolaIdsList;
            }
            $pointCityIds = null;
            if (isset($p['city_ids']) && is_array($p['city_ids'])) {
                $pointCityIds = array_values(array_unique(array_map(static fn ($x) => (int) $x, $p['city_ids'])));
                if ($pointCityIds === []) {
                    $pointCityIds = null;
                }
            }
            $effectiveCityIds = $pointCityIds ?? $rootCityIds;
            if ($effectiveCityIds === null || $effectiveCityIds === []) {
                continue;
            }
            $row['city_ids'] = $effectiveCityIds;
            $out[] = $row;
        }

        return $out;
    }

    private static function statusIsFinal(string $s): bool
    {
        if (str_contains($s, 'prelim')) {
            return false;
        }
        if (str_contains($s, 'prel')) {
            return false;
        }
        if (str_contains($s, 'prov')) {
            return false;
        }
        if ($s === 'p') {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $arr
     */
    private static function pick(array $arr, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') {
                return $arr[$k];
            }
        }

        return $default;
    }

    private static function intish(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (int) $v;
        }

        return null;
    }
}
