<?php

namespace App\Services\Clio\Analysis;

/**
 * Ordenação pedagógica de rótulos de etapa/ano (1º → 2º → …), para tabelas e amostras.
 */
final class EtapaLabelOrder
{
    public const SEGMENT_SERIADA = 'seriada';

    public const SEGMENT_EJA = 'eja';

    public const SEGMENT_PROFISSIONAL = 'profissional';

    public const SEGMENT_ESPECIAL = 'especial';

    public const SEGMENT_COMPLEMENTAR = 'complementar';

    public const SEGMENT_OUTRO = 'outro';

    /**
     * Compara dois rótulos de etapa na sequência escolar esperada.
     */
    public function compare(string $a, string $b): int
    {
        return $this->sortKey($a) <=> $this->sortKey($b);
    }

    /**
     * Segmento educacional para agrupamento na UI (após etapas com idade bem definida).
     */
    public function segment(string $label): string
    {
        $e = mb_strtolower(trim($label));
        if ($e === '' || $e === 'não se aplica' || $e === 'nao se aplica') {
            return self::SEGMENT_OUTRO;
        }
        if (str_contains($e, 'atividade complementar') || str_contains($e, 'ativ. complementar')) {
            return self::SEGMENT_COMPLEMENTAR;
        }
        if (
            preg_match('/\baee\b|atendimento educacional|educa[cç][aã]o\s*especial/u', $e) === 1
            && preg_match('/\d+[ºo°]\s*ano|anos\s*iniciais|anos\s*finais|fundamental|infantil|creche|eja/u', $e) !== 1
        ) {
            return self::SEGMENT_ESPECIAL;
        }
        if (
            str_contains($e, 'profissional')
            || str_contains($e, 'técnico')
            || str_contains($e, 'tecnico')
            || str_contains($e, 'qualificação')
            || str_contains($e, 'qualificacao')
            || str_contains($e, 'fíc')
            || str_contains($e, 'fic ')
        ) {
            return self::SEGMENT_PROFISSIONAL;
        }
        if (str_contains($e, 'eja') || str_contains($e, 'jovens e adultos')) {
            return self::SEGMENT_EJA;
        }
        if (
            str_contains($e, 'creche')
            || str_contains($e, 'berç')
            || str_contains($e, 'berc')
            || str_contains($e, 'pré-escola')
            || str_contains($e, 'pre-escola')
            || str_contains($e, 'pré escola')
            || (str_contains($e, 'infantil') && ! str_contains($e, 'fundamental'))
            || str_contains($e, 'fundamental')
            || str_contains($e, 'anos iniciais')
            || str_contains($e, 'anos finais')
            || str_contains($e, 'médio')
            || str_contains($e, 'medio')
            || preg_match('/\d+[ºo°]\s*ano/u', $e) === 1
        ) {
            return self::SEGMENT_SERIADA;
        }

        return self::SEGMENT_OUTRO;
    }

    public function segmentLabel(string $segment): string
    {
        return match ($segment) {
            self::SEGMENT_SERIADA => __('Etapas com idade/série bem definida'),
            self::SEGMENT_EJA => __('EJA — Educação de Jovens e Adultos'),
            self::SEGMENT_PROFISSIONAL => __('Educação profissional / técnica'),
            self::SEGMENT_ESPECIAL => __('Educação especial (AEE)'),
            self::SEGMENT_COMPLEMENTAR => __('Atividade complementar'),
            default => __('Outros segmentos'),
        };
    }

    public function segmentHint(string $segment): string
    {
        return match ($segment) {
            self::SEGMENT_SERIADA => __('Infantil, Ensino Fundamental e Médio seriados — base da distorção idade-série.'),
            self::SEGMENT_EJA => __('Etapas de EJA; conferência alunos × turmas na mesma etapa.'),
            self::SEGMENT_PROFISSIONAL => __('Cursos profissionais/técnicos presentes nas Relações.'),
            self::SEGMENT_ESPECIAL => __('Turmas/etapas de AEE ou educação especial explícita.'),
            self::SEGMENT_COMPLEMENTAR => __('Atividade complementar — fora do vínculo curricular principal.'),
            default => __('Demais rótulos de etapa encontrados no export.'),
        };
    }

    public function segmentOrder(string $segment): int
    {
        return match ($segment) {
            self::SEGMENT_SERIADA => 10,
            self::SEGMENT_EJA => 20,
            self::SEGMENT_PROFISSIONAL => 30,
            self::SEGMENT_ESPECIAL => 40,
            self::SEGMENT_COMPLEMENTAR => 50,
            default => 60,
        };
    }

    /**
     * @return array{0: int, 1: int, 2: string}
     */
    public function sortKey(string $label): array
    {
        $e = mb_strtolower(trim($label));
        if ($e === '') {
            return [999, 99, ''];
        }

        $band = 50;
        $ano = 50;

        if ($e === 'não se aplica' || $e === 'nao se aplica') {
            return [95, 0, $e];
        }
        if (str_contains($e, 'atividade complementar') || str_contains($e, 'ativ. complementar')) {
            return [85, 0, $e];
        }
        if (preg_match('/\baee\b|atendimento educacional|educa[cç][aã]o\s*especial/u', $e) === 1) {
            return [80, 0, $e];
        }
        if (
            str_contains($e, 'profissional')
            || str_contains($e, 'técnico')
            || str_contains($e, 'tecnico')
            || str_contains($e, 'qualificação')
            || str_contains($e, 'qualificacao')
        ) {
            return [75, 0, $e];
        }
        if (str_contains($e, 'creche') || str_contains($e, 'berç') || str_contains($e, 'berc')) {
            return [10, 0, $e];
        }
        if (
            str_contains($e, 'pré-escola')
            || str_contains($e, 'pre-escola')
            || str_contains($e, 'pré escola')
            || (str_contains($e, 'infantil') && ! str_contains($e, 'fundamental'))
        ) {
            $ano = 0;
            if (preg_match('/\b5\b|cinco/u', $e) === 1) {
                $ano = 5;
            } elseif (preg_match('/\b4\b|quatro/u', $e) === 1) {
                $ano = 4;
            } elseif (preg_match('/\b3\b|três|tres/u', $e) === 1) {
                $ano = 3;
            }

            return [20, $ano, $e];
        }
        if (str_contains($e, 'eja') || str_contains($e, 'jovens e adultos')) {
            return [70, 0, $e];
        }
        if (str_contains($e, 'médio') || str_contains($e, 'medio')) {
            $ano = $this->extractAno($e) ?? 0;

            return [60, $ano, $e];
        }
        if (
            str_contains($e, 'fundamental')
            || str_contains($e, 'anos iniciais')
            || str_contains($e, 'anos finais')
            || preg_match('/\d+[ºo°]\s*ano/u', $e) === 1
        ) {
            $ano = $this->extractAno($e);
            if ($ano === null) {
                if (str_contains($e, 'anos finais')) {
                    $ano = 6;
                } elseif (str_contains($e, 'anos iniciais')) {
                    $ano = 1;
                } else {
                    $ano = 0;
                }
            }

            return [30, $ano, $e];
        }

        return [$band, $ano, $e];
    }

    /**
     * @param  array<string, mixed>  $byEtapa  mapa etapa => payload
     * @return array<string, mixed>
     */
    public function sortAssocByLabel(array $byEtapa): array
    {
        uksort($byEtapa, fn (string $a, string $b): int => $this->compare($a, $b));

        return $byEtapa;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function sortRowsByEtapaKey(array $rows, string $key = 'etapa'): array
    {
        usort($rows, function (array $a, array $b) use ($key): int {
            return $this->compare((string) ($a[$key] ?? ''), (string) ($b[$key] ?? ''));
        });

        return $rows;
    }

    private function extractAno(string $e): ?int
    {
        for ($ano = 1; $ano <= 9; $ano++) {
            if (preg_match('/\b'.$ano.'\s*[.ºo°]\s*ano\b/u', $e) === 1) {
                return $ano;
            }
        }

        return null;
    }
}
