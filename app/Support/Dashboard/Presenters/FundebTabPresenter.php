<?php

namespace App\Support\Dashboard\Presenters;

/**
 * Prepara variáveis de apresentação da aba FUNDEB (evita @php na Blade).
 */
final class FundebTabPresenter
{
    /**
     * @param  array<string, mixed>  $fundebData
     * @return array{
     *     publicSources: array<string, mixed>,
     *     proj: array<string, mixed>,
     *     projAvailable: bool,
     *     distLegal: array<string, mixed>,
     *     distItens: list<mixed>,
     *     porEtapa: list<mixed>,
     *     informe: array<string, mixed>,
     *     informeBlocos: list<mixed>,
     *     moduleRing: callable(string): string,
     *     informeRing: callable(string): string,
     *     fundebMeta: ?string
     * }
     */
    public static function present(array $fundebData): array
    {
        $publicSources = is_array($fundebData['public_data_sources'] ?? null)
            ? $fundebData['public_data_sources']
            : [];
        $proj = is_array($fundebData['resource_projection'] ?? null)
            ? $fundebData['resource_projection']
            : [];
        $distLegal = is_array($proj['distribuicao_legal'] ?? null)
            ? $proj['distribuicao_legal']
            : [];
        $informe = is_array($fundebData['complementacao_informe'] ?? null)
            ? $fundebData['complementacao_informe']
            : [];

        return [
            'publicSources' => $publicSources,
            'proj' => $proj,
            'projAvailable' => (bool) ($proj['available'] ?? false),
            'distLegal' => $distLegal,
            'distItens' => is_array($distLegal['itens'] ?? null) ? $distLegal['itens'] : [],
            'porEtapa' => is_array($proj['por_etapa'] ?? null) ? $proj['por_etapa'] : [],
            'informe' => $informe,
            'informeBlocos' => is_array($informe['blocos'] ?? null) ? $informe['blocos'] : [],
            'moduleRing' => self::statusRing(...),
            'informeRing' => self::statusRing(...),
            'fundebMeta' => self::buildMetaHtml($fundebData),
        ];
    }

    private static function statusRing(string $status): string
    {
        return match ($status) {
            'success' => 'border-l-teal-500',
            'warning' => 'border-l-amber-500',
            'danger' => 'border-l-rose-500',
            default => 'border-l-slate-400',
        };
    }

    /**
     * @param  array<string, mixed>  $fundebData
     */
    private static function buildMetaHtml(array $fundebData): ?string
    {
        if (! filled($fundebData['city_name'] ?? null) && ! filled($fundebData['year_label'] ?? null)) {
            return null;
        }

        $meta = '<span class="font-medium">'.e(__('Contexto')).':</span> '
            .e($fundebData['city_name'] ?? '');

        if (filled($fundebData['year_label'] ?? null)) {
            $meta .= ' — '.e($fundebData['year_label']);
        }

        return $meta;
    }
}
