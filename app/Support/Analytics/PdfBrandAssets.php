<?php

namespace App\Support\Analytics;

final class PdfBrandAssets
{
    /**
     * @param  array<string, mixed>  $brand
     * @return array<string, mixed>
     */
    public static function enrich(array $brand): array
    {
        $iconRel = trim((string) ($brand['icon_path'] ?? 'favicon.svg'), '/');
        $iconPath = public_path($iconRel);

        $serventecUrl = trim((string) ($brand['serventec_url'] ?? ''));
        if ($serventecUrl === '') {
            $serventecUrl = 'https://analise.serventecassessoria.com.br';
        }

        return array_merge($brand, [
            'system_name' => trim((string) ($brand['system_name'] ?? config('app.name', 'SERVLITCYS'))),
            'system_tagline' => trim((string) ($brand['system_tagline'] ?? __('Consultoria, gráficos e Horizonte municipal'))),
            'serventec_url' => $serventecUrl,
            'serventec_display_url' => self::displayHost($serventecUrl),
            'icon_data_uri' => self::fileToDataUri(is_readable($iconPath) ? $iconPath : null),
        ]);
    }

    public static function displayHost(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : preg_replace('#^https?://#i', '', $url) ?? $url;
    }

    public static function fileToDataUri(?string $absolutePath): ?string
    {
        if ($absolutePath === null || ! is_readable($absolutePath)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolutePath));
    }
}
