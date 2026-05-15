<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Estimativa indicativa de perda / ganho potencial (FUNDEB, VAAR, Censo) por tipo de discrepância.
 *
 * Valores são referências configuráveis (VAAF municipal médio × peso por eixo), não substituem
 * cálculo oficial do FNDE ou prestação de contas no Simec.
 */
final class DiscrepanciesFundingImpact
{
    /**
     * @return array{
     *   perda_anual: float,
     *   ganho_potencial_anual: float,
     *   valor_unitario: float,
     *   peso: float,
     *   formula: string,
     *   aviso: string,
     *   explicacao: array<string, mixed>
     * }
     */
    public static function estimate(
        string $checkId,
        int $occurrences,
        ?City $city = null,
        ?IeducarFilterState $filters = null,
    ): array {
        $occurrences = max(0, $occurrences);
        $vaa = self::vaaReferencia($city, $filters);
        $peso = self::pesoParaCheck($checkId);
        $valorUnit = $vaa * $peso;
        $perda = round($occurrences * $valorUnit, 2);
        $ganho = $perda;

        $formula = __(
            ':n ocorrência(s) × R$ :unit (VAAF referência :vaa × peso :p).',
            [
                'n' => number_format($occurrences, 0, ',', '.'),
                'unit' => self::formatBrl($valorUnit),
                'vaa' => self::formatBrl($vaa),
                'p' => number_format($peso, 2, ',', '.'),
            ]
        );

        return [
            'perda_anual' => $perda,
            'ganho_potencial_anual' => $ganho,
            'valor_unitario' => $valorUnit,
            'peso' => $peso,
            'formula' => $formula,
            'aviso' => self::avisoGeral(),
            'explicacao' => self::buildExplicacao($checkId, $occurrences, $vaa, $peso, $valorUnit, $perda, $ganho),
        ];
    }

    public static function vaaReferencia(?City $city = null, ?IeducarFilterState $filters = null): float
    {
        return self::resolveReference($city, $filters)['vaaf'];
    }

    /**
     * @return array{
     *   vaaf: float,
     *   vaat: ?float,
     *   complementacao_vaar: ?float,
     *   fonte: string,
     *   fonte_label: string,
     *   ano: ?int,
     *   ibge: ?string,
     *   notas: ?string
     * }
     */
    public static function resolveReference(?City $city = null, ?IeducarFilterState $filters = null): array
    {
        return FundebMunicipalReferenceResolver::resolve($city, $filters);
    }

    public static function avisoGeral(): string
    {
        return (string) config('ieducar.discrepancies.aviso_financeiro', '');
    }

    public static function pesoParaCheck(string $checkId): float
    {
        $pesos = config('ieducar.discrepancies.peso_por_check', []);
        if (is_array($pesos) && isset($pesos[$checkId])) {
            return max(0.0, (float) $pesos[$checkId]);
        }

        return 1.0;
    }

    /**
     * Texto metodológico geral (painéis de resumo).
     *
     * @return array{titulo: string, passos: list<string>, aviso: string, vaa_label: string}
     */
    public static function metodologiaResumo(?City $city = null, ?IeducarFilterState $filters = null): array
    {
        $ref = self::resolveReference($city, $filters);
        $vaa = $ref['vaaf'];

        return [
            'titulo' => __('Como são calculados os valores financeiros indicativos'),
            'passos' => [
                __('1. Contagem de ocorrências — cada rotina soma matrículas, escolas ou vagas com o problema no filtro actual (ano, escola, curso).'),
                __('2. Valor unitário de referência — VAAF (:vaa por aluno/ano; :fonte) × peso do tipo de problema.', [
                    'vaa' => self::formatBrl($vaa),
                    'fonte' => $ref['fonte_label'],
                ]),
                __('3. Perda estimada anual = ocorrências × valor unitário. Representa ordem de grandeza do que pode deixar de ser contabilizado ou financiado se o cadastro não for corrigido antes do Censo/VAAR.'),
                __('4. Ganho potencial anual — neste modelo, igual à perda: valor que a rede poderia recuperar ou deixar de arriscar após corrigir o cadastro no i-Educar.'),
                __('5. Previsão FUNDEB (aba FUNDEB) — usa outra fórmula: matrículas ativas × VAAF, sem peso por tipo; refere-se ao volume total do fundo, não a uma discrepância isolada.'),
            ],
            'aviso' => self::avisoGeral(),
            'vaa_label' => self::formatBrl($vaa),
            'vaa_fonte' => $ref['fonte'],
            'vaa_fonte_label' => $ref['fonte_label'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fundingReferencePayload(?City $city = null, ?IeducarFilterState $filters = null): array
    {
        $ref = self::resolveReference($city, $filters);

        return [
            'vaa_anual' => $ref['vaaf'],
            'vaa_label' => self::formatBrl($ref['vaaf']),
            'vaa_fonte' => $ref['fonte'],
            'vaa_fonte_label' => $ref['fonte_label'],
            'vaa_ano' => $ref['ano'],
            'vaat' => $ref['vaat'],
            'vaat_label' => $ref['vaat'] !== null ? self::formatBrl($ref['vaat']) : null,
            'complementacao_vaar' => $ref['complementacao_vaar'],
        ];
    }

    /**
     * Explicação do resumo agregado (KPIs de perda/ganho totais).
     *
     * @return array{titulo: string, detalhe: string, passos: list<string>}
     */
    public static function explicacaoResumoAgregado(int $ocorrenciasSoma, float $perdaTotal, float $ganhoTotal, int $tiposComProblema): array
    {
        return [
            'titulo' => __('Cálculo do resumo (soma das rotinas com pendência)'),
            'detalhe' => __(
                ':tipos rotina(s) com pendência · :n ocorrências (soma) · perda indicativa :perda · ganho potencial :ganho.',
                [
                    'tipos' => number_format($tiposComProblema),
                    'n' => number_format($ocorrenciasSoma),
                    'perda' => self::formatBrl($perdaTotal),
                    'ganho' => self::formatBrl($ganhoTotal),
                ]
            ),
            'passos' => [
                __('Cada tipo de discrepância usa o seu peso (config/ieducar.php → discrepancies.peso_por_check).'),
                __('A soma das perdas não é o valor oficial do FNDE — é a soma das estimativas por rotina.'),
                __('Ocorrências de tipos diferentes (ex.: 100 matrículas sem raça + 5 escolas sem INEP) somam-se como contagens, não como alunos únicos deduplicados.'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildExplicacao(
        string $checkId,
        int $occurrences,
        float $vaa,
        float $peso,
        float $valorUnit,
        float $perda,
        float $ganho,
    ): array {
        $checkLabel = self::labelParaCheck($checkId);

        return [
            'check_id' => $checkId,
            'check_label' => $checkLabel,
            'ocorrencias' => $occurrences,
            'vaa' => $vaa,
            'vaa_label' => self::formatBrl($vaa),
            'peso' => $peso,
            'peso_label' => number_format($peso, 2, ',', '.'),
            'valor_unitario' => $valorUnit,
            'valor_unitario_label' => self::formatBrl($valorUnit),
            'perda_label' => self::formatBrl($perda),
            'ganho_label' => self::formatBrl($ganho),
            'formula_curta' => __(
                ':ocorr × :unit = :perda',
                [
                    'ocorr' => number_format($occurrences, 0, ',', '.'),
                    'unit' => self::formatBrl($valorUnit),
                    'perda' => self::formatBrl($perda),
                ]
            ),
            'formula_expandida' => __(
                ':ocorr ocorrência(s) × (VAAF :vaa × peso :p) = :perda',
                [
                    'ocorr' => number_format($occurrences, 0, ',', '.'),
                    'vaa' => self::formatBrl($vaa),
                    'p' => number_format($peso, 2, ',', '.'),
                    'perda' => self::formatBrl($perda),
                ]
            ),
            'perda_texto' => __(
                'Perda estimada: cada uma das :n ocorrências de «:tipo» é multiplicada por :unit (referência anual por ocorrência), totalizando :perda/ano.',
                [
                    'n' => number_format($occurrences, 0, ',', '.'),
                    'tipo' => $checkLabel,
                    'unit' => self::formatBrl($valorUnit),
                    'perda' => self::formatBrl($perda),
                ]
            ),
            'ganho_texto' => __(
                'Ganho potencial: se todas as :n ocorrências forem corrigidas no i-Educar antes do Censo, a estimativa indicativa de recuperação é :ganho/ano (mesma base da perda neste modelo).',
                [
                    'n' => number_format($occurrences, 0, ',', '.'),
                    'ganho' => self::formatBrl($ganho),
                ]
            ),
            'passos' => [
                __('VAAF de referência (IEDUCAR_DISC_VAA_REFERENCIA): :vaa — ordem de grandeza do valor-aluno-ano municipal.', ['vaa' => self::formatBrl($vaa)]),
                __('Peso para «:id»: :p — ajusta impacto relativo (Censo/VAAR costumam usar pesos maiores para NEE, INEP, duplicidade).', [
                    'id' => $checkId,
                    'p' => number_format($peso, 2, ',', '.'),
                ]),
                __('Valor por ocorrência = :vaa × :p = :unit.', [
                    'vaa' => self::formatBrl($vaa),
                    'p' => number_format($peso, 2, ',', '.'),
                    'unit' => self::formatBrl($valorUnit),
                ]),
            ],
        ];
    }

    private static function labelParaCheck(string $checkId): string
    {
        $def = DiscrepanciesCheckCatalog::definitions()[$checkId] ?? null;

        return is_array($def) ? (string) ($def['title'] ?? $checkId) : $checkId;
    }

    public static function formatBrl(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    /**
     * @return list<array{id: string, titulo: string, descricao: string}>
     */
    public static function fundingPillars(): array
    {
        $pillars = config('ieducar.discrepancies.funding_pillars', []);

        return is_array($pillars) ? array_values(array_filter($pillars, is_array(...))) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $pillars
     * @param  list<array<string, mixed>>  $dimensions
     * @return list<array<string, mixed>>
     */
    public static function pillarsWithMunicipioSummary(
        array $pillars,
        array $dimensions,
        string $cityName,
        string $yearLabel
    ): array {
        $pillarChecks = [
            'fundeb-base' => ['sem_raca', 'sem_sexo', 'sem_data_nascimento', 'matricula_duplicada', 'matricula_situacao_invalida', 'distorcao_idade_serie'],
            'vaar-inclusao' => ['nee_sem_aee', 'aee_sem_nee', 'nee_subnotificacao', 'recurso_prova_sem_nee', 'nee_sem_recurso_prova', 'recurso_prova_incompativel', 'sem_raca', 'sem_sexo'],
            'vaar-indicadores' => ['escola_sem_inep', 'escola_inativa_matricula', 'distorcao_idade_serie'],
            'pnae-transporte' => ['escola_sem_geo', 'matricula_duplicada', 'matricula_situacao_invalida', 'rede_vagas_ociosas'],
        ];

        $checksById = [];
        foreach ($dimensions as $c) {
            if (! is_array($c)) {
                continue;
            }
            $checksById[(string) ($c['id'] ?? '')] = $c;
        }

        $out = [];
        foreach ($pillars as $pillar) {
            if (! is_array($pillar)) {
                continue;
            }
            $pid = (string) ($pillar['id'] ?? '');
            $linked = $pillarChecks[$pid] ?? [];
            $tipos = 0;
            $indisponiveis = 0;
            $semDados = 0;
            $ocorrencias = 0;
            $ganho = 0.0;
            $perda = 0.0;
            foreach ($linked as $checkId) {
                $c = $checksById[$checkId] ?? null;
                if ($c === null) {
                    continue;
                }
                $avail = (string) ($c['availability'] ?? 'available');
                if ($avail === 'unavailable') {
                    $indisponiveis++;

                    continue;
                }
                if ($avail === 'no_data' || ($c['status'] ?? '') === 'no_data') {
                    $semDados++;

                    continue;
                }
                if (! ($c['has_issue'] ?? $c['detected'] ?? false)) {
                    continue;
                }
                $tipos++;
                $ocorrencias += (int) ($c['total'] ?? 0);
                $ganho += (float) ($c['ganho_potencial_anual'] ?? 0);
                $perda += (float) ($c['perda_estimada_anual'] ?? 0);
            }

            $status = match (true) {
                $tipos >= 2 || $ocorrencias >= 50 => 'danger',
                $tipos > 0 => 'warning',
                $indisponiveis > 0 || $semDados > 0 => 'neutral',
                default => 'ok',
            };

            $texto = match (true) {
                $tipos > 0 => __(':city — :year: :tipos tipo(s) de problema, :n ocorrência(s), perda indicativa :perda, ganho potencial :ganho.', [
                    'city' => $cityName,
                    'year' => $yearLabel !== '' ? $yearLabel : __('filtro actual'),
                    'tipos' => number_format($tipos),
                    'n' => number_format($ocorrencias),
                    'perda' => self::formatBrl($perda),
                    'ganho' => self::formatBrl($ganho),
                ]),
                $indisponiveis > 0 && $semDados > 0 => __('Sem pendências nas rotinas com dados analisados; :indis rotina(s) indisponível(eis) e :sem dados sem universo no filtro (ver mapa).', [
                    'indis' => number_format($indisponiveis),
                    'sem' => number_format($semDados),
                ]),
                $indisponiveis > 0 => __('Sem pendências nas rotinas verificadas; :n rotina(s) deste eixo não pôde ser executada nesta base (ver mapa).', [
                    'n' => number_format($indisponiveis),
                ]),
                $semDados > 0 => __('Sem pendências onde houve cadastro para analisar; :n rotina(s) sem dados no filtro actual (azul no mapa — não equivale a «tudo certo»).', [
                    'n' => number_format($semDados),
                ]),
                default => __('Cadastro analisado neste eixo: nenhuma pendência detectada no filtro actual.'),
            };

            $out[] = array_merge($pillar, [
                'municipio_resumo' => [
                    'texto' => $texto,
                    'status' => $status,
                    'tipos_afetados' => $tipos,
                    'rotinas_indisponiveis' => $indisponiveis,
                    'ocorrencias' => $ocorrencias,
                    'perda_estimada' => $perda,
                    'ganho_potencial' => $ganho,
                ],
            ]);
        }

        return $out;
    }
}
