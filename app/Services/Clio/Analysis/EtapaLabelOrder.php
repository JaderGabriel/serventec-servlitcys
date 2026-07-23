<?php

namespace App\Services\Clio\Analysis;

/**
 * Ordenação pedagógica de rótulos de etapa/ano (1º → 2º → …), para tabelas e amostras.
 */
final class EtapaLabelOrder
{
    /**
     * Compara dois rótulos de etapa na sequência escolar esperada.
     */
    public function compare(string $a, string $b): int
    {
        return $this->sortKey($a) <=> $this->sortKey($b);
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

        if (preg_match('/\baee\b|atendimento educacional/u', $e) === 1) {
            return [80, 0, $e];
        }
        if (str_contains($e, 'atividade complementar') || str_contains($e, 'ativ. complementar')) {
            return [85, 0, $e];
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
            if (preg_match('/\b'.$ano.'[ºo°]\s*ano\b/u', $e) === 1) {
                return $ano;
            }
        }

        return null;
    }
}
