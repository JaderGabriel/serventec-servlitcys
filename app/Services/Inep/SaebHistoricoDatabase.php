<?php

namespace App\Services\Inep;

use App\Models\City;
use App\Models\SaebImportMeta;
use App\Models\SaebIndicatorPoint;
use App\Support\Ieducar\SaebPointsNormalizer;
use App\Support\Inep\SaebPointsDedupe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Armazenamento SAEB em PostgreSQL (substitui historico.json).
 */
final class SaebHistoricoDatabase
{
    public const META_ROW_ID = 1;

    public const STORAGE_LABEL = 'saeb_indicator_points';

    /**
     * Grava o payload completo (substitui todas as linhas).
     *
     * @param  array<string, mixed>  $decoded
     */
    public function persistFullPayload(array $decoded): void
    {
        $meta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
        $pontos = $decoded['pontos'] ?? $decoded['points'] ?? [];
        if (! is_array($pontos)) {
            $pontos = [];
        }

        DB::transaction(function () use ($meta, $pontos, $decoded): void {
            SaebIndicatorPoint::query()->delete();
            SaebImportMeta::query()->updateOrCreate(
                ['id' => self::META_ROW_ID],
                ['meta' => $meta]
            );

            foreach ($pontos as $raw) {
                if (! is_array($raw)) {
                    continue;
                }
                $dedupe = SaebPointsDedupe::ensureKey($raw);
                $ibge = $this->ibgeFromRaw($raw);
                $cityId = $this->firstCityId($raw);
                $norm = SaebPointsNormalizer::normalizeDecodedPayload([
                    'pontos' => [$raw],
                    'city_ids' => isset($decoded['city_ids']) && is_array($decoded['city_ids']) ? $decoded['city_ids'] : null,
                ]);
                $n = $norm[0] ?? null;

                SaebIndicatorPoint::create([
                    'dedupe_key' => $dedupe,
                    'raw_point' => $raw,
                    'city_id' => $cityId > 0 ? $cityId : null,
                    'ibge_municipio' => $ibge !== '' ? $ibge : '0000000',
                    'ano' => (int) ($raw['ano'] ?? $raw['year'] ?? 0),
                    'disciplina' => isset($raw['disciplina']) ? substr((string) $raw['disciplina'], 0, 64) : null,
                    'etapa' => isset($raw['etapa']) ? substr((string) $raw['etapa'], 0, 64) : null,
                    'valor' => is_array($n) && isset($n['value']) && is_numeric($n['value'])
                        ? $n['value']
                        : (isset($raw['valor']) && is_numeric($raw['valor']) ? $raw['valor'] : ($raw['value'] ?? null)),
                    'series_key' => is_array($n) ? ($n['series_key'] ?? null) : null,
                    'is_final' => is_array($n) ? (bool) ($n['is_final'] ?? true) : true,
                    'status' => isset($raw['status']) ? substr((string) $raw['status'], 0, 32) : null,
                    'escola_id' => is_array($n) ? ($n['escola_id'] ?? null) : null,
                    'escola_ids' => is_array($n) ? ($n['escola_ids'] ?? null) : null,
                    'city_ids' => is_array($n) ? ($n['city_ids'] ?? null) : null,
                    'fonte' => 'import',
                    'payload' => null,
                ]);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allRawPontos(): array
    {
        try {
            $out = [];
            foreach (SaebIndicatorPoint::query()->orderBy('id')->cursor() as $row) {
                $rp = $row->raw_point;
                if (is_array($rp)) {
                    $out[] = $rp;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('saeb.historico_db.read_failed', ['op' => 'allRawPontos', 'message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array{points: list<array<string, mixed>>, meta: ?array<string, mixed>, explicacao_modal: ?array<string, mixed>, path: string}
     */
    public function loadBundleForCharts(): array
    {
        try {
            $metaRow = SaebImportMeta::query()->find(self::META_ROW_ID);
            $meta = is_array($metaRow?->meta) ? $metaRow->meta : null;
            $explicacaoModal = null;
            if ($meta !== null && isset($meta['explicacao_modal']) && is_array($meta['explicacao_modal'])) {
                $explicacaoModal = $meta['explicacao_modal'];
            }

            $pontos = [];
            foreach (SaebIndicatorPoint::query()->orderBy('id')->cursor() as $row) {
                $rp = $row->raw_point;
                if (is_array($rp)) {
                    $pontos[] = $rp;
                }
            }

            $decoded = ['pontos' => $pontos, 'meta' => $meta];
            $points = SaebPointsNormalizer::normalizeDecodedPayload($decoded);

            return [
                'points' => $points,
                'meta' => $meta,
                'explicacao_modal' => $explicacaoModal,
                'path' => self::STORAGE_LABEL,
            ];
        } catch (\Throwable $e) {
            Log::warning('saeb.historico_db.read_failed', ['op' => 'loadBundleForCharts', 'message' => $e->getMessage()]);

            return [
                'points' => [],
                'meta' => null,
                'explicacao_modal' => null,
                'path' => self::STORAGE_LABEL,
            ];
        }
    }

    /**
     * Pontos brutos para um código IBGE (API /api/saeb/municipio/{ibge}).
     *
     * @return array<string, mixed>|null
     */
    public function buildMunicipioPayload(string $ibge7): ?array
    {
        $ibge7 = preg_replace('/\D/', '', $ibge7);
        if (strlen($ibge7) !== 7) {
            return null;
        }

        try {
            $metaRow = SaebImportMeta::query()->find(self::META_ROW_ID);
            $baseMeta = is_array($metaRow?->meta) ? $metaRow->meta : [];

            $out = [];
            foreach (
                SaebIndicatorPoint::query()
                    ->where('ibge_municipio', $ibge7)
                    ->orderBy('id')
                    ->cursor() as $row
            ) {
                $rp = $row->raw_point;
                if (is_array($rp)) {
                    $out[] = $rp;
                }
            }

            if ($out === []) {
                $cityIds = City::query()
                    ->where('ibge_municipio', $ibge7)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($cityIds !== []) {
                    foreach (SaebIndicatorPoint::query()->orderBy('id')->cursor() as $row) {
                        $rp = $row->raw_point;
                        if (! is_array($rp)) {
                            continue;
                        }
                        $ids = $rp['city_ids'] ?? null;
                        if (! is_array($ids)) {
                            continue;
                        }
                        $ids = array_map(static fn ($x) => (int) $x, $ids);
                        foreach ($cityIds as $cid) {
                            if (in_array($cid, $ids, true)) {
                                $out[] = $rp;
                                break;
                            }
                        }
                    }
                }
            }

            if ($out === []) {
                return null;
            }

            return [
                'meta' => array_merge($baseMeta, [
                    'municipio_ibge' => $ibge7,
                    'endpoint' => url('/api/saeb/municipio/'.$ibge7),
                ]),
                'pontos' => array_values($out),
            ];
        } catch (\Throwable $e) {
            Log::warning('saeb.historico_db.read_failed', ['op' => 'buildMunicipioPayload', 'ibge' => $ibge7, 'message' => $e->getMessage()]);

            return null;
        }
    }

    public function pointsCount(): int
    {
        try {
            return (int) SaebIndicatorPoint::query()->count();
        } catch (\Throwable $e) {
            Log::warning('saeb.historico_db.read_failed', ['op' => 'pointsCount', 'message' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        try {
            $row = SaebImportMeta::query()->find(self::META_ROW_ID);

            return is_array($row?->meta) ? $row->meta : null;
        } catch (\Throwable $e) {
            Log::warning('saeb.historico_db.read_failed', ['op' => 'meta', 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function ibgeFromRaw(array $p): string
    {
        if (! empty($p['municipio_ibge'])) {
            $ibge = preg_replace('/\D/', '', (string) $p['municipio_ibge']);
            if (strlen($ibge) === 7) {
                return $ibge;
            }
        }

        $ids = $p['city_ids'] ?? null;
        if (! is_array($ids) || $ids === []) {
            return '';
        }
        $cid = (int) ($ids[0] ?? 0);
        if ($cid <= 0) {
            return '';
        }
        $city = City::query()->find($cid);
        if ($city === null) {
            return '';
        }
        $raw = preg_replace('/\D/', '', (string) $city->ibge_municipio);

        return strlen($raw) === 7 ? $raw : '';
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function firstCityId(array $p): int
    {
        $ids = $p['city_ids'] ?? null;
        if (! is_array($ids) || $ids === []) {
            return 0;
        }

        return (int) ($ids[0] ?? 0);
    }
}
