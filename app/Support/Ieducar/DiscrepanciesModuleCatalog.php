<?php

namespace App\Support\Ieducar;

/**
 * Agrupamento das rotinas de discrepância por módulo de cadastro (painel unificado).
 */
final class DiscrepanciesModuleCatalog
{
    /**
     * @return list<array{
     *   id: string,
     *   title: string,
     *   subtitle: string,
     *   routine_ids: list<string>,
     *   correction_tab: ?string,
     *   correction_label: ?string,
     *   correction_hint: ?string,
     *   admin_route: ?string,
     *   admin_route_label: ?string
     * }>
     */
    public static function modules(): array
    {
        return [
            [
                'id' => 'territorio',
                'title' => __('Território e mapa'),
                'subtitle' => __('Coordenadas, georreferenciação INEP e análise territorial (Cadastro → Unidades).'),
                'routine_ids' => ['escola_sem_geo'],
                'correction_tab' => 'school_units',
                'correction_label' => __('Corrigir em Unidades'),
                'correction_hint' => __('Preencha lat/lng na escola, sincronize INEP (Admin → Geo) ou importe school_unit_geos.'),
                'admin_route' => 'admin.geo-sync.index',
                'admin_route_label' => __('Sincronizar geo (admin)'),
            ],
            [
                'id' => 'escola',
                'title' => __('Unidade escolar'),
                'subtitle' => __('INEP, situação da escola e oferta da rede.'),
                'routine_ids' => ['escola_sem_inep', 'escola_inativa_matricula', 'rede_vagas_ociosas'],
                'correction_tab' => 'school_units',
                'correction_label' => __('Ver Unidades'),
                'correction_hint' => __('Regularize código INEP, situação activa e capacidade no i-Educar.'),
                'admin_route' => null,
                'admin_route_label' => null,
            ],
            [
                'id' => 'matricula',
                'title' => __('Matrícula e identificação'),
                'subtitle' => __('Dados obrigatórios do Censo, duplicidade e situação pedagógica.'),
                'routine_ids' => [
                    'sem_raca',
                    'sem_sexo',
                    'sem_data_nascimento',
                    'matricula_duplicada',
                    'matricula_situacao_invalida',
                    'distorcao_idade_serie',
                ],
                'correction_tab' => 'enrollment',
                'correction_label' => __('Ver Matrículas'),
                'correction_hint' => __('Ajuste ficha do aluno e situação da matrícula no i-Educar antes do Educacenso.'),
                'admin_route' => null,
                'admin_route_label' => null,
            ],
            [
                'id' => 'inclusao',
                'title' => __('Inclusão e avaliação'),
                'subtitle' => __('NEE, AEE, recursos de prova INEP e subnotificação.'),
                'routine_ids' => [
                    'nee_sem_aee',
                    'aee_sem_nee',
                    'nee_subnotificacao',
                    'recurso_prova_sem_nee',
                    'nee_sem_recurso_prova',
                    'recurso_prova_incompativel',
                ],
                'correction_tab' => 'inclusion',
                'correction_label' => __('Ver Inclusão'),
                'correction_hint' => __('Alinhe cadastro NEE, turmas AEE e recursos de prova ao Educacenso.'),
                'admin_route' => null,
                'admin_route_label' => null,
            ],
            [
                'id' => 'fundeb',
                'title' => __('Referência FUNDEB'),
                'subtitle' => __('Portaria FNDE, base de matrículas na importação e coerência IBGE × nome oficial.'),
                'routine_ids' => ['fundeb_vaaf_fonte_censo', 'fundeb_ibge_nome_divergente'],
                'correction_tab' => null,
                'correction_label' => null,
                'correction_hint' => __('Reimporte a portaria vigente (admin) e alinhe matrículas i-Educar antes das projeções de Finanças.'),
                'admin_route' => 'admin.ieducar-compatibility.index',
                'admin_route_label' => __('Importação FUNDEB (admin)'),
            ],
            [
                'id' => 'censo',
                'title' => __('Censo × i-Educar'),
                'subtitle' => __('Divergência entre microdados INEP e base municipal.'),
                'routine_ids' => ['matricula_censo_vs_ieducar'],
                'correction_tab' => 'work_done',
                'correction_label' => __('Ver Censo'),
                'correction_hint' => __('Reconcilie matrículas exportadas com o microdado INEP indexado.'),
                'admin_route' => 'admin.public-data.index',
                'admin_route_label' => __('Hub dados públicos'),
            ],
            [
                'id' => 'cadunico',
                'title' => __('CadÚnico × rede'),
                'subtitle' => __('Agregados Cecad/SAGI (4–17 anos), snapshot municipal e lacuna face às matrículas i-Educar.'),
                'routine_ids' => ['cadunico_snapshot_ausente', 'cadunico_rede_lacuna'],
                'correction_tab' => 'cadunico_previsao',
                'correction_label' => __('Ver CadÚnico'),
                'correction_hint' => __('Sincronize Cecad em Admin → CadÚnico e cruze faixas etárias com a rede antes do Censo.'),
                'admin_route' => 'admin.cadunico-sync.index',
                'admin_route_label' => __('Sincronizar CadÚnico (admin)'),
            ],
        ];
    }

    /**
     * Texto de métrica por rotina (ocorrências vs escolas).
     */
    public static function routineMetricSummary(array $routine): string
    {
        $id = (string) ($routine['id'] ?? '');
        if ($id === 'escola_sem_geo' && ! empty($routine['has_issue'])) {
            $schools = number_format((int) ($routine['schools_count'] ?? 0), 0, ',', '.');
            $mat = (int) ($routine['occurrences_total'] ?? 0);
            if ($mat > 0) {
                return __(':escolas escola(s) · :mat matr.', [
                    'escolas' => $schools,
                    'mat' => number_format($mat, 0, ',', '.'),
                ]);
            }

            return __(':escolas escola(s)', ['escolas' => $schools]);
        }

        if (! empty($routine['has_issue'])) {
            return number_format((int) ($routine['occurrences_total'] ?? 0), 0, ',', '.').' '.__('ocorr.');
        }

        return (string) ($routine['status_label'] ?? __('—'));
    }

    /**
     * @param  list<array<string, mixed>>  $dimensions
     * @param  list<array<string, mixed>>  $checks
     * @return list<array<string, mixed>>
     */
    public static function buildPanel(array $dimensions, array $checks): array
    {
        $byId = [];
        foreach ($dimensions as $dim) {
            if (! is_array($dim)) {
                continue;
            }
            $id = (string) ($dim['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $dim;
            }
        }

        $checksById = [];
        foreach ($checks as $check) {
            if (! is_array($check)) {
                continue;
            }
            $id = (string) ($check['id'] ?? '');
            if ($id !== '') {
                $checksById[$id] = $check;
            }
        }

        $out = [];
        foreach (self::modules() as $module) {
            $routines = [];
            $perda = 0.0;
            $ganho = 0.0;
            $occurrences = 0;
            $schools = [];
            $statusRank = 0;
            $hasUnavailable = false;
            $hasNoData = false;
            $hasAnalyzed = false;

            foreach ($module['routine_ids'] as $routineId) {
                $dim = $byId[$routineId] ?? null;
                if ($dim === null) {
                    continue;
                }

                $st = (string) ($dim['status'] ?? DiscrepanciesRoutineStatus::UNAVAILABLE);
                $statusRank = max($statusRank, self::statusRank($st));
                if ($st === DiscrepanciesRoutineStatus::UNAVAILABLE) {
                    $hasUnavailable = true;
                }
                if ($st === DiscrepanciesRoutineStatus::NO_DATA) {
                    $hasNoData = true;
                }
                if (! empty($dim['analyzed'])) {
                    $hasAnalyzed = true;
                }

                if (! empty($dim['has_issue'])) {
                    $occurrences += (int) ($dim['occurrences_total'] ?? $dim['total'] ?? 0);
                    $perda += (float) ($dim['perda_estimada_anual'] ?? 0);
                    $ganho += (float) ($dim['ganho_potencial_anual'] ?? 0);
                    foreach ($dim['escola_ids'] ?? [] as $eid) {
                        if ((string) $eid !== '') {
                            $schools[(string) $eid] = true;
                        }
                    }
                    if (($dim['schools_count'] ?? 0) > 0 && empty($dim['escola_ids'])) {
                        for ($i = 0; $i < (int) $dim['schools_count']; $i++) {
                            $schools[$routineId.'_'.$i] = true;
                        }
                    }
                }

                $routines[] = array_merge($dim, [
                    'check' => $checksById[$routineId] ?? null,
                    'detail_anchor' => 'disc-routine-'.$routineId,
                ]);
            }

            if ($routines === []) {
                continue;
            }

            $aggregateStatus = self::aggregateStatus($statusRank, $hasUnavailable, $hasNoData, $perda > 0 || $occurrences > 0);
            $issueCount = count(array_filter($routines, static fn (array $r): bool => (bool) ($r['has_issue'] ?? false)));

            $out[] = array_merge($module, [
                'anchor' => 'disc-mod-'.($module['id'] ?? ''),
                'status' => $aggregateStatus,
                'status_label' => self::aggregateLabel($aggregateStatus, $issueCount, $hasUnavailable, $hasNoData),
                'routines' => $routines,
                'routines_total' => count($routines),
                'routines_with_issue' => $issueCount,
                'occurrences_total' => $occurrences,
                'schools_affected' => count($schools) > 0 ? count($schools) : null,
                'perda_estimada_anual' => round($perda, 2),
                'ganho_potencial_anual' => round($ganho, 2),
                'has_analyzed_data' => $hasAnalyzed,
            ]);
        }

        usort($out, static function (array $a, array $b): int {
            $rankA = self::statusRank((string) ($a['status'] ?? ''));
            $rankB = self::statusRank((string) ($b['status'] ?? ''));
            if ($rankA !== $rankB) {
                return $rankB <=> $rankA;
            }

            return ((float) ($b['perda_estimada_anual'] ?? 0)) <=> ((float) ($a['perda_estimada_anual'] ?? 0));
        });

        return $out;
    }

    private static function statusRank(string $status): int
    {
        return match ($status) {
            'danger' => 50,
            'warning' => 40,
            DiscrepanciesRoutineStatus::NO_DATA => 20,
            DiscrepanciesRoutineStatus::OK => 10,
            default => 0,
        };
    }

    private static function aggregateStatus(int $rank, bool $hasUnavailable, bool $hasNoData, bool $hasIssue): string
    {
        if ($rank >= 50) {
            return 'danger';
        }
        if ($rank >= 40) {
            return 'warning';
        }
        if ($hasIssue) {
            return 'warning';
        }
        if ($hasNoData && ! $hasUnavailable) {
            return DiscrepanciesRoutineStatus::NO_DATA;
        }
        if ($hasUnavailable && $rank < 10) {
            return DiscrepanciesRoutineStatus::UNAVAILABLE;
        }
        if ($rank >= 10) {
            return DiscrepanciesRoutineStatus::OK;
        }

        return DiscrepanciesRoutineStatus::UNAVAILABLE;
    }

    private static function aggregateLabel(string $status, int $issueCount, bool $hasUnavailable, bool $hasNoData): string
    {
        return match ($status) {
            'danger' => __('Crítico — :n rotina(s) com pendência', ['n' => $issueCount]),
            'warning' => $issueCount > 0
                ? __('Atenção — :n rotina(s) com pendência', ['n' => $issueCount])
                : __('Atenção'),
            DiscrepanciesRoutineStatus::NO_DATA => __('Sem dados para analisar'),
            DiscrepanciesRoutineStatus::OK => __('Sem pendência detectada'),
            default => $hasUnavailable
                ? __('Indisponível nesta base')
                : ($hasNoData ? __('Sem dados no filtro') : __('Indisponível')),
        };
    }
}
