<?php

namespace App\Services\Inep;

use App\Models\City;
use Illuminate\Support\Facades\Storage;

/**
 * Gera um JSON por código IBGE em storage/app/public/saeb/municipio/{ibge}.json,
 * alinhado com GET /api/saeb/municipio/{ibge}.
 */
final class SaebMunicipioFilesWriter
{
    /**
     * Regista ficheiros por município após gravar o historico.json agregado.
     * Remove ficheiros .json antigos nessa pasta antes de regravar (evita dados obsoletos).
     *
     * @param  array<string, mixed>  $decoded  Payload completo (meta + pontos)
     */
    public static function syncFromDecodedPayload(array $decoded): void
    {
        if (! filter_var(config('ieducar.saeb.municipio_json_files_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $pontos = $decoded['pontos'] ?? $decoded['points'] ?? [];
        if (! is_array($pontos) || $pontos === []) {
            return;
        }

        $disk = Storage::disk('public');
        $dir = 'saeb/municipio';
        if ($disk->exists($dir)) {
            foreach ($disk->files($dir) as $path) {
                if (str_ends_with(strtolower($path), '.json')) {
                    $disk->delete($path);
                }
            }
        }

        $baseMeta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
        $cityIdToIbge = self::cityIdToIbgeMap();
        $byIbge = [];

        foreach ($pontos as $p) {
            if (! is_array($p)) {
                continue;
            }
            $ibge = '';
            if (! empty($p['municipio_ibge'])) {
                $ibge = preg_replace('/\D/', '', (string) $p['municipio_ibge']);
            }
            if ($ibge === '' || strlen($ibge) !== 7) {
                $ibge = self::resolveIbgeFromCityIds($p['city_ids'] ?? null, $cityIdToIbge);
            }
            if ($ibge === '' || strlen($ibge) !== 7) {
                continue;
            }
            $byIbge[$ibge][] = $p;
        }

        foreach ($byIbge as $ibge => $pts) {
            $payload = [
                'meta' => array_merge($baseMeta, [
                    'municipio_ibge' => $ibge,
                    'endpoint' => url('/api/saeb/municipio/'.$ibge),
                ]),
                'pontos' => $pts,
            ];
            try {
                $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $disk->put($dir.'/'.$ibge.'.json', $json);
            } catch (\JsonException) {
                // Ignora um município; o historico.json principal já foi gravado.
            }
        }
    }

    /**
     * @return array<int, string> city_id => ibge (7 dígitos)
     */
    private static function cityIdToIbgeMap(): array
    {
        /** @var array<int, string> $map */
        $map = City::query()
            ->whereNotNull('ibge_municipio')
            ->pluck('ibge_municipio', 'id')
            ->map(fn ($ibge) => preg_replace('/\D/', '', (string) $ibge))
            ->filter(fn (string $ibge): bool => strlen($ibge) === 7)
            ->all();

        return $map;
    }

    /**
     * @param  array<int, mixed>|null  $cityIds
     * @param  array<int, string>  $cityIdToIbge
     */
    private static function resolveIbgeFromCityIds(?array $cityIds, array $cityIdToIbge): string
    {
        if (! is_array($cityIds) || $cityIds === []) {
            return '';
        }
        foreach ($cityIds as $cid) {
            $id = (int) $cid;
            if ($id > 0 && isset($cityIdToIbge[$id])) {
                return $cityIdToIbge[$id];
            }
        }

        return '';
    }
}
