<?php

namespace App\Support\Ieducar;

use App\Support\Dashboard\ChartPayload;

/**
 * Previsão indicativa de recursos FUNDEB (matrículas × VAAF de referência) e pisos legais de aplicação.
 *
 * Não substitui repasse oficial do FNDE, complementação VAAR ou prestação de contas.
 */
final class FundebResourceProjection
{
    /**
     * @param  array<string, mixed>  $enrollmentData
     * @param  array<string, mixed>|null  $discrepanciesData
     * @return array<string, mixed>
     */
    public static function build(
        int $matriculas,
        string $yearLabel,
        array $enrollmentData,
        ?array $discrepanciesData = null,
    ): array {
        if ($matriculas <= 0) {
            return self::empty($yearLabel);
        }

        $cfg = config('ieducar.fundeb', []);
        if (! is_array($cfg)) {
            $cfg = [];
        }

        $vaa = (float) config('ieducar.discrepancies.vaa_referencia_anual', 4500);
        $aviso = (string) ($cfg['aviso_previsao'] ?? config('ieducar.discrepancies.aviso_financeiro', ''));

        $base = round($matriculas * $vaa, 2);
        $summary = is_array($discrepanciesData['summary'] ?? null) ? $discrepanciesData['summary'] : [];
        $perda = round((float) ($summary['perda_estimada_anual'] ?? 0), 2);
        $ganho = round((float) ($summary['ganho_potencial_anual'] ?? 0), 2);

        $complementPct = max(0.0, (float) ($cfg['complementacao_vaar_pct_base'] ?? 0));
        $complementIndicativa = $complementPct > 0 ? round($base * ($complementPct / 100), 2) : 0.0;

        $previsaoReferencia = $base;
        $previsaoCorrigida = round(max(0, $base + $ganho), 2);
        $previsaoRisco = round(max(0, $base - $perda), 2);
        $totalComComplemento = round($previsaoReferencia + $complementIndicativa, 2);

        $distribuicao = self::buildLegalDistribution($previsaoReferencia, $cfg);
        $porEtapa = self::extractEtapaBreakdown($enrollmentData, $vaa, $matriculas);

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        return [
            'available' => true,
            'year_label' => $yearLabel,
            'aviso' => $aviso,
            'matriculas_base' => $matriculas,
            'vaa_referencia' => $vaa,
            'vaa_label' => $fmt($vaa),
            'formula_base' => __(
                ':mat matrícula(s) ativa(s) × :vaa (VAAF de referência configurável) = :total.',
                [
                    'mat' => number_format($matriculas, 0, ',', '.'),
                    'vaa' => $fmt($vaa),
                    'total' => $fmt($base),
                ]
            ),
            'kpis' => [
                [
                    'label' => __('Previsão base (ano)'),
                    'value' => $fmt($previsaoReferencia),
                    'tone' => 'indigo',
                    'explicacao_resumo' => __(
                        ':mat matrícula(s) × :vaa (VAAF referência) = :total.',
                        [
                            'mat' => number_format($matriculas, 0, ',', '.'),
                            'vaa' => $fmt($vaa),
                            'total' => $fmt($previsaoReferencia),
                        ]
                    ),
                    'funding_explicacao' => [
                        'formula_curta' => __(':mat × :vaa = :total', [
                            'mat' => number_format($matriculas, 0, ',', '.'),
                            'vaa' => $fmt($vaa),
                            'total' => $fmt($previsaoReferencia),
                        ]),
                        'formula_expandida' => __(
                            'Previsão base = matrículas ativas no filtro × VAAF de referência (IEDUCAR_DISC_VAA_REFERENCIA), sem peso por tipo de discrepância.',
                        ),
                        'passos' => [
                            __('Matrículas no filtro: :n.', ['n' => number_format($matriculas, 0, ',', '.')]),
                            __('VAAF referência: :vaa por aluno/ano.', ['vaa' => $fmt($vaa)]),
                            __('Total indicativo: :total/ano.', ['total' => $fmt($previsaoReferencia)]),
                        ],
                    ],
                ],
                [
                    'label' => __('Risco cadastro (indicativo)'),
                    'value' => $perda > 0 ? '− '.$fmt($perda) : '—',
                    'tone' => 'rose',
                    'explicacao_resumo' => $perda > 0
                        ? __('Soma das perdas da aba Discrepâncias (ocorrências × VAAF × peso por rotina).')
                        : __('Sem pendências com impacto financeiro na aba Discrepâncias.'),
                    'funding_explicacao' => $perda > 0 ? [
                        'formula_curta' => __('Cenário com risco = base − perda (:perda)', ['perda' => $fmt($perda)]),
                        'formula_expandida' => __(
                            'Previsão com risco de cadastro = :base − :perda = :risco.',
                            ['base' => $fmt($previsaoReferencia), 'perda' => $fmt($perda), 'risco' => $fmt($previsaoRisco)],
                        ),
                        'perda_texto' => __('Importado do resumo de Discrepâncias: cada rotina com pendência contribui com ocorrências × (VAAF × peso).'),
                    ] : null,
                ],
                [
                    'label' => __('Ganho se corrigir cadastro'),
                    'value' => $ganho > 0 ? '+ '.$fmt($ganho) : '—',
                    'tone' => 'emerald',
                    'explicacao_resumo' => $ganho > 0
                        ? __('Igual à perda estimada em Discrepâncias — valor recuperável se o cadastro for corrigido.')
                        : null,
                    'funding_explicacao' => $ganho > 0 ? [
                        'formula_curta' => __('Ganho potencial = :ganho', ['ganho' => $fmt($ganho)]),
                        'ganho_texto' => __('Soma das estimativas por rotina na aba Discrepâncias; não é repasse automático do FNDE.'),
                    ] : null,
                ],
                [
                    'label' => __('Cenário após correções'),
                    'value' => $fmt($previsaoCorrigida),
                    'tone' => 'teal',
                    'explicacao_resumo' => __('Base + ganho potencial (quando há correções de cadastro).'),
                    'funding_explicacao' => [
                        'formula_curta' => __(':base + :ganho = :total', [
                            'base' => $fmt($previsaoReferencia),
                            'ganho' => $fmt($ganho),
                            'total' => $fmt($previsaoCorrigida),
                        ]),
                        'formula_expandida' => __(
                            'Cenário otimista: previsão base mais o ganho indicativo das discrepâncias corrigidas.',
                        ),
                    ],
                ],
            ],
            'totais' => [
                'fundeb_base_anual' => $previsaoReferencia,
                'complementacao_vaar_indicativa' => $complementIndicativa,
                'total_com_complemento_indicativa' => $totalComComplemento,
                'perda_risco_anual' => $perda,
                'ganho_potencial_anual' => $ganho,
                'previsao_cenario_risco' => $previsaoRisco,
                'previsao_cenario_corrigido' => $previsaoCorrigida,
            ],
            'distribuicao_legal' => $distribuicao,
            'por_etapa' => $porEtapa,
            'chart_previsao' => ChartPayload::bar(
                __('Cenários de previsão anual (indicativo)'),
                __('Valor (R$)'),
                [
                    __('Base (matrículas × VAAF)'),
                    __('Com risco de cadastro'),
                    __('Após correções'),
                ],
                [$previsaoReferencia, $previsaoRisco, $previsaoCorrigida],
            ),
            'chart_distribuicao' => $distribuicao['chart'] ?? null,
            'chart_etapa' => self::chartEtapa($porEtapa),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function empty(string $yearLabel): array
    {
        return [
            'available' => false,
            'year_label' => $yearLabel,
            'aviso' => (string) config('ieducar.discrepancies.aviso_financeiro', ''),
            'matriculas_base' => 0,
            'vaa_referencia' => (float) config('ieducar.discrepancies.vaa_referencia_anual', 4500),
            'vaa_label' => DiscrepanciesFundingImpact::formatBrl((float) config('ieducar.discrepancies.vaa_referencia_anual', 4500)),
            'formula_base' => __('Sem matrículas ativas no filtro — não é possível estimar recursos.'),
            'kpis' => [],
            'totais' => [],
            'distribuicao_legal' => ['referencia_legal' => '', 'itens' => [], 'chart' => null],
            'por_etapa' => [],
            'chart_previsao' => null,
            'chart_distribuicao' => null,
            'chart_etapa' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array{referencia_legal: string, nota: string, itens: list<array<string, mixed>>, chart: ?array}
     */
    private static function buildLegalDistribution(float $total, array $cfg): array
    {
        $legal = is_array($cfg['distribuicao_legal'] ?? null) ? $cfg['distribuicao_legal'] : [];
        $pisos = is_array($legal['pisos'] ?? null) ? $legal['pisos'] : self::defaultPisos();

        $pctDocentes = 49.0;
        $pctRemuneracao = 70.0;
        $pctQualidade = 30.0;

        foreach ($pisos as $p) {
            if (! is_array($p)) {
                continue;
            }
            $id = (string) ($p['id'] ?? '');
            $pct = (float) ($p['percentual_minimo'] ?? $p['percentual_maximo'] ?? 0);
            if ($id === 'docentes_efetivos') {
                $pctDocentes = $pct;
            } elseif ($id === 'remuneracao') {
                $pctRemuneracao = $pct;
            } elseif ($id === 'qualidade') {
                $pctQualidade = $pct;
            }
        }

        $pctOutraRemuneracao = max(0.0, $pctRemuneracao - $pctDocentes);
        $valorDocentes = round($total * ($pctDocentes / 100), 2);
        $valorOutraRem = round($total * ($pctOutraRemuneracao / 100), 2);
        $valorQualidade = round($total * ($pctQualidade / 100), 2);

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        $itens = [];
        foreach ($pisos as $p) {
            if (! is_array($p)) {
                continue;
            }
            $id = (string) ($p['id'] ?? '');
            $titulo = (string) ($p['titulo'] ?? '');
            $descricao = (string) ($p['descricao'] ?? '');
            $nota = (string) ($p['nota'] ?? '');

            $pctMin = isset($p['percentual_minimo']) ? (float) $p['percentual_minimo'] : null;
            $pctMax = isset($p['percentual_maximo']) ? (float) $p['percentual_maximo'] : null;

            $valor = match ($id) {
                'docentes_efetivos' => $valorDocentes,
                'remuneracao' => round($valorDocentes + $valorOutraRem, 2),
                'qualidade' => $valorQualidade,
                default => $pctMin !== null ? round($total * ($pctMin / 100), 2) : ($pctMax !== null ? round($total * ($pctMax / 100), 2) : 0.0),
            };

            $pctExibir = $pctMin ?? $pctMax ?? 0.0;

            $itens[] = [
                'id' => $id,
                'titulo' => $titulo,
                'descricao' => $descricao,
                'nota' => $nota,
                'percentual' => $pctExibir,
                'percentual_label' => $pctMin !== null
                    ? __('mín. :p%', ['p' => number_format($pctMin, 0, ',', '.')])
                    : __('até :p%', ['p' => number_format((float) $pctMax, 0, ',', '.')]),
                'valor_minimo' => $valor,
                'valor_label' => $fmt($valor),
            ];
        }

        $hasOutraRem = false;
        foreach ($itens as $item) {
            if (($item['id'] ?? '') === 'outra_remuneracao') {
                $hasOutraRem = true;
                break;
            }
        }
        if ($pctOutraRemuneracao > 0.01 && ! $hasOutraRem) {
            $itens = self::injectOutraRemuneracao($itens, $pctOutraRemuneracao, $valorOutraRem, $fmt);
        }

        $chartLabels = [
            __('Docentes (efetivo exercício)'),
            __('Outros profissionais (remuneração)'),
            __('Demais despesas (até :p%)', ['p' => number_format($pctQualidade, 0, ',', '.')]),
        ];
        $chartValues = [$valorDocentes, $valorOutraRem, $valorQualidade];

        return [
            'referencia_legal' => (string) ($legal['referencia'] ?? __('Lei nº 14.113/2020 (FUNDEB) — pisos mínimos de aplicação anual dos recursos.')),
            'nota' => (string) ($legal['nota'] ?? __(
                'Os percentuais abaixo são pisos legais de planejamento sobre a previsão base; a aplicação efetiva deve constar em plano, prestação de contas e relatórios do FNDE/Tesouro.'
            )),
            'total_base' => $total,
            'total_base_label' => $fmt($total),
            'itens' => array_values($itens),
            'chart' => ChartPayload::doughnut(
                __('Distribuição legal mínima (sobre previsão base)'),
                $chartLabels,
                $chartValues,
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @param  callable(float): string  $fmt
     * @return list<array<string, mixed>>
     */
    private static function injectOutraRemuneracao(array $itens, float $pct, float $valor, callable $fmt): array
    {
        $extra = [
            'id' => 'outra_remuneracao',
            'titulo' => __('Demais profissionais da educação básica (parte da remuneração)'),
            'descricao' => __('Parcela do piso de 70% de remuneração que não se destina ao mínimo de docentes em efetivo exercício.'),
            'nota' => '',
            'percentual' => $pct,
            'percentual_label' => __('mín. :p%', ['p' => number_format($pct, 0, ',', '.')]),
            'valor_minimo' => $valor,
            'valor_label' => $fmt($valor),
        ];

        $out = [];
        foreach ($itens as $item) {
            $out[] = $item;
            if (($item['id'] ?? '') === 'docentes_efetivos') {
                $out[] = $extra;
            }
        }

        return $out;
    }

    /**
     * @return list<array{id: string, titulo: string, descricao: string, percentual_minimo: float, percentual_maximo?: float, nota?: string}>
     */
    private static function defaultPisos(): array
    {
        return [
            [
                'id' => 'remuneracao',
                'titulo' => __('Remuneração dos profissionais da educação básica'),
                'descricao' => __('Folha e encargos dos profissionais da educação básica vinculados à rede.'),
                'percentual_minimo' => 70.0,
            ],
            [
                'id' => 'docentes_efetivos',
                'titulo' => __('Docentes em efetivo exercício'),
                'descricao' => __('Pagamento a docentes em efetivo exercício na educação básica.'),
                'percentual_minimo' => 49.0,
                'nota' => __('Mínimo de 70% do montante destinado à remuneração (equivalente a 49% do total do FUNDEB).'),
            ],
            [
                'id' => 'qualidade',
                'titulo' => __('Demais despesas de manutenção e desenvolvimento'),
                'descricao' => __('Infraestrutura, materiais, formação e outras despesas permitidas, respeitado o teto legal.'),
                'percentual_maximo' => 30.0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $enrollmentData
     * @return list<array{etapa: string, matriculas: int, participacao_pct: float, fundeb_indicativo: float, fundeb_label: string}>
     */
    private static function extractEtapaBreakdown(array $enrollmentData, float $vaa, int $matTotal): array
    {
        $chart = self::findNivelEnsinoChart($enrollmentData['charts'] ?? []);
        if ($chart === null) {
            return [];
        }

        $labels = $chart['labels'] ?? [];
        $data = $chart['datasets'][0]['data'] ?? [];
        if (! is_array($labels) || ! is_array($data) || $labels === []) {
            return [];
        }

        $out = [];
        foreach (array_values($labels) as $i => $label) {
            $mat = (int) ($data[$i] ?? 0);
            if ($mat <= 0) {
                continue;
            }
            $part = $matTotal > 0 ? round(100.0 * $mat / $matTotal, 1) : 0.0;
            $fundeb = round($mat * $vaa, 2);
            $out[] = [
                'etapa' => (string) $label,
                'matriculas' => $mat,
                'participacao_pct' => $part,
                'fundeb_indicativo' => $fundeb,
                'fundeb_label' => DiscrepanciesFundingImpact::formatBrl($fundeb),
            ];
        }

        usort($out, static fn ($a, $b) => ($b['fundeb_indicativo'] ?? 0) <=> ($a['fundeb_indicativo'] ?? 0));

        return $out;
    }

    /**
     * @param  mixed  $charts
     * @return ?array<string, mixed>
     */
    private static function findNivelEnsinoChart($charts): ?array
    {
        if (! is_array($charts)) {
            return null;
        }

        foreach ($charts as $c) {
            if (! is_array($c)) {
                continue;
            }
            $t = mb_strtolower((string) ($c['title'] ?? ''));
            if (str_contains($t, 'nível de ensino') || str_contains($t, 'nivel de ensino')) {
                return $c;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $porEtapa
     * @return ?array<string, mixed>
     */
    private static function chartEtapa(array $porEtapa): ?array
    {
        if ($porEtapa === []) {
            return null;
        }

        $slice = array_slice($porEtapa, 0, 12);
        $labels = array_map(static fn ($r) => (string) ($r['etapa'] ?? ''), $slice);
        $values = array_map(static fn ($r) => (float) ($r['fundeb_indicativo'] ?? 0), $slice);

        return ChartPayload::barHorizontal(
            __('Previsão indicativa por nível de ensino'),
            __('FUNDEB base (R$)'),
            $labels,
            $values,
        );
    }
}
