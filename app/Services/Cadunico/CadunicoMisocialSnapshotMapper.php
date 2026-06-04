<?php

namespace App\Services\Cadunico;

/**
 * Converte documento Solr da Matriz MIS/SAGI (Misocial) em payload de cadunico_municipio_snapshots.
 *
 * @see https://aplicacoes.mds.gov.br/sagi/servicos/misocial/
 */
final class CadunicoMisocialSnapshotMapper
{
    /**
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>|null
     */
    public static function toSnapshotPayload(array $doc, int $ano): ?array
    {
        $popEscolar = self::populacaoEscolar47($doc);
        if ($popEscolar === null || $popEscolar <= 0) {
            $fallback = self::int($doc['igd_pbf_qtd_total_criancas_adolescentes_pbf_i'] ?? null)
                ?? self::int($doc['igd_pab_qtd_total_criancas_adolescentes_pab_i'] ?? null);
            if ($fallback === null || $fallback <= 0) {
                return null;
            }
            $popEscolar = $fallback;
            $c45 = (int) round($popEscolar * 0.15);
            $c610 = (int) round($popEscolar * 0.35);
            $c1114 = (int) round($popEscolar * 0.30);
            $c1517 = max(0, $popEscolar - $c45 - $c610 - $c1114);
        } else {
            $c45 = self::faixa45($doc);
            [$c610, $c1114] = self::faixas610e1114($doc);
            $c1517 = self::faixa1517($doc);
        }

        $anomes = (string) ($doc['anomes_s'] ?? '');
        $refYear = strlen($anomes) >= 4 ? (int) substr($anomes, 0, 4) : $ano;

        $pbfCriancas = self::sumFields($doc, [
            'qtd_pes_pbf_idade_0_e_4_sexo_feminino_i',
            'qtd_pes_pbf_idade_0_e_4_sexo_masculino_i',
            'qtd_pes_pbf_idade_5_a_6_sexo_feminino_i',
            'qtd_pes_pbf_idade_5_a_6_sexo_masculino_i',
            'qtd_pes_pbf_idade_7_a_15_sexo_feminino_i',
            'qtd_pes_pbf_idade_7_a_15_sexo_masculino_i',
            'qtd_pes_pbf_idade_16_a_17_sexo_feminino_i',
            'qtd_pes_pbf_idade_16_a_17_sexo_masculino_i',
        ]);
        $pctPbf = ($popEscolar > 0 && $pbfCriancas > 0)
            ? round(100.0 * $pbfCriancas / $popEscolar, 1)
            : null;

        return [
            'pessoas_cadastradas' => self::int($doc['cadun_qtd_pessoas_cadastradas_i'] ?? null) ?? 0,
            'familias_cadastradas' => self::int($doc['cadun_qtd_familias_cadastradas_i'] ?? null) ?? 0,
            'criancas_0_3' => 0,
            'criancas_4_5' => $c45,
            'criancas_6_10' => $c610,
            'criancas_11_14' => $c1114,
            'criancas_15_17' => $c1517,
            'populacao_escolar_estimada' => $popEscolar,
            'fonte' => 'sagi_misocial',
            'schema_version' => 'misocial-1',
            'metadados' => [
                'imported_via' => 'CadunicoSagiMisocialClient',
                'anomes_referencia' => $anomes,
                'ano_referencia_mis' => $refYear,
                'indicador_base' => 'cadun_qtd_pessoas_cadastradas_i',
                'misocial_pbf_criancas' => $pbfCriancas > 0 ? $pbfCriancas : null,
                'vulnerabilidade' => [
                    'pessoas_cadastradas' => self::int($doc['cadun_qtd_pessoas_cadastradas_i'] ?? null) ?? 0,
                    'familias_cadastradas' => self::int($doc['cadun_qtd_familias_cadastradas_i'] ?? null) ?? 0,
                    'criancas_escolar_cadunico' => $popEscolar,
                    'criancas_pbf_estimada' => $pbfCriancas > 0 ? $pbfCriancas : null,
                    'pct_criancas_pbf' => $pctPbf,
                    'pct_criancas_pbf_label' => $pctPbf !== null
                        ? number_format($pctPbf, 1, ',', '.').'%'
                        : null,
                    'fonte' => 'sagi_misocial',
                ],
            ],
        ];
    }

    /**
     * Estimativa 4–17 anos: faixas etárias MIS (PBF + não-PBF, ambos os sexos).
     */
    private static function populacaoEscolar47(array $doc): ?int
    {
        $c45 = self::faixa45($doc);
        [$c610, $c1114] = self::faixas610e1114($doc);
        $c1517 = self::faixa1517($doc);
        $sum = $c45 + $c610 + $c1114 + $c1517;

        return $sum > 0 ? $sum : null;
    }

    private static function faixa45(array $doc): int
    {
        return self::sumFields($doc, [
            'qtd_pes_pbf_idade_0_e_4_sexo_feminino_i',
            'qtd_pes_pbf_idade_0_e_4_sexo_masculino_i',
            'qtd_pes_cad_nao_pbf_idade_0_e_4_sexo_feminino_i',
            'qtd_pes_cad_nao_pbf_idade_0_e_4_sexo_masculino_i',
            'qtd_pes_pbf_idade_5_a_6_sexo_feminino_i',
            'qtd_pes_pbf_idade_5_a_6_sexo_masculino_i',
            'qtd_pes_cad_nao_pbf_idade_5_a_6_sexo_feminino_i',
            'qtd_pes_cad_nao_pbf_idade_5_a_6_sexo_masculino_i',
        ]);
    }

    /**
     * Faixa 7–15 no MIS repartida entre fundamental inicial (6–10) e final (11–14).
     *
     * @return array{0: int, 1: int}
     */
    private static function faixas610e1114(array $doc): array
    {
        $total = self::sumFields($doc, [
            'qtd_pes_pbf_idade_7_a_15_sexo_feminino_i',
            'qtd_pes_pbf_idade_7_a_15_sexo_masculino_i',
            'qtd_pes_cad_nao_pbf_idade_7_a_15_sexo_feminino_i',
            'qtd_pes_cad_nao_pbf_idade_7_a_15_sexo_masculino_i',
        ]);

        if ($total <= 0) {
            return [0, 0];
        }

        $c610 = (int) round($total * (5 / 9));
        $c1114 = max(0, $total - $c610);

        return [$c610, $c1114];
    }

    private static function faixa1517(array $doc): int
    {
        return self::sumFields($doc, [
            'qtd_pes_pbf_idade_16_a_17_sexo_feminino_i',
            'qtd_pes_pbf_idade_16_a_17_sexo_masculino_i',
            'qtd_pes_cad_nao_pbf_idade_16_a_17_sexo_feminino_i',
            'qtd_pes_cad_nao_pbf_idade_16_a_17_sexo_masculino_i',
        ]);
    }

    /**
     * @param  list<string>  $keys
     */
    private static function sumFields(array $doc, array $keys): int
    {
        $sum = 0;
        foreach ($keys as $key) {
            $sum += self::int($doc[$key] ?? null) ?? 0;
        }

        return $sum;
    }

    private static function int(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return null;
    }
}
