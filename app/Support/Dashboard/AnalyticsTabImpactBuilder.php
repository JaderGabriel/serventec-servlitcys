<?php

namespace App\Support\Dashboard;

use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Faixa visual no topo de cada aba (até Censo): impacto no saldo indicativo + status municipal no filtro.
 */
final class AnalyticsTabImpactBuilder
{
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

        if (! $yearFilterReady) {
            return [
                'ready' => false,
                'tab' => $tab,
                'title' => $def['title'],
                'purpose' => $def['purpose'],
                'status' => 'neutral',
                'status_label' => __('Aguardando filtros'),
                'tab_score' => null,
                'saldo' => null,
                'metrics' => [],
            ];
        }

        $ctx = $municipalityContext ?? [];
        $tabStatus = self::tabStatus($tab, $tabData, $ctx);
        $metrics = self::tabMetrics($tab, $tabData, $ctx);

        $perda = (float) ($ctx['perda_estimada_anual'] ?? 0);
        $ganho = (float) ($ctx['ganho_potencial_anual'] ?? 0);
        $liquido = (float) ($ctx['saldo_liquido'] ?? ($ganho - $perda));

        return [
            'ready' => true,
            'tab' => $tab,
            'title' => $def['title'],
            'purpose' => $def['purpose'],
            'impact_note' => $def['impact_note'],
            'status' => $tabStatus['status'],
            'status_label' => $tabStatus['label'],
            'tab_score' => $tabStatus['score'],
            'municipality_score' => (int) ($ctx['compliance_score'] ?? 0),
            'municipality_status' => (string) ($ctx['compliance_status'] ?? 'neutral'),
            'municipality_label' => (string) ($ctx['compliance_label'] ?? ''),
            'saldo' => [
                'perda' => $perda,
                'perda_fmt' => DiscrepanciesFundingImpact::formatBrl($perda),
                'ganho' => $ganho,
                'ganho_fmt' => DiscrepanciesFundingImpact::formatBrl($ganho),
                'liquido' => $liquido,
                'liquido_fmt' => AnalyticsMunicipalityContext::formatSaldo($liquido),
                'liquido_tone' => $liquido >= 0 ? 'success' : 'danger',
                'tab_share_label' => $tabStatus['share_label'],
                'tab_share_value' => $tabStatus['share_value'],
            ],
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
                'impact_note' => __('Pendências de exportação Censo ligam directamente ao fundeb-base e repasse.'),
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
            'share_label' => __('Matrículas no filtro'),
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
        $markers = is_array($tab['markers'] ?? null) ? count($tab['markers']) : 0;
        $waiting = is_array($tab['waiting'] ?? null) ? $tab['waiting'] : [];
        $lista = (int) ($waiting['total'] ?? $waiting['total_alunos'] ?? 0);

        $status = $lista > 50 ? 'warning' : ($markers > 0 ? 'success' : 'neutral');
        $score = $markers > 0 ? max(45, 100 - min(40, (int) round($lista / 5))) : 50;

        return [
            'status' => $status,
            'label' => $markers > 0
                ? __(':e escola(s) no mapa — :l em lista de espera', ['e' => $markers, 'l' => $lista])
                : __('Sem unidades georreferenciadas no filtro'),
            'score' => $score,
            'share_label' => __('Unidades no mapa'),
            'share_value' => (string) $markers,
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
            return ['status' => 'neutral', 'label' => __('Sem registos de falta no filtro'), 'score' => 60, 'share_label' => null, 'share_value' => null];
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
     * @param  array<string, mixed>  $ctx
     * @return list<array{label: string, value: string, tone?: string}>
     */
    private static function tabMetrics(string $tab, array $tabData, array $ctx): array
    {
        $out = [];
        if (($ctx['pendencias_cadastro'] ?? 0) > 0) {
            $out[] = [
                'label' => __('Pendências cadastro'),
                'value' => (string) (int) $ctx['pendencias_cadastro'],
                'tone' => 'warning',
            ];
        }
        if (($ctx['perda_estimada_anual'] ?? 0) > 0) {
            $out[] = [
                'label' => __('Perda est. (ano)'),
                'value' => DiscrepanciesFundingImpact::formatBrl((float) $ctx['perda_estimada_anual']),
                'tone' => 'danger',
            ];
        }
        if (($ctx['ganho_potencial_anual'] ?? 0) > 0) {
            $out[] = [
                'label' => __('Ganho potencial'),
                'value' => DiscrepanciesFundingImpact::formatBrl((float) $ctx['ganho_potencial_anual']),
                'tone' => 'success',
            ];
        }
        if (($ctx['total_matriculas'] ?? null) !== null) {
            $out[] = [
                'label' => __('Matrículas (filtro)'),
                'value' => number_format((int) $ctx['total_matriculas'], 0, ',', '.'),
                'tone' => 'neutral',
            ];
        }

        return array_slice($out, 0, 4);
    }
}
