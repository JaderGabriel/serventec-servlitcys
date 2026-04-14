<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\MatriculaAtivoFilter;
use App\Support\Ieducar\MatriculaTurmaJoin;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Contexto de anos letivos, unidades escolares, mapa e indicadores opcionais (transporte, lista de espera).
 */
class SchoolUnitsRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   overview: array{
     *     year_global_rows: list<array{ano: int, status: string, detalhe: string}>,
     *     school_year_rows: list<array{escola: string, ano: int, status: string, detalhe: string}>,
     *     units_rows: list<array{escola: string, porte: string, unidade_status: string, matriculas: int}>,
     *     notes: list<string>
     *   },
     *   tab: array{
     *     markers: list<array{lat: float, lng: float, label: string, meta: string}>,
     *     transport: ?array{texto: string, linhas: list<string>},
     *     waiting: ?array{texto: string, turmas_com_lista: ?int, soma_lista: ?int, vagas_declaradas: ?int},
     *     geo_note: ?string,
     *     error: ?string
     *   },
     *   error: ?string
     * }
     */
    public function snapshot(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null || ! $filters->hasYearSelected()) {
            return [
                'overview' => [
                    'year_global_rows' => [],
                    'school_year_rows' => [],
                    'units_rows' => [],
                    'notes' => [],
                ],
                'tab' => [
                    'markers' => [],
                    'transport' => null,
                    'waiting' => null,
                    'geo_note' => null,
                    'error' => null,
                ],
                'error' => null,
            ];
        }

        try {
            return $this->cityData->run($city, function (Connection $db) use ($city, $filters) {
                $notes = [];

                $years = $this->yearsNumericScope($db, $city, $filters);
                if ($years === []) {
                    $notes[] = __('Não foi possível determinar anos letivos (turma/ano letivo).');
                }

                $yearGlobalRows = $this->anoLetivoGlobalRows($db, $city, $years);
                $statusByYear = [];
                foreach ($yearGlobalRows as $r) {
                    $statusByYear[(int) $r['ano']] = ['status' => $r['status'], 'detalhe' => $r['detalhe']];
                }

                $schoolYearRows = $this->schoolYearMatrix($db, $city, $filters, $years, $statusByYear);
                $unitsRows = $this->unitsWithPorte($db, $city, $filters);

                $markers = $this->mapMarkers($db, $city, $filters);
                if ($markers === []) {
                    $geoNote = __('Coordenadas não encontradas nas colunas habituais da escola (latitude/longitude). O mapa só aparece quando a base as preenche.');
                } else {
                    $geoNote = null;
                }

                $transport = $this->transportHint($db, $city, $filters);
                $waiting = $this->waitingListHint($db, $city, $filters);

                return [
                    'overview' => [
                        'year_global_rows' => $yearGlobalRows,
                        'school_year_rows' => $schoolYearRows,
                        'units_rows' => $unitsRows,
                        'notes' => $notes,
                    ],
                    'tab' => [
                        'markers' => $markers,
                        'transport' => $transport,
                        'waiting' => $waiting,
                        'geo_note' => $geoNote,
                        'error' => null,
                    ],
                    'error' => null,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'overview' => [
                    'year_global_rows' => [],
                    'school_year_rows' => [],
                    'units_rows' => [],
                    'notes' => [],
                ],
                'tab' => [
                    'markers' => [],
                    'transport' => null,
                    'waiting' => null,
                    'geo_note' => null,
                    'error' => $e->getMessage(),
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return list<int>
     */
    private function yearsNumericScope(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $yv = $filters->yearFilterValue();
        if ($yv !== null) {
            return [$yv];
        }
        try {
            $turma = IeducarSchema::resolveTable('turma', $city);
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['year'] === '') {
                return [];
            }
            $col = $tc['year'];
            $years = $db->table($turma)
                ->selectRaw('DISTINCT CAST('.$db->getQueryGrammar()->wrap($col).' AS UNSIGNED) as y')
                ->whereNotNull($col)
                ->orderByDesc('y')
                ->limit(12)
                ->pluck('y')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 1990 && $v < 2100)
                ->values()
                ->all();

            return $years;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $years
     * @return list<array{ano: int, status: string, detalhe: string}>
     */
    private function anoLetivoGlobalRows(Connection $db, City $city, array $years): array
    {
        if ($years === []) {
            return [];
        }
        try {
            $table = IeducarSchema::resolveTable('ano_letivo', $city);
            if (! IeducarColumnInspector::tableExists($db, $table, $city)) {
                return [];
            }
            $yearCol = IeducarColumnInspector::firstExistingColumn($db, $table, array_filter([
                (string) config('ieducar.columns.ano_letivo.year'),
                'ano',
            ]), $city);
            if ($yearCol === null) {
                return [];
            }
            $g = $db->getQueryGrammar();
            $wYear = $g->wrap($yearCol);
            $rows = $db->table($table)
                ->whereIn($yearCol, $years)
                ->orderBy($yearCol)
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $ano = (int) ($arr[$yearCol] ?? 0);
                if ($ano <= 0) {
                    continue;
                }
                [$st, $det] = $this->interpretAnoLetivoRow($arr);
                $out[] = ['ano' => $ano, 'status' => $st, 'detalhe' => $det];
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $arr
     * @return array{0: string, 1: string}
     */
    private function interpretAnoLetivoRow(array $arr): array
    {
        $keys = array_change_key_case($arr, CASE_LOWER);
        if (isset($keys['andamento'])) {
            $a = (int) $keys['andamento'];
            if ($a === 1) {
                return [__('Em andamento'), ''];
            }
            if ($a === 2) {
                return [__('Finalizado'), ''];
            }
            if ($a === 3) {
                return [__('Em elaboração'), ''];
            }

            return [__('Andamento :v', ['v' => (string) $a]), ''];
        }
        if (isset($keys['ativo'])) {
            $a = (int) $keys['ativo'];

            return $a === 1
                ? [__('Ativo (ano letivo)'), '']
                : [__('Inativo / encerrado na base'), ''];
        }
        foreach (['data_fechamento', 'data_fim', 'data_finalizacao'] as $dk) {
            if (! empty($keys[$dk])) {
                return [__('Com data de encerramento registada'), (string) $keys[$dk]];
            }
        }

        return [__('Ano letivo cadastrado'), ''];
    }

    /**
     * @param  list<int>  $years
     * @param  array<int, array{status: string, detalhe: string}>  $statusByYear
     * @return list<array{escola: string, ano: int, status: string, detalhe: string}>
     */
    private function schoolYearMatrix(Connection $db, City $city, IeducarFilterState $filters, array $years, array $statusByYear): array
    {
        if ($years === []) {
            return [];
        }
        try {
            $turma = IeducarSchema::resolveTable('turma', $city);
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['year'] === '' || $tc['escola'] === '') {
                return [];
            }
            $yCol = $tc['year'];
            $eCol = $tc['escola'];
            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eId = (string) config('ieducar.columns.escola.id');
            $eName = (string) config('ieducar.columns.escola.name');

            $g = $db->getQueryGrammar();
            $wE = $g->wrap('e');
            $wT = $g->wrap('t');
            $anoSelect = $db->getDriverName() === 'pgsql'
                ? 'CAST('.$wT.'.'.$g->wrap($yCol).' AS integer)'
                : 'CAST('.$wT.'.'.$g->wrap($yCol).' AS UNSIGNED)';

            $q = $db->table($turma.' as t')
                ->join($escolaT.' as e', 't.'.$eCol, '=', 'e.'.$eId)
                ->whereIn('t.'.$yCol, $years)
                ->selectRaw($wE.'.'.$g->wrap($eName).' as escola_nome')
                ->selectRaw($anoSelect.' as ano')
                ->groupBy('e.'.$eId, 'e.'.$eName, 't.'.$yCol)
                ->orderBy($wE.'.'.$g->wrap($eName))
                ->orderBy('ano');

            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['turno'], $filters->turno_id);

            $rows = $q->limit(800)->get();
            $out = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $ano = (int) ($arr['ano'] ?? 0);
                $nome = trim((string) ($arr['escola_nome'] ?? ''));
                $st = $statusByYear[$ano]['status'] ?? __('Turmas ativas neste ano (detalhe em ano letivo indisponível)');
                $det = $statusByYear[$ano]['detalhe'] ?? '';
                if ($nome === '') {
                    $nome = __('Sem nome');
                }
                $out[] = [
                    'escola' => $nome,
                    'ano' => $ano,
                    'status' => $st,
                    'detalhe' => $det,
                ];
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{escola: string, porte: string, unidade_status: string, matriculas: int}>
     */
    private function unitsWithPorte(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eId = (string) config('ieducar.columns.escola.id');
            $eName = (string) config('ieducar.columns.escola.name');
            $eActive = IeducarColumnInspector::firstExistingColumn($db, $escolaT, array_filter([
                (string) config('ieducar.columns.escola.active'),
                'ativo',
            ]), $city);

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $q->join($escolaT.' as e', 't_filter.'.$tc['escola'], '=', 'e.'.$eId);

            if ($eActive !== null) {
                $q->selectRaw('e.'.$eId.' as eid')
                    ->selectRaw('e.'.$eName.' as escola_nome')
                    ->selectRaw('e.'.$eActive.' as escola_ativo')
                    ->selectRaw('COUNT(*) as c')
                    ->groupBy('e.'.$eId, 'e.'.$eName, 'e.'.$eActive);
            } else {
                $q->selectRaw('e.'.$eId.' as eid')
                    ->selectRaw('e.'.$eName.' as escola_nome')
                    ->selectRaw('COUNT(*) as c')
                    ->groupBy('e.'.$eId, 'e.'.$eName);
            }

            $rows = $q->orderByDesc('c')->limit(200)->get();
            $out = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $n = (int) ($arr['c'] ?? 0);
                $porte = $this->porteFromCount($n);
                $ua = $this->labelEscolaAtiva($arr['escola_ativo'] ?? null);
                $out[] = [
                    'escola' => trim((string) ($arr['escola_nome'] ?? '')) ?: '—',
                    'porte' => $porte,
                    'unidade_status' => $ua,
                    'matriculas' => $n,
                ];
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    private function labelEscolaAtiva(mixed $v): string
    {
        if ($v === null) {
            return __('Indisponível');
        }
        if ($v === true || $v === 1 || $v === '1' || $v === 't') {
            return __('Ativa');
        }

        return __('Inativa / encerrada');
    }

    private function porteFromCount(int $n): string
    {
        if ($n < 50) {
            return __('Pequeno porte (<50 matrículas)');
        }
        if ($n < 250) {
            return __('Médio porte (50–249)');
        }

        return __('Grande porte (≥250)');
    }

    /**
     * @return list<array{lat: float, lng: float, label: string, meta: string}>
     */
    private function mapMarkers(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eId = (string) config('ieducar.columns.escola.id');
            $eName = (string) config('ieducar.columns.escola.name');

            $latCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'latitude', 'lat', 'geo_lat', 'latitude_graus',
            ], $city);
            $lngCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'longitude', 'lng', 'lon', 'geo_lng', 'longitude_graus',
            ], $city);
            if ($latCol === null || $lngCol === null) {
                return [];
            }

            $q = $db->table($escolaT.' as e')
                ->whereNotNull('e.'.$latCol)
                ->whereNotNull('e.'.$lngCol)
                ->select(['e.'.$eId.' as id', 'e.'.$eName.' as nome', 'e.'.$latCol.' as la', 'e.'.$lngCol.' as ln']);

            if ($filters->escola_id !== null) {
                $q->where('e.'.$eId, (int) $filters->escola_id);
            }

            $rows = $q->orderBy('e.'.$eName)->limit(120)->get();
            $markers = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $lat = (float) ($arr['la'] ?? 0);
                $lng = (float) ($arr['ln'] ?? 0);
                if (abs($lat) < 0.01 && abs($lng) < 0.01) {
                    continue;
                }
                $markers[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'label' => (string) ($arr['nome'] ?? ''),
                    'meta' => '',
                ];
            }

            return $markers;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @return ?array{texto: string, linhas: list<string>}
     */
    private function transportHint(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $col = IeducarColumnInspector::firstExistingColumn($db, $mat, [
                'transporte_escolar',
                'uso_transporte_escolar',
                'veiculo_transporte_escolar',
                'ref_cod_transporte_escolar',
            ], $city);
            if ($col === null) {
                return [
                    'texto' => __('Não foi encontrada coluna de transporte escolar na tabela de matrícula desta base.'),
                    'linhas' => [],
                ];
            }

            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $rows = $q->selectRaw('m.'.$col.' as tv')
                ->selectRaw('COUNT(*) as c')
                ->groupBy('m.'.$col)
                ->orderByDesc('c')
                ->limit(12)
                ->get();

            $linhas = [];
            foreach ($rows as $r) {
                $a = (array) $r;
                $linhas[] = (string) ($a['tv'] ?? '—').': '.number_format((int) ($a['c'] ?? 0));
            }

            return [
                'texto' => __('Distribuição de matrículas ativas por valor do campo «:col» (transporte).', ['col' => $col]),
                'linhas' => $linhas,
            ];
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * @return ?array{texto: string, turmas_com_lista: ?int, soma_lista: ?int, vagas_declaradas: ?int}
     */
    private function waitingListHint(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $turma = IeducarSchema::resolveTable('turma', $city);
            $listaCol = IeducarColumnInspector::firstExistingColumn($db, $turma, [
                'lista_espera',
                'qt_lista_espera',
                'qtd_lista_espera',
                'numero_lista_espera',
            ], $city);
            $maxCol = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
                (string) config('ieducar.columns.turma.max_alunos'),
                'max_aluno',
                'max_alunos',
            ]), $city);

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);

            $turmasComLista = null;
            $somaLista = null;
            if ($listaCol !== null) {
                $q2 = $db->table($turma.' as t');
                if ($filters->yearFilterValue() !== null && $tc['year'] !== '') {
                    $q2->where('t.'.$tc['year'], $filters->yearFilterValue());
                }
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q2, $db, 't', $tc['escola'], $filters->escola_id);
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q2, $db, 't', $tc['curso'], $filters->curso_id);
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q2, $db, 't', $tc['turno'], $filters->turno_id);
                $sumRow = $q2->selectRaw('SUM(CASE WHEN COALESCE(t.'.$listaCol.',0) > 0 THEN 1 ELSE 0 END) as n_turmas')
                    ->selectRaw('SUM(COALESCE(t.'.$listaCol.',0)) as soma')
                    ->first();
                if ($sumRow) {
                    $turmasComLista = (int) (((array) $sumRow)['n_turmas'] ?? 0);
                    $somaLista = (int) (((array) $sumRow)['soma'] ?? 0);
                }
            }

            $vagas = null;
            if ($maxCol !== null) {
                $q3 = $db->table($turma.' as t');
                if ($filters->yearFilterValue() !== null && $tc['year'] !== '') {
                    $q3->where('t.'.$tc['year'], $filters->yearFilterValue());
                }
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q3, $db, 't', $tc['escola'], $filters->escola_id);
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q3, $db, 't', $tc['curso'], $filters->curso_id);
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q3, $db, 't', $tc['turno'], $filters->turno_id);
                $vrow = $q3->selectRaw('SUM(COALESCE(t.'.$maxCol.',0)) as vm')->first();
                $vagas = (int) (((array) $vrow)['vm'] ?? 0);
            }

            if ($listaCol === null && $maxCol === null) {
                return [
                    'texto' => __('Não foram encontradas colunas de lista de espera ou capacidade na tabela de turmas.'),
                    'turmas_com_lista' => null,
                    'soma_lista' => null,
                    'vagas_declaradas' => null,
                ];
            }

            return [
                'texto' => $listaCol !== null
                    ? __('Lista de espera (coluna :col na turma).', ['col' => $listaCol])
                    : __('Capacidade declarada nas turmas.'),
                'turmas_com_lista' => $turmasComLista,
                'soma_lista' => $somaLista,
                'vagas_declaradas' => $vagas,
            ];
        } catch (QueryException|\Throwable) {
            return null;
        }
    }
}
