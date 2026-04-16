<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrato JSON para integração com IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE, ex.:
 * {APP_URL}/api/saeb/municipio/{ibge}
 */
class SaebMunicipioController extends Controller
{
    /**
     * Aceita código IBGE (7 dígitos) com ou sem sufixo .json na URL.
     */
    public function show(string $code): JsonResponse
    {
        $code = trim($code);
        if (str_ends_with(strtolower($code), '.json')) {
            $code = substr($code, 0, -5);
        }
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 7) {
            return response()->json([
                'message' => __('Código IBGE inválido (esperados 7 dígitos).'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (! filter_var(config('ieducar.saeb.public_api_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'message' => __('Endpoint desactivado.'),
            ], Response::HTTP_NOT_FOUND);
        }

        $rel = 'saeb/municipio/'.$code.'.json';
        $disk = Storage::disk('public');
        if ($disk->exists($rel)) {
            $raw = $disk->get($rel);
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                return response()->json($decoded, Response::HTTP_OK, [], JSON_UNESCAPED_UNICODE);
            }
        }

        $fallback = $this->buildFromHistoricoJson($code);
        if ($fallback !== null) {
            return response()->json($fallback, Response::HTTP_OK, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'message' => __('Não há dados SAEB para o município IBGE :ibge.', ['ibge' => $code]),
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildFromHistoricoJson(string $ibge): ?array
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
