<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Resolve VAAF (e opcionalmente VAAT / complementação VAAR) por município e ano.
 *
 * Prioridade: base app (fundeb_municipio_references) → config por IBGE → fallback global.
 */
final class FundebMunicipalReferenceResolver
{
    public const FONTE_OFICIAL_DB = 'oficial_db';

    public const FONTE_CONFIG_IBGE = 'config_ibge';

    public const FONTE_CONFIG_GLOBAL = 'config_global';

    /**
     * @return array{
     *   vaaf: float,
     *   vaat: ?float,
     *   complementacao_vaar: ?float,
     *   fonte: string,
     *   fonte_label: string,
     *   ano: ?int,
     *   ibge: ?string,
     *   notas: ?string
     * }
     */
    public static function resolve(?City $city, ?IeducarFilterState $filters = null): array
    {
        $fallback = self::fallbackGlobal();
        $ano = self::resolveAno($filters);
        $ibge = self::normalizeIbge($city?->ibge_municipio);

        if ($ibge !== null && $ano !== null) {
            $row = self::findReferenceRow($ibge, $ano);

            if ($row !== null && (float) $row->vaaf > 0) {
                return self::buildPayload(
                    (float) $row->vaaf,
                    $row->vaat !== null ? (float) $row->vaat : null,
                    $row->complementacao_vaar !== null ? (float) $row->complementacao_vaar : null,
                    self::FONTE_OFICIAL_DB,
                    __('VAAF oficial importado (:fonte, :ano)', [
                        'fonte' => $row->fonte ?: __('FNDE/dados municipais'),
                        'ano' => (string) $ano,
                    ]),
                    $ano,
                    $ibge,
                    $row->notas,
                );
            }

            $fromConfig = self::fromConfigIbge($ibge, $ano);
            if ($fromConfig !== null) {
                return $fromConfig;
            }
        }

        if ($ibge !== null) {
            $latest = self::findLatestReferenceRow($ibge);

            if ($latest !== null && (float) $latest->vaaf > 0) {
                return self::buildPayload(
                    (float) $latest->vaaf,
                    $latest->vaat !== null ? (float) $latest->vaat : null,
                    $latest->complementacao_vaar !== null ? (float) $latest->complementacao_vaar : null,
                    self::FONTE_OFICIAL_DB,
                    __('VAAF oficial (:ano mais recente na base)', ['ano' => (string) $latest->ano]),
                    (int) $latest->ano,
                    $ibge,
                    $latest->notas,
                );
            }
        }

        return $fallback;
    }

    /**
     * @return array{
     *   vaaf: float,
     *   vaat: ?float,
     *   complementacao_vaar: ?float,
     *   fonte: string,
     *   fonte_label: string,
     *   ano: ?int,
     *   ibge: ?string,
     *   notas: ?string
     * }
     */
    private static function fallbackGlobal(): array
    {
        $vaa = (float) config('ieducar.discrepancies.vaa_referencia_anual', 4500);

        return self::buildPayload(
            $vaa,
            null,
            null,
            self::FONTE_CONFIG_GLOBAL,
            __('Referência configurável (IEDUCAR_DISC_VAA_REFERENCIA)'),
            null,
            null,
            null,
        );
    }

    /**
     * @return array{
     *   vaaf: float,
     *   vaat: ?float,
     *   complementacao_vaar: ?float,
     *   fonte: string,
     *   fonte_label: string,
     *   ano: ?int,
     *   ibge: ?string,
     *   notas: ?string
     * }|null
     */
    private static function fromConfigIbge(string $ibge, int $ano): ?array
    {
        $map = config('ieducar.fundeb.vaaf_por_ibge', []);
        if (! is_array($map)) {
            return null;
        }

        $entry = $map[$ibge] ?? null;
        if ($entry === null) {
            return null;
        }

        if (is_numeric($entry)) {
            $vaaf = (float) $entry;

            return $vaaf > 0
                ? self::buildPayload(
                    $vaaf,
                    null,
                    null,
                    self::FONTE_CONFIG_IBGE,
                    __('VAAF por IBGE em config (ano :ano)', ['ano' => (string) $ano]),
                    $ano,
                    $ibge,
                    null,
                )
                : null;
        }

        if (! is_array($entry)) {
            return null;
        }

        $yearEntry = $entry[(string) $ano] ?? $entry[$ano] ?? $entry['*'] ?? $entry['default'] ?? null;
        if ($yearEntry === null) {
            return null;
        }

        if (is_numeric($yearEntry)) {
            $vaaf = (float) $yearEntry;

            return $vaaf > 0
                ? self::buildPayload($vaaf, null, null, self::FONTE_CONFIG_IBGE, __('VAAF por IBGE em config'), $ano, $ibge, null)
                : null;
        }

        if (! is_array($yearEntry)) {
            return null;
        }

        $vaaf = (float) ($yearEntry['vaaf'] ?? $yearEntry['vaa'] ?? 0);
        if ($vaaf <= 0) {
            return null;
        }

        return self::buildPayload(
            $vaaf,
            isset($yearEntry['vaat']) ? (float) $yearEntry['vaat'] : null,
            isset($yearEntry['complementacao_vaar']) ? (float) $yearEntry['complementacao_vaar'] : null,
            self::FONTE_CONFIG_IBGE,
            __('VAAF por IBGE em config (ieducar.fundeb.vaaf_por_ibge)'),
            $ano,
            $ibge,
            isset($yearEntry['notas']) ? (string) $yearEntry['notas'] : null,
        );
    }

    /**
     * @return array{
     *   vaaf: float,
     *   vaat: ?float,
     *   complementacao_vaar: ?float,
     *   fonte: string,
     *   fonte_label: string,
     *   ano: ?int,
     *   ibge: ?string,
     *   notas: ?string
     * }
     */
    private static function buildPayload(
        float $vaaf,
        ?float $vaat,
        ?float $complementacaoVaar,
        string $fonte,
        string $fonteLabel,
        ?int $ano,
        ?string $ibge,
        ?string $notas,
    ): array {
        return [
            'vaaf' => $vaaf,
            'vaat' => $vaat,
            'complementacao_vaar' => $complementacaoVaar,
            'fonte' => $fonte,
            'fonte_label' => $fonteLabel,
            'ano' => $ano,
            'ibge' => $ibge,
            'notas' => $notas,
        ];
    }

    private static function resolveAno(?IeducarFilterState $filters): ?int
    {
        if ($filters === null || ! $filters->hasYearSelected() || $filters->isAllSchoolYears()) {
            return null;
        }

        $y = $filters->yearFilterValue();

        return $y !== null && $y > 0 ? $y : null;
    }

    private static function normalizeIbge(mixed $raw): ?string
    {
        $ibge = preg_replace('/\D/', '', (string) $raw);

        return strlen($ibge) === 7 ? $ibge : null;
    }

    private static function findReferenceRow(string $ibge, int $ano): ?FundebMunicipioReference
    {
        try {
            return FundebMunicipioReference::query()
                ->where('ibge_municipio', $ibge)
                ->where('ano', $ano)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function findLatestReferenceRow(string $ibge): ?FundebMunicipioReference
    {
        try {
            return FundebMunicipioReference::query()
                ->where('ibge_municipio', $ibge)
                ->orderByDesc('ano')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
