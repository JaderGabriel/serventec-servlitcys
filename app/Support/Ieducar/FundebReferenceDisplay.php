<?php

namespace App\Support\Ieducar;

use App\Support\Finance\MoneyMath;

/**
 * Formata comparação VAAF municipal (real/importado) × prévia federal para cartões do painel.
 *
 * @see \App\Support\Fundeb\FundebMatrixCellPresentation Cores/legenda da matriz admin (não confundir).
 */
final class FundebReferenceDisplay
{
    /**
     * @param  array<string, mixed>  $ref
     * @return ?array{
     *   real: array{label: string, value: string, hint: ?string},
     *   previa: array{label: string, value: string, hint: ?string}
     * }
     */
    public static function vaafComparacao(array $ref): ?array
    {
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $previa = is_array($ref['previa'] ?? null) ? $ref['previa'] : null;
        $municipal = is_array($ref['municipal'] ?? null) ? $ref['municipal'] : null;

        if ($previa === null && $municipal === null) {
            return null;
        }

        $real = $municipal ?? [
            'vaaf' => (float) ($ref['vaaf'] ?? 0),
            'fonte_label' => (string) ($ref['fonte_label'] ?? ''),
            'ano' => $ref['ano'] ?? null,
        ];

        return [
            'real' => [
                'label' => __('Valor municipal (base do cálculo)'),
                'value' => $municipal !== null ? $fmt((float) $municipal['vaaf']) : __('—'),
                'hint' => $municipal !== null
                    ? (string) ($municipal['fonte_label'] ?? '')
                    : __('Sem dado municipal importado para este IBGE/ano'),
            ],
            'previa' => [
                'label' => __('Prévia federal (referência)'),
                'value' => $previa !== null ? $fmt((float) $previa['vaaf']) : __('—'),
                'hint' => $previa !== null
                    ? (string) ($previa['fonte_label'] ?? '')
                    : __('Configure IEDUCAR_FUNDEB_NATIONAL_VAAF_* ou IEDUCAR_DISC_VAA_REFERENCIA'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $ref
     */
    public static function previsaoComparacao(int $matriculas, array $ref): ?array
    {
        if ($matriculas <= 0) {
            return null;
        }

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $municipalVaaf = is_array($ref['municipal'] ?? null)
            ? (float) ($ref['municipal']['vaaf'] ?? 0)
            : (float) ($ref['vaaf'] ?? 0);
        $previaVaaf = is_array($ref['previa'] ?? null) ? (float) ($ref['previa']['vaaf'] ?? 0) : 0.0;

        $realTotal = MoneyMath::multiplyVaaf($matriculas, $municipalVaaf);
        $previaTotal = $previaVaaf > 0 ? MoneyMath::multiplyVaaf($matriculas, $previaVaaf) : null;

        return [
            'real' => [
                'label' => __('Previsão (municipal × matrículas)'),
                'value' => $fmt($realTotal),
                'hint' => __(':n × :vaa', [
                    'n' => number_format($matriculas, 0, ',', '.'),
                    'vaa' => $fmt($municipalVaaf),
                ]),
            ],
            'previa' => [
                'label' => __('Previsão (prévia federal × matrículas)'),
                'value' => $previaTotal !== null ? $fmt($previaTotal) : __('—'),
                'hint' => $previaTotal !== null
                    ? __(':n × :vaa', [
                        'n' => number_format($matriculas, 0, ',', '.'),
                        'vaa' => $fmt($previaVaaf),
                    ])
                    : null,
            ],
        ];
    }
}
