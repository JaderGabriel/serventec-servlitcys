<?php

namespace App\Support\Rx;

/**
 * Tons visuais das colunas RX: vigente (atual), anterior (histórico) e comparativo (Δ / meta).
 */
final class RxColumnTone
{
    public const VIGENTE = 'vigente';

    public const ANTERIOR = 'anterior';

    public const COMPARATIVO = 'comparativo';

    public const META = 'meta';

    public const NEUTRAL = 'neutral';

    /**
     * @return list<array{tone: string, label: string, description: string}>
     */
    public static function legend(int $vigenteYear, int $anteriorYear): array
    {
        return [
            [
                'tone' => self::VIGENTE,
                'label' => __('Ano vigente :ano', ['ano' => $vigenteYear]),
                'description' => __('Volumes digitados no ano letivo em curso (alunos, matrículas, turmas, Censo).'),
            ],
            [
                'tone' => self::ANTERIOR,
                'label' => __('Ano anterior :ano / referência', ['ano' => $anteriorYear]),
                'description' => __('Histórico imediato ou ano de referência usado para calcular a meta (linha inferior em Matrículas e bloco «Base» na meta).'),
            ],
            [
                'tone' => self::COMPARATIVO,
                'label' => __('Comparativo'),
                'description' => __('Δ face ao ano anterior, progresso e em falta face à meta alvo, dias estimados.'),
            ],
            [
                'tone' => self::META,
                'label' => __('Meta de cadastro'),
                'description' => __('Ano de referência encontrado, volumes base e alvo após saltos percentuais.'),
            ],
        ];
    }

    public static function forColumn(string $key): string
    {
        return match ($key) {
            'alunos', 'turmas', 'censo' => self::VIGENTE,
            'matriculas' => self::VIGENTE,
            'delta', 'progresso', 'falta', 'dias' => self::COMPARATIVO,
            'meta' => self::META,
            'semaforo', 'municipio', 'situacao' => self::NEUTRAL,
            default => self::NEUTRAL,
        };
    }

    public static function thClass(string $tone, bool $right = false): string
    {
        $base = 'serv-rx-th serv-rx-th--'.$tone.' px-3 py-2';

        return $right ? $base.' text-right' : $base;
    }

    public static function tdClass(string $tone, bool $right = false): string
    {
        $base = 'serv-rx-td serv-rx-td--'.$tone.' px-3 py-2';

        return $right ? $base.' text-right' : $base;
    }

    public static function chipClass(string $tone): string
    {
        return 'serv-rx-chip serv-rx-chip--'.$tone;
    }
}
