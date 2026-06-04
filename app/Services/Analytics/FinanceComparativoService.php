<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\Ieducar\EnrollmentRepository;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Finance\MoneyMath;
use App\Support\Analytics\FinanceComparativoInformeBuilder;
use App\Support\Fundeb\FundebReferenceSource;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use App\Support\Ieducar\IeducarWorkActivityQueries;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Http\Request;

/**
 * Aba Comparativo (Finanças): ano base × anterior, projeção do exercício seguinte e detalhe FUNDEB por etapa.
 */
final class FinanceComparativoService
{
    public function __construct(
        private CityDataConnection $cityData,
        private FundebMunicipioReferenceRepository $fundebRefs,
        private EnrollmentRepository $enrollmentRepository,
    ) {}

    public static function resolveBaseYear(Request $request, IeducarFilterState $filters): ?int
    {
        $fromQuery = $request->query('ano_base');
        if (is_numeric($fromQuery)) {
            $y = (int) $fromQuery;
            if ($y >= 2000 && $y <= 2100) {
                return $y;
            }
        }

        if ($filters->hasYearSelected() && ! $filters->isAllSchoolYears()) {
            return (int) $filters->ano_letivo;
        }

        return null;
    }

    /**
     * @param  list<int|string>  $yearOptions
     * @return array<string, mixed>
     */
    public function build(
        City $city,
        int $baseYear,
        IeducarFilterState $filters,
        array $yearOptions = [],
    ): array {
        $prevYear = $baseYear - 1;
        $nextYear = $baseYear + 1;
        $baseFilters = $this->filtersForYear($filters, $baseYear);
        $prevFilters = $this->filtersForYear($filters, $prevYear);

        $empty = [
            'available' => false,
            'city_name' => (string) ($city->name ?? ''),
            'base_year' => $baseYear,
            'prev_year' => $prevYear,
            'next_year' => $nextYear,
            'year_label' => (string) $baseYear,
            'intro' => '',
            'footnote' => __('Valores indicativos para apoio à consultoria municipal. Não substituem portaria FNDE, extrato Simec/VAAR nem prestação de contas.'),
            'year_options' => self::normalizeYearOptions($yearOptions, $baseYear),
            'alerts' => [],
            'summary_kpis' => [],
            'variacoes' => [],
            'base_year_detail' => [],
            'next_year_projection' => [],
            'fundeb_series' => [],
            'informe' => [],
            'export_params' => [],
            'error' => null,
        ];

        if (! $city->hasDataSetup()) {
            return array_merge($empty, [
                'error' => __('Credenciais da base i-Educar incompletas para este município.'),
                'alerts' => [[
                    'tone' => 'danger',
                    'title' => __('Base não configurada'),
                    'message' => __('Configure a conexão i-Educar em Admin → Municípios antes de usar o comparativo.'),
                ]],
            ]);
        }

        $warnings = [];
        $matBase = $this->countMatriculas($city, $baseFilters, $warnings, __('matrículas ano base'));
        $matPrev = $this->countMatriculas($city, $prevFilters, $warnings, __('matrículas ano anterior'));
        $alunosBase = $this->countAlunos($city, $baseFilters, $warnings);
        $alunosPrev = $this->countAlunos($city, $prevFilters, $warnings);
        $turmasBase = $this->countTurmas($city, $baseFilters, $warnings);
        $turmasPrev = $this->countTurmas($city, $prevFilters, $warnings);

        $refBase = $this->resolveVaafForYear($city, $baseFilters);
        $refPrev = $this->resolveVaafForYear($city, $prevFilters);
        $refNext = $this->resolveVaafForYear($city, $this->filtersForYear($filters, $nextYear));

        $previsaoBase = $matBase > 0 && $refBase['vaaf'] > 0
            ? MoneyMath::multiplyVaaf($matBase, $refBase['vaaf'])
            : null;
        $previsaoPrev = $matPrev > 0 && $refPrev['vaaf'] > 0
            ? MoneyMath::multiplyVaaf($matPrev, $refPrev['vaaf'])
            : null;
        $previsaoNext = $matBase > 0 && $refNext['vaaf'] > 0
            ? MoneyMath::multiplyVaaf($matBase, $refNext['vaaf'])
            : null;

        $enrollmentData = [];
        try {
            $enrollmentData = $this->enrollmentRepository->sample($city, $baseFilters);
        } catch (\Throwable $e) {
            $warnings[] = $e->getMessage();
        }

        $porEtapa = $matBase > 0 && $refBase['vaaf'] > 0
            ? $this->porEtapaFromEnrollment($enrollmentData, $refBase['vaaf'], $matBase)
            : [];

        $alerts = $this->buildAlerts(
            $baseYear,
            $matBase,
            $matPrev,
            $refBase,
            $refNext,
            $previsaoBase,
            $previsaoPrev,
            $previsaoNext,
            $porEtapa,
        );

        foreach ($warnings as $w) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => __('Aviso na consulta'),
                'message' => $w,
            ];
        }

        $variacoes = [
            $this->variationRow(__('Matrículas ativas'), $matBase, $matPrev, 'count'),
            $this->variationRow(__('Alunos distintos'), $alunosBase, $alunosPrev, 'count'),
            $this->variationRow(__('Turmas'), $turmasBase, $turmasPrev, 'count'),
            $this->variationRow(
                __('Recursos FUNDEB (previsão base)'),
                $previsaoBase,
                $previsaoPrev,
                'money',
            ),
        ];

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        $report = [
            'available' => true,
            'city_name' => (string) ($city->name ?? ''),
            'base_year' => $baseYear,
            'prev_year' => $prevYear,
            'next_year' => $nextYear,
            'year_label' => (string) $baseYear,
            'intro' => __(
                'Comparativo para consultoria: matrículas do exercício :ano com detalhe por nível de ensino (ponderação indicativa do FUNDEB), variação face a :anterior e leitura do exercício :proximo.',
                ['ano' => (string) $baseYear, 'anterior' => (string) $prevYear, 'proximo' => (string) $nextYear],
            ),
            'footnote' => $empty['footnote'],
            'year_options' => self::normalizeYearOptions($yearOptions, $baseYear),
            'alerts' => $alerts,
            'summary_kpis' => $this->summaryKpis($variacoes, $previsaoNext, $fmt),
            'variacoes' => $variacoes,
            'base_year_detail' => [
                'matriculas' => $matBase,
                'matriculas_fmt' => $matBase > 0 ? number_format($matBase, 0, ',', '.') : '—',
                'vaaf' => $refBase['vaaf'] > 0 ? $refBase['vaaf'] : null,
                'vaaf_label' => $refBase['vaaf'] > 0 ? $fmt($refBase['vaaf']) : '—',
                'vaaf_fonte' => $refBase['fonte_label'],
                'previsao_base' => $previsaoBase,
                'previsao_base_label' => $previsaoBase !== null ? $fmt($previsaoBase) : '—',
                'por_etapa' => $porEtapa,
            ],
            'next_year_projection' => $this->nextYearBlock(
                $nextYear,
                $matBase,
                $previsaoBase,
                $previsaoNext,
                $refNext,
                $fmt,
            ),
            'fundeb_series' => $this->fundebYearSeries($city, $baseYear),
            'error' => null,
        ];

        $report['informe'] = FinanceComparativoInformeBuilder::build($report);
        $report['export_params'] = self::exportParams($city, $filters, $baseYear);

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private static function exportParams(City $city, IeducarFilterState $filters, int $baseYear): array
    {
        $params = array_filter([
            'city_id' => $city->id,
            'ano_letivo' => $filters->ano_letivo,
            'ano_base' => $baseYear,
            'escola_id' => $filters->escola_id,
            'curso_id' => $filters->curso_id,
            'turno_id' => $filters->turno_id,
        ], static fn ($v) => $v !== null && $v !== '');

        return $params;
    }

    private function filtersForYear(IeducarFilterState $filters, int $year): IeducarFilterState
    {
        return new IeducarFilterState(
            ano_letivo: (string) $year,
            escola_id: $filters->escola_id,
            curso_id: $filters->curso_id,
            turno_id: $filters->turno_id,
        );
    }

    /**
     * @param  list<string>  $warnings
     */
    private function countMatriculas(City $city, IeducarFilterState $filters, array &$warnings, string $label): int
    {
        try {
            return (int) $this->cityData->run(
                $city,
                static fn ($db) => MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters) ?? 0,
            );
        } catch (\Throwable $e) {
            $warnings[] = __(':label: :msg', ['label' => $label, 'msg' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * @param  list<string>  $warnings
     */
    private function countAlunos(City $city, IeducarFilterState $filters, array &$warnings): int
    {
        try {
            return (int) $this->cityData->run(
                $city,
                static fn ($db) => IeducarWorkActivityQueries::countAlunosAtivosForYear($db, $city, $filters),
            );
        } catch (\Throwable $e) {
            $warnings[] = __('Alunos: :msg', ['msg' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * @param  list<string>  $warnings
     */
    private function countTurmas(City $city, IeducarFilterState $filters, array &$warnings): int
    {
        try {
            return (int) $this->cityData->run(
                $city,
                static fn ($db) => IeducarWorkActivityQueries::countTurmasForYear($db, $city, $filters),
            );
        } catch (\Throwable $e) {
            $warnings[] = __('Turmas: :msg', ['msg' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * @return array{vaaf: float, fonte: string, fonte_label: string, ano: ?int, placeholder: bool}
     */
    private function resolveVaafForYear(City $city, IeducarFilterState $filters): array
    {
        $resolved = DiscrepanciesFundingImpact::resolveReference($city, $filters);
        $municipal = is_array($resolved['municipal'] ?? null) ? $resolved['municipal'] : null;
        if ($municipal !== null && (float) ($municipal['vaaf'] ?? 0) > 0) {
            return [
                'vaaf' => (float) $municipal['vaaf'],
                'fonte' => (string) ($municipal['fonte'] ?? $resolved['fonte'] ?? ''),
                'fonte_label' => (string) ($municipal['fonte_label'] ?? $resolved['fonte_label'] ?? ''),
                'ano' => isset($municipal['ano']) ? (int) $municipal['ano'] : null,
                'placeholder' => false,
            ];
        }

        $calc = FundebMunicipalReferenceResolver::vaafParaCalculo($city, $filters);

        return [
            'vaaf' => (float) ($calc['vaaf'] ?? 0),
            'fonte' => (string) ($calc['fonte'] ?? ''),
            'fonte_label' => (string) ($calc['fonte_label'] ?? ''),
            'ano' => isset($calc['ano']) ? (int) $calc['ano'] : null,
            'placeholder' => FundebReferenceSource::isPlaceholder((string) ($calc['fonte'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $enrollmentData
     * @return list<array{etapa: string, matriculas: int, participacao_pct: float, fundeb_indicativo: float, fundeb_label: string}>
     */
    private function porEtapaFromEnrollment(array $enrollmentData, float $vaaf, int $matTotal): array
    {
        $chart = null;
        foreach ($enrollmentData['charts'] ?? [] as $c) {
            if (! is_array($c)) {
                continue;
            }
            $t = mb_strtolower((string) ($c['title'] ?? ''));
            if (str_contains($t, 'nível de ensino') || str_contains($t, 'nivel de ensino')) {
                $chart = $c;
                break;
            }
        }

        if ($chart === null) {
            return [];
        }

        $labels = $chart['labels'] ?? [];
        $data = $chart['datasets'][0]['data'] ?? [];
        if (! is_array($labels) || ! is_array($data) || $labels === []) {
            return [];
        }

        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $out = [];
        foreach (array_values($labels) as $i => $label) {
            $mat = (int) ($data[$i] ?? 0);
            if ($mat <= 0) {
                continue;
            }
            $part = $matTotal > 0 ? round(100.0 * $mat / $matTotal, 1) : 0.0;
            $fundeb = MoneyMath::multiplyVaaf($mat, $vaaf);
            $out[] = [
                'etapa' => (string) $label,
                'matriculas' => $mat,
                'participacao_pct' => $part,
                'fundeb_indicativo' => $fundeb,
                'fundeb_label' => $fmt($fundeb),
            ];
        }

        usort($out, static fn ($a, $b) => ($b['fundeb_indicativo'] ?? 0) <=> ($a['fundeb_indicativo'] ?? 0));

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function variationRow(string $label, int|float|null $base, int|float|null $prev, string $kind): array
    {
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $baseVal = $base !== null ? (float) $base : null;
        $prevVal = $prev !== null ? (float) $prev : null;

        $deltaAbs = ($baseVal !== null && $prevVal !== null) ? $baseVal - $prevVal : null;
        $deltaPct = ($deltaAbs !== null && $prevVal > 0) ? round(($deltaAbs / $prevVal) * 100, 1) : null;

        $direction = 'missing';
        if ($baseVal === null || $baseVal <= 0) {
            $direction = 'missing_base';
        } elseif ($prevVal === null || $prevVal <= 0) {
            $direction = 'missing_prev';
        } elseif ($deltaAbs > 0) {
            $direction = 'up';
        } elseif ($deltaAbs < 0) {
            $direction = 'down';
        } else {
            $direction = 'flat';
        }

        $formatValue = static function (?float $v) use ($kind, $fmt): string {
            if ($v === null || $v <= 0) {
                return '—';
            }

            return $kind === 'money' ? $fmt($v) : number_format((int) round($v), 0, ',', '.');
        };

        $deltaLabel = '—';
        if ($deltaAbs !== null && ($baseVal > 0 || $prevVal > 0)) {
            if ($kind === 'money') {
                $sign = $deltaAbs >= 0 ? '+' : '';
                $deltaLabel = $sign.$fmt($deltaAbs);
            } else {
                $sign = $deltaAbs >= 0 ? '+' : '';
                $deltaLabel = $sign.number_format((int) round($deltaAbs), 0, ',', '.');
            }
            if ($deltaPct !== null) {
                $deltaLabel .= ' ('.($deltaPct >= 0 ? '+' : '').number_format($deltaPct, 1, ',', '.').'%)';
            }
        }

        $tone = match ($direction) {
            'up' => 'emerald',
            'down' => 'rose',
            'flat' => 'slate',
            'missing_base', 'missing_prev', 'missing' => 'amber',
            default => 'slate',
        };

        return [
            'label' => $label,
            'kind' => $kind,
            'base' => $baseVal,
            'base_fmt' => $formatValue($baseVal),
            'prev' => $prevVal,
            'prev_fmt' => $formatValue($prevVal),
            'delta_abs' => $deltaAbs,
            'delta_pct' => $deltaPct,
            'delta_label' => $deltaLabel,
            'direction' => $direction,
            'tone' => $tone,
            'leitura' => match ($direction) {
                'up' => __('Avanço face ao ano anterior'),
                'down' => __('Retrocesso face ao ano anterior'),
                'flat' => __('Estável face ao ano anterior'),
                'missing_base' => __('Sem dados no ano base'),
                'missing_prev' => __('Sem base comparável no ano anterior'),
                default => __('Dados insuficientes para comparar'),
            },
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $variacoes
     * @param  callable(float): string  $fmt
     * @return list<array<string, mixed>>
     */
    private function summaryKpis(array $variacoes, ?float $previsaoNext, callable $fmt): array
    {
        $out = [];
        foreach ($variacoes as $row) {
            $out[] = [
                'label' => $row['label'],
                'value' => $row['base_fmt'],
                'tone' => $row['tone'],
                'explicacao_resumo' => ($row['delta_label'] ?? '—').' · '.($row['leitura'] ?? ''),
            ];
        }

        if ($previsaoNext !== null && $previsaoNext > 0) {
            $out[] = [
                'label' => __('Projeção próximo exercício'),
                'value' => $fmt($previsaoNext),
                'tone' => 'teal',
                'explicacao_resumo' => __('Matrículas do ano base × VAAF do exercício seguinte (quando houver referência).'),
            ];
        }

        return $out;
    }

    /**
     * @param  array{vaaf: float, fonte: string, fonte_label: string, ano: ?int, placeholder: bool}  $refNext
     * @param  callable(float): string  $fmt
     * @return array<string, mixed>
     */
    private function nextYearBlock(
        int $nextYear,
        int $matBase,
        ?float $previsaoBase,
        ?float $previsaoNext,
        array $refNext,
        callable $fmt,
    ): array {
        $delta = ($previsaoBase !== null && $previsaoNext !== null)
            ? $previsaoNext - $previsaoBase
            : null;
        $deltaPct = ($delta !== null && $previsaoBase > 0)
            ? round(($delta / $previsaoBase) * 100, 1)
            : null;

        $note = __('Pressupõe matrículas estáveis (:n) e VAAF de :ano.', [
            'n' => $matBase > 0 ? number_format($matBase, 0, ',', '.') : '—',
            'ano' => (string) $nextYear,
        ]);
        if ($refNext['placeholder']) {
            $note .= ' '.__('VAAF do próximo ano é referência nacional/estimada — confirme portaria municipal.');
        }

        return [
            'available' => $previsaoNext !== null && $previsaoNext > 0,
            'year' => $nextYear,
            'matriculas' => $matBase,
            'matriculas_fmt' => $matBase > 0 ? number_format($matBase, 0, ',', '.') : '—',
            'vaaf_label' => $refNext['vaaf'] > 0 ? $fmt($refNext['vaaf']) : '—',
            'vaaf_fonte' => $refNext['fonte_label'],
            'previsao_label' => $previsaoNext !== null ? $fmt($previsaoNext) : '—',
            'previsao_base_label' => $previsaoBase !== null ? $fmt($previsaoBase) : '—',
            'delta_label' => $delta !== null
                ? (($delta >= 0 ? '+' : '').$fmt($delta)
                    .($deltaPct !== null ? ' ('.($deltaPct >= 0 ? '+' : '').number_format($deltaPct, 1, ',', '.').'%)' : ''))
                : '—',
            'tone' => $delta === null ? 'amber' : ($delta >= 0 ? 'emerald' : 'rose'),
            'note' => $note,
        ];
    }

    /**
     * @param  list<array{etapa: string, matriculas: int, participacao_pct: float, fundeb_indicativo: float, fundeb_label: string}>  $porEtapa
     * @return list<array{tone: string, title: string, message: string}>
     */
    private function buildAlerts(
        int $baseYear,
        int $matBase,
        int $matPrev,
        array $refBase,
        array $refNext,
        ?float $previsaoBase,
        ?float $previsaoPrev,
        ?float $previsaoNext,
        array $porEtapa,
    ): array {
        $alerts = [];

        if ($matBase <= 0) {
            $alerts[] = [
                'tone' => 'danger',
                'title' => __('Ano base sem matrículas'),
                'message' => __('Não há matrículas ativas no i-Educar para :ano com os filtros actuais.', ['ano' => (string) $baseYear]),
            ];
        }

        if ($matPrev <= 0 && $matBase > 0) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => __('Histórico incompleto'),
                'message' => __('O ano :ano não tem matrículas registadas — a variação anual fica limitada.', ['ano' => (string) ($baseYear - 1)]),
            ];
        }

        if ($refBase['vaaf'] <= 0) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => __('VAAF do ano base em falta'),
                'message' => __('Importe referências FUNDEB (Admin → Compatibilidade) para calcular recursos no exercício :ano.', ['ano' => (string) $baseYear]),
            ];
        } elseif ($refBase['placeholder']) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => __('VAAF não municipal'),
                'message' => __('O ano base usa valor de referência nacional/estimado, não portaria municipal publicada.'),
            ];
        }

        if ($previsaoNext === null || $previsaoNext <= 0) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => __('Projeção do próximo ano indisponível'),
                'message' => __('Sem VAAF ou matrículas para estimar o exercício :ano.', ['ano' => (string) ($baseYear + 1)]),
            ];
        }

        if ($porEtapa === [] && $matBase > 0) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => __('Detalhe por etapa indisponível'),
                'message' => __('O gráfico de nível de ensino não foi gerado — verifique cadastro de curso/série na aba Matrículas.'),
            ];
        }

        if ($matPrev > 0 && $matBase > 0) {
            $pct = round((($matBase - $matPrev) / $matPrev) * 100, 1);
            if ($pct <= -5) {
                $alerts[] = [
                    'tone' => 'rose',
                    'title' => __('Queda de matrículas'),
                    'message' => __('Rede com :pct% de matrículas face a :ano anterior — alinhar com secretaria e Censo.', [
                        'pct' => number_format(abs($pct), 1, ',', '.'),
                        'ano' => (string) ($baseYear - 1),
                    ]),
                ];
            } elseif ($pct >= 5) {
                $alerts[] = [
                    'tone' => 'emerald',
                    'title' => __('Crescimento de matrículas'),
                    'message' => __('Avanço de :pct% face a :ano anterior.', [
                        'pct' => number_format($pct, 1, ',', '.'),
                        'ano' => (string) ($baseYear - 1),
                    ]),
                ];
            }
        }

        if ($previsaoBase !== null && $previsaoPrev !== null && $previsaoPrev > 0) {
            $finPct = round((($previsaoBase - $previsaoPrev) / $previsaoPrev) * 100, 1);
            if (abs($finPct) >= 3 && $matBase > 0 && $matPrev > 0) {
                $alerts[] = [
                    'tone' => $finPct >= 0 ? 'sky' : 'amber',
                    'title' => __('Mudança financeira indicativa'),
                    'message' => __('Previsão base variou :pct% (matrículas × VAAF por exercício).', [
                        'pct' => ($finPct >= 0 ? '+' : '').number_format($finPct, 1, ',', '.'),
                    ]),
                ];
            }
        }

        return $alerts;
    }

    /**
     * @return list<array{ano: string, vaaf: string, variacao: string, fonte: string}>
     */
    private function fundebYearSeries(City $city, int $anchorYear): array
    {
        $refs = $this->fundebRefs->listForCity($city)
            ->filter(static fn ($r) => ! FundebReferenceSource::isPlaceholder($r->fonte))
            ->unique(static fn ($r) => (int) $r->ano)
            ->sortByDesc('ano')
            ->values();

        if ($refs->isEmpty()) {
            $refs = $this->fundebRefs->listForCity($city)->unique(static fn ($r) => (int) $r->ano)->sortByDesc('ano')->values();
        }

        $rows = [];
        $prevVaaf = null;
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        foreach ($refs->take(6) as $ref) {
            $vaaf = (float) $ref->vaaf;
            $delta = null;
            if ($prevVaaf !== null && $prevVaaf > 0) {
                $delta = round((($vaaf - $prevVaaf) / $prevVaaf) * 100, 1);
            }
            $rows[] = [
                'ano' => (string) $ref->ano,
                'vaaf' => $fmt($vaaf),
                'variacao' => $delta !== null
                    ? (($delta >= 0 ? '+' : '').number_format($delta, 1, ',', '.').'%')
                    : '—',
                'fonte' => (string) ($ref->fonte ?? '—'),
                'is_anchor' => (int) $ref->ano === $anchorYear,
            ];
            $prevVaaf = $vaaf;
        }

        return $rows;
    }

    /**
     * @param  list<int|string>  $yearOptions
     * @return list<array{value: int, label: string}>
     */
    public static function normalizeYearOptions(array $yearOptions, int $selected): array
    {
        $years = [];
        foreach ($yearOptions as $y) {
            if (is_numeric($y)) {
                $years[(int) $y] = (int) $y;
            }
        }
        if ($years === []) {
            for ($y = $selected + 2; $y >= $selected - 8; $y--) {
                if ($y >= 2000) {
                    $years[$y] = $y;
                }
            }
        }
        if (! isset($years[$selected])) {
            $years[$selected] = $selected;
        }
        krsort($years);

        $out = [];
        foreach ($years as $y) {
            $out[] = ['value' => $y, 'label' => (string) $y];
        }

        return $out;
    }
}
