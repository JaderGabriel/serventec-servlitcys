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
            'consultoria_active' => '#059669',
            'catalog_pending' => '#ea580c',
            'prospect_high' => '#dc2626',
            'prospect_medium' => '#ca8a04',
            'prospect_low' => '#64748b',
            'data_sparse' => '#cbd5e1',
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
            usort($ranked, static fn (array $a, array $b): int => ($b['high_prospect'] ?? 0) <=> ($a['high_prospect'] ?? 0)
                ?: ($b['without_consultoria'] ?? 0) <=> ($a['without_consultoria'] ?? 0)
                ?: ($b['avg_benefit'] ?? 0) <=> ($a['avg_benefit'] ?? 0));
            $initialUf = strtoupper(trim((string) ($ranked[0]['uf'] ?? '')));
        }

        $reason = null;
        if ($heavy) {
            $formatted = number_format($markerCount, 0, ',', '.');
            $reason = $initialUf !== ''
                ? __('Base nacional com :total municípios — vista inicial na UF :uf (prospectos) para manter o mapa fluido.', [
                    'total' => $formatted,
                    'uf' => $initialUf,
                ])
                : __('Base nacional com :total municípios — vista inicial restrita a prospectos prioritários.', [
                    'total' => $formatted,
                ]);
        }

        return [
            'heavy_dataset' => $heavy,
            'marker_count_total' => $markerCount,
            'max_render_markers' => $maxRender,
            'heavy_threshold' => $threshold,
            'initial_tier' => $heavy ? 'prospects' : 'all',
            'initial_uf' => $initialUf,
            'reason' => $reason,
        ];
    }

    public static function heatLegendItems(): array
    {
        return [
            [
                'key' => 'heat_low',
                'label' => __('Baixa oportunidade'),
                'description' => __('Propensão indicativa baixa — monitorar ou enriquecer dados.'),
                'color' => '#fde047',
            ],
            [
                'key' => 'heat_mid',
                'label' => __('Média oportunidade'),
                'description' => __('Sinais moderados de benefício com Consultoria.'),
                'color' => '#f97316',
            ],
            [
                'key' => 'heat_high',
                'label' => __('Alta oportunidade'),
                'description' => __('Prioridade comercial — déficits e escala favoráveis.'),
                'color' => '#dc2626',
            ],
        ];
    }
}
