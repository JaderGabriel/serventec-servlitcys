<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Informes indicativos VAAF, VAAT e complementação VAAR para a aba FUNDEB,
 * cruzando referências oficiais/importadas com dados do i-Educar e Discrepâncias.
 */
final class FundebComplementacaoInformeBuilder
{
    /**
     * @param  array<string, mixed>|null  $discrepanciesData
     * @param  array<string, mixed>|null  $inclusionData
     * @param  array<string, mixed>|null  $resourceProjection
     * @return array{
     *   available: bool,
     *   aviso: string,
     *   blocos: list<array{
     *     id: string,
     *     titulo: string,
     *     subtitulo: string,
     *     status: string,
     *     status_label: string,
     *     paragrafos: list<string>,
     *     indicadores: list<array{label: string, value: string, hint: ?string}>,
     *     acoes: list<string>
     *   }>
     * }
     */
    public static function build(
        City $city,
        IeducarFilterState $filters,
        int $matriculas,
        ?array $discrepanciesData = null,
        ?array $inclusionData = null,
        ?array $resourceProjection = null,
    ): array {
        $ref = DiscrepanciesFundingImpact::resolveReference($city, $filters);
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $yearLabel = self::yearLabel($filters);
        $disc = is_array($discrepanciesData) ? $discrepanciesData : [];
        $incl = is_array($inclusionData) ? $inclusionData : [];
        $proj = is_array($resourceProjection) ? $resourceProjection : [];
        $pillars = is_array($disc['funding_pillars'] ?? null) ? $disc['funding_pillars'] : [];
        $summary = is_array($disc['summary'] ?? null) ? $disc['summary'] : [];

        $baseFundeb = (float) ($proj['totais']['fundeb_base_anual'] ?? 0);
        if ($baseFundeb <= 0 && $matriculas > 0) {
            $baseFundeb = round($matriculas * $ref['vaaf'], 2);
        }

        $complementPct = max(0.0, (float) config('ieducar.fundeb.complementacao_vaar_pct_base', 0));
        $complementIndicativaPct = $complementPct > 0 && $baseFundeb > 0
            ? round($baseFundeb * ($complementPct / 100), 2)
            : 0.0;
        $complementOficial = $ref['complementacao_vaar'];
        $complementVaarUsar = ($complementOficial !== null && $complementOficial > 0)
            ? (float) $complementOficial
            : $complementIndicativaPct;

        $blocos = [
            self::blocoVaaf($city, $ref, $matriculas, $baseFundeb, $fmt, $yearLabel),
            self::blocoVaat($ref, $matriculas, $baseFundeb, $fmt, $yearLabel),
            self::blocoVaar(
                $city,
                $ref,
                $pillars,
                $summary,
                $incl,
                $matriculas,
                $baseFundeb,
                $complementVaarUsar,
                $complementOficial !== null && $complementOficial > 0,
                $fmt,
                $yearLabel,
            ),
            self::blocoOutrasComplementacoes($matriculas, $fmt),
        ];

        return [
            'available' => $matriculas > 0 || $ref['fonte'] !== FundebMunicipalReferenceResolver::FONTE_CONFIG_GLOBAL,
            'aviso' => __(
                'Textos gerados para apoio à consultoria municipal. Não substituem extrato do FNDE, Simec (situação VAAR) nem prestação de contas. Importe VAAF/VAAT oficiais com fundeb:import-references ou configure fundeb.vaaf_por_ibge.'
            ),
            'blocos' => array_values(array_filter($blocos)),
        ];
    }

    /**
     * @param  array{
     *   vaaf: float,
     *   vaat: ?float,
     *   complementacao_vaar: ?float,
     *   fonte: string,
     *   fonte_label: string,
     *   ano: ?int,
     *   ibge: ?string,
     *   notas: ?string
     * }  $ref
     * @param  callable(float): string  $fmt
     * @return array<string, mixed>
     */
    private static function blocoVaaf(
        City $city,
        array $ref,
        int $matriculas,
        float $baseFundeb,
        callable $fmt,
        string $yearLabel,
    ): array {
        $oficial = $ref['fonte'] === FundebMunicipalReferenceResolver::FONTE_OFICIAL_DB
            || $ref['fonte'] === FundebMunicipalReferenceResolver::FONTE_CONFIG_IBGE;

        $paragrafos = [
            __(
                'O VAAF (Valor Aluno Ano do Fundeb) é o valor por aluno/ano usado na distribuição do fundo. Neste painel serve de referência para a previsão de volume (matrículas × VAAF) e para estimativas de impacto de cadastro nas Discrepâncias.'
            ),
            __(
                'Município: :city. :year. Fonte do valor: :fonte.',
                ['city' => $city->name, 'year' => $yearLabel, 'fonte' => $ref['fonte_label']],
            ),
        ];

        if (filled($ref['notas'] ?? null)) {
            $paragrafos[] = (string) $ref['notas'];
        }

        if (! $oficial) {
            $paragrafos[] = __(
                'Não há VAAF oficial importado para este IBGE/ano. O sistema usa IEDUCAR_DISC_VAA_REFERENCIA ou entrada em fundeb.vaaf_por_ibge — importe dados do painel FNDE para maior precisão.'
            );
        }

        $indicadores = [
            [
                'label' => __('VAAF utilizado'),
                'value' => $fmt($ref['vaaf']),
                'hint' => $ref['fonte_label'],
            ],
        ];

        if ($matriculas > 0) {
            $indicadores[] = [
                'label' => __('Previsão base (matrículas × VAAF)'),
                'value' => $fmt($baseFundeb),
                'hint' => __(':n matrícula(s) no filtro', ['n' => number_format($matriculas, 0, ',', '.')]),
            ];
        }

        return [
            'id' => 'vaaf',
            'titulo' => __('VAAF — referência municipal'),
            'subtitulo' => __('Base de cálculo do volume FUNDEB no painel'),
            'status' => $oficial ? 'success' : 'warning',
            'status_label' => $oficial ? __('Valor oficial ou configurado por IBGE') : __('Referência global configurável'),
            'paragrafos' => $paragrafos,
            'indicadores' => $indicadores,
            'acoes' => $oficial
                ? [__('Manter cadastro de referências actualizado quando o FNDE publicar novo ano.')]
                : [
                    __('Importar CSV FNDE: php artisan fundeb:import-references …'),
                    __('Ou definir fundeb.vaaf_por_ibge no config/ieducar.php para o código IBGE da cidade.'),
                ],
        ];
    }

    /**
     * @param  array{vaaf: float, vaat: ?float, fonte: string, fonte_label: string}  $ref
     * @param  callable(float): string  $fmt
     * @return array<string, mixed>
     */
    private static function blocoVaat(array $ref, int $matriculas, float $baseFundeb, callable $fmt, string $yearLabel): array
    {
        $vaat = $ref['vaat'];
        $temVaat = $vaat !== null && $vaat > 0;

        $paragrafos = [
            __(
                'O VAAT (Valor Aluno Ano Total) é o patamar mínimo de receita por aluno para efeito da complementação da União (esforço fiscal e matrículas no Censo). A habilitação e o valor exacto dependem de regras do FNDE, Siope/Siconfi e publicações anuais — não são calculados automaticamente aqui.'
            ),
        ];

        $status = 'neutral';
        $statusLabel = __('Consultar FNDE / habilitação VAAT');
        $acoes = [
            __('Verificar no portal FNDE se o município está habilitado à complementação VAAT no exercício.'),
            __('Conferir envio de dados contábeis (Siconfi) e educacionais (Siope) nos prazos legais.'),
        ];

        $indicadores = [];

        if ($temVaat) {
            $indicadores[] = [
                'label' => __('VAAT de referência (importado)'),
                'value' => $fmt($vaat),
                'hint' => $yearLabel,
            ];
            if ($matriculas > 0 && $ref['vaaf'] > 0) {
                $gap = $vaat - $ref['vaaf'];
                $indicadores[] = [
                    'label' => __('Diferença VAAF − VAAT (indicativa)'),
                    'value' => $fmt($gap),
                    'hint' => $gap > 0
                        ? __('VAAF abaixo do VAAT: cenário típico de possível complementação (validar no FNDE).')
                        : __('VAAF igual ou acima do VAAT: complementação VAAT em geral não se aplica.'),
                ];
                if ($gap > 0) {
                    $status = 'warning';
                    $statusLabel = __('VAAF abaixo do VAAT — validar elegibilidade');
                    $paragrafos[] = __(
                        'Com VAAF (:vaaf) inferior ao VAAT (:vaat), o município pode, em tese, ter direito à complementação VAAT, sujeita a habilitação e limites legais. Confirme no extrato oficial.',
                        ['vaaf' => $fmt($ref['vaaf']), 'vaat' => $fmt($vaat)],
                    );
                } else {
                    $status = 'success';
                    $statusLabel = __('VAAF ≥ VAAT na referência importada');
                    $paragrafos[] = __(
                        'Na referência importada, o VAAF (:vaaf) não está abaixo do VAAT (:vaat). A complementação VAAT da União, quando existir regra de elegibilidade por patamar, pode não incidir neste cenário.',
                        ['vaaf' => $fmt($ref['vaaf']), 'vaat' => $fmt($vaat)],
                    );
                }
            }
        } else {
            $paragrafos[] = __(
                'Sem VAAT na base local. Inclua a coluna vaat no CSV de importação ou em fundeb.vaaf_por_ibge quando disponível no material do FNDE.'
            );
            $indicadores[] = [
                'label' => __('VAAT'),
                'value' => '—',
                'hint' => __('Não importado'),
            ];
        }

        return [
            'id' => 'vaat',
            'titulo' => __('VAAT — complementação por esforço fiscal'),
            'subtitulo' => __('Patamar e habilitação (referência FNDE)'),
            'status' => $status,
            'status_label' => $statusLabel,
            'paragrafos' => $paragrafos,
            'indicadores' => $indicadores,
            'acoes' => $acoes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pillars
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $incl
     * @param  callable(float): string  $fmt
     * @return array<string, mixed>
     */
    private static function blocoVaar(
        City $city,
        array $ref,
        array $pillars,
        array $summary,
        array $incl,
        int $matriculas,
        float $baseFundeb,
        float $complementVaar,
        bool $complementOficial,
        callable $fmt,
        string $yearLabel,
    ): array {
        $perda = (float) ($summary['perda_estimada_anual'] ?? 0);
        $ganho = (float) ($summary['ganho_potencial_anual'] ?? 0);
        $semNee = (int) data_get($incl, 'recurso_prova.sem_nee', 0);
        $recursoCom = (int) data_get($incl, 'recurso_prova.com_recurso', 0);

        $pilarInclusao = self::findPillar($pillars, 'vaar-inclusao');
        $pilarIndicadores = self::findPillar($pillars, 'vaar-indicadores');
        $resumoInclusao = is_array($pilarInclusao['municipio_resumo'] ?? null) ? $pilarInclusao['municipio_resumo'] : null;
        $resumoInd = is_array($pilarIndicadores['municipio_resumo'] ?? null) ? $pilarIndicadores['municipio_resumo'] : null;

        $paragrafos = [
            __(
                'O VAAR (Valor Aluno Ano Resultado) vincula repasses complementares ao cumprimento de condicionalidades (gestão democrática, BNCC, indicadores INEP, inclusão, etc.). A comprovação formal é no Simec; este bloco cruza o cadastro i-Educar com os eixos monitorados em Discrepâncias.'
            ),
            __(
                ':city — :year.',
                ['city' => $city->name, 'year' => $yearLabel],
            ),
        ];

        if ($resumoInclusao !== null && filled($resumoInclusao['texto'] ?? null)) {
            $paragrafos[] = __('Eixo inclusão (Discrepâncias): :t.', ['t' => (string) $resumoInclusao['texto']]);
        }

        if ($resumoInd !== null && filled($resumoInd['texto'] ?? null)) {
            $paragrafos[] = __('Eixo indicadores INEP: :t.', ['t' => (string) $resumoInd['texto']]);
        }

        if ($semNee > 0) {
            $paragrafos[] = __(
                'Há :n matrícula(s) com recurso de prova INEP sem NEE cadastrado — risco no eixo inclusão/equidade do Censo e do VAAR. Corrija no i-Educar antes do fecho do Educacenso.',
                ['n' => number_format($semNee)],
            );
        }

        if ($perda > 0) {
            $paragrafos[] = __(
                'O cadastro actual sugere perda indicativa de :perda/ano se as pendências de Discrepâncias não forem corrigidas (modelo VAAF × peso por rotina). Ganho potencial estimado: :ganho.',
                ['perda' => $fmt($perda), 'ganho' => $fmt($ganho)],
            );
        }

        $status = 'neutral';
        $statusLabel = __('Acompanhar Simec + cadastro');
        if (($resumoInclusao['status'] ?? '') === 'danger' || ($resumoInd['status'] ?? '') === 'danger') {
            $status = 'danger';
            $statusLabel = __('Risco elevado em eixos VAAR');
        } elseif (($resumoInclusao['status'] ?? '') === 'warning' || $semNee > 0 || $perda > 0) {
            $status = 'warning';
            $statusLabel = __('Atenção em inclusão ou cadastro');
        } elseif ($complementVaar > 0) {
            $status = 'success';
            $statusLabel = __('Referência de complementação disponível');
        }

        $indicadores = [];
        if ($complementVaar > 0) {
            $indicadores[] = [
                'label' => $complementOficial
                    ? __('Complementação VAAR (importada)')
                    : __('Complementação VAAR indicativa (:pct% sobre base)', [
                        'pct' => number_format((float) config('ieducar.fundeb.complementacao_vaar_pct_base', 0), 1, ',', '.'),
                    ]),
                'value' => $fmt($complementVaar),
                'hint' => $complementOficial
                    ? __('Valor da base importada')
                    : __('IEDUCAR_FUNDEB_VAAR_PCT_BASE — ordem de grandeza'),
            ];
        }

        if ($matriculas > 0) {
            $indicadores[] = [
                'label' => __('Matrículas no filtro'),
                'value' => number_format($matriculas, 0, ',', '.'),
                'hint' => null,
            ];
        }

        if ($semNee > 0) {
            $indicadores[] = [
                'label' => __('Recurso de prova sem NEE'),
                'value' => number_format($semNee),
                'hint' => $recursoCom > 0
                    ? __('de :total com recurso', ['total' => number_format($recursoCom)])
                    : null,
            ];
        }

        if ($perda > 0) {
            $indicadores[] = [
                'label' => __('Perda indicativa (cadastro)'),
                'value' => $fmt($perda),
                'hint' => __('Soma Discrepâncias'),
            ];
        }

        return [
            'id' => 'vaar',
            'titulo' => __('VAAR — condicionalidades e complementação'),
            'subtitulo' => __('Resultado, inclusão e indicadores (Simec / INEP)'),
            'status' => $status,
            'status_label' => $statusLabel,
            'paragrafos' => $paragrafos,
            'indicadores' => $indicadores,
            'acoes' => [
                __('Abrir módulo FUNDEB → Situação VAAR no Simec e comparar com os eixos em Discrepâncias.'),
                __('Priorizar correcções nos eixos «vaar-inclusao» e «vaar-indicadores» antes do prazo do Censo.'),
                __('Documentar plano de ação para diligências pendentes (fora do i-Educar).'),
            ],
        ];
    }

    /**
     * @param  callable(float): string  $fmt
     * @return array<string, mixed>
     */
    private static function blocoOutrasComplementacoes(int $matriculas, callable $fmt): array
    {
        return [
            'id' => 'outras',
            'titulo' => __('Outras receitas e complementações'),
            'subtitulo' => __('Fora do cálculo automático deste painel'),
            'status' => 'neutral',
            'status_label' => __('Consultar fontes oficiais'),
            'paragrafos' => [
                __(
                    'O FUNDEB municipal combina receitas próprias (ICMS, ISS, IPTU etc.), transferências constitucionais e complementações federais (VAAT, VAAR e outras). Este painel não integra extrato do Tesouro nem repasses diários do FNDE.'
                ),
                __(
                    'Use a secção «Fontes públicas» desta aba e o Tesouro Transparente para cruzar transferências constitucionais e o cronograma de repasses do exercício.'
                ),
            ],
            'indicadores' => $matriculas > 0
                ? [
                    [
                        'label' => __('Universo i-Educar (filtro)'),
                        'value' => number_format($matriculas, 0, ',', '.').' '.__('matrículas'),
                        'hint' => __('Denominador comum das estimativas'),
                    ],
                ]
                : [],
            'acoes' => [
                __('Consultar painéis FNDE «Consultas FUNDEB» para valores oficialmente distribuídos.'),
                __('Articular com contabilidade municipal a previsão orçamentária anual da educação.'),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pillars
     * @return array<string, mixed>
     */
    private static function findPillar(array $pillars, string $id): array
    {
        foreach ($pillars as $p) {
            if (is_array($p) && (string) ($p['id'] ?? '') === $id) {
                return $p;
            }
        }

        return [];
    }

    private static function yearLabel(IeducarFilterState $filters): string
    {
        if (! $filters->hasYearSelected()) {
            return __('filtro actual');
        }
        if ($filters->isAllSchoolYears()) {
            return __('todos os anos no filtro');
        }

        return __('ano letivo :y', ['y' => (string) $filters->ano_letivo]);
    }
}
