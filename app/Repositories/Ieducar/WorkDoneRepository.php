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
 * Mensura cadastros recentes no i-Educar por utilizador (excl. admin) e estima esforço restante.
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
            'baseline' => ['turmas' => 0, 'matriculas' => 0, 'ano' => 0],
            'estimativa' => [],
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
            'error' => null,
        ];

        if ($city === null || ! $filters->hasYearSelected()) {
            $base['intro'] = __('Seleccione cidade e ano letivo para acompanhar o Censo e o ritmo de cadastro.');

            return $base;
        }

        try {
            return $this->cityData->run($city, function ($db) use ($city, $filters, $yearLabel, $periodLabels, $base) {
                $ctx = IeducarWorkActivityQueries::matriculaActivityContext($db, $city);
                $currentMat = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters) ?? 0;
                $baseline = IeducarWorkActivityQueries::baselineFromPreviousYear($db, $city, $filters);
                $turmasAtual = IeducarWorkActivityQueries::countTurmasForYear($db, $city, $filters);

                $periods = ['day' => 0, 'week' => 0, 'fortnight' => 0];
                $byUser = [];
                $activityNote = $ctx['note'] ?? null;

                if ($ctx['available'] && filled($ctx['date_col'] ?? null)) {
                    $dateCol = (string) $ctx['date_col'];
                    $periods = IeducarWorkActivityQueries::matriculaCountsByPeriod(
                        $db,
                        $city,
                        $filters,
                        $dateCol,
                        $ctx['user_col'] ?? null
                    );
                    if (filled($ctx['user_col'] ?? null)) {
                        $byUser = IeducarWorkActivityQueries::matriculaByUserSince(
                            $db,
                            $city,
                            $filters,
                            $dateCol,
                            (string) $ctx['user_col'],
                            (int) config('ieducar.work_tracking.periods_days.fortnight', 15)
                        );
                    }
                }

                $estimativa = IeducarWorkActivityQueries::buildEstimate($baseline, $periods, $currentMat);
                $censo = IeducarCensoEscolaQueries::schoolStatuses($db, $city, $filters);

                $methodology = [
                    __('Contagem de matrículas cuja data de cadastro cai no período, com filtros aplicados (escola, curso, turno).'),
                    __('Utilizadores administrativos são excluídos conforme configuração (login, ID, nível).'),
                    __('Meta de esforço: matrículas ativas do ano letivo anterior (:ano) como referência de volume municipal.', ['ano' => $baseline['ano'] ?: '—']),
                    __('Turmas no ano anterior: :t — turmas no filtro actual: :ta.', [
                        't' => number_format($baseline['turmas'], 0, ',', '.'),
                        'ta' => number_format($turmasAtual, 0, ',', '.'),
                    ]),
                    $estimativa['usa_ritmo_observado']
                        ? __('Tempo estimado usa o ritmo observado na quinzena (cadastros/dia) e :h h/dia de trabalho.', [
                            'h' => config('ieducar.work_tracking.working_hours_per_day', 6),
                        ])
                        : __('Tempo estimado usa :min min/registro (configuração) por falta de ritmo observável na quinzena.', [
                            'min' => config('ieducar.work_tracking.default_minutes_per_record', 3.5),
                        ]),
                ];

                return array_merge($base, [
                    'year_label' => $yearLabel,
                    'city_name' => (string) $city->name,
                    'intro' => __(
                        'Situação do Educacenso por escola (exportado ou fechado no i-Educar, quando a base regista esse estado) e ritmo de cadastro recente por equipa municipal — apoio ao fecho do Censo e à alocação de esforço.'
                    ),
                    'footnote' => __(
                        'Exportação/fecho depende das tabelas do módulo Educacenso na instalação (detecção automática). Cadastro recente usa data em matrícula; não substitui auditoria completa do i-Educar.'
                    ),
                    'periods' => $periods,
                    'by_user' => $byUser,
                    'baseline' => $baseline,
                    'turmas_ano_atual' => $turmasAtual,
                    'matriculas_ativas' => $currentMat,
                    'estimativa' => $estimativa,
                    'activity_available' => (bool) $ctx['available'],
                    'activity_note' => $activityNote,
                    'chart_periods' => $this->chartPeriods($periods, $periodLabels),
                    'chart_users' => $this->chartUsers($byUser),
                    'censo' => $censo,
                    'chart_censo' => $this->chartCenso($censo),
                    'methodology' => $methodology,
                    'error' => null,
                ]);
            });
        } catch (QueryException|\Throwable $e) {
            $base['error'] = $e->getMessage();

            return $base;
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
            __('Cadastros na quinzena por utilizador i-Educar'),
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
}
