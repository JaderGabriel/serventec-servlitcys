<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Horizonte\HorizonteMunicipalSgeRegistryService;
use App\Support\Horizonte\HorizonteMunicipalSgeResolver;
use App\Support\Horizonte\HorizonteMapCacheBuster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HorizonteSgeRegistryController extends Controller
{
    public function __construct(
        private readonly HorizonteMunicipalSgeRegistryService $registry,
        private readonly HorizonteMunicipalSgeResolver $resolver,
    ) {}

    public function show(string $ibge): JsonResponse
    {
        $this->authorizeAdmin();

        $normalized = FundebMunicipioReferenceRepository::normalizeIbge($ibge);
        if ($normalized === null) {
            return response()->json(['message' => __('Código IBGE inválido.')], 422);
        }

        $entry = $this->registry->localEntry($normalized);

        return response()->json([
            'ibge' => $normalized,
            'entry' => $entry,
        ]);
    }

    public function upsert(Request $request, string $ibge): JsonResponse
    {
        $this->authorizeAdmin();

        $normalized = FundebMunicipioReferenceRepository::normalizeIbge($ibge);
        if ($normalized === null) {
            return response()->json(['message' => __('Código IBGE inválido.')], 422);
        }

        if ($this->ibgeInConsultoriaCatalog($normalized)) {
            return response()->json([
                'message' => __('Município já está no catálogo Consultoria — o SGE é gerido pela ficha da cidade, não pelo registo Horizonte.'),
            ], 422);
        }

        $validated = $request->validate([
            'system' => ['required', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'app_url' => ['nullable', 'url', 'max:500'],
        ]);

        try {
            $result = $this->registry->upsertLocalEntry(
                $ibge,
                $validated,
                (int) $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => __('Não foi possível gravar o registo SGE.')], 500);
        }

        HorizonteMapCacheBuster::bust();

        $sge = $this->resolver->resolve($result['ibge'], null, $result['entry']);

        return response()->json([
            'message' => __('Registo SGE gravado para IBGE :ibge.', ['ibge' => $result['ibge']]),
            'ibge' => $result['ibge'],
            'entry' => $result['entry'],
            'sge' => $sge,
        ]);
    }

    public function destroy(string $ibge): JsonResponse
    {
        $this->authorizeAdmin();

        if (! $this->registry->removeLocalEntry($ibge)) {
            return response()->json(['message' => __('Registo SGE não encontrado para este município.')], 404);
        }

        HorizonteMapCacheBuster::bust();

        $normalized = FundebMunicipioReferenceRepository::normalizeIbge($ibge);

        return response()->json([
            'message' => __('Registo SGE removido.'),
            'ibge' => $normalized,
            'sge' => $this->resolver->resolve((string) $normalized, null, null),
        ]);
    }

    private function authorizeAdmin(): void
    {
        $user = request()->user();
        abort_unless($user !== null && $user->canImportOrConfigure(), 403);
        abort_unless((bool) config('horizonte.enabled', true), 404);
        abort_unless(filter_var(config('horizonte.sge.enabled', true), FILTER_VALIDATE_BOOLEAN), 403);
    }

    private function ibgeInConsultoriaCatalog(string $ibge): bool
    {
        foreach (City::query()->whereNotNull('ibge_municipio')->pluck('ibge_municipio') as $raw) {
            if (FundebMunicipioReferenceRepository::normalizeIbge((string) $raw) === $ibge) {
                return true;
            }
        }

        return false;
    }
}
