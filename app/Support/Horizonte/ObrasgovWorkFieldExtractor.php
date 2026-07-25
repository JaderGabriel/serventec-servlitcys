<?php

namespace App\Support\Horizonte;

use Carbon\Carbon;

/**
 * Extrai campos gestacionais / financeiros das respostas Obrasgov (nomes reais da API 2026).
 */
final class ObrasgovWorkFieldExtractor
{
    /** Placeholder frequente em investimentos_previstos FNDE — não é valor real. */
    private const PREVISTO_PLACEHOLDER_MAX = 1.0;

    /**
     * Soma vl_investimento_previsto (indicativo). Ignora placeholders ≤ R$ 1,00.
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
            $v = (float) $raw;
            if ($v <= self::PREVISTO_PLACEHOLDER_MAX) {
                continue;
            }
            $sum += $v;
            $found = true;
        }

        return $found && $sum > 0 ? round($sum, 2) : null;
    }

    /**
     * @param  array<string, mixed>|null  $execucao
     */
    public static function percentualExecucao(?array $execucao): ?float
    {
        if ($execucao === null) {
            return null;
        }

        foreach (['percentual_execucao_fisica', 'percentual_execucao', 'pc_execucao_fisica'] as $key) {
            $raw = $execucao[$key] ?? null;
            if (is_numeric($raw)) {
                return round((float) $raw, 2);
            }
        }

        return null;
    }

    /**
     * Totais de empenho a partir da lista `/empenho`.
     *
     * API real: `valor_empenho`, `liquidado`, `pago` (não valor_empenhado/valor_pago).
     *
     * @param  list<array<string, mixed>>  $empenhos
     * @return array{valor_empenhado: ?float, valor_pago: ?float, valor_liquidado: ?float, fonte: ?string}
     */
    public static function totaisEmpenho(array $empenhos): array
    {
        $sumEmpenhado = 0.0;
        $sumPago = 0.0;
        $sumLiquidado = 0.0;
        $hasEmp = false;
        $hasPago = false;
        $hasLiq = false;
        $fonte = null;

        foreach ($empenhos as $emp) {
            if (! is_array($emp)) {
                continue;
            }
            if ($fonte === null) {
                $f = trim((string) ($emp['fonte'] ?? $emp['fonte_orcamentaria'] ?? ''));
                if ($f !== '') {
                    $fonte = mb_substr($f, 0, 128);
                }
            }

            $empVal = self::numericField($emp, [
                'valor_empenho',
                'valor_empenhado',
                'vl_empenho',
                'vl_empenhado',
            ]);
            if ($empVal !== null) {
                $sumEmpenhado += $empVal;
                $hasEmp = true;
            }

            $pagoVal = self::numericField($emp, [
                'pago',
                'valor_pago',
                'vl_pago',
                'rppago',
            ]);
            if ($pagoVal !== null) {
                $sumPago += $pagoVal;
                $hasPago = true;
            }

            $liqVal = self::numericField($emp, [
                'liquidado',
                'valor_liquidado',
                'vl_liquidado',
                'rpaliquidado',
            ]);
            if ($liqVal !== null) {
                $sumLiquidado += $liqVal;
                $hasLiq = true;
            }
        }

        return [
            'valor_empenhado' => $hasEmp && $sumEmpenhado > 0 ? round($sumEmpenhado, 2) : null,
            'valor_pago' => $hasPago && $sumPago > 0 ? round($sumPago, 2) : null,
            'valor_liquidado' => $hasLiq && $sumLiquidado > 0 ? round($sumLiquidado, 2) : null,
            'fonte' => $fonte,
        ];
    }

    /**
     * Primeira data útil de início (efectiva / execução antes de cadastro).
     *
     * @param  array<string, mixed>  $projeto
     * @param  array<string, mixed>|null  $execucao
     */
    public static function dataInicio(array $projeto, ?array $execucao = null): ?string
    {
        // Nomes reais no projeto-investimento: dt_inicial_efetiva / dt_inicial_prevista
        // (não dt_inicio_*). dt_cadastro fica por último — costuma ser data de migração.
        $candidates = [
            $projeto['dt_inicial_efetiva'] ?? null,
            $projeto['dt_inicio_efetiva'] ?? null,
            $projeto['dt_inicio_obra_efetiva'] ?? null,
            is_array($execucao) ? ($execucao['dt_inicial_execucao'] ?? null) : null,
            is_array($execucao) ? ($execucao['dt_inicio_efetiva'] ?? null) : null,
            is_array($execucao) ? ($execucao['data_inicio'] ?? null) : null,
            is_array($execucao) ? ($execucao['dt_inicio'] ?? null) : null,
            $projeto['dt_inicial_prevista'] ?? null,
            $projeto['dt_inicio_prevista'] ?? null,
            $projeto['dt_inicio_obra_prevista'] ?? null,
            $projeto['dt_assinatura_efetiva'] ?? null,
            $projeto['dt_cadastro'] ?? null,
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
     * Data de fim (execução ou prevista) — útil em meta; coluna dedicada opcional.
     *
     * @param  array<string, mixed>  $projeto
     * @param  array<string, mixed>|null  $execucao
     */
    public static function dataFim(array $projeto, ?array $execucao = null): ?string
    {
        $candidates = [
            is_array($execucao) ? ($execucao['dt_final_execucao'] ?? null) : null,
            $projeto['dt_final_efetiva'] ?? null,
            $projeto['dt_final_prevista'] ?? null,
        ];

        foreach ($candidates as $raw) {
            $date = self::parseDate($raw);
            if ($date !== null) {
                return $date;
            }
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
                'data_historico_situacao_investimento',
                'dt_historico_situacao_investimento',
                'dt_situacao',
                'data_situacao',
                'dt_registro',
                'data_registro',
                'dt_historico',
                'data_historico',
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
            'dt_atualizacao_execucao',
            'data_atualizacao_execucao',
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
                || str_contains($lower, 'motivo')
                || str_contains($lower, 'indicativo')
            ) {
                $keep[$key] = $value;
            }
        }

        $fim = self::dataFim([], $execucao);
        if ($fim !== null) {
            $keep['data_fim_derivada'] = $fim;
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

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function numericField(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            $raw = $row[$key] ?? null;
            if (is_numeric($raw)) {
                return (float) $raw;
            }
        }

        return null;
    }
}
