<?php

namespace App\Support\Ieducar;

use App\Models\City;
use Illuminate\Support\Str;

/**
 * URL pública do i-Educar por município (mapa, atalhos).
 */
final class CityIeducarAppUrlResolver
{
    public function resolve(City $city): ?string
    {
        $fromCity = $this->normalizeUrl((string) ($city->ieducar_app_url ?? ''));
        if ($fromCity !== null) {
            return $fromCity;
        }

        $map = config('ieducar.app_urls', []);
        if (is_array($map)) {
            $key = (string) $city->getKey();
            if (isset($map[$key])) {
                $fromMap = $this->normalizeUrl((string) $map[$key]);
                if ($fromMap !== null) {
                    return $fromMap;
                }
            }
        }

        $template = trim((string) config('ieducar.app_url_template', ''));
        if ($template === '') {
            return null;
        }

        $slug = Str::slug(Str::lower(trim((string) $city->name)), '-');
        $ibge = trim((string) ($city->ibge_municipio ?? ''));
        $uf = strtoupper(trim((string) $city->uf));

        $url = str_replace(
            ['{city_id}', '{slug}', '{ibge}', '{uf}'],
            [(string) $city->getKey(), $slug, $ibge, $uf],
            $template
        );

        return $this->normalizeUrl($url);
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return rtrim($url, '/');
    }
}
