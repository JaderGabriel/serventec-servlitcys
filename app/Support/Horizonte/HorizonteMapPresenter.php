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
}
