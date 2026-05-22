<?php

namespace App\Support\Dashboard;

use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Faixa visual no topo de cada aba (até Censo): impacto no saldo indicativo + status municipal no filtro.
 */
final class AnalyticsTabImpactBuilder
{
    /** Abas sem status na faixa (ex.: Cadastro / Visão geral). */
    public const TABS_WITHOUT_STATUS = ['overview'];

    /** Abas com status consolidado do sistema (não só o recorte da aba). */
    public const TABS_SYSTEM_STATUS = ['municipality_health'];

    /** @var list<string> */
    public const TABS_WITH_STRIP = [
        'overview',
        'enrollment',
        'network',
        'school_units',
        'inclusion',
        'performance',
        'attendance',
        'fundeb',
        'other_funding',
        'work_done',
        'municipality_health',
        'discrepancies',
    ];

    /**
     * @param  array<string, mixed>|null  $municipalityContext
     * @param  array<string, mixed>  $tabData
     * @return array<string, mixed>
     */
    public static function build(
        string $tab,
        bool $yearFilterReady,
        ?array $municipalityContext,
        array $tabData = [],
    ): array {
        $def = self::definition($tab);

        $showStatus = ! in_array($tab, self::TABS_WITHOUT_STATUS, true);
        $statusMode = in_array($tab, self::TABS_SYSTEM_STATUS, true) ? 'system' : 'tab';

        if (! $yearFilterReady) {
            return [
                'ready' => false,
                'tab' => $tab,
                'title' => $def['title'],
                'purpose' => $def['purpose'],
                'show_status' => $showStatus,
                'status_mode' => $statusMode,
                'status' => 'neutral',
                'status_label' => __('Aguardando filtros'),
                'status_help' => '',
                'status_issues' => [],
                'tab_score' => null,
                'saldo' => null,
                'metrics' => [],
            ];
        }

        $ctx = $municipalityContext ?? [];
        $tabStatus = self::tabStatus($tab, $tabData, $ctx);
        $tabStatus = self::applyQueryError($tab, $tabData, $tabStatus);
        $statusIssues = self::collectStatusIssues($tab, $tabData, $ctx, $tabStatus);
        $statusHelp = self::statusHelp($tab, $def, $ctx, $statusIssues, $statusMode);
        $metrics = self::tabMetrics($tab, $tabData, $ctx);

        $saldo = self::resolveTabSaldo($tab, $tabData, $ctx, $tabStatus);

        return [
            'ready' => true,
            'tab' => $tab,
            'title' => $def['title'],
            'purpose' => $def['purpose'],
            'impact_note' => $def['impact_note'],
            'show_status' => $showStatus,
            'status_mode' => $statusMode,
            'status' => $tabStatus['status'],
            'status_label' => $tabStatus['label'],
            'status_help' => $statusHelp,
            'status_issues' => $statusIssues,
            'tab_score' => $tabStatus['score'],
            'municipality_score' => (int) ($ctx['compliance_score'] ?? 0),
            'municipality_status' => (string) ($ctx['compliance_status'] ?? 'neutral'),
            'municipality_label' => (string) ($ctx['compliance_label'] ?? ''),
            'saldo' => $saldo,
            'metrics' => $metrics,
            'pendencias' => (int) ($ctx['pendencias_cadastro'] ?? 0),
            'matriculas' => $ctx['total_matriculas'] ?? null,
        ];
    }

    /**
     * @return array{title: string, purpose: string, impact_note: string}
     */
    private static function definition(string $tab): array
    {
        return match ($tab) {
            'overview' => [
                'title' => __('Visão Geral'),
                'purpose' => __('Totais de escola, turma e matrícula no município com os filtros aplicados; resumo de NEE e oferta de rede.'),
                'impact_note' => __('Base do FUNDEB e do Censo: volume de matrículas válidas no filtro.'),
            ],
            'enrollment' => [
                'title' => __('Matrículas'),
                'purpose' => __('Matrículas ativas, turmas, ocupação e distorção idade-série (critério INEP).'),
                'impact_note' => __('Matrícula inconsistente ou distorção elevada aumentam risco de glosa no Censo e no VAAR.'),
            ],
            'network' => [
                'title' => __('Rede & Oferta'),
                'purpose' => __('Capacidade, vagas ociosas e distribuição por turno, segmento e escola.'),
                'impact_note' => __('Ociosidade e oferta desalinhada afetam planejamento e custos de transporte/PNAE.'),
            ],
            'school_units' => [
                'title' => __('Unidades Escolares'),
                'purpose' => __('Mapa, geografia, lista de espera e cobertura por unidade no filtro.'),
                'impact_note' => __('Escolas sem INEP ou fora do cadastro impactam indicadores VAAR e repasses.'),
            ],
            'inclusion' => [
                'title' => __('Inclusão & Diversidade'),
                'purpose' => __('NEE, equidade, recurso de prova e cruzamentos com matrículas ativas.'),
                'impact_note' => __('Subnotificação de NEE e inconsistências VAAR-inclusão pesam no saldo indicativo.'),
            ],
            'performance' => [
                'title' => __('Desempenho'),
                'purpose' => __('Abandono, evasão, aprovação e painéis INEP/SAEB quando disponíveis.'),
                'impact_note' => __('Indicadores fracos e cadastro incompleto reforçam condicionalidades VAAR.'),
            ],
            'attendance' => [
                'title' => __('Frequência'),
                'purpose' => __('Registos de faltas agregados por mês conforme matrícula e turma filtradas.'),
                'impact_note' => __('Frequência irregular pode sinalizar risco operacional e de programas (PNAE/transporte).'),
            ],
            'fundeb' => [
                'title' => __('FUNDEB'),
                'purpose' => __('Condicionalidades, VAAF, previsão de recursos e distribuição legal no filtro.'),
                'impact_note' => __('Previsão de repasse e perdas por cadastro (Discrepâncias) no mesmo recorte.'),
            ],
            'other_funding' => [
                'title' => __('Financiamentos'),
                'purpose' => __('Programas complementares (PNAE, PNATE, PDDE, etc.) e consultas públicas.'),
                'impact_note' => __('Alertas em programas somam risco ao saldo municipal além do FUNDEB base.'),
            ],
            'work_done' => [
                'title' => __('Censo'),
                'purpose' => __('Turmas, matrículas, enturmações, ritmo de cadastro e meta vs. ano anterior.'),
                'impact_note' => __('Pendências de exportação Censo ligam diretamente ao fundeb-base e repasse.'),
            ],
            'municipality_health' => [
                'title' => __('Diagnóstico'),
                'purpose' => __('Índice de conformidade, prioridades de cadastro, VAAF e leitura temática do município.'),
                'impact_note' => __('Consolida Discrepâncias, FUNDEB e fontes públicas numa visão executiva.'),
            ],
            'discrepancies' => [
                'title' => __('Discrepâncias'),
                'purpose' => __('Rotinas de cadastro com impacto indicativo em FUNDEB, VAAR e Censo — por escola e tipo.'),
                'impact_note' => __('Perda e ganho potencial usam o VAAF municipal do filtro (ou prévia federal).'),
            ],
            default => [
                'title' => $tab,
                'purpose' => '',
                'impact_note' => '',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function tabStatus(string $tab, array $tabData, array $ctx): array
    {
        $municipalScore = (int) ($ctx['compliance_score'] ?? 0);
        $municipalStatus = (string) ($ctx['compliance_status'] ?? 'neutral');

        $computed = match ($tab) {
            'overview' => self::statusOverview($tabData, $ctx),
            'enrollment' => self::statusEnrollment($tabData),
            'network' => self::statusNetwork($tabData),
            'school_units' => self::statusSchoolUnits($tabData),
            'inclusion' => self::statusInclusion($tabData),
            'performance' => self::statusPerformance($tabData),
            'attendance' => self::statusAttendance($tabData),
            'fundeb' => self::statusFundeb($tabData),
            'other_funding' => self::statusOtherFunding($tabData),
            'work_done' => self::statusWorkDone($tabData),
            'municipality_health' => self::statusSystemConsolidated($tabData, $ctx),
            'discrepancies' => self::statusDiscrepancies($tabData, $ctx),
            default => ['status' => $municipalStatus, 'label' => (string) ($ctx['compliance_label'] ?? ''), 'score' => $municipalScore, 'share_label' => null, 'share_value' => null],
        };

        if (($computed['score'] ?? null) === null && $municipalScore > 0) {
            $computed['score'] = $municipalScore;
        }

        return $computed;
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusOverview(array $tabData, array $ctx): array
    {
        $kpis = is_array($tabData['overview'] ?? null)
            ? ($tabData['overview']['kpis'] ?? $tabData['overview'])
            : ($tabData['overviewData']['kpis'] ?? null);
        $kpis = is_array($kpis) ? $kpis : [];
        $mat = (int) ($kpis['matriculas'] ?? $ctx['total_matriculas'] ?? 0);
        $pend = (int) ($ctx['pendencias_cadastro'] ?? 0);

        if ($mat <= 0) {
            return ['status' => 'warning', 'label' => __('Sem matrículas no filtro'), 'score' => 40, 'share_label' => null, 'share_value' => null];
        }

        $score = max(0, min(100, 100 - min(50, $pend * 3)));

        return [
            'status' => AnalyticsMunicipalityContext::statusFromScore($score),
            'label' => $pend > 0
                ? __(':n pendência(s) de cadastro no recorte', ['n' => $pend])
                : __('Rede cadastrada no filtro'),
            'score' => $score,
            'share_label' => __('Matrículas realizadas (filtro)'),
            'share_value' => number_format($mat, 0, ',', '.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusEnrollment(array $tabData): array
    {
        $data = is_array($tabData['enrollment'] ?? null) ? $tabData['enrollment'] : ($tabData['enrollmentData'] ?? []);
        $d = is_array($data['distorcao'] ?? null) ? $data['distorcao'] : [];
        $pct = isset($d['pct']) ? (float) $d['pct'] : null;

        if ($pct === null) {
            return ['status' => 'neutral', 'label' => __('Distorção indisponível'), 'score' => null, 'share_label' => null, 'share_value' => null];
        }

        $score = (int) max(0, min(100, 100 - $pct * 1.8));
        $status = $pct >= 25 ? 'danger' : ($pct >= 12 ? 'warning' : 'success');

        return [
            'status' => $status,
            'label' => __('Distorção idade-série: :p%', ['p' => number_format($pct, 1, ',', '.')]),
            'score' => $score,
            'share_label' => __('Impacto Censo/VAAR'),
            'share_value' => $pct >= 15 ? __('Elevado') : ($pct >= 8 ? __('Moderado') : __('Baixo')),
        ];
    }

    /**
     * Impacto indicativo da ociosidade (vagas × VAAF × peso), alinhado a Discrepâncias «rede_vagas_ociosas».
     *
     * @param  array<string, mixed>  $tabData
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    /**
     * Abas em que o saldo municipal (Discrepâncias) é o valor principal.
     *
     * @return list<string>
     */
    private static function tabsWithMunicipalSaldo(): array
    {
        return [
            'fundeb',
            'other_funding',
            'work_done',
            'municipality_health',
            'discrepancies',
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @param  array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}  $tabStatus
     * @return array<string, mixed>|null
     */
    private static function resolveTabSaldo(string $tab, array $tabData, array $ctx, array $tabStatus): ?array
    {
        $raw = match ($tab) {
            'network' => self::saldoFromNetworkOffer($tabData),
            'enrollment' => self::saldoFromEnrollmentDistorcao($tabData),
            'inclusion' => self::saldoFromInclusion($tabData),
            'school_units' => self::saldoFromSchoolUnitsGeo($tabData),
            'overview' => self::saldoFromOverviewCadastro($ctx),
            'performance', 'attendance' => self::saldoPedagogicoSemEstimativa(),
            default => null,
        };

        if ($raw !== null) {
            return self::formatSaldoStrip($raw, $tabStatus);
        }

        if (! in_array($tab, self::tabsWithMunicipalSaldo(), true)) {
            return null;
        }

        $perda = (float) ($ctx['perda_estimada_anual'] ?? 0);
        $ganho = (float) ($ctx['ganho_potencial_anual'] ?? 0);
        if ($perda <= 0 && $ganho <= 0) {
            return null;
        }

        return self::formatSaldoStrip([
            'perda' => $perda,
            'ganho' => $ganho,
            'liquido' => (float) ($ctx['saldo_liquido'] ?? ($ganho - $perda)),
            'footnote' => __('Soma indicativa das Discrepâncias no filtro (VAAF municipal × peso por rotina) — não é repasse oficial.'),
        ], $tabStatus);
    }

    /**
     * @param  array{perda: float, ganho: float, liquido: float, footnote: string, info_only?: bool}  $raw
     * @param  array{share_label: ?string, share_value: ?string}  $tabStatus
     * @return array<string, mixed>
     */
    private static function formatSaldoStrip(array $raw, array $tabStatus): array
    {
        $perda = (float) ($raw['perda'] ?? 0);
        $ganho = (float) ($raw['ganho'] ?? 0);
        $liquido = (float) ($raw['liquido'] ?? ($ganho - $perda));

        return [
            'perda' => $perda,
            'perda_fmt' => DiscrepanciesFundingImpact::formatBrl($perda),
            'ganho' => $ganho,
            'ganho_fmt' => DiscrepanciesFundingImpact::formatBrl($ganho),
            'liquido' => $liquido,
            'liquido_fmt' => AnalyticsMunicipalityContext::formatSaldo($liquido),
            'liquido_tone' => $liquido >= 0 ? 'success' : 'danger',
            'footnote' => (string) ($raw['footnote'] ?? ''),
            'info_only' => (bool) ($raw['info_only'] ?? false),
            'tab_share_label' => $tabStatus['share_label'] ?? null,
            'tab_share_value' => $tabStatus['share_value'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoFromNetworkOffer(array $tabData): ?array
    {
        $data = self::tabPayload($tabData, 'network');
        $k = is_array($data['kpis'] ?? null) ? $data['kpis'] : [];
        $vagas = (int) ($k['vagas_ociosas'] ?? 0);

        if ($vagas <= 0) {
            return null;
        }

        $funding = DiscrepanciesFundingImpact::estimate('rede_vagas_ociosas', $vagas);
        $perda = (float) $funding['perda_anual'];
        $ganho = (float) $funding['ganho_potencial_anual'];
        $liquido = round($ganho - $perda, 2);
        $taxa = ($k['taxa_ociosidade_pct'] ?? null) !== null
            ? number_format((float) $k['taxa_ociosidade_pct'], 1, ',', '.').'%'
            : '—';

        return [
            'perda' => $perda,
            'ganho' => $ganho,
            'liquido' => $liquido,
            'footnote' => __(
                ':vagas vagas ociosas × VAAF × peso :peso (taxa de ociosidade :taxa). Eficiência da oferta — não é repasse FNDE.',
                [
                    'vagas' => number_format($vagas, 0, ',', '.'),
                    'peso' => number_format((float) $funding['peso'], 2, ',', '.'),
                    'taxa' => $taxa,
                ]
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoFromEnrollmentDistorcao(array $tabData): ?array
    {
        $data = self::tabPayload($tabData, 'enrollment');
        $d = is_array($data['distorcao'] ?? null) ? $data['distorcao'] : [];
        $com = (int) ($d['com'] ?? 0);
        if ($com <= 0) {
            return null;
        }

        $funding = DiscrepanciesFundingImpact::estimate('distorcao_idade_serie', $com);
        $perda = (float) $funding['perda_anual'];
        $ganho = (float) $funding['ganho_potencial_anual'];
        $pct = isset($d['pct']) ? number_format((float) $d['pct'], 1, ',', '.') : '—';

        return [
            'perda' => $perda,
            'ganho' => $ganho,
            'liquido' => round($ganho - $perda, 2),
            'footnote' => __(
                ':n matrícula(s) com distorção idade-série (:p% da rede no filtro) × VAAF × peso :peso — risco Censo/VAAR-indicadores.',
                [
                    'n' => number_format($com, 0, ',', '.'),
                    'p' => $pct,
                    'peso' => number_format((float) $funding['peso'], 2, ',', '.'),
                ]
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoFromInclusion(array $tabData): ?array
    {
        $data = self::tabPayload($tabData, 'inclusion');
        $rec = is_array($data['recurso_prova'] ?? null) ? $data['recurso_prova'] : [];
        $semNee = (int) ($rec['sem_nee'] ?? 0);
        $neeSem = (int) ($rec['nee_sem_recurso'] ?? 0);

        $perda = 0.0;
        $ganho = 0.0;
        $parts = [];

        if ($semNee > 0) {
            $e = DiscrepanciesFundingImpact::estimate('recurso_prova_sem_nee', $semNee);
            $perda += (float) $e['perda_anual'];
            $ganho += (float) $e['ganho_potencial_anual'];
            $parts[] = __(':n recurso de prova sem NEE', ['n' => number_format($semNee, 0, ',', '.')]);
        }
        if ($neeSem > 0) {
            $e = DiscrepanciesFundingImpact::estimate('nee_sem_recurso_prova', $neeSem);
            $perda += (float) $e['perda_anual'];
            $ganho += (float) $e['ganho_potencial_anual'];
            $parts[] = __(':n NEE sem recurso de prova', ['n' => number_format($neeSem, 0, ',', '.')]);
        }

        if ($parts === []) {
            return null;
        }

        return [
            'perda' => round($perda, 2),
            'ganho' => round($ganho, 2),
            'liquido' => round($ganho - $perda, 2),
            'footnote' => __(
                'Eixo VAAR-inclusão: :detalhe. VAAF × pesos por tipo — não é repasse FNDE.',
                ['detalhe' => implode('; ', $parts)]
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoFromSchoolUnitsGeo(array $tabData): ?array
    {
        $data = self::tabPayload($tabData, 'school_units');
        $tab = is_array($data['tab'] ?? null) ? $data['tab'] : [];
        $dist = is_array($tab['geo_distribution'] ?? null) ? $tab['geo_distribution'] : [];
        $escopo = (int) ($dist['escolas_no_escopo'] ?? 0);
        $comCoord = (int) ($dist['total_com_coordenadas'] ?? 0);
        $semPosicao = max(0, $escopo - $comCoord);

        if ($semPosicao <= 0) {
            return null;
        }

        $funding = DiscrepanciesFundingImpact::estimate('escola_sem_geo', $semPosicao);
        $perda = (float) $funding['perda_anual'];
        $ganho = (float) $funding['ganho_potencial_anual'];

        return [
            'perda' => $perda,
            'ganho' => $ganho,
            'liquido' => round($ganho - $perda, 2),
            'footnote' => __(
                ':n escola(s) no filtro sem posição no mapa (de :e no escopo) × VAAF × peso :peso — georreferenciação e INEP para VAAR/Censo.',
                [
                    'n' => number_format($semPosicao, 0, ',', '.'),
                    'e' => number_format($escopo, 0, ',', '.'),
                    'peso' => number_format((float) $funding['peso'], 2, ',', '.'),
                ]
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoFromOverviewCadastro(array $ctx): ?array
    {
        $perda = (float) ($ctx['perda_estimada_anual'] ?? 0);
        $ganho = (float) ($ctx['ganho_potencial_anual'] ?? 0);
        if ($perda <= 0 && $ganho <= 0) {
            return null;
        }

        $ocorrencias = (int) ($ctx['pendencias_cadastro'] ?? 0);

        return [
            'perda' => $perda,
            'ganho' => $ganho,
            'liquido' => (float) ($ctx['saldo_liquido'] ?? ($ganho - $perda)),
            'footnote' => $ocorrencias > 0
                ? __('Consolidado das Discrepâncias no filtro (:n ocorrência(s) com peso VAAF) — use a aba homónima para detalhe por escola e rotina.', ['n' => number_format($ocorrencias, 0, ',', '.')])
                : __('Referência municipal das Discrepâncias (VAAF × pesos) — não é repasse oficial.'),
        ];
    }

    /**
     * @return array{perda: float, ganho: float, liquido: float, footnote: string, info_only: bool}
     */
    private static function saldoPedagogicoSemEstimativa(): array
    {
        return [
            'perda' => 0.0,
            'ganho' => 0.0,
            'liquido' => 0.0,
            'info_only' => true,
            'footnote' => __(
                'Esta aba não estima valores próprios. O impacto financeiro indicativo do cadastro está em Discrepâncias e FUNDEB; use Inclusão para o eixo VAAR-inclusão.'
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusNetwork(array $tabData): array
    {
        $data = is_array($tabData['network'] ?? null) ? $tabData['network'] : ($tabData['networkData'] ?? []);
        $k = is_array($data['kpis'] ?? null) ? $data['kpis'] : [];
        $ociosidade = ($k['taxa_ociosidade_pct'] ?? null) !== null ? (float) $k['taxa_ociosidade_pct'] : null;

        if ($ociosidade === null) {
            return ['status' => 'neutral', 'label' => __('Ociosidade não calculada'), 'score' => null, 'share_label' => null, 'share_value' => null];
        }

        $score = (int) max(0, min(100, 100 - $ociosidade * 1.2));
        $status = $ociosidade >= 35 ? 'danger' : ($ociosidade >= 18 ? 'warning' : 'success');

        return [
            'status' => $status,
            'label' => __('Taxa de ociosidade: :p%', ['p' => number_format($ociosidade, 1, ',', '.')]),
            'score' => $score,
            'share_label' => __('Vagas ociosas'),
            'share_value' => number_format((int) ($k['vagas_ociosas'] ?? 0), 0, ',', '.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusSchoolUnits(array $tabData): array
    {
        $data = is_array($tabData['school_units'] ?? null) ? $tabData['school_units'] : ($tabData['schoolUnitsData'] ?? []);
        $tab = is_array($data['tab'] ?? null) ? $data['tab'] : [];
        $dist = is_array($tab['geo_distribution'] ?? null) ? $tab['geo_distribution'] : [];
        $markers = is_array($tab['markers'] ?? null) ? count($tab['markers']) : 0;
        $waiting = is_array($tab['waiting'] ?? null) ? $tab['waiting'] : [];
        $lista = (int) ($waiting['total'] ?? $waiting['total_alunos'] ?? 0);

        $escopo = (int) ($dist['escolas_no_escopo'] ?? 0);
        $comCoord = (int) ($dist['total_com_coordenadas'] ?? 0);
        $exibidos = (int) ($dist['marcadores_exibidos'] ?? $markers);

        $denominador = $escopo > 0 ? $escopo : ($markers > 0 ? $markers : 0);
        $comGeo = $escopo > 0 ? $comCoord : $markers;

        if ($denominador === 0) {
            return [
                'status' => 'neutral',
                'label' => __('Nenhuma escola no escopo do filtro'),
                'score' => null,
                'share_label' => __('Cobertura geográfica'),
                'share_value' => '—',
            ];
        }

        $pct = (int) round(100 * min($comGeo, $denominador) / $denominador);
        $score = max(0, min(100, $pct));

        if ($comGeo <= 0) {
            return [
                'status' => 'danger',
                'label' => __('0 de :e escola(s) com coordenadas no filtro', [
                    'e' => number_format($denominador, 0, ',', '.'),
                ]),
                'score' => 0,
                'share_label' => __('Cobertura geográfica'),
                'share_value' => '0%',
            ];
        }

        $status = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
        if ($lista > 50 && $status === 'success') {
            $status = 'warning';
        }

        $noMapa = $exibidos > 0 ? $exibidos : $comGeo;
        $label = $lista > 0
            ? __(':e de :t escola(s) no mapa — :l em lista de espera', [
                'e' => number_format($noMapa, 0, ',', '.'),
                't' => number_format($denominador, 0, ',', '.'),
                'l' => number_format($lista, 0, ',', '.'),
            ])
            : __(':e de :t escola(s) com coordenadas no filtro', [
                'e' => number_format($noMapa, 0, ',', '.'),
                't' => number_format($denominador, 0, ',', '.'),
            ]);

        return [
            'status' => $status,
            'label' => $label,
            'score' => $score,
            'share_label' => __('Cobertura geográfica'),
            'share_value' => $pct.'%',
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusInclusion(array $tabData): array
    {
        $data = is_array($tabData['inclusion'] ?? null) ? $tabData['inclusion'] : ($tabData['inclusionData'] ?? []);
        $rec = is_array($data['recurso_prova'] ?? null) ? $data['recurso_prova'] : [];
        $semNee = (int) data_get($rec, 'sem_nee', 0);
        $neePct = null;
        $total = (int) ($data['total_matriculas'] ?? 0);
        $nee = is_array($data['nee_grupo_resumo'] ?? null) ? $data['nee_grupo_resumo'] : [];
        $neeTotal = (int) (($nee['deficiencias'] ?? 0) + ($nee['sindromes_tea'] ?? 0) + ($nee['ne_altas_habilidades'] ?? 0));
        if ($total > 0 && $neeTotal > 0) {
            $neePct = round($neeTotal / $total * 100, 1);
        }

        if ($semNee > 0) {
            return [
                'status' => 'danger',
                'label' => __(':n recurso(s) de prova sem NEE', ['n' => $semNee]),
                'score' => 35,
                'share_label' => __('Risco VAAR-inclusão'),
                'share_value' => __('Alto'),
            ];
        }

        if ($neePct !== null) {
            return [
                'status' => 'success',
                'label' => __('NEE no filtro: :p% das matrículas', ['p' => number_format($neePct, 1, ',', '.')]),
                'score' => 78,
                'share_label' => __('Cobertura NEE'),
                'share_value' => number_format($neePct, 1, ',', '.').'%',
            ];
        }

        return ['status' => 'neutral', 'label' => __('Sem alertas NEE no recorte'), 'score' => 70, 'share_label' => null, 'share_value' => null];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusPerformance(array $tabData): array
    {
        $data = is_array($tabData['performance'] ?? null) ? $tabData['performance'] : ($tabData['performanceData'] ?? []);
        $kpis = is_array($data['kpis'] ?? null) ? $data['kpis'] : [];
        $abandono = null;
        $evasao = null;
        foreach ($kpis as $kpi) {
            if (! is_array($kpi)) {
                continue;
            }
            $id = (string) ($kpi['id'] ?? '');
            $pct = ($kpi['percent'] ?? null) !== null ? (float) $kpi['percent'] : null;
            if ($id === 'abandono') {
                $abandono = $pct;
            }
            if ($id === 'evasao') {
                $evasao = $pct;
            }
        }
        $worst = max($abandono ?? 0, $evasao ?? 0);

        if ($abandono === null && $evasao === null) {
            return ['status' => 'neutral', 'label' => __('Indicadores i-Educar indisponíveis'), 'score' => null, 'share_label' => null, 'share_value' => null];
        }

        $score = (int) max(0, min(100, 100 - $worst * 4));
        $status = $worst >= 8 ? 'danger' : ($worst >= 4 ? 'warning' : 'success');

        return [
            'status' => $status,
            'label' => __('Abandono :a% · Evasão :e%', [
                'a' => $abandono !== null ? number_format($abandono, 1, ',', '.') : '—',
                'e' => $evasao !== null ? number_format($evasao, 1, ',', '.') : '—',
            ]),
            'score' => $score,
            'share_label' => __('VAAR-indicadores'),
            'share_value' => $worst >= 6 ? __('Atenção') : __('Estável'),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusAttendance(array $tabData): array
    {
        $data = is_array($tabData['attendance'] ?? null) ? $tabData['attendance'] : ($tabData['attendanceData'] ?? []);
        if (! empty($data['error'])) {
            return ['status' => 'danger', 'label' => __('Erro ao ler faltas'), 'score' => 20, 'share_label' => null, 'share_value' => null];
        }
        $charts = $data['charts'] ?? [];
        if ($charts === [] && empty($data['chart'])) {
            return ['status' => 'neutral', 'label' => __('Sem registros de falta no filtro'), 'score' => 60, 'share_label' => null, 'share_value' => null];
        }

        return [
            'status' => 'success',
            'label' => __('Frequência registada no período'),
            'score' => 72,
            'share_label' => __('Gráficos'),
            'share_value' => (string) max(1, count($charts) ?: 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusFundeb(array $tabData): array
    {
        $data = is_array($tabData['fundeb'] ?? null) ? $tabData['fundeb'] : ($tabData['fundebData'] ?? []);
        $proj = is_array($data['resource_projection'] ?? null) ? $data['resource_projection'] : [];
        $modules = is_array($data['modules'] ?? null) ? $data['modules'] : [];
        $alertas = 0;
        foreach ($modules as $m) {
            if (in_array((string) ($m['status'] ?? ''), ['danger', 'warning'], true)) {
                $alertas++;
            }
        }

        if ((bool) ($proj['available'] ?? false)) {
            $base = (float) data_get($proj, 'totais.fundeb_base_anual', 0);
            $status = $alertas > 2 ? 'warning' : 'success';
            $score = max(40, 95 - $alertas * 12);

            return [
                'status' => $status,
                'label' => $alertas > 0
                    ? __('Previsão OK — :n módulo(s) em alerta', ['n' => $alertas])
                    : __('Previsão de recursos disponível'),
                'score' => $score,
                'share_label' => __('Base FUNDEB (est.)'),
                'share_value' => DiscrepanciesFundingImpact::formatBrl($base),
            ];
        }

        return [
            'status' => 'warning',
            'label' => __('Previsão indisponível (sem matrículas ou VAAF)'),
            'score' => 45,
            'share_label' => null,
            'share_value' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusOtherFunding(array $tabData): array
    {
        $data = is_array($tabData['other_funding'] ?? null) ? $tabData['other_funding'] : ($tabData['otherFundingData'] ?? []);
        $programs = is_array($data['programs'] ?? null) ? $data['programs'] : [];
        $alertas = 0;
        foreach ($programs as $p) {
            if (in_array((string) ($p['status'] ?? ''), ['danger', 'warning'], true)) {
                $alertas++;
            }
        }

        $status = $alertas >= 3 ? 'danger' : ($alertas > 0 ? 'warning' : 'success');
        $score = max(30, 100 - $alertas * 15);

        return [
            'status' => $status,
            'label' => $alertas > 0
                ? __(':n programa(s) em alerta', ['n' => $alertas])
                : __('Programas sem alerta no filtro'),
            'score' => $score,
            'share_label' => __('Programas monitorados'),
            'share_value' => (string) count($programs),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    /**
     * Status consolidado do sistema (Diagnóstico): índice + pendências/alertas agregados.
     *
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusSystemConsolidated(array $tabData, array $ctx): array
    {
        $data = self::tabPayload($tabData, 'health');
        $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $score = (int) ($data['compliance_score'] ?? $ctx['compliance_score'] ?? 0);
        $status = (string) ($data['compliance_status'] ?? $ctx['compliance_status'] ?? 'neutral');
        $baseLabel = (string) ($data['compliance_label'] ?? $ctx['compliance_label'] ?? '');

        if ($score <= 0 && $baseLabel === '') {
            return ['status' => 'neutral', 'label' => __('Consolidado indisponível'), 'score' => null, 'share_label' => null, 'share_value' => null];
        }

        $pendDims = (int) ($summary['pendencias_cadastro'] ?? $ctx['pendencias_cadastro'] ?? 0);
        $modAlert = (int) ($summary['modulos_fundeb_alerta'] ?? 0);
        $progAlert = (int) ($data['programas_alerta'] ?? 0);
        $semNee = (int) ($summary['recurso_prova_sem_nee'] ?? 0);
        $comProblema = (int) ($ctx['com_problema'] ?? 0);
        $escolas = (int) ($summary['escolas_afetadas'] ?? $ctx['escolas_afetadas'] ?? 0);

        $critical = $semNee > 0 || $comProblema > 200 || $pendDims >= 5;
        $hasPending = $pendDims > 0 || $modAlert > 0 || $progAlert > 0 || $comProblema > 0;

        if ($status === 'neutral' && $score > 0) {
            $status = AnalyticsMunicipalityContext::statusFromScore($score);
        }
        if ($critical && $status === 'success') {
            $status = 'danger';
        } elseif ($hasPending && $status === 'success') {
            $status = 'warning';
        }

        $parts = [];
        if ($pendDims > 0) {
            $parts[] = __(':n dim. cadastro', ['n' => $pendDims]);
        }
        if ($comProblema > 0) {
            $parts[] = __(':n disc.', ['n' => $comProblema]);
        }
        if ($modAlert > 0) {
            $parts[] = __(':n FUNDEB', ['n' => $modAlert]);
        }
        if ($progAlert > 0) {
            $parts[] = __(':n prog.', ['n' => $progAlert]);
        }
        if ($semNee > 0) {
            $parts[] = __('NEE inconsistente');
        }

        $label = $baseLabel !== '' ? $baseLabel : __('Índice :n', ['n' => $score]);
        if ($parts !== []) {
            $label = $label.' — '.implode(' · ', $parts);
        }

        return [
            'status' => $status,
            'label' => $label,
            'score' => $score > 0 ? $score : null,
            'share_label' => __('Escolas afetadas'),
            'share_value' => $escolas > 0 ? (string) $escolas : __('Nenhuma'),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusDiscrepancies(array $tabData, array $ctx): array
    {
        $data = is_array($tabData['discrepancies'] ?? null) ? $tabData['discrepancies'] : ($tabData['discrepanciesData'] ?? []);
        $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $comProblema = (int) ($summary['com_problema'] ?? 0);
        $escolas = (int) ($summary['escolas_afetadas'] ?? 0);
        $perda = (float) ($summary['perda_estimada_anual'] ?? $ctx['perda_estimada_anual'] ?? 0);

        if ($comProblema <= 0) {
            return [
                'status' => 'success',
                'label' => __('Sem ocorrências no filtro'),
                'score' => 92,
                'share_label' => __('Escolas afetadas'),
                'share_value' => '0',
            ];
        }

        $matriculas = max(1, (int) ($ctx['total_matriculas'] ?? 1));
        $score = max(15, min(95, 100 - (int) min(60, (int) round($comProblema / ($matriculas / 50)))));
        $status = $comProblema > 500 ? 'danger' : ($comProblema > 80 ? 'warning' : 'warning');

        return [
            'status' => $status,
            'label' => __(':n ocorrência(s) — :e escola(s)', ['n' => $comProblema, 'e' => $escolas]),
            'score' => $score,
            'share_label' => __('Perda est./ano'),
            'share_value' => $perda > 0 ? DiscrepanciesFundingImpact::formatBrl($perda) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusWorkDone(array $tabData): array
    {
        $data = is_array($tabData['work_done'] ?? null) ? $tabData['work_done'] : ($tabData['workDoneData'] ?? []);
        $censo = is_array($data['censo'] ?? null) ? $data['censo'] : [];
        $summary = is_array($censo['summary'] ?? null) ? $censo['summary'] : [];
        $pend = (int) ($summary['pendentes'] ?? $summary['pendentes_total'] ?? 0);
        $meta = is_array($data['estimativa'] ?? null) ? $data['estimativa'] : [];
        $dias = isset($meta['dias_para_concluir_ritmo_atual']) ? (int) $meta['dias_para_concluir_ritmo_atual'] : null;

        if ($pend <= 0 && ($data['activity_available'] ?? false)) {
            return [
                'status' => 'success',
                'label' => __('Meta Censo atingida no recorte'),
                'score' => 90,
                'share_label' => __('Ritmo'),
                'share_value' => isset($meta['ritmo_por_dia']) ? number_format((float) $meta['ritmo_por_dia'], 1, ',', '.').'/dia' : null,
            ];
        }

        $score = max(20, 100 - min(70, $pend));
        $status = $pend > 500 ? 'danger' : ($pend > 100 ? 'warning' : 'warning');

        return [
            'status' => $status,
            'label' => $dias !== null && $dias > 0
                ? __(':p pendência(s) Censo — ~:d dia(s) para meta', ['p' => $pend, 'd' => $dias])
                : __(':p pendência(s) de cadastro Censo', ['p' => $pend]),
            'score' => $score,
            'share_label' => __('Impacto fundeb-base'),
            'share_value' => $pend > 200 ? __('Alto') : __('Moderado'),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array<string, mixed>
     */
    private static function tabPayload(array $tabData, string $tab): array
    {
        $keys = match ($tab) {
            'overview' => ['overview', 'overviewData'],
            'enrollment' => ['enrollment', 'enrollmentData'],
            'network' => ['network', 'networkData'],
            'school_units' => ['school_units', 'schoolUnitsData'],
            'inclusion' => ['inclusion', 'inclusionData'],
            'performance' => ['performance', 'performanceData'],
            'attendance' => ['attendance', 'attendanceData'],
            'fundeb' => ['fundeb', 'fundebData'],
            'other_funding' => ['other_funding', 'otherFundingData'],
            'work_done' => ['work_done', 'workDoneData'],
            'municipality_health', 'health' => ['health', 'healthData', 'municipalityHealthData'],
            'discrepancies' => ['discrepancies', 'discrepanciesData'],
            default => [$tab, $tab.'Data'],
        };

        foreach ($keys as $key) {
            if (is_array($tabData[$key] ?? null)) {
                return $tabData[$key];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}  $computed
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function applyQueryError(string $tab, array $tabData, array $computed): array
    {
        $payload = self::tabPayload($tabData, $tab === 'municipality_health' ? 'health' : $tab);
        if (empty($payload['error'])) {
            return $computed;
        }

        $score = $computed['score'] ?? null;
        if ($score === null) {
            $score = 15;
        } else {
            $score = min((int) $score, 25);
        }

        return [
            'status' => 'danger',
            'label' => __('Erro ao carregar — verifique filtros e conexão'),
            'score' => $score,
            'share_label' => $computed['share_label'] ?? null,
            'share_value' => $computed['share_value'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @param  array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}  $computed
     * @return list<array{type: string, label: string, count: int}>
     */
    private static function collectStatusIssues(string $tab, array $tabData, array $ctx, array $computed): array
    {
        $issues = [];
        $payload = self::tabPayload($tabData, $tab === 'municipality_health' ? 'health' : $tab);

        if (! empty($payload['error'])) {
            $issues[] = ['type' => 'error', 'label' => __('Falha na consulta desta aba'), 'count' => 1];
        }

        if ($tab === 'municipality_health') {
            $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
            $pendDims = (int) ($summary['pendencias_cadastro'] ?? $ctx['pendencias_cadastro'] ?? 0);
            if ($pendDims > 0) {
                $issues[] = ['type' => 'pending', 'label' => __('Dimensões de cadastro com pendência'), 'count' => $pendDims];
            }
            $modAlert = (int) ($summary['modulos_fundeb_alerta'] ?? 0);
            if ($modAlert > 0) {
                $issues[] = ['type' => 'pending', 'label' => __('Módulos FUNDEB em alerta'), 'count' => $modAlert];
            }
            $progAlert = (int) ($payload['programas_alerta'] ?? 0);
            if ($progAlert > 0) {
                $issues[] = ['type' => 'pending', 'label' => __('Programas complementares em alerta'), 'count' => $progAlert];
            }
            $semNee = (int) ($summary['recurso_prova_sem_nee'] ?? 0);
            if ($semNee > 0) {
                $issues[] = ['type' => 'error', 'label' => __('Recurso de prova sem NEE'), 'count' => $semNee];
            }
            $comProblema = (int) ($ctx['com_problema'] ?? 0);
            if ($comProblema > 0) {
                $issues[] = ['type' => 'pending', 'label' => __('Ocorrências em Discrepâncias'), 'count' => $comProblema];
            }

            return $issues;
        }

        $pend = (int) ($ctx['pendencias_cadastro'] ?? 0);
        if ($pend > 0 && in_array($tab, ['enrollment', 'network', 'school_units', 'work_done', 'overview'], true)) {
            $issues[] = ['type' => 'pending', 'label' => __('Pendências de cadastro no município'), 'count' => $pend];
        }

        if ($tab === 'discrepancies') {
            $data = self::tabPayload($tabData, 'discrepancies');
            $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
            $comProblema = (int) ($summary['com_problema'] ?? $ctx['com_problema'] ?? 0);
            if ($comProblema > 0) {
                $issues[] = ['type' => 'pending', 'label' => __('Ocorrências com impacto indicativo'), 'count' => $comProblema];
            }
        }

        if ($tab === 'inclusion') {
            $data = self::tabPayload($tabData, 'inclusion');
            $semNee = (int) data_get($data, 'recurso_prova.sem_nee', 0);
            if ($semNee > 0) {
                $issues[] = ['type' => 'error', 'label' => __('Recurso de prova sem NEE'), 'count' => $semNee];
            }
        }

        if ($tab === 'fundeb') {
            $data = self::tabPayload($tabData, 'fundeb');
            $modAlertas = 0;
            foreach (is_array($data['modules'] ?? null) ? $data['modules'] : [] as $m) {
                if (in_array((string) ($m['status'] ?? ''), ['danger', 'warning'], true)) {
                    $modAlertas++;
                }
            }
            if ($modAlertas > 0) {
                $issues[] = ['type' => 'pending', 'label' => __('Módulos FUNDEB em alerta'), 'count' => $modAlertas];
            }
            if (! (bool) data_get($data, 'resource_projection.available', false)) {
                $issues[] = ['type' => 'unavailable', 'label' => __('Previsão de recursos indisponível'), 'count' => 1];
            }
        }

        if ($tab === 'other_funding') {
            $data = self::tabPayload($tabData, 'other_funding');
            $progAlertas = 0;
            foreach (is_array($data['programs'] ?? null) ? $data['programs'] : [] as $p) {
                if (in_array((string) ($p['status'] ?? ''), ['danger', 'warning'], true)) {
                    $progAlertas++;
                }
            }
            if ($progAlertas > 0) {
                $issues[] = ['type' => 'pending', 'label' => __('Programas em alerta'), 'count' => $progAlertas];
            }
        }

        if ($tab === 'work_done') {
            $data = self::tabPayload($tabData, 'work_done');
            $censo = is_array($data['censo'] ?? null) ? $data['censo'] : [];
            $summary = is_array($censo['summary'] ?? null) ? $censo['summary'] : [];
            $pendCenso = (int) ($summary['pendentes'] ?? $summary['pendentes_total'] ?? 0);
            if ($pendCenso > 0) {
                $issues[] = ['type' => 'pending', 'label' => __('Pendências Censo'), 'count' => $pendCenso];
            }
        }

        if ($tab === 'enrollment') {
            $data = self::tabPayload($tabData, 'enrollment');
            $d = is_array($data['distorcao'] ?? null) ? $data['distorcao'] : [];
            $pct = isset($d['pct']) ? (float) $d['pct'] : null;
            if ($pct !== null && $pct >= 15) {
                $issues[] = ['type' => 'pending', 'label' => __('Distorção idade-série elevada'), 'count' => (int) round($pct)];
            }
        }

        if (($computed['status'] ?? '') === 'danger' && $issues === []) {
            $issues[] = ['type' => 'error', 'label' => (string) ($computed['label'] ?? __('Situação crítica no filtro')), 'count' => 1];
        }

        return array_slice($issues, 0, 8);
    }

    /**
     * @param  array{title: string, purpose: string, impact_note: string}  $def
     * @param  array<string, mixed>  $ctx
     * @param  list<array{type: string, label: string, count: int}>  $issues
     */
    private static function statusHelp(string $tab, array $def, array $ctx, array $issues, string $mode): string
    {
        $parts = [];
        if ($mode === 'system') {
            $parts[] = __('Consolidado do sistema: índice de conformidade, pendências de cadastro, Discrepâncias, FUNDEB e programas no mesmo recorte (cidade e ano).');
        } else {
            $parts[] = __('Status desta aba: calculado só com os dados visíveis no filtro atual.');
        }
        if ($tab === 'school_units') {
            $parts[] = __('O número do anel é a percentagem de escolas do filtro com coordenadas (mapa); 0 unidades no mapa corresponde a 0%, não a 50%.');
        }
        if (($def['impact_note'] ?? '') !== '') {
            $parts[] = (string) $def['impact_note'];
        }
        if ($issues === [] && ($ctx['pendencias_cadastro'] ?? 0) > 0 && $tab !== 'municipality_health') {
            $parts[] = __('Há :n pendência(s) municipais de cadastro — detalhe em Discrepâncias ou Diagnóstico.', [
                'n' => (int) $ctx['pendencias_cadastro'],
            ]);
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return list<array{label: string, value: string, tone?: string}>
     */
    private static function tabMetrics(string $tab, array $tabData, array $ctx): array
    {
        $out = [];

        match ($tab) {
            'enrollment' => self::pushEnrollmentMetrics($out, $tabData),
            'network' => self::pushNetworkMetrics($out, $tabData),
            'inclusion' => self::pushInclusionMetrics($out, $tabData),
            'school_units' => self::pushSchoolUnitsMetrics($out, $tabData),
            'overview' => self::pushOverviewMetrics($out, $ctx),
            default => null,
        };

        $showMunicipalFinance = in_array($tab, self::tabsWithMunicipalSaldo(), true) || $tab === 'overview';

        if ($showMunicipalFinance && ($ctx['perda_estimada_anual'] ?? 0) > 0) {
            $out[] = [
                'label' => $tab === 'overview' ? __('Perda est. municipal') : __('Perda est. (ano)'),
                'value' => DiscrepanciesFundingImpact::formatBrl((float) $ctx['perda_estimada_anual']),
                'tone' => 'danger',
            ];
        }
        if ($showMunicipalFinance && ($ctx['ganho_potencial_anual'] ?? 0) > 0) {
            $out[] = [
                'label' => $tab === 'overview' ? __('Ganho pot. municipal') : __('Ganho potencial'),
                'value' => DiscrepanciesFundingImpact::formatBrl((float) $ctx['ganho_potencial_anual']),
                'tone' => 'success',
            ];
        }

        if (($ctx['pendencias_cadastro'] ?? 0) > 0 && ! in_array($tab, ['discrepancies', 'municipality_health'], true)) {
            $out[] = [
                'label' => __('Ocorr. Discrepâncias'),
                'value' => (string) (int) $ctx['pendencias_cadastro'],
                'tone' => 'warning',
            ];
        }

        if (($ctx['total_matriculas'] ?? null) !== null && in_array($tab, ['overview', 'enrollment', 'inclusion'], true)) {
            $out[] = [
                'label' => __('Matrículas realizadas (filtro)'),
                'value' => number_format((int) $ctx['total_matriculas'], 0, ',', '.'),
                'tone' => 'neutral',
            ];
        }

        return array_slice($out, 0, 4);
    }

    /**
     * @param  list<array{label: string, value: string, tone?: string}>  $out
     */
    private static function pushEnrollmentMetrics(array &$out, array $tabData): void
    {
        $data = self::tabPayload($tabData, 'enrollment');
        $d = is_array($data['distorcao'] ?? null) ? $data['distorcao'] : [];
        if (isset($d['pct'])) {
            $out[] = [
                'label' => __('Distorção idade-série'),
                'value' => number_format((float) $d['pct'], 1, ',', '.').'%',
                'tone' => ((float) $d['pct']) >= 15 ? 'danger' : 'warning',
            ];
        }
        $kpis = is_array($data['kpis'] ?? null) ? $data['kpis'] : [];
        if (($kpis['matriculas'] ?? null) !== null) {
            $out[] = [
                'label' => __('Matrículas (aba)'),
                'value' => number_format((int) $kpis['matriculas'], 0, ',', '.'),
                'tone' => 'neutral',
            ];
        }
    }

    /**
     * @param  list<array{label: string, value: string, tone?: string}>  $out
     */
    private static function pushNetworkMetrics(array &$out, array $tabData): void
    {
        $data = self::tabPayload($tabData, 'network');
        $k = is_array($data['kpis'] ?? null) ? $data['kpis'] : [];
        if (($k['vagas_ociosas'] ?? 0) > 0) {
            $out[] = [
                'label' => __('Vagas ociosas'),
                'value' => number_format((int) $k['vagas_ociosas'], 0, ',', '.'),
                'tone' => 'warning',
            ];
        }
    }

    /**
     * @param  list<array{label: string, value: string, tone?: string}>  $out
     */
    private static function pushInclusionMetrics(array &$out, array $tabData): void
    {
        $data = self::tabPayload($tabData, 'inclusion');
        $rec = is_array($data['recurso_prova'] ?? null) ? $data['recurso_prova'] : [];
        if (($rec['sem_nee'] ?? 0) > 0) {
            $out[] = [
                'label' => __('Recurso sem NEE'),
                'value' => number_format((int) $rec['sem_nee'], 0, ',', '.'),
                'tone' => 'danger',
            ];
        }
    }

    /**
     * @param  list<array{label: string, value: string, tone?: string}>  $out
     */
    private static function pushSchoolUnitsMetrics(array &$out, array $tabData): void
    {
        $data = self::tabPayload($tabData, 'school_units');
        $tab = is_array($data['tab'] ?? null) ? $data['tab'] : [];
        $dist = is_array($tab['geo_distribution'] ?? null) ? $tab['geo_distribution'] : [];
        $sem = max(0, (int) ($dist['escolas_no_escopo'] ?? 0) - (int) ($dist['total_com_coordenadas'] ?? 0));
        if ($sem > 0) {
            $out[] = [
                'label' => __('Sem posição no mapa'),
                'value' => (string) $sem,
                'tone' => 'warning',
            ];
        }
    }

    /**
     * @param  list<array{label: string, value: string, tone?: string}>  $out
     * @param  array<string, mixed>  $ctx
     */
    private static function pushOverviewMetrics(array &$out, array $ctx): void
    {
        if (($ctx['escolas_afetadas'] ?? 0) > 0) {
            $out[] = [
                'label' => __('Escolas afetadas'),
                'value' => (string) (int) $ctx['escolas_afetadas'],
                'tone' => 'warning',
            ];
        }
    }
}
