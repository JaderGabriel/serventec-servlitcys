<?php

namespace App\Support\Horizonte;

use Carbon\Carbon;

/**
 * Extrai campos gestacionais (previsto, datas) das respostas Obrasgov.
 */
final class ObrasgovWorkFieldExtractor
{
    /**
     * Soma vl_investimento_previsto (indicativo — pode ser placeholder).
     *
     * @param  array<string, mixed>  $projeto
     */
    public static function valorPrevisto(array $projeto): ?float
    {
        $items = $projeto['investimentos_previstos'] ?? null;
        if (! is_array($items) || $items === []) {
            return null;
        }

        $sum = 0.0;
        $found = false;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $raw = $item['vl_investimento_previsto'] ?? $item['valor_investimento_previsto'] ?? null;
            if (! is_numeric($raw)) {
                continue;
            }
            $sum += (float) $raw;
            $found = true;
        }

        return $found && $sum > 0 ? round($sum, 2) : null;
    }

    /**
     * Primeira data útil de início (efectiva preferida, depois prevista).
     *
     * @param  array<string, mixed>  $projeto
     * @param  array<string, mixed>|null  $execucao
     */
    public static function dataInicio(array $projeto, ?array $execucao = null): ?string
    {
        $candidates = [
            $projeto['dt_inicio_efetiva'] ?? null,
            $projeto['dt_inicio_obra_efetiva'] ?? null,
            $projeto['dt_inicio_prevista'] ?? null,
            $projeto['dt_inicio_obra_prevista'] ?? null,
            $projeto['dt_assinatura_efetiva'] ?? null,
            $projeto['dt_cadastro'] ?? null,
            is_array($execucao) ? ($execucao['dt_inicio_efetiva'] ?? $execucao['data_inicio'] ?? $execucao['dt_inicio'] ?? null) : null,
        ];

        foreach ($candidates as $raw) {
            $date = self::parseDate($raw);
            if ($date !== null) {
                return $date;
            }
        }

        $ano = $projeto['ano_cadastro'] ?? null;
        if (is_numeric($ano) && (int) $ano >= 1990 && (int) $ano <= 2100) {
            return sprintf('%04d-01-01', (int) $ano);
        }

        return null;
    }

    /**
     * Última data de paralisação/cancelamento no histórico.
     *
     * @param  list<array<string, mixed>>|null  $historico
     */
    public static function dataParalisacao(?array $historico): ?string
    {
        if ($historico === null || $historico === []) {
            return null;
        }

        $best = null;
        foreach ($historico as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ([
                'dt_paralisacao',
                'data_paralisacao',
                'dt_situacao',
                'data_situacao',
                'dt_registro',
                'data_registro',
                'dt_historico',
                'data',
            ] as $key) {
                $date = self::parseDate($row[$key] ?? null);
                if ($date !== null && ($best === null || $date > $best)) {
                    $best = $date;
                }
            }
        }

        return $best;
    }

    /**
     * Data da última aferição física.
     *
     * @param  array<string, mixed>|null  $execucao
     */
    public static function dataUltimaAfericao(?array $execucao): ?string
    {
        if ($execucao === null) {
            return null;
        }

        foreach ([
            'dt_ultima_afericao',
            'data_ultima_afericao',
            'dt_afericao',
            'data_afericao',
            'dt_medicao',
            'data_medicao',
            'dt_atualizacao',
            'data_atualizacao',
            'dt_referencia',
            'data_referencia',
        ] as $key) {
            $date = self::parseDate($execucao[$key] ?? null);
            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Subconjunto útil de execução para meta_execucao.
     *
     * @param  array<string, mixed>|null  $execucao
     * @return array<string, mixed>|null
     */
    public static function metaExecucao(?array $execucao): ?array
    {
        if ($execucao === null || $execucao === []) {
            return null;
        }

        $keep = [];
        foreach ($execucao as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $lower = mb_strtolower($key);
            if (
                str_contains($lower, 'percentual')
                || str_contains($lower, 'dt_')
                || str_contains($lower, 'data')
                || str_contains($lower, 'instrumento')
                || str_contains($lower, 'meta')
            ) {
                $keep[$key] = $value;
            }
        }

        return $keep !== [] ? $keep : null;
    }

    public static function parseDate(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if ($raw instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($raw))->toDateString();
        }

        $str = trim((string) $raw);
        if ($str === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $str) === 1) {
                return Carbon::parse(substr($str, 0, 10))->toDateString();
            }
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $str, $m) === 1) {
                return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
            }

            $parsed = Carbon::parse($str);

            return $parsed->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
