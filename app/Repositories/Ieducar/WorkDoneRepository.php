<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarCensoEscolaQueries;
use App\Support\Ieducar\IeducarUsuarioScope;
use App\Support\Ieducar\IeducarWorkActivityQueries;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\QueryException;

/**
 * Mensura cadastros recentes no i-Educar por usuário (excl. admin) e estima esforço restante.
 */
class WorkDoneRepository
{
    public function __construct(
        private CityDataConnection $cityData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildReport(?City $city, IeducarFilterState $filters): array
    {
        $yearLabel = $this->yearLabel($filters);
        $periodLabels = [
            'day' => __('Último dia'),
            'week' => __('Última semana'),
            'fortnight' => __('Última quinzena'),
        ];

        $base = [
            'year_label' => $yearLabel,
            'city_name' => $city?->name ?? '',
            'intro' => '',
            'footnote' => '',
            'period_labels' => $periodLabels,
            'periods' => ['day' => 0, 'week' => 0, 'fortnight' => 0],
            'by_user' => [],
            'baseline' => ['turmas' => 0, 'matriculas' => 0, 'enturmacoes' => 0, 'ano' => 0],
            'turmas_ano_atual' => 0,
            'enturmacoes_ano_atual' => 0,
            'matriculas_ativas' => 0,
            'alunos_distintos' => null,
            'total_matriculas' => null,
            'estimativa' => [],
            'chart_cadastro_meta' => null,
            'exclusion_notes' => IeducarUsuarioScope::exclusionLabels(),
            'activity_available' => false,
            'activity_note' => null,
            'chart_periods' => null,
            'chart_users' => null,
            'methodology' => [],
            'censo' => [
                'available' => false,
                'source_label' => null,
                'note' => null,
                'exported' => [],
                'closed' => [],
                'pending' => [],
                'summary' => ['total_escolas' => 0, 'exportadas' => 0, 'fechadas' => 0, 'pendentes' => 0],
            ],
            'chart_censo' => null,
            'year_closure' => null,
            'error' => null,
        ];

        if ($city === null || ! $filters->hasYearSelected()) {
            $base['intro'] = __('Selecione cidade e ano letivo para acompanhar o Censo e o ritmo de cadastro.');

            return $base;
        }

        try {
            return $this->cityData->run($city, function ($db) use ($city, $filters, $yearLabel, $periodLabels, $base) {
                $ctx = IeducarWorkActivityQueries::matriculaActivityContext($db, $city);
                $volume = MatriculaChartQueries::volumeCounts($db, $city, $filters);
                $currentMat = $volume['matriculas'];
                $alunosDistintos = $volume['alunos_available'] && ($volume['alunos'] ?? 0) > 0
                    ? (int) $volume['alunos']
                    : null;
                $baseline = IeducarWorkActivityQueries::baselineFromPreviousYear($db, $city, $filters);
                $turmasAtual = IeducarWorkActivityQueries::countTurmasForYear($db, $city, $filters);
                $enturmacoesAtual = IeducarWorkActivityQueries::countEnturmacoesForYear($db, $city, $filters);

                $periods = ['day' => 0, 'week' => 0, 'fortnight' => 0];
                $byUser = [];
                $activityNote = $ctx['note'] ?? null;

                if ($ctx['available'] && filled($ctx['date_expr'] ?? null)) {
                    $dateExpr = (string) $ctx['date_expr'];
                    $periods = IeducarWorkActivityQueries::matriculaCountsByPeriod(
                        $db,
                        $city,
                        $filters,
                        $dateExpr,
                        $ctx['user_col'] ?? null
                    );
                    if (filled($ctx['user_col'] ?? null) && IeducarUsuarioScope::resolve($db, $city) !== null) {
                        try {
                            $byUser = IeducarWorkActivityQueries::matriculaByUserSince(
                                $db,
                                $city,
                                $filters,
                                $dateExpr,
                                (string) $ctx['user_col'],
                                (int) config('ieducar.work_tracking.periods_days.fortnight', 15)
                            );
                        } catch (\Throwable $e) {
                            $activityNote = ($activityNote ? $activityNote.' ' : '')
                                .__('Tabela por usuário indisponível: :msg', ['msg' => $e->getMessage()]);
                        }
                    }
                }

                $estimativa = IeducarWorkActivityQueries::buildEstimate(
                    $baseline,
                    $periods,
                    $turmasAtual,
                    $currentMat,
                    $enturmacoesAtual,
                    $byUser,
                );
                $censo = IeducarCensoEscolaQueries::schoolStatuses($db, $city, $filters);
                $anoStatus = IeducarWorkActivityQueries::anoLetivoStatus($db, $city, $filters);
                $yearClosure = IeducarWorkActivityQueries::yearClosureInsight($filters, $censo, $periods, $anoStatus);

                $methodology = [
                    __('Turmas: turmas distintas no ano letivo do filtro. Matrículas: vínculos ativos em matricula. Enturmações: vínculos matrícula↔turma (pivô matricula_turma ou coluna directa).'),
                    __('Cadastro recente: matrículas com data de cadastro no período (dia/semana/quinzena), com filtros aplicados.'),
                    __('Usuárioes administrativos são excluídos conforme configuração (login, ID, nível).'),
                    __('Meta de volume: totais do ano letivo anterior (:ano) — turmas :t, matrículas :m, enturmações :e.', [
                        'ano' => $baseline['ano'] ?: '—',
                        't' => number_format((int) $baseline['turmas'], 0, ',', '.'),
                        'm' => number_format((int) $baseline['matriculas'], 0, ',', '.'),
                        'e' => number_format((int) ($baseline['enturmacoes'] ?? 0), 0, ',', '.'),
                    ]),
                    $estimativa['usa_ritmo_observado']
                        ? __('Tempo: minutos por tipo derivados do ritmo municipal (:ritmo cad./dia, :q na quinzena); prazo em dias usa ritmo da equipe (:ritmo_eq/dia, :u usuário(es)). Turmas ponderadas em relação à matrícula (peso relativo da configuração). :h h/dia de capacidade por pessoa.', [
                            'ritmo' => number_format((float) ($estimativa['ritmo_por_dia'] ?? 0), 1, ',', '.'),
                            'q' => number_format((int) ($estimativa['cadastros_ultima_quinzena'] ?? 0), 0, ',', '.'),
                            'ritmo_eq' => number_format((float) ($estimativa['ritmo_equipe_por_dia'] ?? 0), 1, ',', '.'),
                            'u' => (int) ($estimativa['usuários_ativos_quinzena'] ?? 0),
                            'h' => config('ieducar.work_tracking.working_hours_per_day', 6),
                        ])
                        : __('Tempo: referência fixa (:mt min/turma, :mm min/matricula, :me min/enturmação) só quando não há cadastro recente mensurável na base.', [
                            'mt' => number_format((float) ($estimativa['minutos_por_turma'] ?? 8), 1, ',', '.'),
                            'mm' => number_format((float) ($estimativa['minutos_por_matricula'] ?? 3.5), 1, ',', '.'),
                            'me' => number_format((float) ($estimativa['minutos_por_enturmacao'] ?? 2.5), 1, ',', '.'),
                        ]),
                ];

                return array_merge($base, [
                    'year_label' => $yearLabel,
                    'city_name' => (string) $city->name,
                    'intro' => __(
                        'Situação do Educacenso por escola (exportado ou fechado no i-Educar, quando a base regista esse estado) e ritmo de cadastro recente por equipe municipal — apoio ao fecho do Censo e à alocação de esforço.'
                    ),
                    'footnote' => __(
                        'Exportação/fecho depende das tabelas do módulo Educacenso na instalação (detecção automática). Cadastro recente usa data em matrícula; não substitui auditoria completa do i-Educar.'
                    ),
                    'periods' => $periods,
                    'by_user' => $byUser,
                    'baseline' => $baseline,
                    'turmas_ano_atual' => $turmasAtual,
                    'enturmacoes_ano_atual' => $enturmacoesAtual,
                    'matriculas_ativas' => $currentMat,
                    'alunos_distintos' => $alunosDistintos,
                    'total_matriculas' => $currentMat > 0 ? $currentMat : null,
                    'estimativa' => $estimativa,
                    'chart_cadastro_meta' => $this->chartCadastroMeta($estimativa),
                    'activity_available' => (bool) $ctx['available'],
                    'activity_note' => $activityNote,
                    'chart_periods' => $this->chartPeriods($periods, $periodLabels),
                    'chart_users' => $this->chartUsers($byUser),
                    'censo' => $censo,
                    'chart_censo' => $this->chartCenso($censo),
                    'year_closure' => $yearClosure,
                    'methodology' => $methodology,
                    'error' => null,
                ]);
            });
        } catch (QueryException|\Throwable $e) {
            return array_merge($base, [
                'city_name' => (string) $city->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function yearLabel(IeducarFilterState $filters): string
    {
        if (! $filters->hasYearSelected()) {
            return '';
        }
        if ($filters->isAllSchoolYears()) {
            return __('Todos os anos (consolidado no filtro)');
        }

        return __('Ano letivo :ano', ['ano' => $filters->ano_letivo]);
    }

    /**
     * @param  array{day: int, week: int, fortnight: int}  $periods
     * @param  array<string, string>  $labels
     * @return ?array<string, mixed>
     */
    private function chartPeriods(array $periods, array $labels): ?array
    {
        if (array_sum($periods) === 0) {
            return null;
        }

        return ChartPayload::bar(
            __('Matrículas cadastradas por período'),
            __('Registos'),
            [
                $labels['day'] ?? __('Dia'),
                $labels['week'] ?? __('Semana'),
                $labels['fortnight'] ?? __('Quinzena'),
            ],
            [
                (int) ($periods['day'] ?? 0),
                (int) ($periods['week'] ?? 0),
                (int) ($periods['fortnight'] ?? 0),
            ]
        );
    }

    /**
     * @param  list<array{usuario_id: int, login: string, nome: string, total: int}>  $byUser
     * @return ?array<string, mixed>
     */
    private function chartUsers(array $byUser): ?array
    {
        if ($byUser === []) {
            return null;
        }

        $top = array_slice($byUser, 0, 12);
        $labels = [];
        $data = [];
        foreach ($top as $row) {
            $login = filled($row['login'] ?? '') ? $row['login'] : ('#'.$row['usuario_id']);
            $labels[] = mb_substr($login, 0, 24);
            $data[] = (int) ($row['total'] ?? 0);
        }

        return ChartPayload::barHorizontal(
            __('Cadastros na quinzena por usuário i-Educar'),
            __('Matrículas'),
            $labels,
            $data
        );
    }

    /**
     * @param  array<string, mixed>  $censo
     * @return ?array<string, mixed>
     */
    private function chartCenso(array $censo): ?array
    {
        if (! ($censo['available'] ?? false)) {
            return null;
        }
        $s = is_array($censo['summary'] ?? null) ? $censo['summary'] : [];
        $data = [
            (int) ($s['exportadas'] ?? 0),
            (int) ($s['fechadas'] ?? 0),
            (int) ($s['pendentes'] ?? 0),
        ];
        if (array_sum($data) === 0) {
            return null;
        }

        return ChartPayload::bar(
            __('Escolas no filtro — situação Censo/Educacenso'),
            __('Unidades'),
            [__('Exportado'), __('Fechado'), __('Pendente')],
            $data
        );
    }

    /**
     * @param  array<string, mixed>  $est
     * @return ?array<string, mixed>
     */
    private function chartCadastroMeta(array $est): ?array
    {
        $meta = [
            (int) ($est['meta_turmas_ano_anterior'] ?? 0),
            (int) ($est['meta_matriculas_ano_anterior'] ?? 0),
            (int) ($est['meta_enturmacoes_ano_anterior'] ?? 0),
        ];
        $atual = [
            (int) ($est['turmas_filtro_atual'] ?? 0),
            (int) ($est['matriculas_ativas_filtro'] ?? 0),
            (int) ($est['enturmacoes_filtro_atual'] ?? 0),
        ];
        if (array_sum($meta) === 0 && array_sum($atual) === 0) {
            return null;
        }

        return ChartPayload::barHorizontalGrouped(
            __('Meta (ano anterior) vs cadastro actual'),
            __('Quantidade'),
            [__('Turmas'), __('Matrículas'), __('Enturmações')],
            [
                ['label' => __('Ano anterior (meta)'), 'data' => $meta],
                ['label' => __('Ano actual (filtro)'), 'data' => $atual],
            ]
        );
    }
}
