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
                'label' => __('Face à meta'),
                'description' => __('Δ face ao ano anterior, progresso cadastral, pendente face à meta e dias estimados.'),
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

    /** Tom do cabeçalho da coluna (pode diferir da célula, ex. Matrículas = referência). */
    public static function headerToneForColumn(string $key): string
    {
        return match ($key) {
            'matriculas' => self::ANTERIOR,
            default => self::forColumn($key),
        };
    }

    /**
     * Estrutura do thead: grupos e chips alinhados às 12 colunas.
     *
     * @return list<array{
     *     key: string,
     *     group_label: ?string,
     *     group_tone: string,
     *     group_colspan: int,
     *     skip_group: bool,
     *     tone: ?string,
     *     tone_label: ?string,
     *     tone_description: ?string,
     *     tone_colspan: int,
     *     tone_compact: bool,
     *     skip_tone: bool,
     *     header_tone: string
     * }>
     */
    public static function tableColumns(int $vigenteYear, int $anteriorYear): array
    {
        $legend = collect(self::legend($vigenteYear, $anteriorYear))->keyBy('tone');

        $chip = static function (string $tone) use ($legend): array {
            $item = $legend->get($tone);

            return [
                'tone' => $tone,
                'tone_label' => $item['label'] ?? '',
                'tone_description' => $item['description'] ?? '',
            ];
        };

        $v = $chip(self::VIGENTE);
        $a = $chip(self::ANTERIOR);
        $m = $chip(self::META);
        $f = $chip(self::COMPARATIVO);

        return [
            [
                'key' => 'semaforo',
                'group_label' => null,
                'group_tone' => self::NEUTRAL,
                'group_colspan' => 2,
                'skip_group' => false,
                'tone' => null,
                'tone_label' => null,
                'tone_description' => null,
                'tone_colspan' => 2,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::NEUTRAL,
            ],
            [
                'key' => 'municipio',
                'group_label' => null,
                'group_tone' => self::NEUTRAL,
                'group_colspan' => 1,
                'skip_group' => true,
                'tone' => null,
                'tone_label' => null,
                'tone_description' => null,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => true,
                'header_tone' => self::NEUTRAL,
            ],
            [
                'key' => 'alunos',
                'group_label' => __('Vigente :ano', ['ano' => $vigenteYear]),
                'group_tone' => self::VIGENTE,
                'group_colspan' => 1,
                'skip_group' => false,
                ...$v,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::VIGENTE,
            ],
            [
                'key' => 'matriculas',
                'group_label' => __('Ref. :ano', ['ano' => $anteriorYear]),
                'group_tone' => self::ANTERIOR,
                'group_colspan' => 1,
                'skip_group' => false,
                ...$a,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::ANTERIOR,
            ],
            [
                'key' => 'delta',
                'group_label' => __('Δ :ano', ['ano' => $anteriorYear]),
                'group_tone' => self::COMPARATIVO,
                'group_colspan' => 1,
                'skip_group' => false,
                'tone' => self::COMPARATIVO,
                'tone_label' => __('Δ vs :ano', ['ano' => $anteriorYear]),
                'tone_description' => $f['tone_description'],
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::COMPARATIVO,
            ],
            [
                'key' => 'turmas',
                'group_label' => __('Turmas'),
                'group_tone' => self::VIGENTE,
                'group_colspan' => 1,
                'skip_group' => false,
                ...$v,
                'tone_colspan' => 1,
                'tone_compact' => true,
                'skip_tone' => false,
                'header_tone' => self::VIGENTE,
            ],
            [
                'key' => 'meta',
                'group_label' => __('Meta'),
                'group_tone' => self::META,
                'group_colspan' => 1,
                'skip_group' => false,
                ...$m,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::META,
            ],
            [
                'key' => 'censo',
                'group_label' => __('Censo'),
                'group_tone' => self::VIGENTE,
                'group_colspan' => 1,
                'skip_group' => false,
                ...$v,
                'tone_colspan' => 1,
                'tone_compact' => true,
                'skip_tone' => false,
                'header_tone' => self::VIGENTE,
            ],
            [
                'key' => 'progresso',
                'group_label' => __('Face à meta'),
                'group_tone' => self::COMPARATIVO,
                'group_colspan' => 3,
                'skip_group' => false,
                ...$f,
                'tone_colspan' => 3,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::COMPARATIVO,
            ],
            [
                'key' => 'falta',
                'group_label' => null,
                'group_tone' => self::COMPARATIVO,
                'group_colspan' => 1,
                'skip_group' => true,
                'tone' => null,
                'tone_label' => null,
                'tone_description' => null,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => true,
                'header_tone' => self::COMPARATIVO,
            ],
            [
                'key' => 'dias',
                'group_label' => null,
                'group_tone' => self::COMPARATIVO,
                'group_colspan' => 1,
                'skip_group' => true,
                'tone' => null,
                'tone_label' => null,
                'tone_description' => null,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => true,
                'header_tone' => self::COMPARATIVO,
            ],
            [
                'key' => 'situacao',
                'group_label' => null,
                'group_tone' => self::NEUTRAL,
                'group_colspan' => 1,
                'skip_group' => false,
                'tone' => null,
                'tone_label' => null,
                'tone_description' => null,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::NEUTRAL,
            ],
        ];
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
