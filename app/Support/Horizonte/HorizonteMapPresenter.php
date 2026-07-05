<?php

namespace App\Support\Horizonte;

use App\Support\Ieducar\DiscrepanciesCheckCatalog;

final class HorizonteMapPresenter
{
    /**
     * @return array<string, string>
     */
    public static function tierColors(): array
    {
        return [
            'consultoria_active' => '#0d9488',
            'catalog_pending' => '#ea580c',
            'prospect_high' => '#be123c',
            'prospect_medium' => '#b45309',
            'prospect_low' => '#475569',
            'data_sparse' => '#94a3b8',
        ];
    }

    /**
     * Textos de metodologia, fórmulas e KPIs para a UI gerencial.
     *
     * @return array<string, mixed>
     */
    public static function methodologyUi(): array
    {
        $weights = config('horizonte.weights', []);
        $high = (int) config('horizonte.high_opportunity_threshold', 70);
        $medium = (int) config('horizonte.medium_opportunity_threshold', 40);
        $refYear = (int) config('horizonte.reference_year', (int) date('Y') - 1);

        $pct = static fn (string $key): int => (int) round(((float) ($weights[$key] ?? 0)) * 100);

        return [
            'reference_year' => $refYear,
            'thresholds' => [
                'high' => $high,
                'medium' => $medium,
            ],
            'disclaimer' => __('Indicadores indicativos para priorização comercial. Não substituem o Diagnóstico i-Educar, discrepâncias ou publicações oficiais FNDE/MEC.'),
            'detection_title' => __('O que é detectado'),
            'detection_intro' => __('O Horizonte cruza apenas dados públicos agregados por município (IBGE) e o catálogo SERVLITCYS. Não lê a base i-Educar de prospectos — só de municípios com Consultoria activa.'),
            'detection_sources' => [
                [
                    'label' => __('Catálogo SERVLITCYS'),
                    'feeds' => __('Consultoria activa, catálogo pendente, registo SGE (informativo).'),
                ],
                [
                    'label' => __('FUNDEB (FNDE)'),
                    'feeds' => __('Complementação VAAR/VAAT/VAAF, receita municipal — dimensão pressão financeira.'),
                ],
                [
                    'label' => __('Censo INEP'),
                    'feeds' => __('Matrículas municipais — escala e denominador da demanda social.'),
                ],
                [
                    'label' => __('SAEB / IDEB'),
                    'feeds' => __('Proficiência LP e MAT — déficit pedagógico (percentil 25 da amostra).'),
                ],
                [
                    'label' => __('CadÚnico (Misocial/CECAD)'),
                    'feeds' => __('Crianças 0–17 e % PBF — demanda social indicativa.'),
                ],
                [
                    'label' => __('IBGE SIDRA'),
                    'feeds' => __('População 4–17 (Censo demográfico) — fallback de escala quando falta Censo escolar.'),
                ],
                [
                    'label' => __('Tesouro Transparente (CKAN)'),
                    'feeds' => __('Repasses federais agregados — dependência de transferências (FUNDEB + educação).'),
                ],
                [
                    'label' => __('SICONFI / API Contas (Tesouro)'),
                    'feeds' => __('RREO municipal — despesa educação/receita, mínimo constitucional, dívida/caixa, restos a pagar e captação própria.'),
                ],
                [
                    'label' => __('Portal da Transparência'),
                    'feeds' => __('Convénios MEC/FNDE, empenhos educação/tecnologia e contratos software (proxy SGE concorrente).'),
                ],
                [
                    'label' => __('IBGE PNAD Contínua'),
                    'feeds' => __('Escolaridade média e NEET jovem — argumento para EJA e expansão de oferta.'),
                ],
                [
                    'label' => __('Alertas MEC/FNDE (VAAT)'),
                    'feeds' => __('Lista oficial FNDE de municípios inabilitados ao VAAT — chip no modal municipal (importação periódica).'),
                ],
            ],
            'scenarios_title' => __('Cenários no mapa'),
            'scenarios_intro' => __('Cada município recebe um tier (cor) e scores 0–100. Prospectos usam limiares configuráveis; clientes e catálogo têm regras próprias.'),
            'tier_scenarios' => [
                [
                    'tier' => 'consultoria_active',
                    'label' => __('Consultoria activa'),
                    'when' => __('Município no catálogo com base i-Educar configurada.'),
                    'effect' => __('Propensão fixa pela prontidão de dados (20–100); benefício = 0 — já é cliente.'),
                ],
                [
                    'tier' => 'catalog_pending',
                    'label' => __('Catálogo · pendente'),
                    'when' => __('Cadastrado no SERVLITCYS, sem conexão i-Educar pronta.'),
                    'effect' => __('Scores limitados (propensão ≤ 85) — priorizar activação da base, não prospecção fria.'),
                ],
                [
                    'tier' => 'prospect_high',
                    'label' => __('Alta propensão'),
                    'when' => __('Prospecto com propensão ≥ :high e pelo menos uma fonte pública (FUNDEB, Censo, SAEB ou CadÚnico).', ['high' => $high]),
                    'effect' => __('Combinação elevada de pressão FUNDEB, déficit SAEB, escala e/ou demanda social — candidato prioritário.'),
                ],
                [
                    'tier' => 'prospect_medium',
                    'label' => __('Média propensão'),
                    'when' => __('Propensão entre :medium e :high−1.', ['medium' => $medium, 'high' => $high]),
                    'effect' => __('Sinais moderados — útil com filtros de UF, matrículas ou repasses.'),
                ],
                [
                    'tier' => 'prospect_low',
                    'label' => __('Baixa propensão'),
                    'when' => __('Propensão abaixo de :medium com dados públicos parciais.', ['medium' => $medium]),
                    'effect' => __('Pressão ou escala fraca; pode ainda ter valor se filtros comerciais forem apertados.'),
                ],
                [
                    'tier' => 'data_sparse',
                    'label' => __('Sem dados públicos'),
                    'when' => __('Sem FUNDEB, Censo, SAEB nem CadÚnico importados para o IBGE.'),
                    'effect' => __('Scores zerados — importar no hub Dados públicos antes de priorizar.'),
                ],
            ],
            'success_title' => __('Propensão a sucesso (0–100)'),
            'success_formula' => __('Σ (peso × dimensão): financeira :wf% + pedagógica :wp% + escala :ws% + demanda social :wd% + transferências :wt% + capacidade fiscal :wfc% + dinâmica matrículas :wm% + prontidão :wr% + benefício×escala :wb%.', [
                'wf' => $pct('financial_pressure'),
                'wp' => $pct('pedagogical_gap'),
                'ws' => $pct('scale'),
                'wd' => $pct('social_demand'),
                'wt' => $pct('transfer_dependency'),
                'wfc' => $pct('fiscal_capacity'),
                'wm' => $pct('enrollment_momentum'),
                'wr' => $pct('data_readiness'),
                'wb' => $pct('benefit_scale'),
            ]),
            'benefit_title' => __('Benefício territorial (0–100)'),
            'benefit_formula' => __('24% pedagógico + 22% financeiro + 16% social + 12% escala + 10% dinâmica matrículas + 8% (100−cap. fiscal) + 8% transferências.'),
            'tier_rules' => __('Alta propensão ≥ :high · Média ≥ :medium · Baixa abaixo de :medium.', [
                'high' => $high,
                'medium' => $medium,
            ]),
            'dimensions' => [
                [
                    'key' => 'financial_pressure',
                    'label' => __('Pressão financeira'),
                    'weight' => $pct('financial_pressure'),
                    'formula' => __('Complementação FUNDEB ÷ receita vs mediana nacional; fallback por complementação / matrícula.'),
                    'detects' => __('Complementação total FUNDEB, receita municipal e razão compl./receita da amostra actual.'),
                    'indicates' => __('Quanto maior, mais o município depende de complementação FUNDEB (VAAR/VAAT) face à receita; quanto menor, menor pressão financeira relativa.'),
                    'scenarios' => [
                        __('Alto (≥70): complementação representa parcela elevada da receita vs mediana — município depende do VAAR/VAAT.'),
                        __('Médio: compl./matrícula elevada sem receita declarada.'),
                        __('Baixo/zero: sem FUNDEB importado ou complementação nula.'),
                    ],
                ],
                [
                    'key' => 'pedagogical_gap',
                    'label' => __('Déficit pedagógico'),
                    'weight' => $pct('pedagogical_gap'),
                    'formula' => __('SAEB LP/MAT abaixo do percentil 25 da amostra (P25 calculado na geração).'),
                    'detects' => __('Média LP+MAT do município comparada ao P25 de todos os municípios com SAEB na geração.'),
                    'indicates' => __('Quanto maior, pior o desempenho SAEB relativo à amostra (mais déficit pedagógico); quanto menor, proficiência mais próxima ou acima do P25.'),
                    'scenarios' => [
                        __('Alto: proficiência abaixo do P25 — déficit relativo à amostra nacional/regional carregada.'),
                        __('Médio (35): sem SAEB, assume lacuna moderada.'),
                        __('Baixo: desempenho acima ou próximo do P25.'),
                    ],
                ],
                [
                    'key' => 'scale_score',
                    'label' => __('Escala'),
                    'weight' => $pct('scale'),
                    'formula' => __('log₁₀(matriculas Censo); fallback população 4–17 SIDRA × 0,85.'),
                    'detects' => __('Matrículas Censo INEP; se ausentes, população escolar estimada via SIDRA.'),
                    'indicates' => __('Quanto maior, mais matrículas ou população escolar — rede maior e maior potencial de impacto; quanto menor, município pequeno.'),
                    'scenarios' => [
                        __('Alto: redes grandes (log matrículas próximo de 5 → ~100).'),
                        __('Baixo: municípios pequenos ou só população SIDRA disponível.'),
                    ],
                ],
                [
                    'key' => 'social_demand',
                    'label' => __('Demanda social'),
                    'weight' => $pct('social_demand'),
                    'formula' => __('CadÚnico escolar vs Censo/SIDRA e % crianças PBF (pressão social indicativa).'),
                    'detects' => __('Razão CadÚnico/Censo (ou SIDRA), percentual de crianças no Bolsa Família.'),
                    'indicates' => __('Quanto maior, mais demanda social indicada (CadÚnico ou PBF acima do esperado); quanto menor, perfil social menos pressionado na amostra.'),
                    'scenarios' => [
                        __('Alto: muitas crianças no CadÚnico vs matrículas declaradas ou alta % PBF.'),
                        __('Baixo: CadÚnico alinhado ao Censo e baixa pressão PBF.'),
                    ],
                ],
                [
                    'key' => 'transfer_dependency',
                    'label' => __('Dependência de transferências'),
                    'weight' => $pct('transfer_dependency'),
                    'formula' => __('Repasses Tesouro ÷ max(receita, complementação FUNDEB) vs mediana nacional; refinado com % receita própria SICONFI quando disponível.'),
                    'detects' => __('Soma repasses CKAN (FUNDEB + educação) ou complementação FUNDEB; captação própria via RREO Anexo 01.'),
                    'indicates' => __('Quanto maior, mais o município depende de transferências federais versus receita própria; quanto menor, menor peso dos repasses no financiamento.'),
                    'scenarios' => [
                        __('Alto: repasses federais pesam mais que a mediana — dependência de transferências constitucionais.'),
                        __('Parcial: só FUNDEB FNDE — complementação estima dependência até importar repasses_tesouro.'),
                        __('Zero: sem FUNDEB nem repasses CKAN.'),
                    ],
                ],
                [
                    'key' => 'fiscal_capacity',
                    'label' => __('Capacidade fiscal'),
                    'weight' => $pct('fiscal_capacity'),
                    'formula' => __('Score SICONFI: liquidez (caixa/dívida), restos a pagar e % educação/receita — invertido na propensão (100−score).'),
                    'detects' => __('RREO Anexos 01, 02, 06 e 14 — despesa educação, mínimo constitucional, dívida consolidada e disponibilidade de caixa.'),
                    'indicates' => __('Quanto maior o score, melhor capacidade de pagar consultoria e i-Educar; na propensão, baixa capacidade aumenta prioridade.'),
                    'scenarios' => [
                        __('Alto: caixa confortável, baixa dívida relativa — menor urgência financeira.'),
                        __('Baixo: restos a pagar elevados ou liquidez fraca — pressão operacional.'),
                    ],
                ],
                [
                    'key' => 'learning_trajectory',
                    'label' => __('Trajectória SAEB'),
                    'weight' => 0,
                    'formula' => __('Tendência LP/MAT nos últimos 3–4 ciclos SAEB (↑↓) — compõe 35% do déficit pedagógico.'),
                    'detects' => __('Série histórica SAEB municipal — delta entre ciclo mais recente e mais antigo da janela.'),
                    'indicates' => __('Queda recente aumenta prioridade pedagógica; recuperação indica momentum positivo.'),
                    'scenarios' => [
                        __('Queda: proficiência em deterioração — argumento para intervenção pedagógica.'),
                        __('Estável/recuperação: menos urgência relativa na dimensão pedagógica.'),
                    ],
                ],
                [
                    'key' => 'enrollment_momentum',
                    'label' => __('Dinâmica de matrículas'),
                    'weight' => $pct('enrollment_momentum'),
                    'formula' => __('Variação % matrículas Censo entre os dois pontos mais recentes da série Educacenso.'),
                    'detects' => __('Série Educacenso municipal — crescimento ou retração da rede.'),
                    'indicates' => __('Quanto maior, expansão recente da rede — oportunidade de escala e onboarding.'),
                    'scenarios' => [
                        __('Alto: crescimento acelerado de matrículas.'),
                        __('Baixo: rede estável ou em retração.'),
                    ],
                ],
                [
                    'key' => 'inclusion_gap',
                    'label' => __('Lacuna de inclusão'),
                    'weight' => 0,
                    'formula' => __('Estimativa crianças 0–17 CadÚnico/SIDRA fora da escola — compõe 25% da demanda social.'),
                    'detects' => __('Cruzamento CadÚnico escolar vs Censo/SIDRA 4–17.'),
                    'indicates' => __('Quanto maior, mais crianças potencialmente fora da escola — argumento EJA/inclusão.'),
                    'scenarios' => [
                        __('Alto: gap CadÚnico−matrículas elevado.'),
                        __('Baixo: cobertura escolar alinhada ao universo CadÚnico.'),
                    ],
                ],
                [
                    'key' => 'data_readiness',
                    'label' => __('Prontidão de dados'),
                    'weight' => $pct('data_readiness'),
                    'formula' => __('Presença FUNDEB + Censo + SAEB + CadÚnico (+ bónus SIDRA/repasses/SICONFI/transparência/PNAD).'),
                    'detects' => __('Contagem de fontes disponíveis por IBGE — não mede qualidade do cadastro escolar.'),
                    'indicates' => __('Quanto maior, mais fontes públicas disponíveis e score de propensão mais confiável; quanto menor, dados parciais ou ausentes.'),
                    'scenarios' => [
                        __('Alto (≥75): triad FUNDEB+Censo+SAEB + CadÚnico e bónus demografia/repasses.'),
                        __('Baixo: só uma ou duas fontes — score de propensão menos fiável.'),
                    ],
                ],
                [
                    'key' => 'benefit_scale',
                    'label' => __('Benefício × escala'),
                    'weight' => $pct('benefit_scale'),
                    'formula' => __('Interacção entre escala (matrículas/pop.) e pressão financeira FUNDEB.'),
                    'detects' => __('min(escala, pressão financeira) — prioriza municípios grandes com pressão FUNDEB.'),
                    'scenarios' => [
                        __('Alto: rede média/grande com complementação elevada — impacto regional potencial.'),
                        __('Baixo: município pequeno ou pressão financeira fraca.'),
                    ],
                ],
            ],
            'outside_formula_title' => __('Discrepâncias i-Educar fora desta fórmula'),
            'outside_formula_intro' => __('Após activar Consultoria, o Diagnóstico e a aba Discrepâncias analisam o cadastro escolar real. Nenhuma destas rotinas entra no score Horizonte de prospectos:'),
            'discrepancy_groups' => self::discrepancyGroupsOutsideFormula(),
            'outside_formula_footer' => __('Também ficam de fora: compliance_score do Diagnóstico, perda/ganho estimado por rotina (VAAF × peso), condicionalidades VAAR no Simec, programas PNAE/PNATE/PDDE e cruzamento Censo exportado vs matrículas activas.'),
            'map_guide' => [
                [
                    'step' => 1,
                    'title' => __('Visão nacional'),
                    'text' => __('Coroplético IBGE por UF com capitais — passe o mouse para ver KPIs agregados; clique num estado ou use «Recorte» para aprofundar.'),
                ],
                [
                    'step' => 2,
                    'title' => __('Mesorregiões (UF extensa)'),
                    'text' => __('Estados com muitos municípios abrem malha por mesorregião IBGE — hover para dados, clique para municípios da região; «Regiões» volta ao mapa estadual.'),
                ],
                [
                    'step' => 3,
                    'title' => __('Detalhe municipal'),
                    'text' => __('Pontos por pressão FUNDEB (Pontos ou Calor) ou contornos municipais IBGE (Contornos). «Resumo UF» centra o estado ou a mesorregião activa e abre KPIs estaduais.'),
                ],
                [
                    'step' => 4,
                    'title' => __('Filtros laterais'),
                    'text' => __('Lentes de decisão (alta pressão, prospectos…) e refinamento de scores — activos com UF aberta; no telemóvel use «Filtros» no mapa.'),
                ],
                [
                    'step' => 5,
                    'title' => __('Ficha do município'),
                    'text' => __('Clique no ponto ou na lista — layout horizontal, timeline FUNDEB em 3 colunas, glossário «Detecta / Indica» por dimensão, alertas MEC/FNDE VAAT e registo SGE.'),
                ],
            ],
            'map_legend_notes' => [
                [
                    'key' => 'approx',
                    'label' => __('Borda tracejada laranja'),
                    'description' => __('Coordenada aproximada — posição indicativa na UF.'),
                ],
                [
                    'key' => 'sparse',
                    'label' => __('Cinza'),
                    'description' => __('Sem FUNDEB/Censo/SAEB — clicável para ver o que falta importar.'),
                ],
            ],
            'kpis' => [
                [
                    'key' => 'with_public_data',
                    'label' => __('Com dados públicos'),
                    'hint' => __('Municípios com FUNDEB, Censo ou SAEB importados.'),
                ],
                [
                    'key' => 'prospect_count',
                    'label' => __('Prospectos'),
                    'hint' => __('Sem Consultoria activa — candidatos à expansão comercial.'),
                ],
                [
                    'key' => 'high_prospect',
                    'label' => __('Alta propensão'),
                    'hint' => __('Propensão ≥ :high — prioridade de abordagem.', ['high' => $high]),
                ],
                [
                    'key' => 'consultoria_active',
                    'label' => __('Consultoria activa'),
                    'hint' => __('Base i-Educar configurada no catálogo SERVLITCYS.'),
                ],
                [
                    'key' => 'prospect_matriculas',
                    'label' => __('Matrículas prospecto'),
                    'hint' => __('Soma Censo ref. :ano em municípios prospecto.', ['ano' => $refYear]),
                ],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, color: string}>
     */
    public static function legendItems(): array
    {
        $colors = self::tierColors();

        return [
            [
                'key' => 'consultoria_active',
                'label' => __('Consultoria activa'),
                'description' => __('Município no catálogo com base i-Educar configurada.'),
                'color' => $colors['consultoria_active'],
            ],
            [
                'key' => 'catalog_pending',
                'label' => __('Catálogo · pendente'),
                'description' => __('Cadastrado no SERVLITCYS mas sem conexão i-Educar pronta.'),
                'color' => $colors['catalog_pending'],
            ],
            [
                'key' => 'prospect_high',
                'label' => __('Alta propensão'),
                'description' => __('Déficits públicos elevados e escala favorável à implementação.'),
                'color' => $colors['prospect_high'],
            ],
            [
                'key' => 'prospect_medium',
                'label' => __('Média propensão'),
                'description' => __('Oportunidade moderada com base em FUNDEB, Censo ou SAEB.'),
                'color' => $colors['prospect_medium'],
            ],
            [
                'key' => 'prospect_low',
                'label' => __('Baixa propensão'),
                'description' => __('Sinais fracos ou dados incompletos para priorização.'),
                'color' => $colors['prospect_low'],
            ],
            [
                'key' => 'data_sparse',
                'label' => __('Sem dados públicos'),
                'description' => __('Importe fontes no hub Dados públicos para enriquecer o score.'),
                'color' => $colors['data_sparse'],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, color: string}>
     */
    /**
     * Metadados de abastecimento / CLI para a UI quando o mapa está vazio ou incompleto.
     *
     * @param  array<string, mixed>  $coverage
     * @return array{
     *     marker_count: int,
     *     needs_refresh: bool,
     *     refresh_command: string,
     *     refresh_dry_run_command: string,
     *     hub_url: string,
     *     message: string|null
     * }
     */
    public static function refreshMeta(int $markerCount, array $coverage): array
    {
        $withPublic = (int) ($coverage['with_public_data'] ?? 0);
        $needsRefresh = $markerCount === 0 || $withPublic === 0;

        $message = match (true) {
            $markerCount === 0 => __('Nenhum município posicionado — importe dados públicos nacionais ou cadastre cidades com código IBGE.'),
            $withPublic === 0 => __('Só municípios do catálogo local — importe FUNDEB, Censo ou SAEB para prospectos nacionais e scores completos.'),
            default => null,
        };

        return [
            'marker_count' => $markerCount,
            'needs_refresh' => $needsRefresh,
            'refresh_command' => 'php artisan horizonte:fortnightly-feed',
            'refresh_dry_run_command' => 'php artisan horizonte:fortnightly-feed --dry-run',
            'hub_url' => route('admin.horizonte-import.index'),
            'message' => $message,
        ];
    }

    /**
     * Política de vista inicial e limite de renderização para bases nacionais grandes.
     *
     * @param  list<array<string, mixed>>  $ufRankings
     * @return array{
     *     heavy_dataset: bool,
     *     marker_count_total: int,
     *     max_render_markers: int,
     *     heavy_threshold: int,
     *     initial_tier: string,
     *     initial_uf: string,
     *     reason: string|null
     * }
     */
    public static function displayPolicy(int $markerCount, array $ufRankings): array
    {
        $threshold = max(100, (int) config('horizonte.map_display.heavy_threshold', 800));
        $maxRender = max(80, min(800, (int) config('horizonte.map_display.max_render_markers', 400)));
        $heavy = $markerCount > $threshold;

        $initialUf = '';
        if ($heavy && $ufRankings !== []) {
            $ranked = $ufRankings;
            usort($ranked, static fn (array $a, array $b): int => ($b['high_pressure'] ?? 0) <=> ($a['high_pressure'] ?? 0)
                ?: ($b['high_prospect'] ?? 0) <=> ($a['high_prospect'] ?? 0)
                ?: ($b['without_consultoria'] ?? 0) <=> ($a['without_consultoria'] ?? 0)
                ?: ($b['avg_benefit'] ?? 0) <=> ($a['avg_benefit'] ?? 0));
            $initialUf = strtoupper(trim((string) ($ranked[0]['uf'] ?? '')));
        }

        $reason = null;
        if ($heavy) {
            $formatted = number_format($markerCount, 0, ',', '.');
            $reason = $initialUf !== ''
                ? __('Base nacional com :total municípios — clique num estado ou abra :uf para ver só municípios de alta pressão FUNDEB.', [
                    'total' => $formatted,
                    'uf' => $initialUf,
                ])
                : __('Base nacional com :total municípios — selecione uma UF para a camada de alta pressão.', [
                    'total' => $formatted,
                ]);
        }

        return [
            'heavy_dataset' => $heavy,
            'marker_count_total' => $markerCount,
            'max_render_markers' => $maxRender,
            'heavy_threshold' => $threshold,
            'initial_tier' => 'high_pressure',
            'initial_uf' => $initialUf,
            'require_uf_selection' => $heavy,
            'initial_mode' => $heavy ? 'overview' : 'regional',
            'reason' => $reason,
            'default_filter' => self::defaultViewFilter(),
        ];
    }

    /**
     * Política de renderização para recorte regional (UF com muitos municípios).
     *
     * @return array{
     *     marker_count: int,
     *     max_render_markers: int,
     *     prefer_map_view: string,
     *     heavy_regional: bool,
     *     allow_show_all: bool,
     *     reason: string|null
     * }
     */
    public static function regionalDisplayPolicy(int $markerCount): array
    {
        $cfg = config('horizonte.map_display', []);
        $mediumAt = max(80, (int) ($cfg['regional_medium_threshold'] ?? 150));
        $heavyAt = max($mediumAt + 1, (int) ($cfg['regional_heavy_threshold'] ?? 300));
        $heatMax = max(100, (int) ($cfg['regional_heat_max'] ?? 150));
        $defaultMax = max(80, min(800, (int) ($cfg['max_render_markers'] ?? 400)));

        $maxRender = $defaultMax;
        $preferView = 'heat';
        $heavyRegional = $markerCount >= $heavyAt;
        $allowShowAll = ! $heavyRegional;
        $reason = null;

        if ($markerCount >= $heavyAt) {
            $maxRender = max(60, min(400, (int) ($cfg['regional_max_render_heavy'] ?? 120)));
            $preferView = 'markers';
            $reason = __('UF com :total municípios — mapa limitado aos :limit de maior propensão; use «Desenhar todos» no mapa para o recorte completo.', [
                'total' => number_format($markerCount, 0, ',', '.'),
                'limit' => number_format($maxRender, 0, ',', '.'),
            ]);
        } elseif ($markerCount >= $mediumAt) {
            $maxRender = max(80, min(500, (int) ($cfg['regional_max_render_medium'] ?? 180)));
            if ($markerCount > $heatMax) {
                $preferView = 'markers';
            }
            $reason = __('UF extensa (:total municípios) — renderização optimizada com clusters.', [
                'total' => number_format($markerCount, 0, ',', '.'),
            ]);
        }

        return [
            'marker_count' => $markerCount,
            'max_render_markers' => $maxRender,
            'prefer_map_view' => $preferView,
            'heavy_regional' => $heavyRegional,
            'allow_show_all' => $allowShowAll,
            'heat_max' => $heatMax,
            'reason' => $reason,
        ];
    }

    /**
     * Filtros iniciais da vista GIS/BI (alta pressão por defeito).
     *
     * @return array<string, mixed>
     */
    public static function defaultViewFilter(): array
    {
        $pressureMin = max(0, min(100, (int) config('horizonte.map_display.financial_pressure_min', 60)));

        return [
            'preset' => (string) config('horizonte.map_display.default_view', 'high_pressure'),
            'tier' => 'prospects',
            'min_financial' => $pressureMin,
            'hide_consultoria' => true,
            'require_fundeb' => true,
            'map_view' => 'heat',
            'hide_approximate_on_map' => filter_var(
                config('horizonte.map_display.hide_approximate_on_map', true),
                FILTER_VALIDATE_BOOL,
            ),
            'pressure_min' => $pressureMin,
            'label' => __('Alta pressão FUNDEB'),
            'description' => __('Prospectos fora da consultoria com pressão financeira ≥ :min ou propensão alta — camada inicial para decisão comercial.', [
                'min' => $pressureMin,
            ]),
        ];
    }

    public static function heatLegendItems(): array
    {
        return [
            [
                'key' => 'heat_low',
                'label' => __('Pressão baixa'),
                'description' => __('Menor pressão FUNDEB entre os municípios do recorte visível.'),
                'color' => '#fef3c7',
            ],
            [
                'key' => 'heat_mid',
                'label' => __('Pressão média'),
                'description' => __('Pressão intermédia no recorte — oportunidade moderada.'),
                'color' => '#d97706',
            ],
            [
                'key' => 'heat_high',
                'label' => __('Pressão alta'),
                'description' => __('Maior pressão FUNDEB no recorte — prioridade comercial.'),
                'color' => '#be123c',
            ],
        ];
    }

    /**
     * Rotinas de discrepância i-Educar agrupadas para a metodologia (não entram no score Horizonte).
     *
     * @return list<array{label: string, items: list<array{id: string, title: string}>}>
     */
    private static function discrepancyGroupsOutsideFormula(): array
    {
        $groups = [
            [
                'label' => __('Cadastro do aluno'),
                'ids' => ['sem_raca', 'sem_sexo', 'sem_data_nascimento', 'matricula_duplicada', 'matricula_situacao_invalida', 'distorcao_idade_serie'],
            ],
            [
                'label' => __('Inclusão e educação especial (NEE)'),
                'ids' => ['nee_sem_aee', 'aee_sem_nee', 'nee_subnotificacao', 'recurso_prova_sem_nee', 'nee_sem_recurso_prova', 'recurso_prova_incompativel'],
            ],
            [
                'label' => __('Escola e território'),
                'ids' => ['escola_sem_inep', 'escola_inativa_matricula', 'escola_sem_geo'],
            ],
            [
                'label' => __('Censo vs i-Educar'),
                'ids' => ['matricula_censo_vs_ieducar'],
            ],
        ];

        $defs = DiscrepanciesCheckCatalog::definitions();
        $out = [];

        foreach ($groups as $group) {
            $items = [];
            foreach ($group['ids'] as $id) {
                $def = $defs[$id] ?? null;
                if (! is_array($def)) {
                    continue;
                }
                $items[] = [
                    'id' => $id,
                    'title' => (string) ($def['title'] ?? $id),
                ];
            }
            if ($items !== []) {
                $out[] = [
                    'label' => $group['label'],
                    'items' => $items,
                ];
            }
        }

        return $out;
    }
}
