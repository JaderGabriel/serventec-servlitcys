<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Resolve o caminho absoluto do CSV de fallback INEP (coordenadas offline).
 *
 * Padrão Laravel: ficheiro no disco public (`storage/app/public`), exposto via
 * `php artisan storage:link` como `public/storage/...` quando necessário.
 *
 * Compatibilidade: valores antigos `app/...` (relativos a `storage/`) continuam a funcionar.
 */
final class InepGeoFallbackCsvPath
{
    public static function absolute(?string $configured): string
    {
        $configured = trim((string) $configured);
        if ($configured === '') {
            $configured = 'inep_geo_fallback.csv';
        }
        if (str_starts_with($configured, '/')) {
            return $configured;
        }
        // Legado: caminho sob storage/ (ex.: app/inep_geo_fallback.csv antes do disco public)
        if (str_starts_with($configured, 'app/') && ! str_starts_with($configured, 'app/public/')) {
            return storage_path($configured);
        }
        if (str_starts_with($configured, 'app/public/')) {
            $relative = substr($configured, strlen('app/public/'));

            return Storage::disk('public')->path($relative);
        }

        return Storage::disk('public')->path($configured);
    }
}
