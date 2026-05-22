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
                'label' => __('Ativo · base configurada'),
                'description' => __('Município ativo com conexão i-Educar (host, base e usuário).'),
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
