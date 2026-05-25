<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Finance\MoneyMath;
use Illuminate\Database\Connection;

/**
 * Impacto financeiro indicativo da educação especial (NEE) no FUNDEB/VAAR.
 *
 * A ponderação oficial da educação especial no Anexo da Lei nº 14.113/2020 é 1,20
 * (20% acima da matrícula de referência). O ganho indicativo nesta aba é o incremento
 * (peso − 1) × VAAF × matrículas NEE, não a base integral (já contada na aba Matrículas).
 */
final class InclusionFundebImpact
{
    /**
     * Ponderação «educação especial» — Lei 14.113/2020, Anexo, alínea n) (padrão 1,20).
     */
    public static function pesoEducacaoEspecial(): float
    {
        return max(1.0, (float) config('ieducar.inclusion.fundeb_peso_educacao_especial', 1.2));
    }

    /**
     * Perda indicativa: matrículas em turma AEE sem deficiência/NEE no cadastro (risco Censo e ponderação FUNDEB).
     *
     * @return array<string, mixed>
     */
    public static function riscoTurmaAeeSemCadastroDeficiencia(
        int $matriculas,
        City $city,
        IeducarFilterState $filters,
    ): array {
        $matriculas = max(0, $matriculas);
        if ($matriculas <= 0) {
            return ['available' => false];
        }

        $calc = FundebMunicipalReferenceResolver::vaafParaCalculo($city, $filters);
        $vaaf = (float) ($calc['vaaf'] ?? 0);
        if ($vaaf <= 0) {
            return [
                'available' => false,
                'matriculas' => $matriculas,
            ];
        }

        $peso = self::pesoEducacaoEspecial();
        $incremento = max(0.0, $peso - 1.0);
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        $perdaPonderacao = $incremento > 0
            ? MoneyMath::multiplyVaaf($matriculas, $vaaf * $incremento)
            : 0.0;

        $impactoVaar = DiscrepanciesFundingImpact::estimate('aee_sem_nee', $matriculas, $city, $filters);
        $perdaVaar = (float) ($impactoVaar['perda_anual'] ?? 0);

        $perdaIndicativa = MoneyMath::roundMoney(max($perdaPonderacao, $perdaVaar));
        $ganhoPotencial = $perdaIndicativa;

        $rotuloVaaf = FundebReferenceDisplay::rotuloVaafCurto(
            DiscrepanciesFundingImpact::resolveReference($city, $filters)
        );

        return [
            'available' => true,
            'matriculas' => $matriculas,
            'peso_educacao_especial' => $peso,
            'incremento_ponderacao' => $incremento,
            'vaaf' => $vaaf,
            'vaaf_fmt' => $fmt($vaaf),
            'vaaf_fonte' => (string) ($calc['fonte_label'] ?? ''),
            'perda_ponderacao_anual' => $perdaPonderacao,
            'perda_ponderacao_anual_fmt' => $perdaPonderacao > 0 ? $fmt($perdaPonderacao) : null,
            'perda_vaar_anual' => $perdaVaar,
            'perda_vaar_anual_fmt' => $perdaVaar > 0 ? $fmt($perdaVaar) : null,
            'perda_anual' => $perdaIndicativa,
            'perda_anual_fmt' => $fmt($perdaIndicativa),
            'ganho_potencial_anual' => $ganhoPotencial,
            'ganho_potencial_anual_fmt' => $fmt($ganhoPotencial),
            'formula' => $incremento > 0
                ? __(
                    ':n matrícula(s) × :vaaf (:rotulo) × :inc (incremento ponderação educação especial :p) ≈ :valor/ano.',
                    [
                        'n' => number_format($matriculas, 0, ',', '.'),
                        'vaaf' => $fmt($vaaf),
                        'rotulo' => $rotuloVaaf,
                        'inc' => number_format($incremento, 2, ',', '.'),
                        'p' => number_format($peso, 2, ',', '.'),
                        'valor' => $fmt($perdaPonderacao),
                    ]
                )
                : __(
                    ':n matrícula(s) × :vaaf (:rotulo) × peso VAAR-inclusão (:p) ≈ :valor/ano.',
                    [
                        'n' => number_format($matriculas, 0, ',', '.'),
                        'vaaf' => $fmt($vaaf),
                        'rotulo' => $rotuloVaaf,
                        'p' => number_format((float) ($impactoVaar['peso'] ?? 1), 2, ',', '.'),
                        'valor' => $fmt($perdaVaar),
                    ]
                ),
            'observacao' => __(
                'Cada matrícula em turma AEE (heurística) sem registo em cadastro.deficiência / fisica_deficiencia entra nesta conta — não se somam outras matrículas do mesmo aluno em ensino regular ou atividades complementares. No Educacenso e na VAAR-inclusão o município arrisca não comprovar a ponderação :p (Lei 14.113/2020). O ganho potencial ao regularizar o cadastro é o valor indicado.',
                ['p' => number_format($peso, 2, ',', '.')]
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $totalMatriculas = null,
    ): array {
        try {
            $nee = InclusionDashboardQueries::countMatriculasComNee($db, $city, $filters);
            if ($nee <= 0) {
                return ['available' => false];
            }

            $calc = FundebMunicipalReferenceResolver::vaafParaCalculo($city, $filters);
            $vaaf = (float) ($calc['vaaf'] ?? 0);
            if ($vaaf <= 0) {
                return ['available' => false, 'matriculas_nee' => $nee];
            }

            $peso = self::pesoEducacaoEspecial();
            $incremento = max(0.0, $peso - 1.0);
            $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

            $adicionalVaaf = $incremento > 0
                ? MoneyMath::multiplyVaaf($nee, $vaaf * $incremento)
                : 0.0;
            $baseSemPeso = MoneyMath::multiplyVaaf($nee, $vaaf);
            $basePonderada = MoneyMath::multiplyVaaf($nee, $vaaf * $peso);

            $vaarSlice = self::parcelaVaarIndicativa($city, $filters, $nee, $incremento, $totalMatriculas);
            $adicionalVaar = (float) ($vaarSlice['valor'] ?? 0.0);
            $totalIncremental = MoneyMath::roundMoney($adicionalVaaf + $adicionalVaar);

            return [
                'available' => true,
                'matriculas_nee' => $nee,
                'total_matriculas' => $totalMatriculas !== null && $totalMatriculas > 0 ? $totalMatriculas : null,
                'peso_educacao_especial' => $peso,
                'incremento_ponderacao' => $incremento,
                'peso_fonte_label' => __('Lei nº 14.113/2020 (Anexo) — educação especial :p', [
                    'p' => number_format($peso, 2, ',', '.'),
                ]),
                'vaaf' => $vaaf,
                'vaaf_fmt' => $fmt($vaaf),
                'vaaf_fonte' => (string) ($calc['fonte_label'] ?? ''),
                'vaaf_origem' => (string) ($calc['origem'] ?? ''),
                'vaa_municipal_importado' => ($calc['origem'] ?? '') === 'municipal',
                'base_sem_ponderacao_anual' => $baseSemPeso,
                'base_sem_ponderacao_anual_fmt' => $fmt($baseSemPeso),
                'base_ponderada_anual' => $basePonderada,
                'base_ponderada_anual_fmt' => $fmt($basePonderada),
                'adicional_vaaf_anual' => $adicionalVaaf,
                'adicional_vaaf_anual_fmt' => $adicionalVaaf > 0 ? $fmt($adicionalVaaf) : null,
                'adicional_vaar_anual' => $adicionalVaar,
                'adicional_vaar_anual_fmt' => $adicionalVaar > 0 ? $fmt($adicionalVaar) : null,
                'adicional_vaar_fonte' => $vaarSlice['fonte'] ?? null,
                'total_incremental_anual' => $totalIncremental,
                'total_incremental_anual_fmt' => $fmt($totalIncremental),
                /** @deprecated Preferir adicional_vaaf_anual / total_incremental_anual */
                'base_anual' => $baseSemPeso,
                'base_anual_fmt' => $fmt($baseSemPeso),
                'adicional_anual' => $adicionalVaaf,
                'adicional_anual_fmt' => $adicionalVaaf > 0 ? $fmt($adicionalVaaf) : null,
                'total_indicativo_anual' => MoneyMath::roundMoney($basePonderada + $adicionalVaar),
                'total_indicativo_anual_fmt' => $fmt(MoneyMath::roundMoney($basePonderada + $adicionalVaar)),
            ];
        } catch (\Throwable) {
            return ['available' => false];
        }
    }

    /**
     * Estima a fatia da complementação VAAR atribuível ao incremento de ponderação NEE.
     *
     * @return array{valor: float, fonte: ?string}
     */
    private static function parcelaVaarIndicativa(
        City $city,
        IeducarFilterState $filters,
        int $nee,
        float $incrementoPonderacao,
        ?int $totalMatriculas,
    ): array {
        if ($incrementoPonderacao <= 0 || $nee <= 0) {
            return ['valor' => 0.0, 'fonte' => null];
        }

        $totalMat = max(0, (int) ($totalMatriculas ?? 0));
        if ($totalMat <= 0) {
            return ['valor' => 0.0, 'fonte' => null];
        }

        $ref = DiscrepanciesFundingImpact::resolveReference($city, $filters);
        $useImported = filter_var(config('ieducar.fundeb.use_imported_vaar', true), FILTER_VALIDATE_BOOL);
        $complementOficial = $ref['complementacao_vaar'] ?? null;

        if ($useImported && $complementOficial !== null && (float) $complementOficial > 0) {
            $valor = MoneyMath::roundMoney(
                (float) $complementOficial * ($nee * $incrementoPonderacao) / $totalMat
            );

            return [
                'valor' => $valor,
                'fonte' => __('complementação VAAR importada (FNDE) — fatia proporcional ao incremento NEE'),
            ];
        }

        $pct = max(0.0, (float) config('ieducar.fundeb.complementacao_vaar_pct_base', 0));
        if ($pct <= 0) {
            return ['valor' => 0.0, 'fonte' => null];
        }

        $vaaf = (float) FundebMunicipalReferenceResolver::vaafParaCalculo($city, $filters)['vaaf'];
        $baseRede = MoneyMath::multiplyVaaf($totalMat, $vaaf);
        $complementIndicativa = MoneyMath::roundMoney($baseRede * ($pct / 100));
        $valor = MoneyMath::roundMoney(
            $complementIndicativa * ($nee * $incrementoPonderacao) / $totalMat
        );

        return [
            'valor' => $valor,
            'fonte' => __('IEDUCAR_FUNDEB_VAAR_PCT_BASE — fatia proporcional ao incremento NEE'),
        ];
    }
}
