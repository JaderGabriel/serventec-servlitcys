<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Support\Collection;

/**
 * Resolve VAAF (e opcionalmente VAAT / complementação VAAR) por município e ano.
 *
 * Prioridade municipal: vigente → anos anteriores → registo mais recente → config IBGE.
 * Prévia federal: piso nacional configurável (painéis FNDE / IEDUCAR_FUNDEB_NATIONAL_VAAF_*).
 * Cálculos do painel usam o valor municipal quando existir; a prévia aparece sempre que disponível para comparação.
 */
final class FundebMunicipalReferenceResolver
{
    public const FONTE_OFICIAL_DB = 'oficial_db';

    public const FONTE_CONFIG_IBGE = 'config_ibge';

    public const FONTE_CONFIG_GLOBAL = 'config_global';

    public const FONTE_PREVIA_NACIONAL = 'previa_nacional';

    /** @var array<string, array<string, mixed>> */
    private static array $resolveCache = [];

    /**
     * @return array{
     *   vaaf: float,
     *   vaat: ?float,
     *   complementacao_vaar: ?float,
     *   fonte: string,
     *   fonte_label: string,
     *   ano: ?int,
     *   ibge: ?string,
     *   notas: ?string,
     *   municipal: ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int},
     *   previa: ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int},
     *   divergencia: ?array{absoluto: float, pct: float, mensagem: string}
     * }
     */
    public static function resolve(?City $city, ?IeducarFilterState $filters = null): array
    {
        $key = self::cacheKey($city, $filters);
        if (isset(self::$resolveCache[$key])) {
            return self::$resolveCache[$key];
        }

        $payload = self::resolveUncached($city, $filters);
        self::$resolveCache[$key] = $payload;

        return $payload;
    }

    public static function clearCache(): void
    {
        self::$resolveCache = [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveUncached(?City $city, ?IeducarFilterState $filters): array
    {
        $ibge = self::normalizeIbge($city?->ibge_municipio);
        $anchorAno = self::resolveAnchorAno($filters);
        $previa = self::resolvePreviaNacional($anchorAno, $ibge);

        $municipal = null;
        if ($ibge !== null) {
            $municipal = self::resolveMunicipal($ibge, $anchorAno, $city);
        }

        if ($municipal !== null) {
            $primary = self::buildPayload(
                (float) $municipal['vaaf'],
                $municipal['vaat'] ?? null,
                $municipal['complementacao_vaar'] ?? null,
                (string) $municipal['fonte'],
                (string) $municipal['fonte_label'],
                $municipal['ano'] ?? null,
                $ibge,
                null,
            );

            return self::enrichWithComparison($primary, $previa, $ibge, $municipal);
        }

        if ($previa !== null) {
            return self::enrichWithComparison(
                self::buildPayload(
                    (float) $previa['vaaf'],
                    $previa['vaat'] ?? null,
                    $previa['complementacao_vaar'] ?? null,
                    self::FONTE_PREVIA_NACIONAL,
                    (string) $previa['fonte_label'],
                    $anchorAno,
                    $ibge,
                    __('Sem VAAF municipal importado; cálculos usam a prévia federal configurada.'),
                ),
                $previa,
                $ibge,
                municipal: null,
            );
        }

        $global = self::fallbackGlobal();

        return self::enrichWithComparison($global, $previa, $ibge, municipal: null);
    }

    /**
     * @return ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int}
     */
    private static function resolveMunicipal(string $ibge, int $anchorAno, ?City $city): ?array
    {
        $years = FundebReferenceYearOrder::candidateYears($anchorAno);
        $byYear = self::loadReferencesByYears($ibge, $years);

        foreach ($years as $ano) {
            $row = $byYear->get($ano);
            if ($row !== null && (float) $row->vaaf > 0) {
                return [
                    'vaaf' => (float) $row->vaaf,
                    'vaat' => $row->vaat !== null ? (float) $row->vaat : null,
                    'complementacao_vaar' => $row->complementacao_vaar !== null ? (float) $row->complementacao_vaar : null,
                    'fonte' => self::FONTE_OFICIAL_DB,
                    'fonte_label' => self::labelForResolvedYear(
                        __('VAAF municipal (:fonte, :ano)', [
                            'fonte' => $row->fonte ?: __('FNDE/dados importados'),
                            'ano' => (string) $ano,
                        ]),
                        $ano,
                        $anchorAno,
                    ),
                    'ano' => $ano,
                ];
            }

            $fromConfig = self::fromConfigIbge($ibge, $ano);
            if ($fromConfig !== null) {
                $fromConfig['fonte_label'] = self::labelForResolvedYear(
                    (string) $fromConfig['fonte_label'],
                    $ano,
                    $anchorAno,
                );

                return [
                    'vaaf' => (float) $fromConfig['vaaf'],
                    'vaat' => $fromConfig['vaat'],
                    'complementacao_vaar' => $fromConfig['complementacao_vaar'],
                    'fonte' => (string) $fromConfig['fonte'],
                    'fonte_label' => (string) $fromConfig['fonte_label'],
                    'ano' => (int) ($fromConfig['ano'] ?? $ano),
                ];
            }
        }

        $latest = $byYear->sortKeysDesc()->first();
        if ($latest === null) {
            $latest = self::findLatestReferenceRow($ibge);
        }

        if ($latest !== null && (float) $latest->vaaf > 0) {
            $ano = (int) $latest->ano;

            return [
                'vaaf' => (float) $latest->vaaf,
                'vaat' => $latest->vaat !== null ? (float) $latest->vaat : null,
                'complementacao_vaar' => $latest->complementacao_vaar !== null ? (float) $latest->complementacao_vaar : null,
                'fonte' => self::FONTE_OFICIAL_DB,
                'fonte_label' => self::labelForResolvedYear(
                    __('VAAF municipal (:ano mais recente na base)', ['ano' => (string) $ano]),
                    $ano,
                    $anchorAno,
                ),
                'ano' => $ano,
            ];
        }

        return null;
    }

    /**
     * Prévia federal (piso / referência nacional publicada ou configurada).
     *
     * @return ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int}
     */
    public static function resolvePreviaNacional(int $ano, ?string $ibge = null): ?array
    {
        $enabled = (bool) config('ieducar.fundeb.open_data.national_floor.enabled', true);
        if (! $enabled) {
            return null;
        }

        $byYear = config('ieducar.fundeb.open_data.national_floor.vaaf_by_year', []);
        $vaaf = null;
        if (is_array($byYear)) {
            foreach (FundebReferenceYearOrder::candidateYears($ano, 3) as $y) {
                if (isset($byYear[$y]) && $byYear[$y] !== null && (float) $byYear[$y] > 0) {
                    $vaaf = (float) $byYear[$y];
                    $ano = $y;
                    break;
                }
            }
        }

        if ($vaaf === null || $vaaf <= 0) {
            $vaaf = (float) config('ieducar.discrepancies.vaa_referencia_anual', 0);
        }

        if ($vaaf <= 0) {
            return null;
        }

        $fonteDetalhe = is_array($byYear) && isset($byYear[$ano])
            ? __('Prévia federal (IEDUCAR_FUNDEB_NATIONAL_VAAF_:ano)', ['ano' => (string) $ano])
            : __('Prévia federal (IEDUCAR_DISC_VAA_REFERENCIA)');

        return [
            'vaaf' => $vaaf,
            'vaat' => null,
            'complementacao_vaar' => null,
            'fonte' => self::FONTE_PREVIA_NACIONAL,
            'fonte_label' => $fonteDetalhe,
            'ano' => $ano,
        ];
    }

    /**
     * @param  array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int, ibge: ?string, notas: ?string}  $primary
     * @param  ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int}  $municipal
     * @param  ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int}  $previa
     * @return array<string, mixed>
     */
    private static function enrichWithComparison(
        array $primary,
        ?array $previa,
        ?string $ibge,
        ?array $municipal = null,
    ): array {
        $municipal ??= ($primary['fonte'] ?? '') !== self::FONTE_PREVIA_NACIONAL
            && ($primary['fonte'] ?? '') !== self::FONTE_CONFIG_GLOBAL
            ? [
                'vaaf' => (float) $primary['vaaf'],
                'vaat' => $primary['vaat'] ?? null,
                'complementacao_vaar' => $primary['complementacao_vaar'] ?? null,
                'fonte' => (string) ($primary['fonte'] ?? ''),
                'fonte_label' => (string) ($primary['fonte_label'] ?? ''),
                'ano' => $primary['ano'] ?? null,
            ]
            : null;

        $divergencia = null;
        if ($municipal !== null && $previa !== null && (float) $previa['vaaf'] > 0) {
            $diff = round((float) $municipal['vaaf'] - (float) $previa['vaaf'], 2);
            $pct = round(100.0 * abs($diff) / (float) $previa['vaaf'], 1);
            if (abs($diff) >= 0.01) {
                $divergencia = [
                    'absoluto' => $diff,
                    'pct' => $pct,
                    'mensagem' => $diff > 0
                        ? __('VAAF municipal :municipal está :pct% acima da prévia federal :previa.', [
                            'municipal' => DiscrepanciesFundingImpact::formatBrl((float) $municipal['vaaf']),
                            'previa' => DiscrepanciesFundingImpact::formatBrl((float) $previa['vaaf']),
                            'pct' => number_format($pct, 1, ',', '.'),
                        ])
                        : __('VAAF municipal :municipal está :pct% abaixo da prévia federal :previa.', [
                            'municipal' => DiscrepanciesFundingImpact::formatBrl((float) $municipal['vaaf']),
                            'previa' => DiscrepanciesFundingImpact::formatBrl((float) $previa['vaaf']),
                            'pct' => number_format($pct, 1, ',', '.'),
                        ]),
                ];
            }
        }

        return array_merge($primary, [
            'municipal' => $municipal,
            'previa' => $previa,
            'divergencia' => $divergencia,
            'ibge' => $ibge ?? $primary['ibge'] ?? null,
        ]);
    }

    /**
     * @param  list<int>  $years
     * @return Collection<int, FundebMunicipioReference>
     */
    private static function loadReferencesByYears(string $ibge, array $years): Collection
    {
        if ($years === []) {
            return collect();
        }

        try {
            return FundebMunicipioReference::query()
                ->where('ibge_municipio', $ibge)
                ->whereIn('ano', $years)
                ->get()
                ->keyBy(static fn (FundebMunicipioReference $r): int => (int) $r->ano);
        } catch (\Throwable) {
            return collect();
        }
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

    private static function resolveAnchorAno(?IeducarFilterState $filters): int
    {
        if ($filters !== null && $filters->hasYearSelected() && ! $filters->isAllSchoolYears()) {
            $y = $filters->yearFilterValue();
            if ($y !== null && $y > 0) {
                return $y;
            }
        }

        return (int) date('Y');
    }

    private static function labelForResolvedYear(string $baseLabel, int $resolvedAno, int $anchorAno): string
    {
        if ($resolvedAno === $anchorAno) {
            return $baseLabel;
        }

        return $baseLabel.' '.__('(referência :ref; sem dado para :anchor)', [
            'ref' => (string) $resolvedAno,
            'anchor' => (string) $anchorAno,
        ]);
    }

    private static function normalizeIbge(mixed $raw): ?string
    {
        $ibge = preg_replace('/\D/', '', (string) $raw);

        return strlen($ibge) === 7 ? $ibge : null;
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

    private static function cacheKey(?City $city, ?IeducarFilterState $filters): string
    {
        $ibge = self::normalizeIbge($city?->ibge_municipio) ?? '0';
        $anchor = self::resolveAnchorAno($filters);

        return $ibge.'_'.$anchor;
    }
}
