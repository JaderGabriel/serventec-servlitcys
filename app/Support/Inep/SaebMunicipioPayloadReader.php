<?php

namespace App\Support\Inep;

use App\Models\City;
use Illuminate\Support\Facades\Storage;

/**
 * Mesma lógica que GET /api/saeb/municipio/{ibge}: ficheiro por município ou corte do historico.json.
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

        $rel = 'saeb/municipio/'.$ibge.'.json';
        $disk = Storage::disk('public');
        if ($disk->exists($rel)) {
            $raw = $disk->get($rel);
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return self::buildFromHistoricoJson($ibge);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buildFromHistoricoJson(string $ibge): ?array
    {
        $rel = trim((string) config('ieducar.saeb.json_path', 'saeb/historico.json'));
        if ($rel === '') {
            return null;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($rel)) {
            return null;
        }
        $decoded = json_decode((string) $disk->get($rel), true);
        if (! is_array($decoded)) {
            return null;
        }
        $pontos = $decoded['pontos'] ?? $decoded['points'] ?? [];
        if (! is_array($pontos)) {
            return null;
        }

        $cityIds = City::query()
            ->where('ibge_municipio', $ibge)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $out = [];
        foreach ($pontos as $p) {
            if (! is_array($p)) {
                continue;
            }
            $pIbge = isset($p['municipio_ibge']) ? preg_replace('/\D/', '', (string) $p['municipio_ibge']) : '';
            if ($pIbge === $ibge) {
                $out[] = $p;

                continue;
            }
            $ids = $p['city_ids'] ?? null;
            if (is_array($ids) && $cityIds !== []) {
                $ids = array_map(static fn ($x) => (int) $x, $ids);
                foreach ($cityIds as $cid) {
                    if (in_array($cid, $ids, true)) {
                        $out[] = $p;
                        break;
                    }
                }
            }
        }

        if ($out === []) {
            return null;
        }

        $meta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];

        return [
            'meta' => array_merge($meta, [
                'municipio_ibge' => $ibge,
                'endpoint' => url('/api/saeb/municipio/'.$ibge),
            ]),
            'pontos' => array_values($out),
        ];
    }
}
