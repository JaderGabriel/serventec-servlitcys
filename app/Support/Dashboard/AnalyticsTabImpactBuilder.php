<?php

namespace App\Support\Dashboard;

use App\Support\Finance\MoneyMath;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebReferenceDisplay;

/**
 * Faixa visual no topo de cada aba (até Censo): impacto no saldo indicativo + status municipal no filtro.
 */
final class AnalyticsTabImpactBuilder
{
    /** Abas sem status na faixa (ex.: Cadastro / Visão geral). */
    public const TABS_WITHOUT_STATUS = ['overview'];

    /** Abas sem bloco «Impacto no saldo» na faixa (conteúdo financeiro na própria aba). */
    public const TABS_WITHOUT_SALDO = [
        'overview',
        'municipality_health',
        'discrepancies',
        'other_funding',
        'work_done',
    ];

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
        $showSaldo = ! in_array($tab, self::TABS_WITHOUT_SALDO, true);
        $statusMode = in_array($tab, self::TABS_SYSTEM_STATUS, true) ? 'system' : 'tab';

        if (! $yearFilterReady) {
            return [
                'ready' => false,
                'tab' => $tab,
                'title' => $def['title'],
                'purpose' => $def['purpose'],
                'show_saldo' => $showSaldo,
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

        $saldo = $showSaldo ? self::resolveTabSaldo($tab, $tabData, $ctx, $tabStatus) : null;

        return [
            'ready' => true,
            'tab' => $tab,
            'title' => $def['title'],
            'purpose' => $def['purpose'],
            'impact_note' => $def['impact_note'],
            'show_saldo' => $showSaldo,
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
                'purpose' => __('Matrículas ativas, turmas, ocupação e secção de distorção idade-série (critério INEP) no mesmo recorte.'),
                'impact_note' => __('O ganho estimado usa o VAAF municipal (ou prévia federal configurada) × matrículas já realizadas no filtro. Não há perda nesta aba — as matrículas existem; eventual ganho adicional ao corrigir cadastro aparece só como potencial.'),
            ],
            'cadunico_previsao' => [
                'title' => __('CadÚnico'),
                'purpose' => __('Cruza agregados Cecad (4–17 anos) com matrículas i-Educar para estimar crianças fora da rede e impacto FUNDEB indicativo.'),
                'impact_note' => __('Valores agregados (LGPD); importe Cecad em Admin ou via automação. Lacuna elevada sugere busca ativa.'),
            ],
            'network' => [
                'title' => __('Rede & Oferta'),
                'purpose' => __('Capacidade, vagas ociosas e distribuição por turno, segmento e escola.'),
                'impact_note' => __('Saldo indicativo com o mesmo VAAF da aba Matrículas (municipal → prévia federal → valor configurado).'),
            ],
            'school_units' => [
                'title' => __('Unidades Escolares'),
                'purpose' => __('Mapa, geografia, lista de espera e cobertura por unidade no filtro.'),
                'impact_note' => __('Escolas sem INEP ou fora do cadastro impactam indicadores VAAR e repasses.'),
            ],
            'inclusion' => [
                'title' => __('Inclusão & Diversidade'),
                'purpose' => __('NEE, equidade, recurso de prova e cruzamentos com matrículas ativas.'),
                'impact_note' => __('Incremento FUNDEB (ponderação 1,20 — Lei 14.113) nas matrículas NEE + riscos VAAR-inclusão (Discrepâncias).'),
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
                'impact_note' => __('Totais por rotina e resumo financeiro na página; VAAF municipal do filtro (ou prévia federal).'),
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
            'enrollment' => self::statusEnrollment($tabData, $ctx),
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
            'cadunico_previsao' => self::statusCadunicoPrevisao($tabData),
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
     * @param  array<string, mixed>  $ctx
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusEnrollment(array $tabData, array $ctx = []): array
    {
        $snap = self::enrollmentTabSnapshot($tabData, $ctx);
        $mat = $snap['mat'];
        $turmas = $snap['turmas'];
        $pend = $snap['pendencias'];
        $pct = $snap['distorcao_pct'];
        $ocup = $snap['ocupacao'];

        if ($mat <= 0) {
            return [
                'status' => 'warning',
                'label' => __('Sem matrículas ativas no filtro'),
                'score' => 35,
                'share_label' => __('Matrículas realizadas'),
                'share_value' => '0',
            ];
        }

        $score = 100.0;
        $score -= min(28.0, $pend * 2.2);
        if ($pct !== null) {
            $score -= min(22.0, $pct * 0.85);
        } elseif ($snap['distorcao_indisponivel']) {
            $score -= 10.0;
        }
        if ($ocup !== null) {
            if ($ocup < 20.0) {
                $score -= 12.0;
            } elseif ($ocup > 98.0) {
                $score -= 6.0;
            }
        }
        if ($turmas <= 0) {
            $score -= 15.0;
        }

        $scoreInt = (int) max(0, min(100, round($score)));
        $status = AnalyticsMunicipalityContext::statusFromScore($scoreInt);

        $labelParts = [
            __(':n matrículas', ['n' => number_format($mat, 0, ',', '.')]),
        ];
        if ($turmas > 0) {
            $labelParts[] = __(':t turmas', ['t' => number_format($turmas, 0, ',', '.')]);
        }
        if ($ocup !== null) {
            $labelParts[] = __('ocupação :p%', ['p' => number_format($ocup, 1, ',', '.')]);
        }
        if ($pend > 0) {
            $labelParts[] = __(':p pend. cadastro', ['p' => number_format($pend, 0, ',', '.')]);
        }
        if ($pct !== null) {
            $labelParts[] = __('distorção :d%', ['d' => number_format($pct, 1, ',', '.')]);
        } elseif ($snap['distorcao_indisponivel']) {
            $labelParts[] = __('distorção indisponível');
        }

        return [
            'status' => $status,
            'label' => implode(' · ', $labelParts),
            'score' => $scoreInt,
            'share_label' => __('Matrículas realizadas (filtro)'),
            'share_value' => number_format($mat, 0, ',', '.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{status: string, label: string, score: ?int, share_label: ?string, share_value: ?string}
     */
    private static function statusCadunicoPrevisao(array $tabData): array
    {
        $data = self::tabPayload($tabData, 'cadunico_previsao');
        $gap = is_array($data['gap'] ?? null) ? $data['gap'] : [];

        if (! ($gap['available'] ?? false)) {
            return [
                'status' => 'warning',
                'label' => __('Importe agregados Cecad para este município/ano'),
                'score' => 40,
                'share_label' => __('CadÚnico'),
                'share_value' => '—',
            ];
        }

        $gapTotal = (int) ($gap['gap_total'] ?? 0);
        $status = (string) ($gap['status'] ?? 'neutral');
        $score = match ($status) {
            'success', 'emerald' => 88,
            'warning', 'amber' => max(45, 78 - min(25, (int) round($gapTotal / 20))),
            'danger', 'rose' => 42,
            default => 65,
        };

        $label = (string) ($gap['cobertura_label'] ?? __('Lacuna :n', ['n' => $gap['gap_total_fmt'] ?? '0']));

        return [
            'status' => $status === 'success' ? 'success' : ($status === 'warning' ? 'warning' : ($status === 'danger' ? 'danger' : 'neutral')),
            'label' => $label,
            'score' => $score,
            'share_label' => __('Fora da rede (est.)'),
            'share_value' => (string) ($gap['gap_total_fmt'] ?? '0'),
        ];
    }

    /**
     * @return list<string>
     */
    private static function enrollmentDiscrepancyCheckIds(): array
    {
        return [
            'matricula_duplicada',
            'matricula_situacao_invalida',
            'sem_data_nascimento',
            'sem_raca',
            'sem_sexo',
            'distorcao_idade_serie',
            'matricula_censo_vs_ieducar',
            'escola_inativa_matricula',
            'nee_sem_aee',
            'turma_aee_sem_nee',
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return array{
     *     mat: int,
     *     turmas: int,
     *     ocupacao: ?float,
     *     distorcao_pct: ?float,
     *     distorcao_com: int,
     *     pendencias: int,
     *     distorcao_indisponivel: bool
     * }
     */
    private static function enrollmentTabSnapshot(array $tabData, array $ctx): array
    {
        $data = self::tabPayload($tabData, 'enrollment');
        $kpis = is_array($data['kpis'] ?? null) ? $data['kpis'] : [];
        $d = is_array($data['distorcao'] ?? null) ? $data['distorcao'] : [];

        $mat = (int) ($kpis['matriculas'] ?? $ctx['total_matriculas'] ?? 0);
        $turmas = (int) ($kpis['turmas_distintas'] ?? 0);
        $ocup = isset($kpis['ocupacao_pct']) ? (float) $kpis['ocupacao_pct'] : null;

        $pct = isset($d['pct']) ? (float) $d['pct'] : null;
        if ($pct === null && isset($ctx['distorcao_pct'])) {
            $pct = (float) $ctx['distorcao_pct'];
        }

        $com = (int) ($d['com'] ?? $ctx['distorcao_com'] ?? 0);

        return [
            'mat' => $mat,
            'turmas' => $turmas,
            'ocupacao' => $ocup,
            'distorcao_pct' => $pct,
            'distorcao_com' => $com,
            'pendencias' => (int) ($ctx['pendencias_cadastro'] ?? 0),
            'distorcao_indisponivel' => $mat > 0 && $pct === null && $com <= 0,
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
            'network' => self::saldoFromNetworkOffer($tabData, $ctx),
            'enrollment' => self::saldoFromEnrollment($tabData, $ctx),
            'inclusion' => self::saldoFromInclusion($tabData, $ctx),
            'school_units' => self::saldoFromSchoolUnitsGeo($tabData),
            'performance' => self::saldoFromPerformance($tabData, $ctx),
            'attendance' => self::saldoFromAttendance($tabData, $ctx),
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
     * @param  array{perda: float, ganho: float, liquido: float, footnote: string, info_only?: bool, fundeb_lines?: list<string>}  $raw
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
            'fundeb_lines' => is_array($raw['fundeb_lines'] ?? null) ? $raw['fundeb_lines'] : [],
            'fundeb_calculo' => is_array($raw['fundeb_calculo'] ?? null) ? $raw['fundeb_calculo'] : null,
            'tab_share_label' => $tabStatus['share_label'] ?? null,
            'tab_share_value' => $tabStatus['share_value'] ?? null,
            'gain_only' => (bool) ($raw['gain_only'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoFromNetworkOffer(array $tabData, array $ctx = []): ?array
    {
        $data = self::tabPayload($tabData, 'network');
        $k = is_array($data['kpis'] ?? null) ? $data['kpis'] : [];
        $vagas = (int) ($k['vagas_ociosas'] ?? 0);

        if ($vagas <= 0) {
            return null;
        }

        $impact = self::tabFundingImpactFromReference('rede_vagas_ociosas', $vagas, self::resolveFundingReference($ctx));
        $perda = (float) $impact['perda_anual'];
        $ganho = (float) $impact['ganho_potencial_anual'];
        $liquido = round($ganho - $perda, 2);
        $taxa = ($k['taxa_ociosidade_pct'] ?? null) !== null
            ? number_format((float) $k['taxa_ociosidade_pct'], 1, ',', '.').'%'
            : '—';
        $rotulo = FundebReferenceDisplay::rotuloVaafCurto(self::resolveFundingReference($ctx));

        return [
            'perda' => $perda,
            'ganho' => $ganho,
            'liquido' => $liquido,
            'footnote' => __(
                ':vagas vagas ociosas × VAAF (:rotulo) × peso :peso (taxa de ociosidade :taxa). Eficiência da oferta — não é repasse FNDE.',
                [
                    'vagas' => number_format($vagas, 0, ',', '.'),
                    'rotulo' => $rotulo,
                    'peso' => number_format((float) $impact['peso'], 2, ',', '.'),
                    'taxa' => $taxa,
                ]
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoFromEnrollment(array $tabData, array $ctx = []): ?array
    {
        $snap = self::enrollmentTabSnapshot($tabData, $ctx);
        $mat = $snap['mat'];
        if ($mat <= 0) {
            return null;
        }

        $fundingRef = self::resolveFundingReference($ctx);
        $vaaf = (float) ($fundingRef['vaa_anual'] ?? 0);
        $ganhoRealizado = $vaaf > 0 ? MoneyMath::multiplyVaaf($mat, $vaaf) : 0.0;

        $ganhoCorrecao = 0.0;
        $correcaoNotes = [];
        $distorcaoFromChecks = false;

        $checks = self::discrepancyChecksFromTabData($tabData);
        if ($checks !== []) {
            $allowed = array_flip(self::enrollmentDiscrepancyCheckIds());
            foreach ($checks as $check) {
                if (! is_array($check)) {
                    continue;
                }
                $id = (string) ($check['id'] ?? '');
                if ($id === '' || ! isset($allowed[$id])) {
                    continue;
                }
                $occurrences = (int) ($check['total'] ?? 0);
                if ($occurrences <= 0) {
                    continue;
                }
                if ($id === 'distorcao_idade_serie') {
                    $distorcaoFromChecks = true;
                }
                $impact = self::tabFundingImpactFromReference($id, $occurrences, $fundingRef);
                $ganhoCorrecao += (float) $impact['ganho_potencial_anual'];
                $correcaoNotes[] = self::enrollmentCorrecaoFootnoteLine(
                    (string) ($check['titulo'] ?? $check['title'] ?? $id),
                    $occurrences,
                    $impact,
                );
            }
        }

        $com = $snap['distorcao_com'];
        if (! $distorcaoFromChecks && $com > 0) {
            $impact = self::tabFundingImpactFromReference('distorcao_idade_serie', $com, $fundingRef);
            $ganhoCorrecao += (float) $impact['ganho_potencial_anual'];
            $pct = $snap['distorcao_pct'];
            $correcaoNotes[] = __(
                'Distorção idade-série (ganho potencial ao regularizar): :n matrícula(s) (:p%) × VAAF × peso :peso.',
                [
                    'n' => number_format($com, 0, ',', '.'),
                    'p' => $pct !== null ? number_format($pct, 1, ',', '.') : '—',
                    'peso' => number_format((float) $impact['peso'], 2, ',', '.'),
                ]
            );
        }

        $ganho = round($ganhoRealizado + $ganhoCorrecao, 2);
        $fundebCalculo = FundebReferenceDisplay::blocoCalculoMatriculasVaaf($mat, $fundingRef);

        $rotulo = $fundingRef !== null ? FundebReferenceDisplay::rotuloVaafCurto($fundingRef) : __('VAAF');
        $vaafFmt = trim((string) ($fundingRef['vaa_label'] ?? ''));
        if ($vaafFmt === '' && $vaaf > 0) {
            $vaafFmt = DiscrepanciesFundingImpact::formatBrl($vaaf);
        }

        $footnoteParts = [
            __(
                'Ganho estimado (matrículas realizadas): :n × :vaaf/aluno/ano (:rotulo) ≈ :total/ano — não há perda nesta aba.',
                [
                    'n' => number_format($mat, 0, ',', '.'),
                    'vaaf' => $vaafFmt !== '' ? $vaafFmt : '—',
                    'rotulo' => $rotulo,
                    'total' => DiscrepanciesFundingImpact::formatBrl($ganhoRealizado),
                ]
            ),
        ];

        if ($ganhoCorrecao > 0 && $correcaoNotes !== []) {
            $footnoteParts[] = __(
                'Ganho potencial adicional ao corrigir cadastro no recorte: ≈ :valor/ano. :detalhe',
                [
                    'valor' => DiscrepanciesFundingImpact::formatBrl($ganhoCorrecao),
                    'detalhe' => implode(' ', $correcaoNotes),
                ]
            );
        }

        $footnoteParts[] = __('VAAF: municipal → prévia federal (config.) → estimativas → valor configurado (IEDUCAR_DISC_VAA_REFERENCIA). Não é repasse FNDE/Simec.');

        return [
            'perda' => 0.0,
            'ganho' => $ganho,
            'liquido' => $ganho,
            'gain_only' => true,
            'footnote' => implode(' ', $footnoteParts),
            'fundeb_calculo' => $fundebCalculo,
            'fundeb_lines' => self::enrollmentFundebLines($mat, $ctx),
        ];
    }

    /**
     * Impacto financeiro indicativo com o mesmo VAAF do contexto (Matrículas, Rede, etc.).
     *
     * @return array{perda_anual: float, ganho_potencial_anual: float, peso: float}
     */
    private static function tabFundingImpactFromReference(string $checkId, int $occurrences, ?array $fundingRef): array
    {
        $vaaf = (float) ($fundingRef['vaa_anual'] ?? 0);
        $peso = DiscrepanciesFundingImpact::pesoParaCheck($checkId);

        if ($vaaf <= 0) {
            $full = DiscrepanciesFundingImpact::estimate($checkId, $occurrences);

            return [
                'perda_anual' => (float) $full['perda_anual'],
                'ganho_potencial_anual' => (float) $full['ganho_potencial_anual'],
                'peso' => (float) $full['peso'],
            ];
        }

        $impact = MoneyMath::impactFromOccurrences($occurrences, $vaaf, $peso);

        return [
            'perda_anual' => $impact,
            'ganho_potencial_anual' => $impact,
            'peso' => $peso,
        ];
    }

    /**
     * @param  array{ganho_potencial_anual: float, peso: float}  $impact
     */
    private static function enrollmentCorrecaoFootnoteLine(string $rotina, int $occurrences, array $impact): string
    {
        return __(':rotina: :n ocorrência(s) — ganho potencial ≈ :ganho (VAAF × peso :peso).', [
            'rotina' => $rotina,
            'n' => number_format($occurrences, 0, ',', '.'),
            'ganho' => DiscrepanciesFundingImpact::formatBrl((float) $impact['ganho_potencial_anual']),
            'peso' => number_format((float) $impact['peso'], 2, ',', '.'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return ?array<string, mixed>
     */
    private static function resolveFundingReference(array $ctx): ?array
    {
        return is_array($ctx['funding_reference'] ?? null) ? $ctx['funding_reference'] : null;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return list<string>
     */
    private static function enrollmentFundebLines(int $matriculas, array $ctx): array
    {
        if ($matriculas <= 0) {
            return [];
        }

        $funding = self::resolveFundingReference($ctx);
        $line = FundebReferenceDisplay::linhaMatriculasVaafBase($matriculas, $funding);

        return $line !== null ? [$line] : [];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return list<array<string, mixed>>
     */
    private static function discrepancyChecksFromTabData(array $tabData): array
    {
        foreach (['discrepancies', 'discrepanciesData'] as $key) {
            $payload = $tabData[$key] ?? null;
            if (! is_array($payload)) {
                continue;
            }
            $checks = $payload['checks'] ?? null;
            if (is_array($checks) && $checks !== []) {
                return $checks;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoFromInclusion(array $tabData, array $ctx = []): ?array
    {
        $data = self::tabPayload($tabData, 'inclusion');
        $rec = is_array($data['recurso_prova'] ?? null) ? $data['recurso_prova'] : [];
        $semNee = (int) ($rec['sem_nee'] ?? 0);
        $neeSem = (int) ($rec['nee_sem_recurso'] ?? 0);
        $fundebNee = is_array($data['fundeb_nee'] ?? null) ? $data['fundeb_nee'] : [];
        $fundingRef = self::resolveFundingReference($ctx);

        $perda = 0.0;
        $ganho = 0.0;
        $parts = [];
        $fundebLines = [];
        $correcaoNotes = [];

        if (($fundebNee['available'] ?? false) && (int) ($fundebNee['matriculas_nee'] ?? 0) > 0) {
            $nNee = (int) $fundebNee['matriculas_nee'];
            $peso = (float) ($fundebNee['peso_educacao_especial'] ?? 1.2);
            $incremento = max(0.0, (float) ($fundebNee['incremento_ponderacao'] ?? ($peso - 1.0)));
            $adicionalVaaf = (float) ($fundebNee['adicional_vaaf_anual'] ?? $fundebNee['adicional_anual'] ?? 0);
            $adicionalVaar = (float) ($fundebNee['adicional_vaar_anual'] ?? 0);
            $totalIncremental = (float) ($fundebNee['total_incremental_anual'] ?? ($adicionalVaaf + $adicionalVaar));

            $ganho += $totalIncremental;

            $vaafFmt = (string) ($fundebNee['vaaf_fmt'] ?? ($fundingRef['vaa_label'] ?? ''));
            $rotulo = $fundingRef !== null
                ? FundebReferenceDisplay::rotuloVaafCurto($fundingRef)
                : __('VAAF');

            if ($adicionalVaaf > 0 && $incremento > 0) {
                $fundebLines[] = __(
                    'Ponderação FUNDEB educação especial (:fonte, factor :p): :n matrícula(s) NEE × :vaaf × :inc ≈ :adic/ano (incremento sobre matrícula de referência 1,00).',
                    [
                        'fonte' => (string) ($fundebNee['peso_fonte_label'] ?? __('Lei 14.113/2020')),
                        'p' => number_format($peso, 2, ',', '.'),
                        'n' => number_format($nNee, 0, ',', '.'),
                        'vaaf' => $vaafFmt !== '' ? $vaafFmt : '—',
                        'inc' => number_format($incremento, 2, ',', '.'),
                        'adic' => (string) ($fundebNee['adicional_vaaf_anual_fmt'] ?? $fundebNee['adicional_anual_fmt'] ?? '—'),
                    ]
                );
            }

            if ($adicionalVaar > 0) {
                $fundebLines[] = __(
                    'Parcela indicativa da complementação VAAR (:fonte): ≈ :vaar/ano (proporcional ao incremento NEE no total de matrículas do filtro).',
                    [
                        'fonte' => (string) ($fundebNee['adicional_vaar_fonte'] ?? __('importação ou % configurado')),
                        'vaar' => (string) ($fundebNee['adicional_vaar_anual_fmt'] ?? '—'),
                    ]
                );
            }

            if ($totalIncremental > 0) {
                $fundebLines[] = __(
                    'Ganho indicativo FUNDEB/VAAR (só incremento NEE, :rotulo): ≈ :total/ano — a base :base/aluno já entra na aba Matrículas.',
                    [
                        'rotulo' => $rotulo,
                        'total' => (string) ($fundebNee['total_incremental_anual_fmt'] ?? DiscrepanciesFundingImpact::formatBrl($totalIncremental)),
                        'base' => $vaafFmt !== '' ? $vaafFmt : '—',
                    ]
                );
            }
        }

        $checks = self::discrepancyChecksFromTabData($tabData);
        if ($checks !== []) {
            $allowed = array_flip(self::inclusionDiscrepancyCheckIds());
            foreach ($checks as $check) {
                if (! is_array($check)) {
                    continue;
                }
                $id = (string) ($check['id'] ?? '');
                if ($id === '' || ! isset($allowed[$id])) {
                    continue;
                }
                $occurrences = (int) ($check['total'] ?? 0);
                if ($occurrences <= 0) {
                    continue;
                }
                $impact = self::tabFundingImpactFromReference($id, $occurrences, $fundingRef);
                $perda += (float) $impact['perda_anual'];
                $ganho += (float) $impact['ganho_potencial_anual'];
                $correcaoNotes[] = self::enrollmentCorrecaoFootnoteLine(
                    (string) ($check['titulo'] ?? $check['title'] ?? $id),
                    $occurrences,
                    $impact,
                );
            }
        }

        if ($semNee > 0) {
            $e = self::tabFundingImpactFromReference('recurso_prova_sem_nee', $semNee, $fundingRef);
            $perda += (float) $e['perda_anual'];
            $ganho += (float) $e['ganho_potencial_anual'];
            $parts[] = __(':n recurso de prova sem NEE', ['n' => number_format($semNee, 0, ',', '.')]);
        }
        if ($neeSem > 0) {
            $e = self::tabFundingImpactFromReference('nee_sem_recurso_prova', $neeSem, $fundingRef);
            $perda += (float) $e['perda_anual'];
            $ganho += (float) $e['ganho_potencial_anual'];
            $parts[] = __(':n NEE sem recurso de prova', ['n' => number_format($neeSem, 0, ',', '.')]);
        }

        $riscoAee = is_array($fundebNee['risco_aee_sem_cadastro'] ?? null)
            ? $fundebNee['risco_aee_sem_cadastro']
            : [];
        $matAeeSemCadastro = self::matriculasAeeSemCadastroFromInclusionTab($data);
        if (($riscoAee['available'] ?? false) && (float) ($riscoAee['perda_anual'] ?? 0) > 0) {
            $perdaAee = (float) $riscoAee['perda_anual'];
            $ganhoAee = (float) ($riscoAee['ganho_potencial_anual'] ?? $perdaAee);
            $perda += $perdaAee;
            $ganho += $ganhoAee;
            $fundebLines[] = (string) ($riscoAee['observacao'] ?? '');
            if (filled($riscoAee['formula'] ?? null)) {
                $fundebLines[] = (string) $riscoAee['formula'];
            }
            $fundebLines[] = __(
                'Perda indicativa (turma AEE sem deficiência no cadastro): ≈ :valor/ano — :n matrícula(s).',
                [
                    'valor' => (string) ($riscoAee['perda_anual_fmt'] ?? DiscrepanciesFundingImpact::formatBrl($perdaAee)),
                    'n' => number_format($matAeeSemCadastro, 0, ',', '.'),
                ]
            );
        } elseif ($matAeeSemCadastro > 0) {
            $e = self::tabFundingImpactFromReference('aee_sem_nee', $matAeeSemCadastro, $fundingRef);
            $perda += (float) $e['perda_anual'];
            $ganho += (float) $e['ganho_potencial_anual'];
            $parts[] = __(
                ':n matrícula(s) em turma AEE sem deficiência no cadastro (perda indicativa ≈ :valor/ano ao regularizar cadastro Censo/NEE).',
                [
                    'n' => number_format($matAeeSemCadastro, 0, ',', '.'),
                    'valor' => DiscrepanciesFundingImpact::formatBrl((float) $e['perda_anual']),
                ]
            );
        }

        if ($parts === [] && $fundebLines === [] && $correcaoNotes === []) {
            return null;
        }

        $footnoteParts = $fundebLines;
        if ($correcaoNotes !== []) {
            $footnoteParts[] = __(
                'Risco cadastral (Discrepâncias / VAAR-inclusão): :detalhe.',
                ['detalhe' => implode(' ', $correcaoNotes)]
            );
        } elseif ($parts !== []) {
            $footnoteParts[] = __(
                'Risco cadastral (VAAR-inclusão): :detalhe.',
                ['detalhe' => implode('; ', $parts)]
            );
        }
        $footnoteParts[] = DiscrepanciesFundingImpact::avisoGeral();

        $ganho = round($ganho, 2);
        $perda = round($perda, 2);
        $temIncrementoFundeb = ($fundebNee['available'] ?? false)
            && (float) ($fundebNee['total_incremental_anual'] ?? 0) > 0;

        return [
            'perda' => $perda,
            'ganho' => $ganho,
            'liquido' => round($ganho - $perda, 2),
            'footnote' => implode(' ', array_filter($footnoteParts)),
            'fundeb_lines' => $fundebLines,
            'gain_only' => $perda <= 0 && $ganho > 0 && $temIncrementoFundeb,
            'info_only' => $perda <= 0 && $ganho <= 0 && $fundebLines !== [],
        ];
    }

    /**
     * @return list<string>
     */
    private static function inclusionDiscrepancyCheckIds(): array
    {
        return [
            'nee_sem_aee',
            'aee_sem_nee',
            'nee_subnotificacao',
            'recurso_prova_sem_nee',
            'nee_sem_recurso_prova',
            'recurso_prova_incompativel',
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
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoFromPerformance(array $tabData, array $ctx): ?array
    {
        $data = self::tabPayload($tabData, 'performance');
        $kpis = is_array($data['kpis'] ?? null) ? $data['kpis'] : [];
        $abandono = 0;
        $remanejamento = 0;
        foreach ($kpis as $kpi) {
            if (! is_array($kpi)) {
                continue;
            }
            $id = (string) ($kpi['id'] ?? '');
            $q = (int) ($kpi['quantidade'] ?? 0);
            if ($id === 'abandono') {
                $abandono = $q;
            }
            if ($id === 'remanejamento') {
                $remanejamento = $q;
            }
        }

        $fluxo = $abandono + $remanejamento;
        if ($fluxo <= 0) {
            return self::saldoPedagogicoCtxShare(
                $ctx,
                0.12,
                __('Sem abandono/remanejamento no filtro. Fatia indicativa (~12%%) do saldo municipal das Discrepâncias (eixo VAAR-indicadores).')
            );
        }

        $funding = DiscrepanciesFundingImpact::estimate('fluxo_abandono_remanejamento', $fluxo);

        return [
            'perda' => (float) $funding['perda_anual'],
            'ganho' => (float) $funding['ganho_potencial_anual'],
            'liquido' => round((float) $funding['ganho_potencial_anual'] - (float) $funding['perda_anual'], 2),
            'footnote' => __(
                ':aband matrícula(s) em abandono + :rem remanejamento × VAAF × peso :peso — risco de indicadores VAAR/Censo (não é repasse FNDE).',
                [
                    'aband' => number_format($abandono, 0, ',', '.'),
                    'rem' => number_format($remanejamento, 0, ',', '.'),
                    'peso' => number_format((float) $funding['peso'], 2, ',', '.'),
                ]
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $tabData
     * @param  array<string, mixed>  $ctx
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoFromAttendance(array $tabData, array $ctx): ?array
    {
        $data = self::tabPayload($tabData, 'attendance');
        $diag = self::attendanceDiagnostics($data);

        if ($diag['mode'] === 'query_error') {
            return self::saldoFromAttendanceGap($ctx, 'infrastructure', (string) ($data['error'] ?? ''));
        }

        if ($diag['mode'] === 'unavailable') {
            return self::saldoFromAttendanceGap(
                $ctx,
                'infrastructure',
                (string) ($diag['message'] ?? '')
            );
        }

        if ($diag['mode'] === 'empty') {
            return self::saldoFromAttendanceGap($ctx, 'empty', (string) ($diag['message'] ?? ''));
        }

        $totalFaltas = $diag['total_faltas'];
        $lotes = max(1, (int) round($totalFaltas / 25));
        $funding = DiscrepanciesFundingImpact::estimate('faltas_registro_mensal', $lotes);

        return [
            'perda' => (float) $funding['perda_anual'],
            'ganho' => (float) $funding['ganho_potencial_anual'],
            'liquido' => round((float) $funding['ganho_potencial_anual'] - (float) $funding['perda_anual'], 2),
            'footnote' => __(
                ':n registo(s) de falta no filtro (≈ :lotes lote(s) de 25) × VAAF × peso :peso — indicador operacional; não substitui glosa de programa.',
                [
                    'n' => number_format($totalFaltas, 0, ',', '.'),
                    'lotes' => number_format($lotes, 0, ',', '.'),
                    'peso' => number_format((float) $funding['peso'], 2, ',', '.'),
                ]
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{mode: string, message: string, total_faltas: int, has_charts: bool}
     */
    private static function attendanceDiagnostics(array $data): array
    {
        $message = trim((string) ($data['message'] ?? ''));
        $error = trim((string) ($data['error'] ?? ''));
        $unavailable = (bool) ($data['unavailable'] ?? false);
        $charts = is_array($data['charts'] ?? null) ? $data['charts'] : [];
        $hasCharts = $charts !== [] || ! empty($data['chart']);

        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        $totalFaltas = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $totalFaltas += (int) ($row['faltas'] ?? 0);
        }

        if ($error !== '') {
            return ['mode' => 'query_error', 'message' => $error, 'total_faltas' => 0, 'has_charts' => false];
        }

        if ($unavailable) {
            return ['mode' => 'unavailable', 'message' => $message, 'total_faltas' => 0, 'has_charts' => false];
        }

        if ($totalFaltas > 0 || $hasCharts) {
            return ['mode' => 'ok', 'message' => $message, 'total_faltas' => $totalFaltas, 'has_charts' => $hasCharts];
        }

        if ($message !== '') {
            return ['mode' => 'empty', 'message' => $message, 'total_faltas' => 0, 'has_charts' => false];
        }

        return ['mode' => 'empty', 'message' => __('Sem registros de falta para os filtros selecionados.'), 'total_faltas' => 0, 'has_charts' => false];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}
     */
    private static function saldoFromAttendanceGap(array $ctx, string $kind, string $detail): array
    {
        $mat = max(0, (int) ($ctx['total_matriculas'] ?? 0));
        $ratio = $kind === 'infrastructure' ? 0.25 : 0.12;
        $occurrences = $mat > 0
            ? min(800, max(1, (int) round($mat * $ratio)))
            : ($kind === 'infrastructure' ? 80 : 40);

        $checkId = $kind === 'infrastructure' ? 'frequencia_sem_base_faltas' : 'frequencia_nao_lancada';
        $funding = DiscrepanciesFundingImpact::estimate($checkId, $occurrences);

        $ctxShare = self::saldoPedagogicoCtxShare($ctx, $kind === 'infrastructure' ? 0.10 : 0.08, '');
        $perda = (float) $funding['perda_anual'];
        $ganho = (float) $funding['ganho_potencial_anual'];
        if ($ctxShare !== null) {
            $perda = max($perda, (float) $ctxShare['perda']);
            $ganho = max($ganho, (float) $ctxShare['ganho']);
        }

        $footnote = $kind === 'infrastructure'
            ? __(
                'Sem trilha de falta_aluno no filtro (:n matrícula(s) estimadas sem base × VAAF × peso :peso). :detalhe Configure IEDUCAR_TABLE_FALTA_ALUNO ou corrija colunas — risco PNAE/transporte.',
                [
                    'n' => number_format($occurrences, 0, ',', '.'),
                    'peso' => number_format((float) $funding['peso'], 2, ',', '.'),
                    'detalhe' => $detail !== '' ? $detail.' ' : '',
                ]
            )
            : __(
                'Nenhum lançamento de falta no período (:n matrícula(s) ativas no recorte × VAAF × peso :peso). Frequência não registada aumenta risco operacional e de programas (PNAE/transporte).',
                [
                    'n' => number_format($occurrences, 0, ',', '.'),
                    'peso' => number_format((float) $funding['peso'], 2, ',', '.'),
                ]
            );

        return [
            'perda' => round($perda, 2),
            'ganho' => round($ganho, 2),
            'liquido' => round($ganho - $perda, 2),
            'footnote' => $footnote,
        ];
    }

    /**
     * Fatia do saldo consolidado das Discrepâncias quando a aba não tem contagem própria.
     *
     * @return array{perda: float, ganho: float, liquido: float, footnote: string}|null
     */
    private static function saldoPedagogicoCtxShare(array $ctx, float $sharePct, string $footnote): ?array
    {
        $perda = (float) ($ctx['perda_estimada_anual'] ?? 0);
        $ganho = (float) ($ctx['ganho_potencial_anual'] ?? 0);
        if ($perda <= 0 && $ganho <= 0) {
            return null;
        }

        $factor = max(0.0, min(1.0, $sharePct));

        return [
            'perda' => round($perda * $factor, 2),
            'ganho' => round($ganho * $factor, 2),
            'liquido' => round(((float) ($ctx['saldo_liquido'] ?? ($ganho - $perda))) * $factor, 2),
            'footnote' => $footnote,
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
        $aeeSemCadastro = self::matriculasAeeSemCadastroFromInclusionTab($data);
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

        if ($aeeSemCadastro > 0) {
            return [
                'status' => 'warning',
                'label' => __(':n em turma AEE sem deficiência no cadastro', ['n' => $aeeSemCadastro]),
                'score' => 45,
                'share_label' => __('Risco FUNDEB/VAAR'),
                'share_value' => __('Cadastro'),
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
        $diag = self::attendanceDiagnostics($data);

        if ($diag['mode'] === 'query_error') {
            return [
                'status' => 'danger',
                'label' => __('Erro ao ler faltas — :msg', ['msg' => mb_strimwidth($diag['message'], 0, 120, '…')]),
                'score' => 12,
                'share_label' => null,
                'share_value' => null,
            ];
        }

        if ($diag['mode'] === 'unavailable') {
            $label = $diag['message'] !== ''
                ? $diag['message']
                : __('Base de faltas indisponível (falta_aluno)');

            return [
                'status' => 'danger',
                'label' => $label,
                'score' => 15,
                'share_label' => __('Cadastro'),
                'share_value' => __('Sem falta_aluno'),
            ];
        }

        if ($diag['mode'] === 'empty') {
            return [
                'status' => 'warning',
                'label' => __('Sem lançamento de faltas no filtro — risco PNAE/transporte'),
                'score' => 28,
                'share_label' => __('Registos'),
                'share_value' => '0',
            ];
        }

        $totalFaltas = $diag['total_faltas'];
        $charts = is_array($data['charts'] ?? null) ? $data['charts'] : [];
        $score = $totalFaltas >= 5000 ? 55 : ($totalFaltas >= 1500 ? 62 : 78);
        $status = $totalFaltas >= 5000 ? 'warning' : 'success';

        return [
            'status' => $status,
            'label' => __(':n registo(s) de falta no período', ['n' => number_format($totalFaltas, 0, ',', '.')]),
            'score' => $score,
            'share_label' => __('Meses'),
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
            'cadunico_previsao' => ['cadunico_previsao', 'cadunicoPrevisaoData'],
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
        if ($tab === 'attendance' && (bool) ($payload['unavailable'] ?? false)) {
            return $computed;
        }
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
            $aeeSem = self::matriculasAeeSemCadastroFromInclusionTab($data);
            if ($aeeSem > 0) {
                $issues[] = ['type' => 'warning', 'label' => __('Turma AEE sem deficiência no cadastro'), 'count' => $aeeSem];
            }
        }

        if ($tab === 'attendance') {
            $data = self::tabPayload($tabData, 'attendance');
            $diag = self::attendanceDiagnostics($data);
            if ($diag['mode'] === 'unavailable') {
                $issues[] = ['type' => 'unavailable', 'label' => __('Base falta_aluno inacessível'), 'count' => 1];
            } elseif ($diag['mode'] === 'query_error') {
                $issues[] = ['type' => 'error', 'label' => __('Falha ao agregar faltas'), 'count' => 1];
            } elseif ($diag['mode'] === 'empty') {
                $issues[] = ['type' => 'pending', 'label' => __('Frequência não lançada no filtro'), 'count' => 1];
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

        if ($tab === 'cadunico_previsao') {
            $data = self::tabPayload($tabData, 'cadunico_previsao');
            $gap = is_array($data['gap'] ?? null) ? $data['gap'] : [];
            if (! ($gap['available'] ?? false)) {
                $issues[] = ['type' => 'pending', 'label' => __('CadÚnico/Cecad não importado'), 'count' => 1];
            } elseif ((int) ($gap['gap_total'] ?? 0) > 50) {
                $issues[] = ['type' => 'warning', 'label' => __('Lacuna elevada na rede'), 'count' => 1];
            }
        }

        if ($tab === 'enrollment') {
            $snap = self::enrollmentTabSnapshot($tabData, $ctx);
            if ($snap['mat'] <= 0) {
                $issues[] = ['type' => 'unavailable', 'label' => __('Sem matrículas ativas no filtro'), 'count' => 1];
            }
            if ($snap['turmas'] <= 0 && $snap['mat'] > 0) {
                $issues[] = ['type' => 'pending', 'label' => __('Matrículas sem turma distinta no recorte'), 'count' => $snap['mat']];
            }
            if ($snap['distorcao_indisponivel']) {
                $issues[] = ['type' => 'unavailable', 'label' => __('Distorção idade-série indisponível'), 'count' => 1];
            }
            $pct = $snap['distorcao_pct'];
            if ($pct !== null && $pct >= 15) {
                $issues[] = ['type' => 'pending', 'label' => __('Distorção idade-série elevada (secção da aba)'), 'count' => (int) round($pct)];
            }
            $ocup = $snap['ocupacao'];
            if ($ocup !== null && $ocup < 20.0) {
                $issues[] = ['type' => 'pending', 'label' => __('Ocupação média muito baixa'), 'count' => (int) round($ocup)];
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
        if ($tab === 'attendance') {
            $parts[] = __('Sem falta_aluno ou sem lançamentos no filtro, o status fica em alerta (não neutro) e o saldo estima matrículas sem trilha de frequência (PNAE/transporte).');
        }
        if ($tab === 'enrollment') {
            $parts[] = __('O medidor resume matrículas, turmas, ocupação, pendências de cadastro e distorção (secção própria abaixo) — não usa só a distorção.');
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
            'cadunico_previsao' => self::pushCadunicoPrevisaoMetrics($out, $tabData),
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
    private static function pushCadunicoPrevisaoMetrics(array &$out, array $tabData): void
    {
        $data = self::tabPayload($tabData, 'cadunico_previsao');
        $gap = is_array($data['gap'] ?? null) ? $data['gap'] : [];
        if (($gap['cadunico_total_escolar'] ?? null) !== null) {
            $out[] = [
                'label' => __('CadÚnico (4-17)'),
                'value' => number_format((int) $gap['cadunico_total_escolar'], 0, ',', '.'),
                'tone' => 'neutral',
            ];
        }
        if (filled($gap['gap_total_fmt'] ?? null)) {
            $out[] = [
                'label' => __('Fora da rede'),
                'value' => (string) $gap['gap_total_fmt'],
                'tone' => ((int) ($gap['gap_total'] ?? 0) > 50) ? 'warning' : 'neutral',
            ];
        }
    }

    /**
     * @param  list<array{label: string, value: string, tone?: string}>  $out
     */
    private static function pushEnrollmentMetrics(array &$out, array $tabData): void
    {
        $data = self::tabPayload($tabData, 'enrollment');
        $kpis = is_array($data['kpis'] ?? null) ? $data['kpis'] : [];
        if (($kpis['matriculas'] ?? null) !== null) {
            $out[] = [
                'label' => __('Matrículas (aba)'),
                'value' => number_format((int) $kpis['matriculas'], 0, ',', '.'),
                'tone' => 'neutral',
            ];
        }
        if (($kpis['turmas_distintas'] ?? null) !== null) {
            $out[] = [
                'label' => __('Turmas'),
                'value' => number_format((int) $kpis['turmas_distintas'], 0, ',', '.'),
                'tone' => 'neutral',
            ];
        }
        if (isset($kpis['ocupacao_pct']) && $kpis['ocupacao_pct'] !== null) {
            $ocup = (float) $kpis['ocupacao_pct'];
            $out[] = [
                'label' => __('Ocupação média'),
                'value' => number_format($ocup, 1, ',', '.').'%',
                'tone' => $ocup < 20 ? 'warning' : 'neutral',
            ];
        }
        $d = is_array($data['distorcao'] ?? null) ? $data['distorcao'] : [];
        if (isset($d['pct'])) {
            $out[] = [
                'label' => __('Distorção (secção)'),
                'value' => number_format((float) $d['pct'], 1, ',', '.').'%',
                'tone' => ((float) $d['pct']) >= 15 ? 'danger' : 'warning',
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
        $aeeSem = self::matriculasAeeSemCadastroFromInclusionTab($data);
        if ($aeeSem > 0) {
            $out[] = [
                'label' => __('AEE sem cadastro NEE'),
                'value' => number_format($aeeSem, 0, ',', '.'),
                'tone' => 'warning',
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

    /**
     * Matrículas em turma AEE sem cadastro NEE (vínculo AEE exclusivo na contagem financeira).
     *
     * @param  array<string, mixed>  $data
     */
    private static function matriculasAeeSemCadastroFromInclusionTab(array $data): int
    {
        $fundeb = is_array($data['fundeb_nee'] ?? null) ? $data['fundeb_nee'] : [];
        $n = (int) ($fundeb['matriculas_aee_sem_cadastro'] ?? 0);
        if ($n > 0) {
            return $n;
        }
        $n = (int) data_get($data, 'aee_cross.matriculas_aee_sem_cadastro', 0);
        if ($n > 0) {
            return $n;
        }
        $rec = is_array($data['recurso_prova'] ?? null) ? $data['recurso_prova'] : [];

        return (int) ($rec['aee_sem_cadastro_nee'] ?? 0);
    }
}
