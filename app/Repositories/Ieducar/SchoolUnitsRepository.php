<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Services\Inep\InepCatalogoEscolasGeoService;
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
        private CityDataConnection $cityData,
        private InepCatalogoEscolasGeoService $inepGeo
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
     *     geo_source: ?string,
     *     geo_attribution: list<string>,
     *     map_scope: 'matricula'|'rede_escola',
     *     show_waiting_capacity: bool,
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
                    'geo_source' => null,
                    'geo_attribution' => [],
                    'map_scope' => 'matricula',
                    'show_waiting_capacity' => true,
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

                $mapBundle = $this->buildMapMarkers($db, $city, $filters);
                $markers = $mapBundle['markers'];
                $geoNote = $mapBundle['geo_note'];
                $geoSource = $mapBundle['geo_source'];
                $geoAttribution = $mapBundle['geo_attribution'];
                $mapScope = $mapBundle['map_scope'];
                $showWaitingCapacity = $mapBundle['show_waiting_capacity'];

                $transport = $this->transportHint($db, $city, $filters);
                $waiting = $showWaitingCapacity
                    ? $this->waitingListHint($db, $city, $filters)
                    : null;

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
                        'geo_source' => $geoSource,
                        'geo_attribution' => $geoAttribution,
                        'map_scope' => $mapScope,
                        'show_waiting_capacity' => $showWaitingCapacity,
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
                    'geo_source' => null,
                    'geo_attribution' => [],
                    'map_scope' => 'matricula',
                    'show_waiting_capacity' => true,
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
     * @return list<string>
     */
    private function geoAttributionLines(): array
    {
        return [
            __('Base local: latitude/longitude na tabela escola, quando existirem.'),
            __('INEP (dados abertos): consulta ao serviço ArcGIS do Catálogo de Escolas (georreferenciação), usando o código INEP da escola quando a coluna existir na base e a opção estiver ativa (cache aplicado).'),
            __('Referência: portal do INEP — Catálogo de Escolas e dados abertos.'),
        ];
    }

    /**
     * Escolas no âmbito dos filtros (matrícula ativa → turma → escola), com colunas opcionais para mapa.
     *
     * @return list<object|array<string, mixed>>
     */
    private function escolasScopedForMap(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eId = (string) config('ieducar.columns.escola.id');
            $eName = (string) config('ieducar.columns.escola.name');

            $latCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'latitude', 'lat', 'geo_lat', 'latitude_graus',
            ], $city);
            $lngCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'longitude', 'lng', 'lon', 'geo_lng', 'longitude_graus',
            ], $city);
            $inepCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'codigo_inep', 'cod_escola_inep', 'inep', 'cod_inep', 'codigo_escola_inep', 'inep_escola', 'ref_cod_escola_inep',
            ], $city);

            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $q->join($escolaT.' as e', 't_filter.'.$tc['escola'], '=', 'e.'.$eId);

            if ($filters->escola_id !== null) {
                $q->where('e.'.$eId, (int) $filters->escola_id);
            }

            $g = $db->getQueryGrammar();
            $we = $g->wrap('e');
            $q->selectRaw($we.'.'.$g->wrap($eId).' as eid')
                ->selectRaw('MAX('.$we.'.'.$g->wrap($eName).') as escola_nome');
            if ($latCol !== null) {
                $q->selectRaw('MAX('.$we.'.'.$g->wrap($latCol).') as la');
            }
            if ($lngCol !== null) {
                $q->selectRaw('MAX('.$we.'.'.$g->wrap($lngCol).') as ln');
            }
            if ($inepCol !== null) {
                $q->selectRaw('MAX('.$we.'.'.$g->wrap($inepCol).') as inep');
            }
            $q->groupBy('e.'.$eId);

            $rows = $q->orderByRaw('MAX('.$we.'.'.$g->wrap($eName).')')->limit(200)->get();

            return $rows->all();
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Unidades na tabela escola (cadastro da rede), sem depender de matrícula — fallback para o mapa.
     *
     * @return list<object|array<string, mixed>>
     */
    private function escolasRedeParaMapaFallback(Connection $db, City $city, IeducarFilterState $filters): array
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
            $inepCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'codigo_inep', 'cod_escola_inep', 'inep', 'cod_inep', 'codigo_escola_inep', 'inep_escola', 'ref_cod_escola_inep',
            ], $city);

            $eActive = IeducarColumnInspector::firstExistingColumn($db, $escolaT, array_filter([
                (string) config('ieducar.columns.escola.active'),
                'ativo',
            ]), $city);

            $q = $db->table($escolaT.' as e');
            if ($eActive !== null) {
                $q->where(function ($w) use ($eActive) {
                    $w->where('e.'.$eActive, 1)
                        ->orWhere('e.'.$eActive, '1')
                        ->orWhere('e.'.$eActive, 't')
                        ->orWhere('e.'.$eActive, true);
                });
            }

            $g = $db->getQueryGrammar();
            $we = $g->wrap('e');
            $q->selectRaw($we.'.'.$g->wrap($eId).' as eid')
                ->selectRaw($we.'.'.$g->wrap($eName).' as escola_nome');
            if ($latCol !== null) {
                $q->selectRaw($we.'.'.$g->wrap($latCol).' as la');
            }
            if ($lngCol !== null) {
                $q->selectRaw($we.'.'.$g->wrap($lngCol).' as ln');
            }
            if ($inepCol !== null) {
                $q->selectRaw($we.'.'.$g->wrap($inepCol).' as inep');
            }

            if ($filters->escola_id !== null) {
                $q->where('e.'.$eId, (int) $filters->escola_id);
            }

            $rows = $q->orderByRaw($we.'.'.$g->wrap($eName))->limit(250)->get();

            return $rows->all();
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<object|array<string, mixed>>  $rows
     * @return array{
     *   markers: list<array{lat: float, lng: float, label: string, meta: string}>,
     *   geo_note: ?string,
     *   geo_source: string,
     *   geo_attribution: list<string>
     * }
     */
    private function buildMarkersFromEscolaRows(array $rows): array
    {
        $markers = [];
        $dbCount = 0;
        $inepCount = 0;
        /** @var array<int, string> */
        $pendingInep = [];

        foreach ($rows as $row) {
            $arr = (array) $row;
            $nome = trim((string) ($arr['escola_nome'] ?? ''));
            if ($nome === '') {
                $nome = __('Sem nome');
            }
            $la = array_key_exists('la', $arr) && $arr['la'] !== null ? (float) $arr['la'] : null;
            $ln = array_key_exists('ln', $arr) && $arr['ln'] !== null ? (float) $arr['ln'] : null;
            $inepRaw = $arr['inep'] ?? null;
            $inep = is_numeric($inepRaw) ? (int) $inepRaw : null;
            if ($inep !== null && $inep <= 0) {
                $inep = null;
            }

            $hasDb = $la !== null && $ln !== null
                && ! (abs($la) < 0.01 && abs($ln) < 0.01)
                && abs($la) <= 90 && abs($ln) <= 180;

            if ($hasDb) {
                $markers[] = [
                    'lat' => $la,
                    'lng' => $ln,
                    'label' => $nome,
                    'meta' => __('Coordenadas na base i-Educar (tabela escola).'),
                ];
                $dbCount++;

                continue;
            }

            if ($inep !== null) {
                $pendingInep[$inep] = $nome;
            }
        }

        if ($pendingInep !== [] && filter_var(config('ieducar.inep_geocoding.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $hits = $this->inepGeo->lookupByInepCodes(array_keys($pendingInep));
            foreach ($pendingInep as $code => $label) {
                if (! isset($hits[$code])) {
                    continue;
                }
                $markers[] = [
                    'lat' => $hits[$code]['lat'],
                    'lng' => $hits[$code]['lng'],
                    'label' => $label,
                    'meta' => __('Catálogo de Escolas (INEP/MEC): coordenadas públicas — código INEP :code.', ['code' => $code]),
                ];
                $inepCount++;
            }
        }

        $markers = array_slice($markers, 0, 120);

        $geoSource = 'none';
        if ($dbCount > 0 && $inepCount > 0) {
            $geoSource = 'mixed';
        } elseif ($dbCount > 0) {
            $geoSource = 'db';
        } elseif ($inepCount > 0) {
            $geoSource = 'inep_arcgis';
        }

        $attribution = $this->geoAttributionLines();

        $geoNote = null;
        if ($markers === []) {
            $geoNote = __('Sem coordenadas para as escolas do filtro: preencha latitude/longitude na escola na base i-Educar ou garanta o código INEP (ex.: codigo_inep) para consulta ao Catálogo de Escolas INEP.');
        }

        return [
            'markers' => $markers,
            'geo_note' => $geoNote,
            'geo_source' => $geoSource,
            'geo_attribution' => $attribution,
        ];
    }

    /**
     * @return array{
     *   markers: list<array{lat: float, lng: float, label: string, meta: string}>,
     *   geo_note: ?string,
     *   geo_source: string,
     *   geo_attribution: list<string>,
     *   map_scope: 'matricula'|'rede_escola',
     *   show_waiting_capacity: bool
     * }
     */
    private function buildMapMarkers(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $attribution = $this->geoAttributionLines();

        $scopedRows = $this->escolasScopedForMap($db, $city, $filters);
        $fromMatricula = $this->buildMarkersFromEscolaRows($scopedRows);

        if ($fromMatricula['markers'] !== []) {
            return [
                'markers' => $fromMatricula['markers'],
                'geo_note' => $fromMatricula['geo_note'],
                'geo_source' => $fromMatricula['geo_source'],
                'geo_attribution' => $fromMatricula['geo_attribution'],
                'map_scope' => 'matricula',
                'show_waiting_capacity' => true,
            ];
        }

        $redeRows = $this->escolasRedeParaMapaFallback($db, $city, $filters);
        $fromRede = $this->buildMarkersFromEscolaRows($redeRows);

        if ($fromRede['markers'] !== []) {
            $extra = __('Mapa com unidades cadastradas na rede (tabela escola). Não são exibidos indicadores de lista de espera e capacidade por turma neste modo, porque o mapa não está restrito ao âmbito de matrículas ativas nos filtros.');

            return [
                'markers' => $fromRede['markers'],
                'geo_note' => $fromRede['geo_note'] !== null ? $fromRede['geo_note'].' '.$extra : $extra,
                'geo_source' => $fromRede['geo_source'],
                'geo_attribution' => $fromRede['geo_attribution'],
                'map_scope' => 'rede_escola',
                'show_waiting_capacity' => false,
            ];
        }

        $geoNote = $scopedRows === []
            ? __('Não há escolas no âmbito dos filtros (matrículas ativas) para posicionar no mapa.')
            : __('Sem coordenadas para as escolas do filtro: preencha latitude/longitude na escola na base i-Educar ou garanta o código INEP (ex.: codigo_inep) para consulta ao Catálogo de Escolas INEP.');

        return [
            'markers' => [],
            'geo_note' => $geoNote,
            'geo_source' => 'none',
            'geo_attribution' => $attribution,
            'map_scope' => 'matricula',
            'show_waiting_capacity' => true,
        ];
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
