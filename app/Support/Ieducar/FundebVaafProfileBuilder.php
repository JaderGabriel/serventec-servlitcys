<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebFndeEstadoVaafService;
use App\Services\Fundeb\FundebFndePublicationAlerts;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use App\Services\Fundeb\FundebMatriculasByYearService;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Fundeb\FundebReferenceSource;
use App\Support\Fundeb\FundebValueLexicon;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Perfil VAAF/receita FUNDEB por município: ano corrente de planejamento + anos futuros configurados.
 */
final class FundebVaafProfileBuilder
{
    public function __construct(
        private FundebFndeReceitaCsvService $fndeReceita,
        private FundebFndeEstadoVaafService $fndeEstadoVaaf,
        private FundebMatriculasByYearService $matriculas,
        private FundebFndePublicationAlerts $alerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(
        City $city,
        ?IeducarFilterState $filters = null,
        ?int $matriculasFiltroAtual = null,
        ?array $discrepanciesData = null,
        ?array $enrollmentData = null,
    ): array {
        $years = FundebOpenDataImportService::yearsForPlanningProfile();
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        $matByYear = $this->matriculas->forCityYears($city, $years);
        $yearBlocks = [];

        foreach ($years as $ano) {
            $yearBlocks[$ano] = $this->buildYearBlock($city, $ibge, $ano, $matByYear[$ano] ?? null);
        }

        $anchorAno = $filters !== null && $filters->hasYearSelected() && ! $filters->isAllSchoolYears()
            ? (int) $filters->ano_letivo
            : FundebOpenDataImportService::suggestedImportYear();

        $matAnchor = $matriculasFiltroAtual ?? (int) (($matByYear[$anchorAno]['usado'] ?? 0));
        $refAnchor = DiscrepanciesFundingImpact::resolveReference($city, $filters);

        $projection = $matAnchor > 0
            ? FundebResourceProjection::build(
                $matAnchor,
                __('Ano letivo :year', ['year' => (string) $anchorAno]),
                is_array($enrollmentData) ? $enrollmentData : [],
                $discrepanciesData,
                $city,
                $filters,
                $refAnchor,
            )
            : FundebResourceProjection::build(0, '', [], $discrepanciesData, $city, $filters, $refAnchor);

        $alertList = $this->alerts->evaluate($yearBlocks);

        return [
            'ibge' => $ibge,
            'city_name' => $city->name,
            'uf' => $city->uf,
            'planning_years' => $years,
            'anchor_ano' => $anchorAno,
            'years' => $yearBlocks,
            'ponderacoes_discrepancias' => config('ieducar.discrepancies.peso_por_check', []),
            'distribuicao_legal' => $projection['distribuicao_legal'] ?? [],
            'previsao_ano_corrente' => $projection,
            'portarias' => $this->collectPortariaLinks($yearBlocks),
            'alerts' => $alertList,
            'alerts_count' => [
                'danger' => count(array_filter($alertList, static fn (array $a): bool => ($a['severity'] ?? '') === 'danger')),
                'warning' => count(array_filter($alertList, static fn (array $a): bool => ($a['severity'] ?? '') === 'warning')),
                'info' => count(array_filter($alertList, static fn (array $a): bool => ($a['severity'] ?? '') === 'info')),
            ],
            'fontes' => $this->fontesResumo(),
        ];
    }

    /**
     * Indicador gerencial compacto no rodapé da consultoria (exercício actual e referência anterior).
     *
     * Com discrepâncias ou pendências de publicação, não projeta o exercício seguinte.
     *
     * @param  array{com_problema?: int, corrigiveis?: int}|null  $discrepanciesSummary
     * @return array<string, mixed>
     */
    public function buildDockMeter(
        City $city,
        IeducarFilterState $filters,
        ?int $matriculasFiltro = null,
        ?array $discrepanciesSummary = null,
    ): array {
        if (! $filters->hasYearSelected() || $filters->isAllSchoolYears()) {
            return self::emptyDockMeter();
        }

        $anchor = (int) $filters->ano_letivo;
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        $matByYear = $this->matriculas->forCityYears($city, [$anchor - 1, $anchor]);

        if ($matriculasFiltro !== null && $matriculasFiltro > 0) {
            $matByYear[$anchor] = array_merge($matByYear[$anchor] ?? [
                'ano' => $anchor,
                'ieducar' => $matriculasFiltro,
                'censo' => null,
                'usado' => $matriculasFiltro,
                'fonte_usada' => 'ieducar_filtro',
            ], [
                'usado' => $matriculasFiltro,
                'ieducar' => $matriculasFiltro,
                'fonte_usada' => 'ieducar_filtro',
            ]);
        }

        $previousBlock = $this->buildYearBlock($city, $ibge, $anchor - 1, $matByYear[$anchor - 1] ?? null);
        $currentBlock = $this->buildYearBlock($city, $ibge, $anchor, $matByYear[$anchor] ?? null);
        $yearBlocks = [$anchor - 1 => $previousBlock, $anchor => $currentBlock];
        $alerts = $this->alerts->evaluate($yearBlocks);
        $blocking = self::evaluateDockBlocking($anchor, $currentBlock, $alerts, $discrepanciesSummary);

        $currentFigure = self::dockConsolidatedFigure($currentBlock);
        $previousFigure = self::dockConsolidatedFigure($previousBlock);
        $variationPct = self::dockVariationPct($previousFigure['amount'], $currentFigure['amount']);

        $matriculas = (int) ($currentBlock['matriculas']['usado'] ?? 0);
        $secondary = [];

        if ($matriculas > 0) {
            $secondary[] = [
                'label' => __('Matrículas'),
                'value' => number_format($matriculas, 0, ',', '.'),
                'tone' => 'muted',
            ];
        }

        if ($variationPct !== null) {
            $secondary[] = [
                'label' => __('vs :ano', ['ano' => (string) ($anchor - 1)]),
                'value' => ($variationPct > 0 ? '+' : '').number_format($variationPct, 1, ',', '.').'%',
                'tone' => self::dockDeltaTone($variationPct),
            ];
        }

        $status = $blocking['status'];
        $statusLabel = self::dockStatusLabel($status);
        $hasFigure = $currentFigure['amount'] !== null && $currentFigure['amount'] > 0;

        $alert = null;
        if ($blocking['blocked']) {
            $primary = $blocking['reasons'][0] ?? null;
            $alert = [
                'severity' => is_array($primary) ? (string) ($primary['severity'] ?? 'warning') : 'warning',
                'message' => is_array($primary)
                    ? (string) ($primary['message'] ?? __('Há pendências — o ano seguinte não é projetado.'))
                    : __('Há pendências — o ano seguinte não é projetado.'),
            ];
        } elseif (! $hasFigure) {
            $status = 'neutral';
            $statusLabel = self::dockStatusLabel($status);
        }

        return [
            'available' => $hasFigure,
            'partial' => ! $hasFigure && ! $blocking['blocked'],
            'anchor_ano' => $anchor,
            'title' => __('FUNDEB'),
            'hint' => __('Valor publicado pelo FNDE ou consolidado do ano letivo. O ano seguinte não é projetado se houver pendências.'),
            'status' => $status,
            'status_label' => $statusLabel,
            'primary_value' => $hasFigure ? $currentFigure['display'] : '—',
            'primary_label' => $currentFigure['label'],
            'phase_label' => FundebValueLexicon::exercisePhaseLabel($anchor),
            'secondary' => $secondary,
            'variation_pct' => $variationPct,
            'alert' => $alert,
            'projection_blocked' => $blocking['blocked'],
            'next_year_note' => $blocking['blocked']
                ? __('Ano seguinte: sem projeção')
                : null,
        ];
    }

    /**
     * @return array{available: false, partial: bool, anchor_ano: ?int, title: string, hint: string, status: string, status_label: string, primary_value: string, primary_label: string, phase_label: string, secondary: list<empty>, variation_pct: null, alert: null, projection_blocked: false, next_year_note: null}
     */
    private static function emptyDockMeter(?int $anchor = null): array
    {
        return [
            'available' => false,
            'partial' => false,
            'anchor_ano' => $anchor,
            'title' => __('FUNDEB'),
            'hint' => __('Valor publicado pelo FNDE ou consolidado do ano letivo. O ano seguinte não é projetado se houver pendências.'),
            'status' => 'neutral',
            'status_label' => self::dockStatusLabel('neutral'),
            'primary_value' => '—',
            'primary_label' => __('Exercício'),
            'phase_label' => '',
            'secondary' => [],
            'variation_pct' => null,
            'alert' => null,
            'projection_blocked' => false,
            'next_year_note' => null,
        ];
    }

    /**
     * @param  list<array{id: string, severity: string, ano: ?int, titulo: string, mensagem: string, acao: ?string}>  $alerts
     * @param  array{com_problema?: int, corrigiveis?: int}|null  $discrepanciesSummary
     * @return array{blocked: bool, status: string, reasons: list<array{severity: string, message: string}>}
     */
    private static function evaluateDockBlocking(
        int $anchor,
        array $currentBlock,
        array $alerts,
        ?array $discrepanciesSummary,
    ): array {
        $reasons = [];
        $hasDanger = false;

        $comProblema = (int) ($discrepanciesSummary['com_problema'] ?? 0);
        $corrigiveis = (int) ($discrepanciesSummary['corrigiveis'] ?? 0);

        if ($comProblema > 0) {
            $reasons[] = [
                'severity' => $comProblema >= 50 ? 'danger' : 'warning',
                'message' => __('Inconsistências no cadastro (:n casos)', ['n' => number_format($comProblema, 0, ',', '.')]),
            ];
        } elseif ($corrigiveis > 0) {
            $reasons[] = [
                'severity' => 'warning',
                'message' => __(':n item(ns) a corrigir no cadastro', ['n' => number_format($corrigiveis, 0, ',', '.')]),
            ];
        }

        foreach ($alerts as $alert) {
            if (! is_array($alert)) {
                continue;
            }
            $severity = (string) ($alert['severity'] ?? '');
            if (! in_array($severity, ['danger', 'warning'], true)) {
                continue;
            }
            $ano = $alert['ano'] ?? null;
            if ($ano !== null && (int) $ano !== $anchor) {
                continue;
            }
            $reasons[] = [
                'severity' => $severity,
                'message' => (string) ($alert['titulo'] ?? $alert['mensagem'] ?? __('Pendência FUNDEB')),
            ];
        }

        $matUsado = (int) ($currentBlock['matriculas']['usado'] ?? 0);
        if ($matUsado <= 0 && $reasons === []) {
            $reasons[] = [
                'severity' => 'danger',
                'message' => __('Sem matrículas para calcular este ano letivo'),
            ];
        }

        $db = is_array($currentBlock['db_reference'] ?? null) ? $currentBlock['db_reference'] : null;
        if (
            $db !== null
            && FundebReferenceSource::isPlaceholder($db['fonte'] ?? null)
            && ! self::hasPublishedReceita($currentBlock)
        ) {
            $reasons[] = [
                'severity' => 'warning',
                'message' => __('VAAF municipal ainda não importado'),
            ];
        }

        foreach ($reasons as $reason) {
            if (($reason['severity'] ?? '') === 'danger') {
                $hasDanger = true;
                break;
            }
        }

        $blocked = $reasons !== [];
        $status = $blocked
            ? ($hasDanger ? 'danger' : 'warning')
            : 'success';

        return [
            'blocked' => $blocked,
            'status' => $status,
            'reasons' => $reasons,
        ];
    }

    /**
     * Valor gerencial: prioriza receita FNDE publicada; evita projeção matrículas×VAAF.
     *
     * @return array{amount: ?float, display: string, label: string}
     */
    private static function dockConsolidatedFigure(array $block): array
    {
        $ano = (int) ($block['ano'] ?? 0);
        $receita = is_array($block['receita'] ?? null) ? $block['receita'] : [];
        $totalReceita = isset($receita['total']) && is_numeric($receita['total'])
            ? (float) $receita['total']
            : null;

        if ($totalReceita !== null && $totalReceita > 0 && ($receita['disponivel'] ?? false)) {
            return [
                'amount' => $totalReceita,
                'display' => self::formatDockBrl($totalReceita, true),
                'label' => __('Receita FNDE :ano', ['ano' => (string) $ano]),
            ];
        }

        $phase = FundebValueLexicon::exercisePhase($ano);
        if ($phase === FundebValueLexicon::PHASE_PROJECTION) {
            return [
                'amount' => null,
                'display' => '—',
                'label' => __('Exercício :ano', ['ano' => (string) $ano]),
            ];
        }

        $base = isset($block['previsao_recursos']['base_anual'])
            ? (float) $block['previsao_recursos']['base_anual']
            : null;

        if ($base !== null && $base > 0) {
            return [
                'amount' => $base,
                'display' => self::formatDockBrl($base, true),
                'label' => __('Valor consolidado :ano', ['ano' => (string) $ano]),
            ];
        }

        return [
            'amount' => null,
            'display' => '—',
            'label' => __('Exercício :ano', ['ano' => (string) $ano]),
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function hasPublishedReceita(array $block): bool
    {
        $receita = is_array($block['receita'] ?? null) ? $block['receita'] : [];

        return ($receita['disponivel'] ?? false)
            && isset($receita['total'])
            && is_numeric($receita['total'])
            && (float) $receita['total'] > 0;
    }

    private static function dockVariationPct(?float $from, ?float $to): ?float
    {
        if ($from === null || $to === null || $from <= 0 || $to <= 0) {
            return null;
        }

        return round((($to - $from) / $from) * 100, 1);
    }

    private static function dockStatusLabel(string $status): string
    {
        return match ($status) {
            'success' => __('Em linha'),
            'warning' => __('Revisar'),
            'danger' => __('Priorizar'),
            default => __('Indisponível'),
        };
    }

    private static function formatDockBrl(?float $value, bool $compact = false): string
    {
        if ($value === null || $value <= 0) {
            return '—';
        }

        if (! $compact) {
            return DiscrepanciesFundingImpact::formatBrl($value);
        }

        if ($value >= 1_000_000_000) {
            return 'R$ '.number_format($value / 1_000_000_000, 1, ',', '.').' bi';
        }

        if ($value >= 1_000_000) {
            return 'R$ '.number_format($value / 1_000_000, 1, ',', '.').' mi';
        }

        if ($value >= 10_000) {
            return 'R$ '.number_format($value / 1_000, 0, ',', '.').' mil';
        }

        return DiscrepanciesFundingImpact::formatBrl($value);
    }

    private static function dockDeltaTone(?float $deltaPct): string
    {
        if ($deltaPct === null) {
            return 'muted';
        }

        if (abs($deltaPct) < 0.05) {
            return 'neutral';
        }

        return $deltaPct > 0 ? 'up' : 'down';
    }

    /**
     * @param  ?array{ano: int, ieducar: int, censo: ?int, usado: int, fonte_usada: string}  $matRow
     * @return array<string, mixed>
     */
    private function buildYearBlock(City $city, ?string $ibge, int $ano, ?array $matRow): array
    {
        $filters = new IeducarFilterState((string) $ano, null, null, null);
        $resolver = FundebMunicipalReferenceResolver::resolve($city, $filters);
        $db = $ibge !== null
            ? FundebMunicipioReference::query()->where('ibge_municipio', $ibge)->where('ano', $ano)->first()
            : null;

        $receitaRow = $ibge !== null ? $this->fndeReceita->rowForIbge($ibge, $ano) : null;
        $uf = strtoupper(trim((string) $city->uf));
        $estadoRow = strlen($uf) === 2 ? $this->fndeEstadoVaaf->rowForUf($uf, $ano) : null;
        $refEstadual = is_array($resolver['referencia_estadual'] ?? null) ? $resolver['referencia_estadual'] : null;
        $matUsado = (int) ($matRow['usado'] ?? 0);
        $totalReceita = $receitaRow !== null ? (float) $receitaRow['total_receita'] : null;
        $vaafEst = $totalReceita !== null && $matUsado > 0
            ? $this->fndeReceita->estimateVaafFromReceitaAndMatriculas($totalReceita, $matUsado)
            : null;

        $min = (float) config('ieducar.fundeb.open_data.vaaf_estimate_min', 2500);
        $max = (float) config('ieducar.fundeb.open_data.vaaf_estimate_max', 18000);
        $rawVaaf = $totalReceita !== null && $matUsado > 0 ? round($totalReceita / $matUsado, 2) : null;

        $previsaoBase = $matUsado > 0 && $vaafEst !== null
            ? round($matUsado * $vaafEst, 2)
            : ($matUsado > 0 && (float) ($resolver['vaaf'] ?? 0) > 0
                ? round($matUsado * (float) $resolver['vaaf'], 2)
                : null);

        $distCfg = config('ieducar.fundeb.distribuicao_legal', []);
        $distribuicao = $previsaoBase !== null && $previsaoBase > 0
            ? $this->legalSlice($previsaoBase, is_array($distCfg) ? $distCfg : [])
            : [];

        return [
            'ano' => $ano,
            'label' => $this->yearLabel($ano),
            'resolver' => $resolver,
            'db_reference' => $db !== null ? [
                'vaaf' => (float) $db->vaaf,
                'vaat' => $db->vaat !== null ? (float) $db->vaat : null,
                'complementacao_vaar' => $db->complementacao_vaar !== null ? (float) $db->complementacao_vaar : null,
                'fonte' => (string) $db->fonte,
                'tipo_valor' => $db->tipo_valor,
                'placeholder' => FundebReferenceSource::isPlaceholder($db->fonte),
                'imported_at' => $db->imported_at?->format('Y-m-d H:i'),
            ] : null,
            'receita' => [
                'disponivel' => $receitaRow !== null,
                'total' => $totalReceita,
                'complementacao_vaaf' => $receitaRow['complementacao_vaaf'] ?? null,
                'complementacao_vaat' => $receitaRow['complementacao_vaat'] ?? null,
                'complementacao_vaar' => $receitaRow['complementacao_vaar'] ?? null,
                'ano_publicacao' => $receitaRow['ano_publicacao'] ?? null,
                'csv_url' => $receitaRow['csv_url'] ?? null,
                'entidade' => $receitaRow['entidade'] ?? null,
            ],
            'matriculas' => $matRow ?? [
                'ano' => $ano,
                'ieducar' => 0,
                'censo' => null,
                'usado' => 0,
                'fonte_usada' => 'indisponivel',
            ],
            'vaaf_estimado' => [
                'valor' => $vaafEst,
                'bruto' => $rawVaaf,
                'fora_limites' => $rawVaaf !== null && ($rawVaaf < $min || $rawVaaf > $max),
                'fonte' => FundebReferenceSource::FONTE_FNDE_RECEITA_IEDUCAR,
            ],
            'referencia_estadual' => [
                'disponivel' => $estadoRow !== null || $refEstadual !== null,
                'vaaf' => $estadoRow !== null
                    ? (float) $estadoRow['vaaf']
                    : ($refEstadual !== null ? (float) ($refEstadual['vaaf'] ?? 0) : null),
                'total_receita' => $estadoRow['total_receita_vaaf'] ?? null,
                'complementacao_vaaf' => $estadoRow['complementacao_vaaf'] ?? null,
                'ano_publicacao' => $estadoRow['ano_publicacao'] ?? ($refEstadual['ano'] ?? null),
                'pdf_url' => $estadoRow['pdf_url'] ?? null,
                'uf' => $uf !== '' ? $uf : null,
                'fonte_label' => $refEstadual['fonte_label'] ?? null,
            ],
            'previsao_recursos' => [
                'base_anual' => $previsaoBase,
                'formula' => $previsaoBase !== null && $vaafEst !== null
                    ? __(':mat × :vaaf (estimativa portaria ÷ matrículas)', [
                        'mat' => number_format($matUsado, 0, ',', '.'),
                        'vaaf' => number_format($vaafEst, 2, ',', '.'),
                    ])
                    : null,
            ],
            'distribuicao_planejada' => $distribuicao,
        ];
    }

    private function yearLabel(int $ano): string
    {
        $cy = (int) date('Y');
        if ($ano === $cy) {
            return __('Exercício atual (:ano)', ['ano' => (string) $ano]);
        }
        if ($ano === $cy + 1) {
            return __('Próximo exercício (:ano) — planejamento', ['ano' => (string) $ano]);
        }
        if ($ano > $cy) {
            return __(':ano (futuro)', ['ano' => (string) $ano]);
        }

        return (string) $ano;
    }

    /**
     * @param  array<string, mixed>  $distCfg
     * @return list<array<string, mixed>>
     */
    private function legalSlice(float $base, array $distCfg): array
    {
        $pisos = is_array($distCfg['pisos'] ?? null) ? $distCfg['pisos'] : [];
        $itens = [];
        foreach ($pisos as $piso) {
            if (! is_array($piso)) {
                continue;
            }
            $pct = (float) ($piso['percentual_minimo'] ?? $piso['percentual_maximo'] ?? 0);
            $valor = $pct > 0 ? round($base * ($pct / 100), 2) : null;
            $itens[] = [
                'id' => $piso['id'] ?? '',
                'titulo' => $piso['titulo'] ?? '',
                'percentual' => $pct,
                'valor_planejado' => $valor,
                'descricao' => $piso['descricao'] ?? '',
            ];
        }

        return $itens;
    }

    /**
     * @param  array<int, array<string, mixed>>  $yearBlocks
     * @return list<array{ano: int, url: string, publicacao: ?int}>
     */
    private function collectPortariaLinks(array $yearBlocks): array
    {
        $links = [];
        foreach ($yearBlocks as $ano => $block) {
            $url = $block['receita']['csv_url'] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }
            $links[] = [
                'ano' => (int) $ano,
                'url' => $url,
                'publicacao' => $block['receita']['ano_publicacao'] ?? null,
            ];
        }

        return $links;
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    private function fontesResumo(): array
    {
        return [
            ['key' => 'fnde_portaria', 'label' => __('Portaria FNDE — CSV receita total por ente'), 'url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas'],
            ['key' => 'fnde_estado_vaaf', 'label' => __('Consultas FNDE — VAAF estimado por UF/DF (PDF)'), 'url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas'],
            ['key' => 'ckan', 'label' => __('FNDE dados abertos (CKAN)'), 'url' => (string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos')],
            ['key' => 'ieducar', 'label' => __('Matrículas ativas (i-Educar)'), 'url' => ''],
            ['key' => 'censo', 'label' => __('Censo INEP (agregado municipal)'), 'url' => 'https://www.gov.br/inep/pt-br/areas-de-atuacao/pesquisas-estatisticas-e-indicadores/censo-escolar/resultado'],
        ];
    }
}
