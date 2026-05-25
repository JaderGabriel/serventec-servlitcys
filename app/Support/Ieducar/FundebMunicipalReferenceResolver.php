<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Services\Fundeb\FundebFndeEstadoVaafService;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use App\Services\Fundeb\FundebMatriculasByYearService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Fundeb\FundebReferenceSource;
use Illuminate\Support\Collection;

/**
 * Resolve VAAF (e opcionalmente VAAT / complementação VAAR) por município e ano.
 *
 * Prioridade municipal: vigente → anos anteriores → registro mais recente → config IBGE.
 * Prévia federal: piso nacional configurável (painéis FNDE / IEDUCAR_FUNDEB_NATIONAL_VAAF_*).
 * Cálculos do painel usam o valor municipal quando existir; a prévia aparece sempre que disponível para comparação.
 */
final class FundebMunicipalReferenceResolver
{
    public const FONTE_OFICIAL_DB = 'oficial_db';

    public const FONTE_CONFIG_IBGE = 'config_ibge';

    public const FONTE_CONFIG_GLOBAL = 'config_global';

    public const FONTE_PREVIA_NACIONAL = 'previa_nacional';

    /** Último recurso: IEDUCAR_DISC_VAA_REFERENCIA (ex.: R$ 4.500/aluno/ano). */
    public const FONTE_VALOR_CONFIGURADO = 'valor_configurado';

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
     *   referencia_estadual: ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int, uf: ?string},
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
     * VAAF usado em cálculos do painel (Matrículas, Rede, Discrepâncias, etc.).
     *
     * Ordem: (1) municipal oficial importado → (2) prévia federal (IEDUCAR_FUNDEB_NATIONAL_VAAF_*)
     * → (3) estimativa receita÷matrículas → (4) VAAF por IBGE em config → (5) valor configurado
     * IEDUCAR_DISC_VAA_REFERENCIA (ex.: 4.500), só após esgotar os anteriores.
     *
     * @return array{vaaf: float, fonte_label: string, ano: ?int, origem: string}
     */
    public static function vaafParaCalculo(?City $city, ?IeducarFilterState $filters = null): array
    {
        $ref = self::resolve($city, $filters);
        $anchorAno = self::resolveAnchorAno($filters);
        $ibge = self::normalizeIbge($city?->ibge_municipio);

        $municipal = is_array($ref['municipal'] ?? null) ? $ref['municipal'] : null;
        if (
            $municipal !== null
            && (float) ($municipal['vaaf'] ?? 0) > 0
            && (string) ($municipal['fonte'] ?? '') === self::FONTE_OFICIAL_DB
        ) {
            return [
                'vaaf' => (float) $municipal['vaaf'],
                'fonte_label' => (string) ($municipal['fonte_label'] ?? $ref['fonte_label'] ?? ''),
                'ano' => $municipal['ano'] ?? $ref['ano'] ?? null,
                'origem' => 'municipal',
            ];
        }

        $previa = is_array($ref['previa'] ?? null) ? $ref['previa'] : null;
        if (
            $previa !== null
            && (float) ($previa['vaaf'] ?? 0) > 0
            && (string) ($previa['fonte'] ?? '') === self::FONTE_PREVIA_NACIONAL
        ) {
            return [
                'vaaf' => (float) $previa['vaaf'],
                'fonte_label' => (string) ($previa['fonte_label'] ?? ''),
                'ano' => $previa['ano'] ?? null,
                'origem' => self::FONTE_PREVIA_NACIONAL,
            ];
        }

        if ($city !== null) {
            $estimado = self::tryEstimateVaafFromReceita($city, $filters);
            if ($estimado !== null) {
                return $estimado;
            }
        }

        if ($ibge !== null) {
            $fromIbge = self::fromConfigIbge($ibge, $anchorAno);
            if ($fromIbge !== null && (float) ($fromIbge['vaaf'] ?? 0) > 0) {
                return [
                    'vaaf' => (float) $fromIbge['vaaf'],
                    'fonte_label' => (string) ($fromIbge['fonte_label'] ?? ''),
                    'ano' => $fromIbge['ano'] ?? $anchorAno,
                    'origem' => self::FONTE_CONFIG_IBGE,
                ];
            }
        }

        $estadual = is_array($ref['referencia_estadual'] ?? null) ? $ref['referencia_estadual'] : null;
        if ($estadual !== null && (float) ($estadual['vaaf'] ?? 0) > 0) {
            return [
                'vaaf' => (float) $estadual['vaaf'],
                'fonte_label' => (string) ($estadual['fonte_label'] ?? ''),
                'ano' => $estadual['ano'] ?? null,
                'origem' => FundebReferenceSource::FONTE_FNDE_ESTADO_VAAF,
            ];
        }

        return self::resolveValorConfigurado();
    }

    /**
     * @return array{vaaf: float, fonte_label: string, ano: ?int, origem: string}
     */
    public static function resolveValorConfigurado(): array
    {
        $vaa = max(0.0, (float) config('ieducar.discrepancies.vaa_referencia_anual', 4500));

        return [
            'vaaf' => $vaa,
            'fonte_label' => __('Valor configurado :valor/aluno/ano (IEDUCAR_DISC_VAA_REFERENCIA)', [
                'valor' => DiscrepanciesFundingImpact::formatBrl($vaa),
            ]),
            'ano' => null,
            'origem' => self::FONTE_VALOR_CONFIGURADO,
        ];
    }

    /**
     * @return ?array{vaaf: float, fonte_label: string, ano: ?int, origem: string}
     */
    private static function tryEstimateVaafFromReceita(City $city, ?IeducarFilterState $filters): ?array
    {
        $ibge = self::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return null;
        }

        $ano = self::resolveAnchorAno($filters);

        try {
            $receitaSvc = app(FundebFndeReceitaCsvService::class);
            $matSvc = app(FundebMatriculasByYearService::class);
            $receitaRow = $receitaSvc->rowForIbge($ibge, $ano);
            $matRow = $matSvc->forCityYears($city, [$ano])[$ano] ?? null;
            $matUsado = (int) ($matRow['usado'] ?? 0);
            $totalReceita = $receitaRow !== null ? (float) ($receitaRow['total_receita'] ?? 0) : 0.0;

            if ($totalReceita <= 0 || $matUsado <= 0) {
                return null;
            }

            $vaafEst = $receitaSvc->estimateVaafFromReceitaAndMatriculas($totalReceita, $matUsado);
            if ($vaafEst === null || $vaafEst <= 0) {
                return null;
            }

            return [
                'vaaf' => $vaafEst,
                'fonte_label' => __('VAAF estimado (receita FNDE ÷ matrículas, :ano)', ['ano' => (string) $ano]),
                'ano' => $ano,
                'origem' => FundebReferenceSource::FONTE_FNDE_RECEITA_IEDUCAR,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveUncached(?City $city, ?IeducarFilterState $filters): array
    {
        $ibge = self::normalizeIbge($city?->ibge_municipio);
        $anchorAno = self::resolveAnchorAno($filters);
        $uf = self::normalizeUf($city?->uf);
        $referenciaEstadual = $uf !== null ? self::resolveReferenciaEstadual($uf, $anchorAno) : null;
        $previa = self::resolvePreviaNacional($anchorAno, $ibge, $referenciaEstadual);

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

            return self::enrichWithComparison($primary, $previa, $ibge, $municipal, $referenciaEstadual);
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
                referenciaEstadual: $referenciaEstadual,
            );
        }

        $global = self::fallbackGlobal();

        return self::enrichWithComparison($global, $previa, $ibge, municipal: null, referenciaEstadual: $referenciaEstadual);
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
            if ($row !== null && (float) $row->vaaf > 0 && ! FundebReferenceSource::isPlaceholder($row->fonte)) {
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

        if ($latest !== null && (float) $latest->vaaf > 0 && ! FundebReferenceSource::isPlaceholder($latest->fonte)) {
            $ano = (int) $latest->ano;

            return [
                'vaaf' => (float) $latest->vaaf,
                'vaat' => $latest->vaat !== null ? (float) $latest->vaat : null,
                'complementacao_vaar' => $latest->complementacao_vaar !== null ? (float) $latest->complementacao_vaar : null,
                'fonte' => self::FONTE_OFICIAL_DB,
                'fonte_label' => self::labelForResolvedYear(
                    __('VAAF municipal (:fonte, :ano mais recente na base)', [
                        'fonte' => $latest->fonte ?: __('FNDE/dados importados'),
                        'ano' => (string) $ano,
                    ]),
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
    public static function resolvePreviaNacional(int $ano, ?string $ibge = null, ?array $referenciaEstadual = null): ?array
    {
        $byYear = config('ieducar.fundeb.open_data.national_floor.vaaf_by_year', []);
        $vaaf = null;
        $resolvedAno = $ano;
        if (is_array($byYear)) {
            foreach (FundebReferenceYearOrder::candidateYears($ano, 3) as $y) {
                if (isset($byYear[$y]) && $byYear[$y] !== null && (float) $byYear[$y] > 0) {
                    $vaaf = (float) $byYear[$y];
                    $resolvedAno = $y;
                    break;
                }
            }
        }

        if ($vaaf !== null && $vaaf > 0 && (bool) config('ieducar.fundeb.open_data.national_floor.enabled', true)) {
            $vaafFmt = DiscrepanciesFundingImpact::formatBrl($vaaf);

            return [
                'vaaf' => $vaaf,
                'vaat' => null,
                'complementacao_vaar' => null,
                'fonte' => self::FONTE_PREVIA_NACIONAL,
                'fonte_label' => __('Prévia federal :valor/aluno/ano (IEDUCAR_FUNDEB_NATIONAL_VAAF_:ano)', [
                    'valor' => $vaafFmt,
                    'ano' => (string) $resolvedAno,
                ]),
                'ano' => $resolvedAno,
            ];
        }

        return $referenciaEstadual;
    }

    /**
     * VAAF consolidado por UF/DF (PDF Consultas FNDE — valor aluno/ano e receita anual prevista).
     *
     * @return ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int, uf: string}
     */
    public static function resolveReferenciaEstadual(string $uf, int $ano): ?array
    {
        if (! (bool) config('ieducar.fundeb.open_data.fnde_estado_vaaf_enabled', true)) {
            return null;
        }

        try {
            $row = app(FundebFndeEstadoVaafService::class)->rowForUf($uf, $ano);
        } catch (\Throwable) {
            return null;
        }

        if ($row === null || (float) ($row['vaaf'] ?? 0) <= 0) {
            return null;
        }

        $pubAno = (int) ($row['ano_publicacao'] ?? $ano);

        return [
            'vaaf' => (float) $row['vaaf'],
            'vaat' => null,
            'complementacao_vaar' => null,
            'fonte' => FundebReferenceSource::FONTE_FNDE_ESTADO_VAAF,
            'fonte_label' => __('VAAF estadual FNDE (:uf, publicação :ano)', [
                'uf' => strtoupper($uf),
                'ano' => (string) $pubAno,
            ]),
            'ano' => $pubAno,
            'uf' => strtoupper($uf),
        ];
    }

    private static function normalizeUf(?string $uf): ?string
    {
        $uf = strtoupper(trim((string) $uf));

        return strlen($uf) === 2 ? $uf : null;
    }

    /**
     * @param  array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int, ibge: ?string, notas: ?string}  $primary
     * @param  ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int}  $municipal
     * @param  ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int}  $previa
     * @param  ?array{vaaf: float, vaat: ?float, complementacao_vaar: ?float, fonte: string, fonte_label: string, ano: ?int, uf?: string}  $referenciaEstadual
     * @return array<string, mixed>
     */
    private static function enrichWithComparison(
        array $primary,
        ?array $previa,
        ?string $ibge,
        ?array $municipal = null,
        ?array $referenciaEstadual = null,
    ): array {
        $fontePrimary = (string) ($primary['fonte'] ?? '');
        $municipal ??= ! in_array($fontePrimary, [
            self::FONTE_PREVIA_NACIONAL,
            self::FONTE_CONFIG_GLOBAL,
            self::FONTE_VALOR_CONFIGURADO,
        ], true)
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
            'referencia_estadual' => $referenciaEstadual,
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
        $configured = self::resolveValorConfigurado();

        return self::buildPayload(
            (float) $configured['vaaf'],
            null,
            null,
            self::FONTE_VALOR_CONFIGURADO,
            (string) $configured['fonte_label'],
            null,
            null,
            __('Sem VAAF municipal nem prévia federal por ano; usa-se o valor configurado em IEDUCAR_DISC_VAA_REFERENCIA.'),
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
            $query = FundebMunicipioReference::query()
                ->where('ibge_municipio', $ibge)
                ->orderByDesc('ano');

            foreach (FundebReferenceSource::PLACEHOLDER_FONTES as $placeholder) {
                $query->where('fonte', '!=', $placeholder);
            }

            return $query->first();
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
