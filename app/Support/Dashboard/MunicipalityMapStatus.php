<?php

namespace App\Support\Dashboard;

/**
 * Estados visuais dos marcadores no mapa de municípios (Início).
 * Cores partilhadas entre PHP (legenda) e JS (Leaflet).
 */
final class MunicipalityMapStatus
{
    /** @var array<string, string> */
    public const COLORS = [
        'ready' => '#0284c7',
        'incomplete' => '#ea580c',
        'inactive_setup' => '#7c3aed',
        'inactive' => '#475569',
    ];

    /**
     * @return list<array{
     *     status: string,
     *     label: string,
     *     description: string,
     *     color: string,
     *     count: int
     * }>
     */
    public static function legendItems(array $byStatus): array
    {
        $definitions = [
            'ready' => [
                'label' => __('Conexão OK'),
                'description' => __('Base i-Educar configurada — a cor do pin segue o cadastro RX (linha abaixo).'),
            ],
            'incomplete' => [
                'label' => __('Ativo · credenciais incompletas'),
                'description' => __('Marcado ativo, mas falta configurar a base de dados municipal.'),
            ],
            'inactive_setup' => [
                'label' => __('Inativo · base configurada'),
                'description' => __('Credenciais gravadas; município desativado no cadastro.'),
            ],
            'inactive' => [
                'label' => __('Inativo'),
                'description' => __('Sem conexão i-Educar completa ou município desativado.'),
            ],
        ];

        $items = [];
        foreach ($definitions as $status => $meta) {
            $items[] = [
                'status' => $status,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'color' => self::COLORS[$status],
                'count' => (int) ($byStatus[$status] ?? 0),
            ];
        }

        return $items;
    }

    public static function colorFor(string $status): string
    {
        return self::COLORS[$status] ?? self::COLORS['inactive'];
    }

    /**
     * @return array<string, string>
     */
    public static function colorsForJs(): array
    {
        return self::COLORS;
    }
}
