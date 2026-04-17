<?php

namespace App\Support\Inep;

use App\Services\Inep\SaebHistoricoDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * Mesma lógica que GET /api/saeb/municipio/{ibge}: base de dados (principal) ou ficheiro legado por município.
 */
final class SaebMunicipioPayloadReader
{
    /**
     * @return array<string, mixed>|null
     */
    public static function loadForIbge(string $ibge): ?array
    {
        $ibge = preg_replace('/\D/', '', $ibge);
        if (strlen($ibge) !== 7) {
            return null;
        }

        $fromDb = app(SaebHistoricoDatabase::class)->buildMunicipioPayload($ibge);
        if ($fromDb !== null) {
            return $fromDb;
        }

        return self::loadLegacyMunicipioFile($ibge);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadLegacyMunicipioFile(string $ibge): ?array
    {
        $rel = 'saeb/municipio/'.$ibge.'.json';
        $disk = Storage::disk('public');
        if (! $disk->exists($rel)) {
            return null;
        }
        $raw = $disk->get($rel);
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
