<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Finance\MoneyMath;
use App\Support\Fundeb\FundebMatrixCellPresentation;

/**
 * Formata comparação VAAF municipal (real/importado) × prévia federal para cartões do painel.
 *
 * @see FundebMatrixCellPresentation Cores/legenda da matriz admin (não confundir).
 */
final class FundebReferenceDisplay
{
    /**
     * Tipo do VAAF usado nos cálculos (rótulos e textos do painel).
     *
     * @param  array<string, mixed>|null  $funding  payload de {@see DiscrepanciesFundingImpact::fundingReferencePayload}
     */
    public static function tipoVaafCalculo(?array $funding): string
    {
        if ($funding === null) {
            return 'referencia';
        }

        if (! empty($funding['vaa_municipal_importado'])) {
            return 'municipal';
        }

        $origem = strtolower(trim((string) ($funding['vaa_fonte'] ?? '')));
        $fonte = mb_strtolower(trim((string) ($funding['vaa_fonte_label'] ?? '')));

        if ($origem === 'municipal' || str_contains($fonte, 'municipal')) {
            return 'municipal';
        }

        if (
            $origem === FundebMunicipalReferenceResolver::FONTE_PREVIA_NACIONAL
            || $origem === 'previa_nacional'
            || str_contains($fonte, 'prévia federal')
            || str_contains($fonte, 'previa federal')
        ) {
            return 'previa';
        }

        if (str_contains($fonte, 'estimad') || str_contains($origem, 'fnde_receita')) {
            return 'estimado';
        }

        if (
            $origem === FundebMunicipalReferenceResolver::FONTE_CONFIG_GLOBAL
            || str_contains($fonte, 'ieducar_disc_vaa')
            || str_contains($fonte, 'referência configurável')
        ) {
            return 'config';
        }

        return 'referencia';
    }

    /**
     * Rótulo curto para exibir ao lado do valor (ex.: cabeçalho da aba).
     *
     * @param  array<string, mixed>|null  $funding
     */
    public static function rotuloVaafCurto(?array $funding): string
    {
        return match (self::tipoVaafCalculo($funding)) {
            'municipal' => __('VAAF municipal'),
            'previa' => __('Prévia federal (configurável)'),
            'estimado' => __('VAAF estimado (receita ÷ matrículas)'),
            'config' => __('Referência configurável (piso indicativo)'),
            default => __('Valor-aluno/ano de referência'),
        };
    }

    /**
     * Linha «matrículas × VAAF ≈ total/ano» com origem explícita (evita «VAAF ref.» ambíguo).
     *
     * @param  array<string, mixed>|null  $funding
     * @param  array{nee?: bool}  $opts
     */
    public static function linhaMatriculasVaafBase(int $matriculas, ?array $funding, array $opts = []): ?string
    {
        if ($matriculas <= 0 || $funding === null) {
            return null;
        }

        $vaaf = (float) ($funding['vaa_anual'] ?? 0);
        if ($vaaf <= 0) {
            return null;
        }

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $vaafFmt = trim((string) ($funding['vaa_label'] ?? ''));
        if ($vaafFmt === '') {
            $vaafFmt = $fmt($vaaf);
        }

        $fonte = trim((string) ($funding['vaa_fonte_label'] ?? ''));
        $rotulo = self::rotuloVaafCurto($funding);
        $base = $fmt(MoneyMath::multiplyVaaf($matriculas, $vaaf));
        $n = number_format($matriculas, 0, ',', '.');

        $avisoSemMunicipal = self::tipoVaafCalculo($funding) !== 'municipal'
            ? ' '.__('Sem VAAF municipal importado para este IBGE/ano; ordem de grandeza — não é repasse oficial FNDE/Simec.')
            : '';

        if (! empty($opts['nee'])) {
            return __(
                ':n matrícula(s) NEE × :vaaf/aluno/ano (:rotulo — :fonte) ≈ :base/ano de base FUNDEB indicativa por aluno.:extra',
                [
                    'n' => $n,
                    'vaaf' => $vaafFmt,
                    'rotulo' => $rotulo,
                    'fonte' => $fonte !== '' ? $fonte : __('sem detalhe de fonte'),
                    'base' => $base,
                    'extra' => $avisoSemMunicipal,
                ]
            );
        }

        return __(
            ':n matrícula(s) × :vaaf/aluno/ano (:rotulo — :fonte) ≈ :base/ano de volume indicativo FUNDEB no filtro.:extra',
            [
                'n' => $n,
                'vaaf' => $vaafFmt,
                'rotulo' => $rotulo,
                'fonte' => $fonte !== '' ? $fonte : __('sem detalhe de fonte'),
                'base' => $base,
                'extra' => $avisoSemMunicipal,
            ]
        );
    }

    /**
     * Fórmula da previsão base FUNDEB (aba FUNDEB).
     *
     * @param  array<string, mixed>|null  $funding
     */
    public static function formulaPrevisaoBase(int $matriculas, float $vaaf, ?array $funding): string
    {
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $total = $fmt(MoneyMath::multiplyVaaf($matriculas, $vaaf));
        $fonte = trim((string) ($funding['vaa_fonte_label'] ?? ''));
        $mat = number_format($matriculas, 0, ',', '.');
        $vaa = $fmt($vaaf);

        return match (self::tipoVaafCalculo($funding)) {
            'municipal' => __(
                ':mat matrícula(s) × :vaa/aluno/ano (VAAF municipal — :fonte) = :total.',
                ['mat' => $mat, 'vaa' => $vaa, 'fonte' => $fonte, 'total' => $total]
            ),
            'previa' => __(
                ':mat matrícula(s) × :vaa/aluno/ano (prévia federal configurável — :fonte) = :total.',
                ['mat' => $mat, 'vaa' => $vaa, 'fonte' => $fonte, 'total' => $total]
            ),
            'estimado' => __(
                ':mat matrícula(s) × :vaa/aluno/ano (VAAF estimado — :fonte) = :total.',
                ['mat' => $mat, 'vaa' => $vaa, 'fonte' => $fonte, 'total' => $total]
            ),
            default => __(
                ':mat matrícula(s) × :vaa/aluno/ano (referência configurável — :fonte) = :total.',
                ['mat' => $mat, 'vaa' => $vaa, 'fonte' => $fonte, 'total' => $total]
            ),
        };
    }

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
    public static function previsaoComparacao(
        int $matriculas,
        array $ref,
        ?City $city = null,
        ?IeducarFilterState $filters = null,
    ): ?array {
        if ($matriculas <= 0) {
            return null;
        }

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $municipalVaaf = is_array($ref['municipal'] ?? null)
            ? (float) ($ref['municipal']['vaaf'] ?? 0)
            : (float) FundebMunicipalReferenceResolver::vaafParaCalculo($city, $filters)['vaaf'];
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
