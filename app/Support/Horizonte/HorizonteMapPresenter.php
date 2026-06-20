<?php

namespace App\Support\Horizonte;

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
            'success_title' => __('Propensão a sucesso (0–100)'),
            'success_formula' => __('Σ (peso × dimensão): financeira :wf% + pedagógica :wp% + escala :ws% + demanda social :wd% + transferências :wt% + prontidão :wr% + benefício×escala :wb%.', [
                'wf' => $pct('financial_pressure'),
                'wp' => $pct('pedagogical_gap'),
                'ws' => $pct('scale'),
                'wd' => $pct('social_demand'),
                'wt' => $pct('transfer_dependency'),
                'wr' => $pct('data_readiness'),
                'wb' => $pct('benefit_scale'),
            ]),
            'benefit_title' => __('Benefício territorial (0–100)'),
            'benefit_formula' => __('28% pedagógico + 28% financeiro + 18% demanda social + 14% escala + 7% transferências + 5% prontidão — enfatiza impacto regional.'),
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
                ],
                [
                    'key' => 'pedagogical_gap',
                    'label' => __('Déficit pedagógico'),
                    'weight' => $pct('pedagogical_gap'),
                    'formula' => __('SAEB LP/MAT abaixo do percentil 25 da amostra (P25 calculado na geração).'),
                ],
                [
                    'key' => 'scale_score',
                    'label' => __('Escala'),
                    'weight' => $pct('scale'),
                    'formula' => __('log₁₀(matriculas Censo); fallback população 4–17 SIDRA × 0,85.'),
                ],
                [
                    'key' => 'social_demand',
                    'label' => __('Demanda social'),
                    'weight' => $pct('social_demand'),
                    'formula' => __('CadÚnico escolar vs Censo/SIDRA e % crianças PBF (pressão social indicativa).'),
                ],
                [
                    'key' => 'transfer_dependency',
                    'label' => __('Dependência de transferências'),
                    'weight' => $pct('transfer_dependency'),
                    'formula' => __('Repasses Tesouro ÷ max(receita, complementação FUNDEB) vs mediana nacional.'),
                ],
                [
                    'key' => 'data_readiness',
                    'label' => __('Prontidão de dados'),
                    'weight' => $pct('data_readiness'),
                    'formula' => __('Presença FUNDEB + Censo + SAEB + CadÚnico (+ bónus SIDRA/repasses).'),
                ],
                [
                    'key' => 'benefit_scale',
                    'label' => __('Benefício × escala'),
                    'weight' => $pct('benefit_scale'),
                    'formula' => __('Interacção entre escala (matrículas/pop.) e pressão financeira FUNDEB.'),
                ],
            ],
            'map_guide' => [
                [
                    'step' => 1,
                    'title' => __('Visão nacional'),
                    'text' => __('Clique num estado (bolha) para carregar municípios da UF.'),
                ],
                [
                    'step' => 2,
                    'title' => __('Detalhe municipal'),
                    'text' => __('Filtros e segmentos; todos os pontos no mapa são clicáveis, incluindo cinza (sem dados públicos).'),
                ],
                [
                    'step' => 3,
                    'title' => __('Ficha do município'),
                    'text' => __('Clique no ponto ou na lista para ver scores, dimensões e registo SGE.'),
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
            'hub_url' => route('admin.public-data.index', ['hub' => 'horizonte']),
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
            $reason = __('UF com :total municípios — mapa limitado aos :limit de maior propensão; clusters activos (sem «mostrar todos»).', [
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
                'label' => __('Baixa oportunidade'),
                'description' => __('Propensão indicativa baixa — monitorar ou enriquecer dados.'),
                'color' => '#fde68a',
            ],
            [
                'key' => 'heat_mid',
                'label' => __('Média oportunidade'),
                'description' => __('Sinais moderados de benefício com Consultoria.'),
                'color' => '#b45309',
            ],
            [
                'key' => 'heat_high',
                'label' => __('Alta oportunidade'),
                'description' => __('Prioridade comercial — déficits e escala favoráveis.'),
                'color' => '#be123c',
            ],
        ];
    }
}
