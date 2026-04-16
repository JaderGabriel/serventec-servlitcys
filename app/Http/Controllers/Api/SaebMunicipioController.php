<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Inep\SaebMunicipioPayloadReader;
use Illuminate\Http\JsonResponse;
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

        $payload = SaebMunicipioPayloadReader::loadForIbge($code);
        if ($payload !== null) {
            return response()->json($payload, Response::HTTP_OK, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'message' => __('Não há dados SAEB para o município IBGE :ibge.', ['ibge' => $code]),
        ], Response::HTTP_NOT_FOUND);
    }
}
