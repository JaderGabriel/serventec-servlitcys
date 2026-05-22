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
        'ready' => '#10b981',
        'incomplete' => '#f59e0b',
        'inactive_setup' => '#64748b',
        'inactive' => '#94a3b8',
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
                'label' => __('Activo · base configurada'),
                'description' => __('Município activo com ligação i-Educar (host, base e utilizador).'),
            ],
            'incomplete' => [
                'label' => __('Activo · credenciais incompletas'),
                'description' => __('Marcado activo, mas falta configurar a base de dados municipal.'),
            ],
            'inactive_setup' => [
                'label' => __('Inactivo · base configurada'),
                'description' => __('Credenciais gravadas; município desactivado no cadastro.'),
            ],
            'inactive' => [
                'label' => __('Inactivo'),
                'description' => __('Sem ligação i-Educar completa ou município desactivado.'),
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
