<?php

namespace App\Support\Horizonte;

/**
 * Resolve alertas oficiais MEC/FNDE por município para o modal Horizonte.
 *
 * Estados expostos ao front:
 * - found: pendência identificada (com link)
 * - clear: verificado — município não consta nas listas importadas
 * - unavailable: sem verificação (sync desactivado ou nunca executado)
 */
final class HorizonteMunicipalAlertsResolver
{
    /**
     * @param  ?array<string, mixed>  $entry
     * @param  ?array<string, mixed>  $meta
     * @return array{
     *     status: string,
     *     status_label: string,
     *     count: int,
     *     headline: string,
     *     detail_url: ?string,
     *     checked_at: ?string,
     *     sources: list<string>,
     *     items: list<array<string, mixed>>
     * }
     */
    public function resolve(?array $entry, ?array $meta): array
    {
        if (! filter_var(config('horizonte.municipal_alerts.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return $this->unavailable(__('Monitorização desactivada'));
        }

        $syncedAt = is_array($meta) ? trim((string) ($meta['synced_at'] ?? '')) : '';
        if ($syncedAt === '') {
            return $this->unavailable(__('Verificação de pendências em preparação'));
        }

        $items = is_array($entry['items'] ?? null) ? array_values($entry['items']) : [];
        if ($items === []) {
            return $this->clear($syncedAt, $meta);
        }

        usort($items, static function (array $a, array $b): int {
            $order = ['danger' => 0, 'warning' => 1, 'info' => 2];
            $sa = $order[$a['severity'] ?? 'info'] ?? 9;
            $sb = $order[$b['severity'] ?? 'info'] ?? 9;

            return $sa <=> $sb;
        });

        $primary = $items[0];
        $count = count($items);
        $headline = $count > 1
            ? __(':n pendências MEC/FNDE', ['n' => (string) $count])
            : (string) ($primary['title'] ?? __('Pendência MEC/FNDE'));

        return [
            'status' => 'found',
            'status_label' => __('Pendência encontrada'),
            'count' => $count,
            'headline' => $headline,
            'detail_url' => filled($primary['detail_url'] ?? null)
                ? (string) $primary['detail_url']
                : (string) config('horizonte.municipal_alerts.detail_urls.fnde_consultas', ''),
            'checked_at' => $syncedAt,
            'sources' => is_array($meta['sources'] ?? null) ? array_values($meta['sources']) : [],
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>
     */
    private function clear(string $syncedAt, ?array $meta): array
    {
        return [
            'status' => 'clear',
            'status_label' => __('Sem pendências'),
            'count' => 0,
            'headline' => __('Não consta nas listas importadas'),
            'detail_url' => null,
            'checked_at' => $syncedAt,
            'sources' => is_array($meta['sources'] ?? null) ? array_values($meta['sources']) : [],
            'items' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $reason): array
    {
        return [
            'status' => 'unavailable',
            'status_label' => __('Não verificado'),
            'count' => 0,
            'headline' => $reason,
            'detail_url' => null,
            'checked_at' => null,
            'sources' => [],
            'items' => [],
        ];
    }
}
