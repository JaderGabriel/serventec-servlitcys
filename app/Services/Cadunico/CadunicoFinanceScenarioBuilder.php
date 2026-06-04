<?php

namespace App\Services\Cadunico;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Finance\MoneyMath;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\InclusionFundebImpact;

/**
 * Cenários financeiros indicativos sobre a lacuna «fora da rede» (NEE, AEE, VAAR).
 */
final class CadunicoFinanceScenarioBuilder
{
    /**
     * @param  array{nee_matriculas?: int, alunos_nee?: int, matriculas_aee_sem_cadastro?: int, alunos_aee_sem_cadastro?: int}  $inclusionHints
     * @return array<string, mixed>
     */
    public static function build(
        int $gapBase,
        float $vaaf,
        int $matriculasRede,
        int $alunosRede,
        array $inclusionHints = [],
    ): array {
        if ($gapBase <= 0 || $vaaf <= 0) {
            return ['available' => false];
        }

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $pesoNee = InclusionFundebImpact::pesoEducacaoEspecial();
        $incrementoNee = max(0.0, $pesoNee - 1.0);

        $matNee = max(0, (int) ($inclusionHints['nee_matriculas'] ?? 0));
        $alunosNee = max(0, (int) ($inclusionHints['alunos_nee'] ?? 0));
        $taxaNee = self::rate($alunosNee > 0 ? $alunosNee : $matNee, $alunosRede > 0 ? $alunosRede : $matriculasRede);

        $neeForaRede = (int) round($gapBase * $taxaNee);
        $baseNee = MoneyMath::multiplyVaaf($neeForaRede, $vaaf);
        $adicionalNee = $incrementoNee > 0
            ? MoneyMath::multiplyVaaf($neeForaRede, $vaaf * $incrementoNee)
            : 0.0;

        $aeeMat = max(0, (int) ($inclusionHints['matriculas_aee_sem_cadastro'] ?? 0));
        $aeeAlunos = max(0, (int) ($inclusionHints['alunos_aee_sem_cadastro'] ?? 0));
        $taxaAee = self::rate($aeeAlunos > 0 ? $aeeAlunos : $aeeMat, $alunosRede > 0 ? $alunosRede : $matriculasRede);
        $aeeForaRede = (int) round($gapBase * $taxaAee);
        $riscoAee = $incrementoNee > 0
            ? MoneyMath::multiplyVaaf($aeeForaRede, $vaaf * $incrementoNee)
            : 0.0;

        $baseTotal = MoneyMath::multiplyVaaf($gapBase, $vaaf);
        $complementPct = max(0.0, (float) config('ieducar.fundeb.complementacao_vaar_pct_base', 0));
        $vaarIndicativo = $complementPct > 0
            ? MoneyMath::roundMoney($baseTotal * ($complementPct / 100) * 0.15)
            : 0.0;

        $itens = [
            [
                'id' => 'base',
                'titulo' => __('Integração à rede (base VAAF)'),
                'quantidade' => $gapBase,
                'valor_anual' => $baseTotal,
                'valor_label' => $fmt($baseTotal),
                'formula' => __(':n × :vaaf ≈ :total/ano', [
                    'n' => number_format($gapBase, 0, ',', '.'),
                    'vaaf' => $fmt($vaaf),
                    'total' => $fmt($baseTotal),
                ]),
                'tone' => 'indigo',
                'aviso' => __('Cenário: todas as crianças da lacuna passam a contar como matrícula municipal.'),
            ],
            [
                'id' => 'nee',
                'titulo' => __('Ponderação NEE (cenário)'),
                'quantidade' => $neeForaRede,
                'valor_anual' => $adicionalNee,
                'valor_label' => $adicionalNee > 0 ? $fmt($adicionalNee) : '—',
                'formula' => $incrementoNee > 0
                    ? __(':n × :vaaf × :inc (peso :p) — taxa NEE na rede :t%', [
                        'n' => number_format($neeForaRede, 0, ',', '.'),
                        'vaaf' => $fmt($vaaf),
                        'inc' => number_format($incrementoNee, 2, ',', '.'),
                        'p' => number_format($pesoNee, 2, ',', '.'),
                        't' => number_format($taxaNee * 100, 1, ',', '.'),
                    ])
                    : null,
                'tone' => 'violet',
                'aviso' => __('Proporção derivada da aba Inclusão; não identifica NEE no CadÚnico.'),
            ],
            [
                'id' => 'aee',
                'titulo' => __('Risco AEE sem cadastro (cenário)'),
                'quantidade' => $aeeForaRede,
                'valor_anual' => $riscoAee,
                'valor_label' => $riscoAee > 0 ? $fmt($riscoAee) : '—',
                'formula' => __('Fatia da lacuna com taxa observada de turma AEE sem cadastro NEE na rede.'),
                'tone' => 'amber',
                'aviso' => __('Indicativo de regularização de cadastro, não repasse automático.'),
            ],
        ];

        if ($vaarIndicativo > 0) {
            $itens[] = [
                'id' => 'vaar',
                'titulo' => __('Ordem VAAR (cenário)'),
                'quantidade' => null,
                'valor_anual' => $vaarIndicativo,
                'valor_label' => $fmt($vaarIndicativo),
                'formula' => __('~15% da complementação VAAR indicativa sobre a base da lacuna.'),
                'tone' => 'teal',
                'aviso' => __('Não substitui VAAR-inclusão oficial.'),
            ];
        }

        $totalCenarios = MoneyMath::roundMoney($baseTotal + $adicionalNee + $riscoAee + $vaarIndicativo);

        return [
            'available' => true,
            'gap_base' => $gapBase,
            'vaaf' => $vaaf,
            'taxa_nee_rede' => $taxaNee,
            'taxa_aee_rede' => $taxaAee,
            'itens' => $itens,
            'total_cenarios_anual' => $totalCenarios,
            'total_cenarios_label' => $fmt($totalCenarios),
            'aviso_geral' => __(
                'Valores são cenários sobre a lacuna CadÚnico − rede. Não substituem simulação FNDE nem cadastro individual.'
            ),
        ];
    }

    private static function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0 || $numerator <= 0) {
            return 0.0;
        }

        return min(1.0, $numerator / $denominator);
    }
}
