<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\Ieducar\DiscrepanciesRepository;
use App\Repositories\Ieducar\OverviewRepository;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Finance\MoneyMath;
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
        private OverviewRepository $overview,
        private DiscrepanciesRepository $discrepancies,
        private MunicipalTransferSnapshotRepository $transfers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildReport(City $city, IeducarFilterState $filters): array
    {
        $ano = $filters->yearFilterValue() ?? 0;
        if ($ano <= 0) {
            $ano = max(2000, (int) date('Y') - 1);
        }

        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        $overview = $this->overview->snapshot($city, $filters);
        $matriculas = (int) ($overview['kpis']['matriculas'] ?? $overview['total_matriculas'] ?? 0);

        $disc = $this->discrepancies->snapshot($city, $filters);
        $projection = FundebResourceProjection::build(
            $matriculas,
            (string) ($disc['year_label'] ?? (string) $ano),
            $overview,
            $disc,
            $city,
            $filters,
        );

        $expectedAnnual = (float) ($projection['previsao_referencia'] ?? 0);
        $expectedVaaf = (float) ($projection['vaaf_calculo'] ?? 0);
        $expectedFonte = (string) ($projection['vaa_fonte_label'] ?? '');

        $snapshots = $ibge !== null ? $this->transfers->forCityYear($city, $ano) : [];
        $fundebRows = $this->filterFundebTransfers($snapshots);
        $observedAnnual = round(array_sum(array_map(static fn ($r) => (float) $r->valor, $fundebRows)), 2);

        $delta = MoneyMath::roundMoney($observedAnnual - $expectedAnnual);
        $deltaPct = $expectedAnnual > 0
            ? round(($delta / $expectedAnnual) * 100, 1)
            : null;

        $thresholdPct = max(1.0, (float) config('ieducar.finance_realtime.alert_threshold_pct', 15));
        $alerts = $this->buildAlerts($expectedAnnual, $observedAnnual, $delta, $deltaPct, $fundebRows, $matriculas, $thresholdPct);

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
            'observed_annual' => $observedAnnual,
            'observed_annual_fmt' => DiscrepanciesFundingImpact::formatBrl($observedAnnual),
            'delta' => $delta,
            'delta_fmt' => DiscrepanciesFundingImpact::formatBrl(abs($delta)),
            'delta_sign' => $delta >= 0 ? 'positive' : 'negative',
            'delta_pct' => $deltaPct,
            'has_transfer_data' => $fundebRows !== [],
            'transfer_count' => count($fundebRows),
            'alerts' => $alerts,
            'extrato' => $this->buildExtratoVisual($fundebRows, $city, $ano),
            'lay_guide' => $this->layPersonGuide(),
            'methodology' => FundebImpactMethodology::panel($city, $filters),
            'data_sources_note' => $this->dataSourcesNote(),
            'bb_open_finance' => $this->bbOpenFinanceStatus(),
            'formula' => $matriculas > 0 && $expectedVaaf > 0
                ? __('Expectativa ≈ :mat matrículas × :vaaf/aluno = :total/ano', [
                    'mat' => number_format($matriculas, 0, ',', '.'),
                    'vaaf' => DiscrepanciesFundingImpact::formatBrl($expectedVaaf),
                    'total' => DiscrepanciesFundingImpact::formatBrl($expectedAnnual),
                ])
                : __('Importe VAAF municipal e matrículas activas para calcular a expectativa.'),
            'aviso' => (string) config('ieducar.finance_realtime.aviso', config('ieducar.fundeb.aviso_previsao', '')),
        ];
    }

    /**
     * @param  list<\App\Models\MunicipalTransferSnapshot>  $snapshots
     * @return list<\App\Models\MunicipalTransferSnapshot>
     */
    private function filterFundebTransfers(array $snapshots): array
    {
        $needles = config('ieducar.finance_realtime.program_keywords', [
            'fundeb', 'fnde', 'educacao basica', 'educação básica', 'manutencao', 'manutenção',
        ]);
        if (! is_array($needles)) {
            $needles = ['fundeb'];
        }

        $out = [];
        foreach ($snapshots as $row) {
            $blob = mb_strtolower((string) $row->programa_id.' '.(string) $row->programa_label.' '.(string) $row->fonte);
            foreach ($needles as $n) {
                if (str_contains($blob, mb_strtolower((string) $n))) {
                    $out[] = $row;
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<\App\Models\MunicipalTransferSnapshot>  $rows
     * @return list<array{date: string, description: string, credit: ?string, debit: ?string, balance: ?string, fonte: string, valor_fmt: string}>
     */
    private function buildExtratoVisual(array $rows, City $city, int $ano): array
    {
        $lines = [];
        $running = 0.0;
        foreach ($rows as $i => $row) {
            $valor = (float) $row->valor;
            $running += $valor;
            $imported = $row->imported_at?->format('d/m/Y') ?? '—';
            $lines[] = [
                'date' => $imported,
                'description' => trim((string) ($row->programa_label ?: $row->programa_id)),
                'credit' => $valor > 0 ? DiscrepanciesFundingImpact::formatBrl($valor) : null,
                'debit' => $valor < 0 ? DiscrepanciesFundingImpact::formatBrl(abs($valor)) : null,
                'balance' => DiscrepanciesFundingImpact::formatBrl($running),
                'fonte' => (string) $row->fonte,
                'valor_fmt' => DiscrepanciesFundingImpact::formatBrl($valor),
            ];
        }

        if ($lines === []) {
            $lines[] = [
                'date' => '—',
                'description' => __('Sem repasses FUNDEB importados para :city / :ano. Use Admin → Dados públicos → Repasses.', [
                    'city' => $city->name,
                    'ano' => (string) $ano,
                ]),
                'credit' => null,
                'debit' => null,
                'balance' => null,
                'fonte' => '—',
                'valor_fmt' => '—',
            ];
        }

        return $lines;
    }

    /**
     * @return list<array{severity: string, title: string, detail: string}>
     */
    private function buildAlerts(
        float $expected,
        float $observed,
        float $delta,
        ?float $deltaPct,
        array $fundebRows,
        int $matriculas,
        float $thresholdPct,
    ): array {
        $alerts = [];

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

        if ($expected > 0 && $observed > 0 && $deltaPct !== null && abs($deltaPct) >= $thresholdPct) {
            $alerts[] = [
                'severity' => $delta < 0 ? 'danger' : 'info',
                'title' => $delta < 0
                    ? __('Repasse observado abaixo da expectativa')
                    : __('Repasse observado acima da expectativa'),
                'detail' => __('Diferença de :pct% (:delta) — verifique cronograma FNDE, retenções e base de matrículas.', [
                    'pct' => number_format(abs($deltaPct), 1, ',', '.'),
                    'delta' => DiscrepanciesFundingImpact::formatBrl(abs($delta)),
                ]),
            ];
        }

        if ($expected > 0 && $observed > 0 && abs($delta) < ($expected * 0.02)) {
            $alerts[] = [
                'severity' => 'success',
                'title' => __('Valores próximos da expectativa'),
                'detail' => __('Diferença inferior a 2% no recorte anual importado.'),
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
                'text' => __('São valores que o governo federal registou como transferidos (Tesouro Transparente ou Portal da Transparência), depois de importados pelo administrador do sistema.'),
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
        return (string) config(
            'ieducar.finance_realtime.sources_note',
            __('Dados públicos: Tesouro Transparente e Portal da Transparência. Opcional: API Banco do Brasil (Open Finance) via credenciais no .env.'),
        );
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
                ? __('Open Finance BB configurado — integração em evolução; use repasses públicos importados.')
                : __('Para saldo em conta BB: IEDUCAR_BB_OPEN_FINANCE_ENABLED=true e credenciais OAuth.'),
        ];
    }
}
