<?php

namespace App\Support\Rx;

/**
 * Tons visuais das colunas RX: meta (alvo), vigente (feito), falta (pendente) e contexto.
 */
final class RxColumnTone
{
    public const VIGENTE = 'vigente';

    public const ANTERIOR = 'anterior';

    public const COMPARATIVO = 'comparativo';

    public const META = 'meta';

    public const FALTA = 'falta';

    public const NEUTRAL = 'neutral';

    /**
     * @return list<array{tone: string, label: string, description: string, icon: string}>
     */
    public static function legend(int $vigenteYear, int $anteriorYear): array
    {
        return [
            [
                'tone' => self::META,
                'icon' => 'chart-bar',
                'label' => __('Meta alvo'),
                'description' => __('Volume esperado de turmas e matrículas (referência histórica + saltos percentuais).'),
            ],
            [
                'tone' => self::VIGENTE,
                'icon' => 'check-circle',
                'label' => __('Já cadastrado :ano', ['ano' => $vigenteYear]),
                'description' => __('Alunos, matrículas e turmas activos no i-Educar — o que a rede já digitou.'),
            ],
            [
                'tone' => self::FALTA,
                'icon' => 'exclamation-triangle',
                'label' => __('Falta cadastrar'),
                'description' => __('Registos e prazo estimado para fechar o gap face à meta.'),
            ],
            [
                'tone' => self::COMPARATIVO,
                'icon' => 'arrow-path',
                'label' => __('Contexto'),
                'description' => __('Comparação com :ano anterior, Censo Escolar e estado da leitura i-Educar.', ['ano' => $anteriorYear]),
            ],
        ];
    }

    public static function forColumn(string $key): string
    {
        return match ($key) {
            'alunos', 'matriculas', 'turmas', 'progresso' => self::VIGENTE,
            'falta', 'dias' => self::FALTA,
            'meta' => self::META,
            'delta', 'censo' => self::COMPARATIVO,
            'semaforo', 'municipio', 'situacao' => self::NEUTRAL,
            default => self::NEUTRAL,
        };
    }

    public static function headerToneForColumn(string $key): string
    {
        return self::forColumn($key);
    }

    /**
     * Ordem: identificação · meta · cadastrado (4) · falta (2) · contexto (3).
     *
     * @return list<array<string, mixed>>
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
                'group_icon' => $item['icon'] ?? null,
            ];
        };

        $m = $chip(self::META);
        $v = $chip(self::VIGENTE);
        $f = $chip(self::FALTA);
        $c = $chip(self::COMPARATIVO);

        return [
            [
                'key' => 'semaforo',
                'group_label' => __('Município'),
                'group_icon' => 'map-pin',
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
                'group_icon' => null,
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
                'key' => 'meta',
                'group_label' => __('Meta alvo'),
                ...$m,
                'group_tone' => self::META,
                'group_colspan' => 1,
                'skip_group' => false,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::META,
            ],
            [
                'key' => 'alunos',
                'group_label' => __('Já cadastrado :ano', ['ano' => $vigenteYear]),
                ...$v,
                'group_tone' => self::VIGENTE,
                'group_colspan' => 4,
                'skip_group' => false,
                'tone_colspan' => 4,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::VIGENTE,
            ],
            [
                'key' => 'matriculas',
                'group_label' => null,
                'group_icon' => null,
                'group_tone' => self::VIGENTE,
                'group_colspan' => 1,
                'skip_group' => true,
                'tone' => null,
                'tone_label' => null,
                'tone_description' => null,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => true,
                'header_tone' => self::VIGENTE,
            ],
            [
                'key' => 'turmas',
                'group_label' => null,
                'group_icon' => null,
                'group_tone' => self::VIGENTE,
                'group_colspan' => 1,
                'skip_group' => true,
                'tone' => null,
                'tone_label' => null,
                'tone_description' => null,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => true,
                'header_tone' => self::VIGENTE,
            ],
            [
                'key' => 'progresso',
                'group_label' => null,
                'group_icon' => null,
                'group_tone' => self::VIGENTE,
                'group_colspan' => 1,
                'skip_group' => true,
                'tone' => null,
                'tone_label' => null,
                'tone_description' => null,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => true,
                'header_tone' => self::VIGENTE,
            ],
            [
                'key' => 'falta',
                'group_label' => __('Falta cadastrar'),
                ...$f,
                'group_tone' => self::FALTA,
                'group_colspan' => 2,
                'skip_group' => false,
                'tone_colspan' => 2,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::FALTA,
            ],
            [
                'key' => 'dias',
                'group_label' => null,
                'group_icon' => null,
                'group_tone' => self::FALTA,
                'group_colspan' => 1,
                'skip_group' => true,
                'tone' => null,
                'tone_label' => null,
                'tone_description' => null,
                'tone_colspan' => 1,
                'tone_compact' => false,
                'skip_tone' => true,
                'header_tone' => self::FALTA,
            ],
            [
                'key' => 'delta',
                'group_label' => __('Contexto'),
                ...$c,
                'group_tone' => self::COMPARATIVO,
                'group_colspan' => 3,
                'skip_group' => false,
                'tone_colspan' => 3,
                'tone_compact' => false,
                'skip_tone' => false,
                'header_tone' => self::COMPARATIVO,
            ],
            [
                'key' => 'censo',
                'group_label' => null,
                'group_icon' => null,
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
                'group_icon' => null,
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
