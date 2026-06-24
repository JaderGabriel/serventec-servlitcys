<?php

namespace App\Support\Admin;

use App\Support\Product\ProductReleaseTag;

/**
 * Catálogo de entregas escalonadas por mês e releases associadas.
 */
final class DocumentationEscalonadasCatalog
{
    /**
     * @return list<array{id: string, label: string, path: string, hint: string, version_range: string}>
     */
    public static function monthlyDocuments(): array
    {
        return [
            [
                'id' => '202606',
                'label' => __('Junho/2026'),
                'path' => 'docs/ENTREGAS_ESCALONADAS_JUNHO_2026.md',
                'version_range' => '3.5.0 → 4.4.2',
                'hint' => '3.5.0 → 4.4.2',
            ],
            [
                'id' => '202605',
                'label' => __('Maio/2026 (arquivo)'),
                'path' => 'docs/ENTREGAS_ESCALONADAS_MAIO_2026.md',
                'version_range' => '2.3.6 → 3.4.0',
                'hint' => '2.3.6 → 3.4.0',
            ],
        ];
    }

    public static function indexPath(): string
    {
        return 'docs/ENTREGAS_ESCALONADAS.md';
    }

    public static function isEscalonadasPath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));

        return str_contains($path, 'ENTREGAS_ESCALONADAS');
    }

    /**
     * @return list<string>
     */
    public static function listedPaths(): array
    {
        $paths = [self::indexPath()];
        foreach (self::monthlyDocuments() as $month) {
            $paths[] = $month['path'];
        }

        return $paths;
    }

    /**
     * Releases cujo prefixo de data (YYYYMMDD) pertence ao mês YYYYMM.
     *
     * @return list<array{label: string, path: string, hint?: string, sort_key: string, version: string, tag: string}>
     */
    public static function releasesForMonth(string $monthId): array
    {
        $monthId = preg_replace('/\D/', '', $monthId) ?? '';
        if (strlen($monthId) !== 6) {
            return [];
        }

        $out = [];
        foreach (DocumentationCatalog::discoverReleaseEntries() as $entry) {
            $sortKey = (string) ($entry['sort_key'] ?? '');
            if (! str_starts_with($sortKey, $monthId)) {
                continue;
            }

            $meta = self::releaseMetaFromPath($entry['path']);
            $out[] = array_merge($entry, [
                'version' => $meta['version'] ?? '',
                'tag' => $meta['tag'] ?? '',
            ]);
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['sort_key'], $b['sort_key']));

        return $out;
    }

    /**
     * @return array{tag: string, version: string}
     */
    public static function releaseMetaFromPath(string $path): array
    {
        $resolved = DocumentationCatalog::resolveReadablePath($path);
        if ($resolved === null) {
            return ['tag' => '', 'version' => ''];
        }

        $absolute = base_path($resolved);
        if (! is_readable($absolute)) {
            return ['tag' => '', 'version' => ''];
        }

        $head = (string) file_get_contents($absolute, false, null, 0, 512);
        $tag = '';
        $version = '';

        if (preg_match('/Release `([^`]+)`/i', $head, $matches)) {
            $tag = trim($matches[1]);
        }

        if (preg_match('/— ServLitcys\s+([\d.]+(?:[a-z])?)/iu', $head, $matches)) {
            $version = trim($matches[1]);
        }

        if ($tag === '') {
            $basename = basename($resolved, '.md');
            $parsed = ProductReleaseTag::parseDocBasename($basename);
            if ($parsed !== null) {
                $suffix = $parsed['suffix'] !== '' ? $parsed['suffix'] : '';
                $tag = $parsed['date'].$suffix.'-'.str_replace(' ', '-', $parsed['codename']);
            }
        }

        return ['tag' => $tag, 'version' => $version];
    }

    /**
     * Secção do menu lateral do leitor de documentação.
     *
     * @return array<string, mixed>
     */
    public static function menuSection(): array
    {
        $monthItems = [];
        foreach (self::monthlyDocuments() as $month) {
            $releaseCount = count(self::releasesForMonth($month['id']));
            $hint = $month['hint'];
            if ($releaseCount > 0) {
                $hint .= ' · '.$releaseCount.' '.__('releases');
            }

            $monthItems[] = [
                'label' => $month['label'],
                'path' => $month['path'],
                'hint' => $hint,
            ];
        }

        return [
            'key' => 'escalonadas',
            'title' => __('Entregas escalonadas'),
            'description' => __('Cronologia mensal de entregas com referências às releases.'),
            'audience' => DocumentationCatalog::AUDIENCE_ALL,
            'items' => [
                [
                    'label' => __('Índice — entregas'),
                    'path' => self::indexPath(),
                    'hint' => __('Por mês e release'),
                ],
            ],
            'submenus' => [[
                'title' => __('Por mês'),
                'items' => $monthItems,
            ]],
        ];
    }
}
