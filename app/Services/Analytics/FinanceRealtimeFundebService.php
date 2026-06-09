<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\Ieducar\DiscrepanciesRepository;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Finance\MoneyMath;
use App\Support\Funding\FinanceRealtimeYearEndOutlook;
use App\Support\Funding\FundebExtratoFontePriority;
use App\Support\Funding\FundebExtratoVisualBuilder;
use App\Support\Funding\FundebPortariaExpectation;
use App\Support\Funding\FundebTransferScope;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebImpactMethodology;
use App\Support\Ieducar\FundebResourceProjection;

/**
 * Conciliação em tempo quase real: repasses públicos observados × expectativa FUNDEB (VAAF × matrículas).
 *
 * Fontes: Tesouro Transparente, Portal da Transparência (import admin). Conta BB exige credenciais Open Finance (opcional).
 */
final class FinanceRealtimeFundebService
{
    public function __construct(
        private DiscrepanciesRepository $discrepancies,
        private MunicipalTransferSnapshotRepository $transfers,
        private FundebMunicipioReferenceRepository $fundebReferences,
    ) {}

    /**
     * @param  array<string, mixed>|null  $municipalityContext  Contexto da faixa de impacto (evita Discrepâncias + Visão geral).
     * @return array<string, mixed>
     */
    public function buildReport(City $city, IeducarFilterState $filters, ?array $municipalityContext = null): array
    {
        $ano = $filters->yearFilterValue() ?? 0;
        if ($ano <= 0) {
            $ano = max(2000, (int) date('Y') - 1);
        }

        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        $ctx = is_array($municipalityContext) ? $municipalityContext : [];
        $fundingRef = is_array($ctx['funding_reference'] ?? null) ? $ctx['funding_reference'] : null;

        $matriculas = (int) ($ctx['total_matriculas'] ?? 0);
        $alunosDistintos = isset($ctx['total_alunos_distintos']) && is_numeric($ctx['total_alunos_distintos'])
            ? (int) $ctx['total_alunos_distintos']
            : null;
        $baseCalculo = isset($ctx['base_calculo_fundeb']) && is_numeric($ctx['base_calculo_fundeb'])
            ? (int) $ctx['base_calculo_fundeb']
            : 0;
        $yearLabel = (string) ($ctx['year_label'] ?? '');

        if ($baseCalculo <= 0 || $fundingRef === null) {
            $light = $this->discrepancies->lightFundingContext($city, $filters);
            $matriculas = (int) ($light['total_matriculas'] ?? $matriculas);
            $alunosDistintos = isset($light['total_alunos_distintos']) && is_numeric($light['total_alunos_distintos'])
                ? (int) $light['total_alunos_distintos']
                : $alunosDistintos;
            $baseCalculo = (int) ($light['base_calculo_fundeb'] ?? $baseCalculo);
            if ($baseCalculo <= 0) {
                $baseCalculo = $matriculas;
            }
            $yearLabel = (string) ($light['year_label'] ?? $yearLabel);
            $fundingRef = is_array($light['funding_reference'] ?? null) ? $light['funding_reference'] : $fundingRef;
        }
        if ($baseCalculo <= 0) {
            $baseCalculo = $matriculas;
        }

        $overview = [
            'kpis' => [
                'matriculas' => $matriculas > 0 ? $matriculas : null,
                'alunos_distintos' => $alunosDistintos > 0 ? $alunosDistintos : null,
            ],
            'total_matriculas' => $matriculas > 0 ? $matriculas : null,
        ];

        $disc = [
            'year_label' => $yearLabel !== '' ? $yearLabel : (string) $ano,
            'funding_reference' => $fundingRef,
            'summary' => is_array($ctx['summary'] ?? null) ? $ctx['summary'] : [],
            'total_matriculas' => $matriculas > 0 ? $matriculas : null,
            'total_alunos_distintos' => $alunosDistintos > 0 ? $alunosDistintos : null,
        ];

        $projection = FundebResourceProjection::build(
            $baseCalculo,
            (string) ($disc['year_label'] ?? (string) $ano),
            $overview,
            $disc,
            $city,
            $filters,
            null,
            $matriculas,
            $alunosDistintos > 0 ? $alunosDistintos : null,
        );

        $expectedVaaf = (float) ($projection['vaaf_calculo'] ?? 0);
        $expectedFonte = (string) ($projection['vaa_fonte_label'] ?? '');
        $baseCalculoExpectativa = $baseCalculo > 0 ? $baseCalculo : $matriculas;

        $fundebRef = $this->fundebReferences->findForCityYear($city, $ano);
        $expectation = FundebPortariaExpectation::buildAnnual(
            $baseCalculoExpectativa,
            $expectedVaaf,
            $fundebRef,
        );
        $expectedAnnual = (float) ($expectation['annual'] ?? ($projection['previsao_referencia'] ?? 0));

        $snapshots = $ibge !== null ? $this->transfers->forCityYear($city, $ano) : [];
        $snapshotsMunicipal = FundebTransferScope::municipalSnapshotsOnly($snapshots);
        $fundebRowsAll = $this->filterFundebTransfers($snapshotsMunicipal);
        $fundebRows = FundebExtratoFontePriority::pickPrimaryFundebRows($fundebRowsAll);
        $observedAnnual = round(array_sum(array_map(static fn ($r) => (float) $r->valor, $fundebRows)), 2);
        $periodicSchedule = FundebPortariaExpectation::periodicSchedule($expectedAnnual, $ano, $fundebRows);

        $delta = MoneyMath::roundMoney($observedAnnual - $expectedAnnual);
        $deltaPct = $expectedAnnual > 0
            ? round(($delta / $expectedAnnual) * 100, 1)
            : null;

        $yearEndOutlook = FinanceRealtimeYearEndOutlook::build(
            $expectedAnnual,
            $observedAnnual,
            $ano,
            $periodicSchedule,
        );

        $alerts = $this->buildAlerts(
            $expectedAnnual,
            $fundebRows,
            $matriculas,
            $snapshots,
            $snapshotsMunicipal,
            $ano,
        );

        if ($ibge === null) {
            array_unshift($alerts, [
                'severity' => 'warning',
                'title' => __('Código IBGE do município ausente'),
                'detail' => __('Cadastre o IBGE em Admin → Municípios para cruzar repasses públicos importados com a expectativa FUNDEB.'),
            ]);
        }

        return [
            'available' => $ibge !== null,
            'city_name' => $city->name,
            'uf' => $city->uf,
            'ibge' => $ibge,
            'ano' => $ano,
            'year_label' => (string) ($disc['year_label'] ?? (string) $ano),
            'matriculas' => $matriculas,
            'expected_annual' => $expectedAnnual,
            'expected_annual_fmt' => DiscrepanciesFundingImpact::formatBrl($expectedAnnual),
            'expected_vaaf' => $expectedVaaf,
            'expected_vaaf_fmt' => DiscrepanciesFundingImpact::formatBrl($expectedVaaf),
            'expected_fonte' => $expectedFonte,
            'expected_source' => (string) ($expectation['source'] ?? 'matricula_vaaf'),
            'expected_monthly' => (float) ($periodicSchedule['monthly'] ?? 0),
            'expected_monthly_fmt' => DiscrepanciesFundingImpact::formatBrl((float) ($periodicSchedule['monthly'] ?? 0)),
            'expected_periodic' => (float) ($periodicSchedule['periodic_expected'] ?? 0),
            'expected_periodic_fmt' => DiscrepanciesFundingImpact::formatBrl((float) ($periodicSchedule['periodic_expected'] ?? 0)),
            'expected_periodic_label' => (string) ($periodicSchedule['label'] ?? ''),
            'portaria_adjustments' => $expectation['adjustments'] ?? [],
            'portaria_adjustments_note' => $expectation['adjustments_note'] ?? null,
            'portaria_publication_year' => $expectation['portaria_publication_year'] ?? null,
            'portaria_url' => $expectation['url_portaria'] ?? null,
            'receita_portaria' => $expectation['receita_portaria'] ?? null,
            'receita_portaria_fmt' => isset($expectation['receita_portaria']) && is_numeric($expectation['receita_portaria'])
                ? DiscrepanciesFundingImpact::formatBrl((float) $expectation['receita_portaria'])
                : null,
            'observed_annual' => $observedAnnual,
            'observed_annual_fmt' => DiscrepanciesFundingImpact::formatBrl($observedAnnual),
            'delta' => $delta,
            'delta_fmt' => DiscrepanciesFundingImpact::formatBrl(abs($delta)),
            'delta_sign' => $delta >= 0 ? 'positive' : 'negative',
            'delta_pct' => $deltaPct,
            'year_end_outlook' => $yearEndOutlook,
            'has_transfer_data' => $fundebRows !== [],
            'transfer_count' => count($fundebRows),
            'alerts' => $alerts,
            'extrato' => (new FundebExtratoVisualBuilder)->build($fundebRowsAll, $city, $ano, $expectedAnnual, $snapshots),
            'lay_guide' => $this->layPersonGuide(),
            'methodology_compact' => FundebImpactMethodology::compactFromContext($ctx),
            'data_sources_note' => $this->dataSourcesNote(),
            'bb_open_finance' => $this->bbOpenFinanceStatus(),
            'formula' => $this->expectationFormula(
                $baseCalculoExpectativa,
                $expectedVaaf,
                $expectedAnnual,
                $expectation,
                $periodicSchedule,
            ),
            'aviso' => (string) config('ieducar.finance_realtime.aviso', config('ieducar.fundeb.aviso_previsao', '')),
        ];
    }

    /**
     * Payload mínimo para a aba (lazy load sem ano ou pré-visualização).
     *
     * @return array<string, mixed>
     */
    public function tabShell(City $city, IeducarFilterState $filters): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        $ano = $filters->yearFilterValue() ?? max(2000, (int) date('Y') - 1);
        $alerts = [];

        if ($ibge === null) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => __('Código IBGE do município ausente'),
                'detail' => __('Cadastre o IBGE em Admin → Municípios para cruzar repasses públicos com a expectativa FUNDEB.'),
            ];
        }

        if ($filters->hasYearSelected() && $filters->isAllSchoolYears()) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => __('Ano letivo específico recomendado'),
                'detail' => __('Aplique um ano letivo concreto (não «Todos os anos») para alinhar matrículas e repasses ao mesmo exercício.'),
            ];
        }

        return [
            'available' => $ibge !== null,
            'city_name' => (string) $city->name,
            'uf' => (string) ($city->uf ?? ''),
            'ibge' => $ibge,
            'ano' => $ano,
            'year_label' => $filters->yearLabelForDisplay() !== '' ? $filters->yearLabelForDisplay() : (string) $ano,
            'expected_annual_fmt' => '—',
            'observed_annual_fmt' => '—',
            'delta_fmt' => '—',
            'delta_sign' => 'positive',
            'delta_pct' => null,
            'transfer_count' => 0,
            'alerts' => $alerts,
            'extrato' => [],
            'lay_guide' => $this->layPersonGuide(),
            'methodology_compact' => null,
            'data_sources_note' => $this->dataSourcesNote(),
            'bb_open_finance' => $this->bbOpenFinanceStatus(),
            'formula' => __('Após aplicar os filtros, a expectativa usa matrículas ativas × VAAF municipal importado.'),
            'aviso' => (string) config('ieducar.finance_realtime.aviso', config('ieducar.fundeb.aviso_previsao', '')),
        ];
    }

    /**
     * @param  list<MunicipalTransferSnapshot>  $snapshots
     * @return list<MunicipalTransferSnapshot>
     */
    private function filterFundebTransfers(array $snapshots): array
    {
        $out = [];
        foreach ($snapshots as $row) {
            if (FundebTransferScope::matchesFinanceRealtimeProgram($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @return list<array{severity: string, title: string, detail: string}>
     */
    private function buildAlerts(
        float $expected,
        array $fundebRows,
        int $matriculas,
        array $snapshots,
        array $snapshotsMunicipal,
        int $ano,
    ): array {
        $alerts = [];

        if ($fundebRows === [] && $snapshots !== [] && $snapshotsMunicipal === []) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => __('Apenas totais por UF (não por município)'),
                'detail' => __('A publicação STN (tesouro_publicacao) grava o total da UF — não use para comparar municípios. Execute php artisan funding:rebuild-finance-realtime --ano=:ano --all-cities para repasses municipais (CKAN/SISWEB/BB).', [
                    'ano' => (string) $ano,
                ]),
            ];
        }

        if ($fundebRows === []) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => __('Sem repasses observados na base'),
                'detail' => __('Importe Tesouro/Portal na administração para comparar com a expectativa FUNDEB.'),
            ];
        }

        if ($expected <= 0 && $matriculas > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => __('Expectativa FUNDEB indisponível'),
                'detail' => __('Importe VAAF municipal (FNDE) em Compatibilidade i-Educar / FUNDEB.'),
            ];
        }

        return $alerts;
    }

    /**
     * @return list<array{icon: string, title: string, text: string}>
     */
    private function layPersonGuide(): array
    {
        return [
            [
                'icon' => '1',
                'title' => __('O que é a «expectativa»?'),
                'text' => __('É uma estimativa de quanto o município deveria receber no ano, multiplicando o número de alunos na rede pelo valor-aluno (VAAF) publicado ou importado do FNDE. Não é o extrato bancário.'),
            ],
            [
                'icon' => '2',
                'title' => __('O que são «repasses observados»?'),
                'text' => __('São valores que o governo federal registou como transferidos (CSV municipal do Tesouro e espelho SISWEB), importados em Admin → Dados públicos. No extrato, CKAN e SISWEB aparecem lado a lado; a linha «Conciliação entre fontes» resume se os totais batem.'),
            ],
            [
                'icon' => '3',
                'title' => __('Por que pode haver diferença?'),
                'text' => __('O repasse real chega em parcelas, pode incluir complementações, retenções ou atrasos. Matrículas do i-Educar podem divergir do Censo usado pelo FNDE.'),
            ],
            [
                'icon' => '4',
                'title' => __('Conta no Banco do Brasil'),
                'text' => __('Ligação directa à conta municipal exige autorização Open Finance do titular. Enquanto isso, use o extrato simulado abaixo com os dados públicos já importados.'),
            ],
        ];
    }

    private function dataSourcesNote(): string
    {
        $default = __('Extratos analisados: publicação FUNDEB (Tesouro Transparente), REPASSES/SISWEB e extrato BB (export ou Open Finance). Importe via Admin → Dados públicos → Repasses.');

        return (string) config('ieducar.finance_realtime.sources_note', $default);
    }

    /**
     * @param  array<string, mixed>  $expectation
     * @param  array<string, mixed>  $periodicSchedule
     */
    private function expectationFormula(
        int $baseCalculo,
        float $expectedVaaf,
        float $expectedAnnual,
        array $expectation,
        array $periodicSchedule,
    ): string {
        if ($baseCalculo <= 0 || $expectedVaaf <= 0) {
            return __('Importe VAAF municipal e matrículas ativas para calcular a expectativa.');
        }

        $baseLine = __('Base matrículas × VAAF: :mat × :vaaf = :base/ano', [
            'mat' => number_format($baseCalculo, 0, ',', '.'),
            'vaaf' => DiscrepanciesFundingImpact::formatBrl($expectedVaaf),
            'base' => DiscrepanciesFundingImpact::formatBrl((float) ($expectation['base_mat_vaaf'] ?? 0)),
        ]);

        if (($expectation['source'] ?? '') === 'portaria_receita' && isset($expectation['receita_portaria'])) {
            $pub = $expectation['portaria_publication_year'] ?? null;
            $annualLine = $pub !== null
                ? __('Expectativa anual (receita portaria FNDE :ano): :total', [
                    'ano' => (string) $pub,
                    'total' => DiscrepanciesFundingImpact::formatBrl($expectedAnnual),
                ])
                : __('Expectativa anual (receita portaria FNDE): :total', [
                    'total' => DiscrepanciesFundingImpact::formatBrl($expectedAnnual),
                ]);
        } else {
            $annualLine = __('Expectativa anual: :total', [
                'total' => DiscrepanciesFundingImpact::formatBrl($expectedAnnual),
            ]);
        }

        $periodicLine = (string) ($periodicSchedule['label'] ?? '');

        return implode(' · ', array_filter([$baseLine, $annualLine, $periodicLine]));
    }

    /**
     * @return array{enabled: bool, configured: bool, message: string}
     */
    private function bbOpenFinanceStatus(): array
    {
        $enabled = filter_var(config('ieducar.finance_realtime.bb_enabled', false), FILTER_VALIDATE_BOOL);
        $clientId = trim((string) config('ieducar.finance_realtime.bb_client_id', ''));

        return [
            'enabled' => $enabled,
            'configured' => $clientId !== '',
            'message' => $enabled && $clientId !== ''
                ? __('Open Finance BB configurado — consulta automática de extrato em evolução. Use download CSV (IEDUCAR_BB_EXTRATO_URL_TEMPLATE) ou docs/BB_EXTRATO_OPEN_FINANCE.md.')
                : __('Extrato BB: configure IEDUCAR_BB_EXTRATO_URL_TEMPLATE (download automático) ou copie CSV para storage/app/funding/bb_extrato/. Open Finance: ver docs/BB_EXTRATO_OPEN_FINANCE.md.'),
        ];
    }
}
