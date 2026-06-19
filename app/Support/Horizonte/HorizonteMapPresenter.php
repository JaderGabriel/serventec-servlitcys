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
