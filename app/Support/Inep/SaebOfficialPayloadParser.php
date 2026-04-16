<?php

namespace App\Support\Inep;

use App\Models\City;

/**
 * Converte respostas HTTP (JSON) da fonte oficial em linhas «pontos» compatíveis com o painel.
 */
final class SaebOfficialPayloadParser
{
    /**
     * Extrai lista de pontos e associa cada ponto ao id interno da cidade (city_ids).
     *
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    public static function pontosForCity(array $decoded, City $city): array
    {
        $ibge = (string) ($city->ibge_municipio ?? '');
        $raw = self::extractRawPontos($decoded);
        if ($raw === []) {
            return [];
        }

        $cityId = (int) $city->getKey();
        $out = [];
        foreach ($raw as $p) {
            if (! is_array($p)) {
                continue;
            }
            $p['city_ids'] = [$cityId];
            if ($ibge !== '' && empty($p['municipio_ibge'])) {
                $p['municipio_ibge'] = $ibge;
            }
            $out[] = $p;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private static function extractRawPontos(array $decoded): array
    {
        $pontos = $decoded['pontos'] ?? $decoded['points'] ?? null;
        if (is_array($pontos) && $pontos !== []) {
            return array_values(array_filter($pontos, 'is_array'));
        }

        $resultados = $decoded['resultados'] ?? $decoded['dados'] ?? null;
        if (is_array($resultados) && $resultados !== []) {
            return self::mapResultadosRows($resultados);
        }

        return [];
    }

    /**
     * Formato alternativo (ex.: exportações tabulares em JSON).
     *
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private static function mapResultadosRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ano = self::intish($row['ano'] ?? $row['year'] ?? $row['ano_aplicacao'] ?? null);
            if ($ano === null || $ano <= 0) {
                continue;
            }
            $disc = strtolower((string) ($row['disciplina'] ?? $row['disc'] ?? 'lp'));
            $etapa = strtolower((string) ($row['etapa'] ?? $row['etapa_ensino'] ?? 'efaf'));
            $val = $row['valor'] ?? $row['value'] ?? $row['percentual_proficientes'] ?? $row['proficientes_pct'] ?? null;
            if (! is_numeric($val)) {
                continue;
            }
            $status = strtolower((string) ($row['status'] ?? $row['tipo'] ?? 'final'));
            $rowOut = [
                'ano' => $ano,
                'disciplina' => $disc,
                'etapa' => $etapa,
                'valor' => (float) $val,
                'status' => $status,
                'unidade' => (string) ($row['unidade'] ?? '% proficientes'),
            ];
            $eid = self::intish($row['escola_id'] ?? $row['cod_escola'] ?? null);
            if ($eid !== null && $eid > 0) {
                $rowOut['escola_id'] = $eid;
            }
            $out[] = $rowOut;
        }

        return $out;
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
