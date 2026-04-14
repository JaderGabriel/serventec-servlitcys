<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use App\Services\CityDataConnection;
use App\Services\Inep\InepCatalogoEscolasGeoService;
use App\Services\Inep\InepCensoEscolaGeoAggService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\MatriculaAtivoFilter;
use App\Support\Ieducar\MatriculaChartQueries;
use App\Support\Ieducar\MatriculaTurmaJoin;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Contexto de anos letivos, unidades escolares, mapa e indicadores opcionais (transporte, lista de espera).
 */
class SchoolUnitsRepository
{
    public function __construct(
        private CityDataConnection $cityData,
        private InepCatalogoEscolasGeoService $inepGeo,
        private InepCensoEscolaGeoAggService $censoGeoAgg
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
     *     geo_distribution: ?array<string, mixed>,
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
                    'geo_distribution' => null,
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
                $geoDistribution = $mapBundle['geo_distribution'] ?? null;
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
                        'geo_distribution' => $geoDistribution,
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
                    'geo_distribution' => null,
                    'map_scope' => 'matricula',
                    'show_waiting_capacity' => true,
                    'error' => $e->getMessage(),
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Em PostgreSQL (Portabilis/iEducar 2.x), algumas bases não têm coluna `escola.nome` e expõem o nome via
     * função `relatorio.get_nome_escola(cod_escola)`. Este helper centraliza o SQL usado no dashboard para
     * alinhar com a forma como os filtros carregam as escolas (FilterOptionsService).
     *
     * @return array{expr: string, order_expr: string}
     */
    private function escolaNomeSql(Connection $db, City $city, string $escolaAlias, string $escolaIdCol): array
    {
        $g = $db->getQueryGrammar();
        $wAlias = $g->wrap($escolaAlias);
        $wId = $g->wrap($escolaIdCol);

        if ($db->getDriverName() === 'pgsql'
            && filter_var(config('ieducar.pgsql_use_relatorio_escola_nome', true), FILTER_VALIDATE_BOOLEAN)) {
            $relSchema = (string) config('ieducar.pgsql_schema_relatorio', 'relatorio');
            $relSchema = $relSchema !== '' ? trim($relSchema) : 'relatorio';
            $fn = $g->wrapTable($relSchema).'.get_nome_escola';
            $expr = $fn.'('.$wAlias.'.'.$wId.')';

            return [
                'expr' => $expr,
                'order_expr' => $expr,
            ];
        }

        $eName = (string) config('ieducar.columns.escola.name');
        $expr = $wAlias.'.'.$g->wrap($eName);

        return [
            'expr' => $expr,
            'order_expr' => $expr,
        ];
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
            $en = $this->escolaNomeSql($db, $city, 'e', $eId);

            $g = $db->getQueryGrammar();
            $wE = $g->wrap('e');
            $wT = $g->wrap('t');
            $anoSelect = $db->getDriverName() === 'pgsql'
                ? 'CAST('.$wT.'.'.$g->wrap($yCol).' AS integer)'
                : 'CAST('.$wT.'.'.$g->wrap($yCol).' AS UNSIGNED)';

            $q = $db->table($turma.' as t')
                ->join($escolaT.' as e', 't.'.$eCol, '=', 'e.'.$eId)
                ->whereIn('t.'.$yCol, $years)
                ->selectRaw('MAX('.$en['expr'].') as escola_nome')
                ->selectRaw($anoSelect.' as ano')
                ->groupBy('e.'.$eId, 't.'.$yCol)
                ->orderByRaw('MAX('.$en['order_expr'].')')
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
            $en = $this->escolaNomeSql($db, $city, 'e', $eId);
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
                    ->selectRaw('MAX('.$en['expr'].') as escola_nome')
                    ->selectRaw('e.'.$eActive.' as escola_ativo')
                    ->selectRaw('COUNT(*) as c')
                    ->groupBy('e.'.$eId, 'e.'.$eActive);
            } else {
                $q->selectRaw('e.'.$eId.' as eid')
                    ->selectRaw('MAX('.$en['expr'].') as escola_nome')
                    ->selectRaw('COUNT(*) as c')
                    ->groupBy('e.'.$eId);
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
            __('Tabela school_unit_geos: guarda última observação i-Educar, coordenadas oficiais (INEP) e divergência (Haversine) acima do limiar configurado (ieducar.inep_geocoding.divergence_threshold_meters).'),
            __('Referência: portal do INEP — Catálogo de Escolas e dados abertos.'),
        ];
    }

    private function divergenceThresholdMeters(): float
    {
        return (float) config('ieducar.inep_geocoding.divergence_threshold_meters', 100);
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): ?float
    {
        if (abs($lat1) > 90 || abs($lat2) > 90 || abs($lng1) > 180 || abs($lng2) > 180) {
            return null;
        }
        $r = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * (sin($dl / 2) ** 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $r * $c;
    }

    /**
     * @return array<string, mixed>
     */
    private function geoDivergencePayload(
        ?float $ieduLat,
        ?float $ieduLng,
        ?float $offLat,
        ?float $offLng,
        ?float $divMeters,
        bool $hasDiv,
    ): array {
        return [
            'has_divergence' => $hasDiv,
            'meters' => $divMeters,
            'threshold_meters' => $this->divergenceThresholdMeters(),
            'ieducar_lat' => $ieduLat,
            'ieducar_lng' => $ieduLng,
            'official_lat' => $offLat,
            'official_lng' => $offLng,
            'official_label' => __('Catálogo INEP (ArcGIS)'),
            'ieducar_label' => __('i-Educar (tabela escola)'),
        ];
    }

    private function fonteCoordenadaLabel(string $fonte): string
    {
        return match ($fonte) {
            'db' => __('Coordenadas na base i-Educar'),
            'inep' => __('Catálogo INEP (ArcGIS)'),
            'local' => __('Cache local (school_unit_geos)'),
            default => $fonte,
        };
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
            $en = $this->escolaNomeSql($db, $city, 'e', $eId);

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
                ->selectRaw('MAX('.$en['expr'].') as escola_nome');
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

            $rows = $q->orderByRaw('MAX('.$en['order_expr'].')')->limit(200)->get();

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
            $en = $this->escolaNomeSql($db, $city, 'e', $eId);

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
                    $col = 'e.'.$eActive;
                    $w->where($col, 1)
                        ->orWhere($col, '1')
                        ->orWhere($col, 't')
                        ->orWhere($col, true)
                        ->orWhereNull($col);
                });
            }

            $g = $db->getQueryGrammar();
            $we = $g->wrap('e');
            $q->selectRaw($we.'.'.$g->wrap($eId).' as eid')
                ->selectRaw($en['expr'].' as escola_nome');
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

            $rows = $q->orderByRaw($en['order_expr'])->limit(250)->get();

            return $rows->all();
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    private function escolaStatusKeyFromAtivo(mixed $v): string
    {
        if ($v === null) {
            return 'unknown';
        }
        if ($v === true || $v === 1 || $v === '1' || $v === 't') {
            return 'ativa';
        }

        return 'inativa';
    }

    /**
     * @param  list<int>  $eids
     * @return array<int, int>
     */
    private function matriculasCountByEscolaIds(Connection $db, City $city, IeducarFilterState $filters, array $eids): array
    {
        return MatriculaChartQueries::matriculasCountByEscolaIds($db, $city, $filters, $eids);
    }

    /**
     * Primeira tabela qualificada que existir na base (pmieducar/cadastro ou nome curto em MySQL).
     *
     * @param  list<string>  $candidates
     */
    private function firstExistingQualifiedTable(Connection $db, City $city, array $candidates): ?string
    {
        foreach ($candidates as $t) {
            $t = trim((string) $t);
            if ($t !== '' && IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return null;
    }

    private function preferNonEmpty(?string $existing, ?string $incoming): ?string
    {
        $ex = trim((string) ($existing ?? ''));
        if ($ex !== '') {
            return $ex;
        }
        $in = trim((string) ($incoming ?? ''));

        return $in !== '' ? $in : null;
    }

    /**
     * Formato alinhado a relatorio.get_telefone_escola / view_dados_escola (ddd + fone numéricos).
     */
    private function formatDddFone(mixed $ddd, mixed $fone): string
    {
        $d = preg_replace('/\D+/', '', (string) ($ddd ?? '')) ?? '';
        $f = preg_replace('/\D+/', '', (string) ($fone ?? '')) ?? '';
        if ($f === '') {
            return '';
        }
        if ($d !== '' && strlen($f) >= 8) {
            return '('.$d.') '.$f;
        }

        return $f;
    }

    /**
     * Enriquece contacto/endereço/gestor com escola_complemento e cadastro (pessoa, fone_pessoa, juridica),
     * como em relatorio.view_dados_escola no i-Educar Portabilis.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<int, array<string, mixed>>
     */
    private function mergeIeducarCadastroExtras(Connection $db, City $city, array $cards): array
    {
        if ($cards === []) {
            return $cards;
        }

        $eids = array_keys($cards);
        $escolaT = IeducarSchema::resolveTable('escola', $city);
        $eId = (string) config('ieducar.columns.escola.id');
        $mainSchema = trim((string) IeducarSchema::effectiveSchema($city));
        if ($mainSchema === '') {
            $mainSchema = trim((string) config('ieducar.pgsql_default_schema', 'pmieducar')) ?: 'pmieducar';
        }

        $compT = $this->firstExistingQualifiedTable($db, $city, [
            $mainSchema.'.escola_complemento',
            'escola_complemento',
        ]);
        if ($compT !== null) {
            try {
                $fk = IeducarColumnInspector::firstExistingColumn($db, $compT, ['ref_cod_escola', 'cod_escola'], $city) ?? 'ref_cod_escola';
                $rows = $db->table($compT)->whereIn($fk, $eids)->get();
                foreach ($rows as $row) {
                    $a = (array) $row;
                    $eid = (int) ($a[$fk] ?? 0);
                    if ($eid <= 0 || ! isset($cards[$eid])) {
                        continue;
                    }
                    $log = trim((string) ($a['logradouro'] ?? ''));
                    $num = trim((string) ($a['numero'] ?? ''));
                    $co = trim((string) ($a['complemento'] ?? ''));
                    $bai = trim((string) ($a['bairro'] ?? ''));
                    $mun = trim((string) ($a['municipio'] ?? ''));
                    $cepRaw = $a['cep'] ?? null;
                    $cep = $cepRaw !== null && $cepRaw !== ''
                        ? str_pad(preg_replace('/\D+/', '', (string) $cepRaw) ?? '', 8, '0', STR_PAD_LEFT)
                        : '';
                    $parts = [];
                    if ($log !== '') {
                        $parts[] = $log.($num !== '' ? ', '.$num : '');
                    }
                    if ($co !== '') {
                        $parts[] = $co;
                    }
                    if ($bai !== '') {
                        $parts[] = $bai;
                    }
                    if ($mun !== '') {
                        $parts[] = $mun;
                    }
                    if ($cep !== '' && strlen($cep) === 8) {
                        $parts[] = __('CEP').' '.substr($cep, 0, 5).'-'.substr($cep, 5, 3);
                    }
                    $end = $parts !== [] ? implode(' — ', $parts) : null;
                    if ($end !== null) {
                        $cards[$eid]['endereco'] = $this->preferNonEmpty($cards[$eid]['endereco'] ?? null, $end);
                    }
                    $tel = $this->formatDddFone($a['ddd_telefone'] ?? null, $a['telefone'] ?? null);
                    if ($tel !== '') {
                        $cards[$eid]['telefone'] = $this->preferNonEmpty($cards[$eid]['telefone'] ?? null, $tel);
                    }
                    $em = trim((string) ($a['email'] ?? ''));
                    if ($em !== '') {
                        $cards[$eid]['email'] = $this->preferNonEmpty($cards[$eid]['email'] ?? null, $em);
                    }
                }
            } catch (QueryException|\Throwable) {
            }
        }

        $refPesCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, ['ref_idpes', 'ref_cod_pessoa'], $city);
        $refGestCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, ['ref_idpes_gestor', 'ref_cod_pessoa_gestor'], $city);
        $emailGestCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, ['email_gestor'], $city);

        $g = $db->getQueryGrammar();
        $we = $g->wrap('e');
        $idpesByEid = [];
        $idpesGestByEid = [];
        $emailGestByEid = [];
        try {
            $q = $db->table($escolaT.' as e')->whereIn('e.'.$eId, $eids);
            $q->selectRaw($we.'.'.$g->wrap($eId).' as eid');
            if ($refPesCol !== null) {
                $q->selectRaw($we.'.'.$g->wrap($refPesCol).' as ref_idpes');
            }
            if ($refGestCol !== null) {
                $q->selectRaw($we.'.'.$g->wrap($refGestCol).' as ref_idpes_gestor');
            }
            if ($emailGestCol !== null) {
                $q->selectRaw($we.'.'.$g->wrap($emailGestCol).' as email_gestor');
            }
            foreach ($q->get() as $row) {
                $a = (array) $row;
                $eid = (int) ($a['eid'] ?? 0);
                if ($eid <= 0) {
                    continue;
                }
                if (isset($a['ref_idpes']) && is_numeric($a['ref_idpes']) && (int) $a['ref_idpes'] > 0) {
                    $idpesByEid[$eid] = (int) $a['ref_idpes'];
                }
                if (isset($a['ref_idpes_gestor']) && is_numeric($a['ref_idpes_gestor']) && (int) $a['ref_idpes_gestor'] > 0) {
                    $idpesGestByEid[$eid] = (int) $a['ref_idpes_gestor'];
                }
                if (isset($a['email_gestor']) && trim((string) $a['email_gestor']) !== '') {
                    $emailGestByEid[$eid] = trim((string) $a['email_gestor']);
                }
            }
        } catch (QueryException|\Throwable) {
        }

        $pessoaT = IeducarSchema::resolveTable('pessoa', $city);
        $idpesCol = (string) config('ieducar.columns.pessoa.id', 'idpes');
        $nomePessoaCol = IeducarColumnInspector::firstExistingColumn($db, $pessoaT, ['nome', 'nm_pessoa'], $city);
        $emailPessoaCol = IeducarColumnInspector::firstExistingColumn($db, $pessoaT, ['email', 'e_mail', 'mail'], $city);

        $allPes = array_values(array_unique(array_filter(array_merge(
            array_values($idpesByEid),
            array_values($idpesGestByEid),
        ))));
        $pessoaNomeById = [];
        $pessoaEmailById = [];
        if ($allPes !== [] && IeducarColumnInspector::tableExists($db, $pessoaT, $city)) {
            try {
                $pq = $db->table($pessoaT)->whereIn($idpesCol, $allPes);
                $sel = [$idpesCol];
                if ($nomePessoaCol !== null) {
                    $sel[] = $nomePessoaCol;
                }
                if ($emailPessoaCol !== null) {
                    $sel[] = $emailPessoaCol;
                }
                foreach ($pq->get($sel) as $pr) {
                    $pa = (array) $pr;
                    $ip = (int) ($pa[$idpesCol] ?? 0);
                    if ($ip <= 0) {
                        continue;
                    }
                    if ($nomePessoaCol !== null) {
                        $pessoaNomeById[$ip] = trim((string) ($pa[$nomePessoaCol] ?? ''));
                    }
                    if ($emailPessoaCol !== null) {
                        $pessoaEmailById[$ip] = trim((string) ($pa[$emailPessoaCol] ?? ''));
                    }
                }
            } catch (QueryException|\Throwable) {
            }
        }

        $cadSchema = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro'));
        $foneT = $this->firstExistingQualifiedTable($db, $city, array_filter([
            $cadSchema !== '' ? $cadSchema.'.fone_pessoa' : '',
            'fone_pessoa',
        ]));
        $foneTipoCol = null;
        $foneDddCol = null;
        $foneNumCol = null;
        $foneIdpesCol = null;
        if ($foneT !== null) {
            $foneTipoCol = IeducarColumnInspector::firstExistingColumn($db, $foneT, ['tipo'], $city);
            $foneDddCol = IeducarColumnInspector::firstExistingColumn($db, $foneT, ['ddd'], $city);
            $foneNumCol = IeducarColumnInspector::firstExistingColumn($db, $foneT, ['fone', 'telefone'], $city);
            $foneIdpesCol = IeducarColumnInspector::firstExistingColumn($db, $foneT, ['idpes'], $city);
        }

        $foneStrByIdpes = [];
        if ($foneT !== null && $foneTipoCol !== null && $foneNumCol !== null && $foneIdpesCol !== null) {
            $idpesEscola = array_values(array_unique(array_values($idpesByEid)));
            if ($idpesEscola !== []) {
                try {
                    $fq = $db->table($foneT)->whereIn($foneIdpesCol, $idpesEscola)->where($foneTipoCol, 1);
                    foreach ($fq->get() as $fr) {
                        $fa = (array) $fr;
                        $ip = (int) ($fa[$foneIdpesCol] ?? 0);
                        if ($ip <= 0) {
                            continue;
                        }
                        $ddd = $foneDddCol !== null ? ($fa[$foneDddCol] ?? null) : null;
                        $fn = $this->formatDddFone($ddd, $fa[$foneNumCol] ?? null);
                        if ($fn !== '' && ! isset($foneStrByIdpes[$ip])) {
                            $foneStrByIdpes[$ip] = $fn;
                        }
                    }
                } catch (QueryException|\Throwable) {
                }
            }
        }

        foreach ($eids as $eid) {
            if (! isset($cards[$eid])) {
                continue;
            }
            $idpes = $idpesByEid[$eid] ?? null;
            if ($idpes !== null && $idpes > 0) {
                if (($cards[$eid]['email'] ?? null) === null && isset($pessoaEmailById[$idpes]) && $pessoaEmailById[$idpes] !== '') {
                    $cards[$eid]['email'] = $pessoaEmailById[$idpes];
                }
                if (($cards[$eid]['telefone'] ?? null) === null && isset($foneStrByIdpes[$idpes])) {
                    $cards[$eid]['telefone'] = $foneStrByIdpes[$idpes];
                }
            }
            $idpesG = $idpesGestByEid[$eid] ?? null;
            if ($idpesG !== null && $idpesG > 0 && ($nomePessoaCol !== null)) {
                $nm = $pessoaNomeById[$idpesG] ?? '';
                if ($nm !== '' && (($cards[$eid]['gestor'] ?? null) === null || trim((string) $cards[$eid]['gestor']) === '')) {
                    $cards[$eid]['gestor'] = $nm;
                }
            }
            if (isset($emailGestByEid[$eid])) {
                $eg = $emailGestByEid[$eid];
                $g0 = trim((string) ($cards[$eid]['gestor'] ?? ''));
                if ($eg !== '') {
                    if ($g0 !== '') {
                        $cards[$eid]['gestor'] = $g0.' — '.$eg;
                    } else {
                        $cards[$eid]['gestor'] = $eg;
                    }
                }
            }
        }

        $modSchema = trim((string) config('ieducar.pgsql_schema_modules', 'modules'));
        $phpT = $this->firstExistingQualifiedTable($db, $city, array_filter([
            $modSchema !== '' ? $modSchema.'.person_has_place' : '',
            'person_has_place',
        ]));
        $adrT = $this->firstExistingQualifiedTable($db, $city, array_filter([
            $modSchema !== '' ? $modSchema.'.addresses' : '',
            'addresses',
        ]));
        if ($phpT !== null && $adrT !== null && $idpesByEid !== []) {
            $phpPersonCol = IeducarColumnInspector::firstExistingColumn($db, $phpT, ['person_id', 'idpes'], $city);
            $phpPlaceCol = IeducarColumnInspector::firstExistingColumn($db, $phpT, ['place_id'], $city);
            $phpTypeCol = IeducarColumnInspector::firstExistingColumn($db, $phpT, ['type'], $city);
            $adrIdCol = IeducarColumnInspector::firstExistingColumn($db, $adrT, ['id'], $city);
            $adrStreet = IeducarColumnInspector::firstExistingColumn($db, $adrT, ['address', 'logradouro', 'nm_logradouro'], $city);
            $adrNum = IeducarColumnInspector::firstExistingColumn($db, $adrT, ['number', 'numero', 'nr_numero'], $city);
            $adrBai = IeducarColumnInspector::firstExistingColumn($db, $adrT, ['neighborhood', 'bairro', 'nm_bairro'], $city);
            $adrCity = IeducarColumnInspector::firstExistingColumn($db, $adrT, ['city', 'municipio', 'nm_municipio'], $city);
            $adrCep = IeducarColumnInspector::firstExistingColumn($db, $adrT, ['postal_code', 'cep', 'nr_cep'], $city);
            $adrUf = IeducarColumnInspector::firstExistingColumn($db, $adrT, ['state_abbreviation', 'uf'], $city);
            if ($phpPersonCol !== null && $phpPlaceCol !== null && $adrIdCol !== null && $adrStreet !== null) {
                $idpesList = array_values(array_unique(array_values($idpesByEid)));
                try {
                    $addrQ = $db->table($phpT.' as php')
                        ->join($adrT.' as a', 'php.'.$phpPlaceCol, '=', 'a.'.$adrIdCol)
                        ->whereIn('php.'.$phpPersonCol, $idpesList);
                    if ($phpTypeCol !== null) {
                        $addrQ->where('php.'.$phpTypeCol, 1);
                    }
                    $addrQ->select([
                        'php.'.$phpPersonCol.' as idpes',
                        'a.'.$adrStreet.' as logradouro',
                    ]);
                    if ($adrNum !== null) {
                        $addrQ->addSelect('a.'.$adrNum.' as numero');
                    }
                    if ($adrBai !== null) {
                        $addrQ->addSelect('a.'.$adrBai.' as bairro');
                    }
                    if ($adrCity !== null) {
                        $addrQ->addSelect('a.'.$adrCity.' as municipio');
                    }
                    if ($adrCep !== null) {
                        $addrQ->addSelect('a.'.$adrCep.' as cep');
                    }
                    if ($adrUf !== null) {
                        $addrQ->addSelect('a.'.$adrUf.' as uf');
                    }
                    $endByIdpes = [];
                    foreach ($addrQ->get() as $ar) {
                        $ba = (array) $ar;
                        $ip = (int) ($ba['idpes'] ?? 0);
                        if ($ip <= 0 || isset($endByIdpes[$ip])) {
                            continue;
                        }
                        $lr = trim((string) ($ba['logradouro'] ?? ''));
                        $nr = trim((string) ($ba['numero'] ?? ''));
                        $br = trim((string) ($ba['bairro'] ?? ''));
                        $mu = trim((string) ($ba['municipio'] ?? ''));
                        $cp = trim((string) ($ba['cep'] ?? ''));
                        $uf = trim((string) ($ba['uf'] ?? ''));
                        $cpDigits = preg_replace('/\D+/', '', $cp) ?? '';
                        $cepFmt = strlen($cpDigits) === 8
                            ? __('CEP').' '.substr($cpDigits, 0, 5).'-'.substr($cpDigits, 5, 3)
                            : ($cp !== '' ? __('CEP').' '.$cp : '');
                        $parts = [];
                        if ($lr !== '') {
                            $parts[] = $lr.($nr !== '' ? ', '.$nr : '');
                        }
                        if ($br !== '') {
                            $parts[] = $br;
                        }
                        if ($mu !== '') {
                            $parts[] = $uf !== '' ? $mu.'/'.$uf : $mu;
                        } elseif ($uf !== '') {
                            $parts[] = $uf;
                        }
                        if ($cepFmt !== '') {
                            $parts[] = $cepFmt;
                        }
                        if ($parts !== []) {
                            $endByIdpes[$ip] = implode(' — ', $parts);
                        }
                    }
                    foreach ($eids as $eid) {
                        $ip = $idpesByEid[$eid] ?? null;
                        if ($ip === null || ! isset($endByIdpes[$ip])) {
                            continue;
                        }
                        $cards[$eid]['endereco'] = $this->preferNonEmpty($cards[$eid]['endereco'] ?? null, $endByIdpes[$ip]);
                    }
                } catch (QueryException|\Throwable) {
                }
            }
        }

        if ($db->getDriverName() === 'pgsql'
            && filter_var(config('ieducar.pgsql_use_relatorio_get_telefone_escola', true), FILTER_VALIDATE_BOOLEAN)) {
            $relSchema = trim((string) config('ieducar.pgsql_schema_relatorio', 'relatorio')) ?: 'relatorio';
            $g = $db->getQueryGrammar();
            $fn = $g->wrapTable($relSchema).'.get_telefone_escola';
            foreach ($eids as $eid) {
                if (! isset($cards[$eid])) {
                    continue;
                }
                $tel = trim((string) ($cards[$eid]['telefone'] ?? ''));
                if ($tel !== '') {
                    continue;
                }
                try {
                    $rows = $db->select('SELECT '.$fn.'(?) AS t', [$eid]);
                    $t = isset($rows[0]) && isset($rows[0]->t) ? trim((string) $rows[0]->t) : '';
                    if ($t !== '') {
                        $cards[$eid]['telefone'] = $t;
                    }
                } catch (QueryException|\Throwable) {
                }
            }
        }

        foreach ($eids as $eid) {
            if (! isset($cards[$eid])) {
                continue;
            }
            foreach (['telefone', 'email', 'gestor', 'endereco'] as $k) {
                if (($cards[$eid][$k] ?? '') === '') {
                    $cards[$eid][$k] = null;
                }
            }
        }

        return $cards;
    }

    /**
     * @param  list<int>  $eids
     * @return array<int, array<string, mixed>>
     */
    private function fetchEscolaCardFieldsByIds(Connection $db, City $city, array $eids): array
    {
        if ($eids === []) {
            return [];
        }
        try {
            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eId = (string) config('ieducar.columns.escola.id');
            $en = $this->escolaNomeSql($db, $city, 'e', $eId);
            $tel = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'telefone', 'fone', 'fone_1', 'nr_telefone', 'tel', 'tel_comercial',
                'telefone_1', 'telefone_escola', 'telefone_ddd', 'nr_telefone_1',
            ], $city);
            $ddd = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'ddd', 'ddd_telefone', 'nr_ddd', 'ddd_1',
            ], $city);
            $email = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'email', 'mail', 'e_mail',
            ], $city);
            $gest = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'nome_responsavel', 'nm_responsavel', 'responsavel', 'nome_gestor', 'nm_diretor',
            ], $city);
            $log = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'logradouro', 'endereco', 'nm_logradouro', 'descricao_endereco', 'localizacao',
            ], $city);
            $num = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'numero', 'nr_numero', 'num',
            ], $city);
            $bai = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'bairro', 'nm_bairro',
            ], $city);
            $mun = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'municipio', 'cidade', 'nm_municipio',
            ], $city);
            $cep = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'cep', 'nr_cep',
            ], $city);
            $comp = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'complemento', 'complemento_endereco', 'nm_complemento',
            ], $city);
            $inepCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'codigo_inep', 'cod_escola_inep', 'inep', 'cod_inep', 'codigo_escola_inep', 'inep_escola', 'ref_cod_escola_inep',
            ], $city);
            $eActive = IeducarColumnInspector::firstExistingColumn($db, $escolaT, array_filter([
                (string) config('ieducar.columns.escola.active'),
                'ativo',
            ]), $city);

            $g = $db->getQueryGrammar();
            $we = $g->wrap('e');

            $q = $db->table($escolaT.' as e')->whereIn('e.'.$eId, $eids);
            $q->selectRaw($we.'.'.$g->wrap($eId).' as eid')
                ->selectRaw($en['expr'].' as nome_escola');
            if ($tel !== null) {
                $q->selectRaw($we.'.'.$g->wrap($tel).' as tel_raw');
            }
            if ($email !== null) {
                $q->selectRaw($we.'.'.$g->wrap($email).' as email_raw');
            }
            if ($gest !== null) {
                $q->selectRaw($we.'.'.$g->wrap($gest).' as gest_raw');
            }
            if ($log !== null) {
                $q->selectRaw($we.'.'.$g->wrap($log).' as log_raw');
            }
            if ($num !== null) {
                $q->selectRaw($we.'.'.$g->wrap($num).' as num_raw');
            }
            if ($bai !== null) {
                $q->selectRaw($we.'.'.$g->wrap($bai).' as bai_raw');
            }
            if ($mun !== null) {
                $q->selectRaw($we.'.'.$g->wrap($mun).' as mun_raw');
            }
            if ($cep !== null) {
                $q->selectRaw($we.'.'.$g->wrap($cep).' as cep_raw');
            }
            if ($ddd !== null) {
                $q->selectRaw($we.'.'.$g->wrap($ddd).' as ddd_raw');
            }
            if ($comp !== null) {
                $q->selectRaw($we.'.'.$g->wrap($comp).' as comp_raw');
            }
            if ($inepCol !== null) {
                $q->selectRaw($we.'.'.$g->wrap($inepCol).' as inep_raw');
            }
            if ($eActive !== null) {
                $q->selectRaw($we.'.'.$g->wrap($eActive).' as ativo_raw');
            }

            $out = [];
            foreach ($q->get() as $row) {
                $a = (array) $row;
                $eid = (int) ($a['eid'] ?? 0);
                if ($eid <= 0) {
                    continue;
                }
                $parts = [];
                $lr = trim((string) ($a['log_raw'] ?? ''));
                $nr = trim((string) ($a['num_raw'] ?? ''));
                $br = trim((string) ($a['bai_raw'] ?? ''));
                $mu = trim((string) ($a['mun_raw'] ?? ''));
                $cp = trim((string) ($a['cep_raw'] ?? ''));
                $co = trim((string) ($a['comp_raw'] ?? ''));
                if ($lr !== '') {
                    $parts[] = $lr.($nr !== '' ? ', '.$nr : '');
                }
                if ($co !== '') {
                    $parts[] = $co;
                }
                if ($br !== '') {
                    $parts[] = $br;
                }
                if ($mu !== '') {
                    $parts[] = $mu;
                }
                if ($cp !== '') {
                    $parts[] = __('CEP').' '.$cp;
                }
                $endereco = $parts !== [] ? implode(' — ', $parts) : null;

                $inepVal = $a['inep_raw'] ?? null;
                $inep = is_numeric($inepVal) ? (int) $inepVal : null;
                if ($inep !== null && $inep <= 0) {
                    $inep = null;
                }

                $ativo = $a['ativo_raw'] ?? null;
                $sk = $this->escolaStatusKeyFromAtivo($ativo);

                $telStr = isset($a['tel_raw']) ? trim((string) $a['tel_raw']) : '';
                $dddStr = isset($a['ddd_raw']) ? preg_replace('/\D+/', '', (string) $a['ddd_raw']) : '';
                if ($telStr !== '' && $dddStr !== '' && ! str_starts_with(preg_replace('/\D+/', '', $telStr), $dddStr)) {
                    $telStr = '('.$dddStr.') '.$telStr;
                }

                $out[$eid] = [
                    'nome' => trim((string) ($a['nome_escola'] ?? '')) ?: null,
                    'telefone' => $telStr !== '' ? $telStr : null,
                    'email' => isset($a['email_raw']) ? trim((string) $a['email_raw']) : null,
                    'gestor' => isset($a['gest_raw']) ? trim((string) $a['gest_raw']) : null,
                    'endereco' => $endereco,
                    'inep' => $inep,
                    'status_key' => $sk,
                    'status_label' => $this->labelEscolaAtiva($ativo),
                ];
                foreach (['telefone', 'email', 'gestor'] as $k) {
                    if (($out[$eid][$k] ?? '') === '') {
                        $out[$eid][$k] = null;
                    }
                }
            }

            return $this->mergeIeducarCadastroExtras($db, $city, $out);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Cursos e anos/séries ofertados (turmas distintas no ano e filtros), por escola.
     *
     * @param  list<int>  $eids
     * @return array<int, list<string>>
     */
    private function ofertaCursoSeriePorEscolaIds(Connection $db, City $city, IeducarFilterState $filters, array $eids): array
    {
        if ($eids === []) {
            return [];
        }
        try {
            $turma = IeducarSchema::resolveTable('turma', $city);
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['escola'] === '' || $tc['curso'] === '') {
                return [];
            }

            $q = $db->table($turma.' as t');
            if ($filters->yearFilterValue() !== null && $tc['year'] !== '') {
                $q->where('t.'.$tc['year'], $filters->yearFilterValue());
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't', $tc['turno'], $filters->turno_id);
            $q->whereIn('t.'.$tc['escola'], $eids);

            $cols = ['t.'.$tc['escola'].' as eid', 't.'.$tc['curso'].' as cid'];
            if ($tc['serie'] !== '') {
                $cols[] = 't.'.$tc['serie'].' as sid';
            }
            $rows = $q->select($cols)->distinct()->get();

            $cursoT = IeducarSchema::resolveTable('curso', $city);
            $cIdCol = (string) config('ieducar.columns.curso.id');
            $cNameCol = (string) config('ieducar.columns.curso.name');

            $serieT = IeducarSchema::resolveTable('serie', $city);
            $sIdCol = (string) config('ieducar.columns.serie.id');
            $sNameCol = IeducarColumnInspector::firstExistingColumn($db, $serieT, [
                (string) config('ieducar.columns.serie.name'),
                'nm_serie',
                'serie',
            ], $city);

            $cids = [];
            $sids = [];
            foreach ($rows as $row) {
                $a = (array) $row;
                $cid = (int) ($a['cid'] ?? 0);
                if ($cid > 0) {
                    $cids[$cid] = true;
                }
                if (isset($a['sid'])) {
                    $sid = (int) $a['sid'];
                    if ($sid > 0) {
                        $sids[$sid] = true;
                    }
                }
            }

            $cmap = [];
            if ($cids !== []) {
                foreach ($db->table($cursoT)->whereIn($cIdCol, array_keys($cids))->get() as $cr) {
                    $cmap[(int) $cr->{$cIdCol}] = trim((string) ($cr->{$cNameCol} ?? ''));
                }
            }
            $smap = [];
            if ($sNameCol !== null && $sids !== []) {
                foreach ($db->table($serieT)->whereIn($sIdCol, array_keys($sids))->get() as $sr) {
                    $smap[(int) $sr->{$sIdCol}] = trim((string) ($sr->{$sNameCol} ?? ''));
                }
            }

            /** @var array<int, array<string, bool>> $acc */
            $acc = [];
            foreach ($rows as $row) {
                $a = (array) $row;
                $eid = (int) ($a['eid'] ?? 0);
                if ($eid <= 0) {
                    continue;
                }
                $cid = (int) ($a['cid'] ?? 0);
                $cn = $cid > 0 ? ($cmap[$cid] ?? ('#'.$cid)) : '';
                $sn = '';
                if ($tc['serie'] !== '' && isset($a['sid'])) {
                    $sid = (int) $a['sid'];
                    $sn = $sid > 0 && $sNameCol !== null ? ($smap[$sid] ?? ('#'.$sid)) : '';
                }
                $line = $cn;
                if ($sn !== '') {
                    $line = $line !== '' ? $line.' — '.$sn : $sn;
                }
                if ($line === '') {
                    continue;
                }
                if (! isset($acc[$eid])) {
                    $acc[$eid] = [];
                }
                $acc[$eid][$line] = true;
            }

            $out = [];
            foreach ($acc as $eid => $lines) {
                $list = array_keys($lines);
                sort($list, SORT_STRING);
                $out[$eid] = array_values($list);
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $eids
     * @return array<int, array<string, mixed>>
     */
    private function loadMapMarkerSchoolPayloads(Connection $db, City $city, IeducarFilterState $filters, array $eids): array
    {
        $eids = array_values(array_unique(array_values(array_filter($eids, fn ($x) => (int) $x > 0))));
        if ($eids === []) {
            return [];
        }
        $matBy = $this->matriculasCountByEscolaIds($db, $city, $filters, $eids);
        $capVagasBy = MatriculaChartQueries::capacidadeEVagasPorEscolaIds($db, $city, $filters, $eids);
        $cardBy = $this->fetchEscolaCardFieldsByIds($db, $city, $eids);
        $ofertaBy = $this->ofertaCursoSeriePorEscolaIds($db, $city, $filters, $eids);

        $inepFromGeoByEid = [];
        try {
            $inepFromGeoByEid = SchoolUnitGeo::query()
                ->where('city_id', $city->id)
                ->whereIn('escola_id', $eids)
                ->get()
                ->keyBy('escola_id');
        } catch (\Throwable) {
            $inepFromGeoByEid = [];
        }

        $out = [];
        foreach ($eids as $eid) {
            $mat = $matBy[$eid] ?? 0;
            $bundle = $capVagasBy[$eid] ?? null;
            $cap = $bundle !== null ? ($bundle['capacidade_declarada'] ?? null) : null;
            $vagas = $bundle !== null ? ($bundle['vagas_disponiveis'] ?? null) : null;
            $card = $cardBy[$eid] ?? [];
            $geoRow = $inepFromGeoByEid[$eid] ?? null;
            if ($geoRow !== null) {
                $ic = $geoRow->inep_code ?? null;
                if (($card['inep'] ?? null) === null && is_numeric($ic) && (int) $ic > 0) {
                    $card['inep'] = (int) $ic;
                }
            }
            $out[$eid] = array_merge([
                'eid' => $eid,
                'matriculas' => $mat,
                'capacidade_declarada' => $cap,
                'vagas_disponiveis' => $vagas,
            ], $card);
            $out[$eid]['oferta_curso_serie'] = $ofertaBy[$eid] ?? [];
        }

        return $out;
    }

    /**
     * @param  list<object|array<string, mixed>>  $rows
     * @param  'matricula'|'rede_escola'  $mapScopeLabel
     * @return array{
     *   markers: list<array<string, mixed>>,
     *   geo_note: ?string,
     *   geo_source: string,
     *   geo_attribution: list<string>,
     *   geo_distribution: array<string, mixed>
     * }
     */
    private function buildMarkersFromEscolaRows(Connection $db, City $city, IeducarFilterState $filters, array $rows, string $mapScopeLabel): array
    {
        $markers = [];
        $dbCount = 0;
        $inepCount = 0;
        $localCount = 0;
        /** @var list<array{inep: int, eid: int, nome: string}> $pendingInep */
        $pendingInep = [];

        $eidsEscopo = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $eidRow = isset($arr['eid']) ? (int) $arr['eid'] : 0;
            if ($eidRow > 0) {
                $eidsEscopo[$eidRow] = true;
            }
        }
        $nEscolasEscopo = count($eidsEscopo);

        // Geos locais (cache) por cidade+escola: fallback quando iEducar não tem lat/lng.
        $localByEid = [];
        try {
            if ($nEscolasEscopo > 0) {
                $localByEid = SchoolUnitGeo::query()
                    ->where('city_id', $city->id)
                    ->whereIn('escola_id', array_keys($eidsEscopo))
                    ->get()
                    ->keyBy('escola_id')
                    ->all();
            }
        } catch (\Throwable) {
            $localByEid = [];
        }

        foreach ($rows as $row) {
            $arr = (array) $row;
            $nome = trim((string) ($arr['escola_nome'] ?? ''));
            if ($nome === '') {
                $nome = __('Sem nome');
            }
            $eid = isset($arr['eid']) ? (int) $arr['eid'] : 0;
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
                $existing = $eid > 0 ? ($localByEid[$eid] ?? null) : null;
                $offLat = $existing && is_numeric($existing->official_lat) ? (float) $existing->official_lat : null;
                $offLng = $existing && is_numeric($existing->official_lng) ? (float) $existing->official_lng : null;
                $divM = null;
                $hasDiv = false;
                if ($offLat !== null && $offLng !== null) {
                    $divM = $this->haversineMeters($la, $ln, $offLat, $offLng);
                    $hasDiv = $divM !== null && $divM >= $this->divergenceThresholdMeters();
                }

                // Atualiza cache local com a coordenada do iEducar (fonte preferida) e divergência vs oficial (INEP) quando existir.
                try {
                    $now = now();
                    $payload = [
                        'city_id' => (int) $city->id,
                        'escola_id' => $eid,
                        'inep_code' => $inep,
                        'lat' => $la,
                        'lng' => $ln,
                        'ieducar_lat' => $la,
                        'ieducar_lng' => $ln,
                        'ieducar_seen_at' => $now,
                        'has_divergence' => $hasDiv,
                        'divergence_meters' => $divM,
                        'meta' => json_encode(['nome' => $nome], JSON_UNESCAPED_UNICODE),
                        'updated_at' => $now,
                    ];

                    DB::table((new SchoolUnitGeo)->getTable())->upsert(
                        [array_merge($payload, ['created_at' => $now])],
                        ['city_id', 'escola_id'],
                        ['inep_code', 'lat', 'lng', 'ieducar_lat', 'ieducar_lng', 'ieducar_seen_at', 'has_divergence', 'divergence_meters', 'meta', 'updated_at'],
                    );
                } catch (\Throwable) {
                }

                $geoDiv = ($offLat !== null && $offLng !== null) || $hasDiv
                    ? $this->geoDivergencePayload($la, $ln, $offLat, $offLng, $divM, $hasDiv)
                    : [];

                $markers[] = [
                    'lat' => $la,
                    'lng' => $ln,
                    'label' => $nome,
                    'meta' => __('Coordenadas na base i-Educar (tabela escola).'),
                    'eid' => $eid,
                    'fonte_coordenada' => 'db',
                    'fonte_coordenada_label' => $this->fonteCoordenadaLabel('db'),
                    'geo_divergence' => $geoDiv !== [] ? $geoDiv : null,
                ];
                $dbCount++;

                continue;
            }

            // Sem coordenadas no iEducar: tentar fallback local (mapa lat/lng ou só oficial INEP em cache).
            $local = $eid > 0 ? ($localByEid[$eid] ?? null) : null;
            if ($local instanceof SchoolUnitGeo) {
                $mapLat = null;
                $mapLng = null;
                if (is_numeric($local->lat) && is_numeric($local->lng)) {
                    $mapLat = (float) $local->lat;
                    $mapLng = (float) $local->lng;
                } elseif (is_numeric($local->official_lat) && is_numeric($local->official_lng)) {
                    $mapLat = (float) $local->official_lat;
                    $mapLng = (float) $local->official_lng;
                }
                $mapOk = $mapLat !== null && $mapLng !== null
                    && ! (abs($mapLat) < 0.01 && abs($mapLng) < 0.01)
                    && abs($mapLat) <= 90 && abs($mapLng) <= 180;

                if ($mapOk) {
                    $localCount++;
                    $geoDiv = $this->geoDivergencePayload(
                        is_numeric($local->ieducar_lat) ? (float) $local->ieducar_lat : null,
                        is_numeric($local->ieducar_lng) ? (float) $local->ieducar_lng : null,
                        is_numeric($local->official_lat) ? (float) $local->official_lat : null,
                        is_numeric($local->official_lng) ? (float) $local->official_lng : null,
                        $local->divergence_meters !== null ? (float) $local->divergence_meters : null,
                        (bool) $local->has_divergence,
                    );
                    $fromOfficialOnly = is_numeric($local->lat) && is_numeric($local->lng) ? false : true;
                    $markers[] = [
                        'lat' => $mapLat,
                        'lng' => $mapLng,
                        'label' => $nome,
                        'meta' => $fromOfficialOnly
                            ? __('Coordenadas oficiais INEP em cache (school_unit_geos); a tabela escola ainda não tem lat/lng para o mapa.')
                            : __('Coordenadas locais (cache) — preenchidas a partir do iEducar em sincronização anterior.'),
                        'eid' => $eid,
                        'fonte_coordenada' => $fromOfficialOnly ? 'inep' : 'local',
                        'fonte_coordenada_label' => $this->fonteCoordenadaLabel($fromOfficialOnly ? 'inep' : 'local'),
                        'geo_divergence' => $geoDiv,
                    ];

                    continue;
                }
            }

            // Por fim, (opcional) INEP oficial (quando houver fonte nacional funcionando).
            if ($inep !== null && $eid > 0) {
                $pendingInep[] = ['inep' => $inep, 'eid' => $eid, 'nome' => $nome];
            }
        }

        $inepEnabled = filter_var(config('ieducar.inep_geocoding.enabled', true), FILTER_VALIDATE_BOOLEAN);
        if ($pendingInep !== [] && $inepEnabled) {
            $codes = [];
            foreach ($pendingInep as $p) {
                $codes[$p['inep']] = true;
            }
            $hits = $this->inepGeo->lookupByInepCodes(array_keys($codes));
            foreach ($pendingInep as $p) {
                $code = $p['inep'];
                if (! isset($hits[$code])) {
                    continue;
                }
                $hit = $hits[$code];
                $ola = (float) $hit['lat'];
                $olg = (float) $hit['lng'];
                $eidInep = (int) $p['eid'];
                $nomeInep = $p['nome'];
                $existingInep = $eidInep > 0 ? ($localByEid[$eidInep] ?? null) : null;
                $ieduLatInep = $existingInep && is_numeric($existingInep->ieducar_lat) ? (float) $existingInep->ieducar_lat : null;
                $ieduLngInep = $existingInep && is_numeric($existingInep->ieducar_lng) ? (float) $existingInep->ieducar_lng : null;
                $nowInep = now();
                $divInep = null;
                $hasDivInep = false;
                if ($ieduLatInep !== null && $ieduLngInep !== null) {
                    $divInep = $this->haversineMeters($ieduLatInep, $ieduLngInep, $ola, $olg);
                    $hasDivInep = $divInep !== null && $divInep >= $this->divergenceThresholdMeters();
                }
                try {
                    $rowInep = [
                        'city_id' => (int) $city->id,
                        'escola_id' => $eidInep,
                        'inep_code' => $code,
                        'lat' => $ola,
                        'lng' => $olg,
                        'ieducar_lat' => $ieduLatInep,
                        'ieducar_lng' => $ieduLngInep,
                        'ieducar_seen_at' => $ieduLatInep !== null ? ($existingInep?->ieducar_seen_at ?? $nowInep) : null,
                        'official_lat' => $ola,
                        'official_lng' => $olg,
                        'official_source' => 'inep_arcgis',
                        'official_seen_at' => $nowInep,
                        'has_divergence' => $hasDivInep,
                        'divergence_meters' => $divInep,
                        'meta' => json_encode(['nome' => $nomeInep], JSON_UNESCAPED_UNICODE),
                        'updated_at' => $nowInep,
                        'created_at' => $nowInep,
                    ];
                    DB::table((new SchoolUnitGeo)->getTable())->upsert(
                        [$rowInep],
                        ['city_id', 'escola_id'],
                        ['inep_code', 'lat', 'lng', 'ieducar_lat', 'ieducar_lng', 'ieducar_seen_at', 'official_lat', 'official_lng', 'official_source', 'official_seen_at', 'has_divergence', 'divergence_meters', 'meta', 'updated_at'],
                    );
                } catch (\Throwable) {
                }

                $markers[] = [
                    'lat' => $ola,
                    'lng' => $olg,
                    'label' => $nomeInep,
                    'meta' => __('Catálogo de Escolas (INEP/MEC): coordenadas públicas — código INEP :code.', ['code' => $code]),
                    'eid' => $eidInep,
                    'fonte_coordenada' => 'inep',
                    'fonte_coordenada_label' => $this->fonteCoordenadaLabel('inep'),
                    'geo_divergence' => $this->geoDivergencePayload($ieduLatInep, $ieduLngInep, $ola, $olg, $divInep, $hasDivInep),
                ];
                $inepCount++;
            }
        }

        $totalComCoord = count($markers);
        $limite = 120;
        $markersSlice = array_slice($markers, 0, $limite);

        $eidsCarga = [];
        foreach ($markersSlice as $m) {
            $id = (int) ($m['eid'] ?? 0);
            if ($id > 0) {
                $eidsCarga[] = $id;
            }
        }
        $payloads = $this->loadMapMarkerSchoolPayloads($db, $city, $filters, $eidsCarga);

        $finalMarkers = [];
        foreach ($markersSlice as $m) {
            $eid = (int) ($m['eid'] ?? 0);
            $m['school'] = $eid > 0 ? ($payloads[$eid] ?? null) : null;
            $finalMarkers[] = $m;
        }

        $finalMarkers = $this->enrichMarkersWithInepCatalogAndLinks($finalMarkers);

        $geoSource = 'none';
        $nFontes = ($dbCount > 0 ? 1 : 0) + ($inepCount > 0 ? 1 : 0) + ($localCount > 0 ? 1 : 0);
        if ($nFontes > 1) {
            $geoSource = 'mixed';
        } elseif ($dbCount > 0) {
            $geoSource = 'db';
        } elseif ($inepCount > 0) {
            $geoSource = 'inep_arcgis';
        } elseif ($localCount > 0) {
            $geoSource = 'local_cache';
        }

        $attribution = $this->geoAttributionLines();

        $geoNote = null;
        if ($finalMarkers === []) {
            $geoNote = __('Sem coordenadas para as escolas do filtro: preencha latitude/longitude na escola na base i-Educar ou garanta o código INEP (ex.: codigo_inep) para consulta ao Catálogo de Escolas INEP.');
        }

        $geoDistribution = [
            'map_scope' => $mapScopeLabel,
            'escolas_no_escopo' => $nEscolasEscopo,
            'com_coordenadas_base' => $dbCount,
            'com_coordenadas_inep' => $inepCount,
            'com_coordenadas_cache_local' => $localCount,
            'total_com_coordenadas' => $totalComCoord,
            'limite_marcadores' => $limite,
            'marcadores_exibidos' => count($finalMarkers),
            'inep_geocoding_ativo' => $inepEnabled,
            'divergence_threshold_m' => $this->divergenceThresholdMeters(),
        ];

        return [
            'markers' => $finalMarkers,
            'geo_note' => $geoNote,
            'geo_source' => $geoSource,
            'geo_attribution' => $attribution,
            'geo_distribution' => $geoDistribution,
        ];
    }

    /**
     * URL público INEP para a escola (Portal IDEB ou template em config com {inep}).
     */
    private function inepPortalEscolaPublicUrl(int $inep): string
    {
        if ($inep <= 0) {
            return '';
        }
        $t = trim((string) config('ieducar.inep_geocoding.inep_portal_escola_url_template', ''));
        if ($t === '') {
            $t = 'https://www.portalideb.org.br/resultado/escola/{inep}';
        }
        if (str_contains($t, '{inep}')) {
            return str_replace('{inep}', (string) $inep, $t);
        }

        return $t;
    }

    /**
     * Catálogo INEP (ArcGIS), link público INEP (Portal IDEB / painel por escola) e conciliação com a base local.
     *
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, mixed>>
     */
    private function enrichMarkersWithInepCatalogAndLinks(array $markers): array
    {
        $enrich = filter_var(config('ieducar.inep_geocoding.enrich_markers_with_inep_catalog', true), FILTER_VALIDATE_BOOLEAN);
        $legacyQedu = rtrim((string) config('ieducar.inep_geocoding.qedu_escola_base_url', 'https://www.qedu.org.br/escola'), '/');
        if ($legacyQedu === '') {
            $legacyQedu = 'https://www.qedu.org.br/escola';
        }

        $inepCodes = [];
        foreach ($markers as $m) {
            $school = $m['school'] ?? null;
            if (! is_array($school)) {
                continue;
            }
            $inep = isset($school['inep']) && is_numeric($school['inep']) ? (int) $school['inep'] : null;
            if ($inep !== null && $inep > 0) {
                $inepCodes[$inep] = true;
            }
        }
        $codesList = array_keys($inepCodes);

        $hits = [];
        if ($enrich && $codesList !== []) {
            $hits = $this->inepGeo->lookupByInepCodes($codesList);
        }

        $out = [];
        foreach ($markers as $m) {
            $school = isset($m['school']) && is_array($m['school']) ? $m['school'] : null;
            if (is_array($school) && ! array_key_exists('endereco_cadastro', $school)) {
                $el = trim((string) ($school['endereco'] ?? ''));
                $school['endereco_cadastro'] = $el !== '' ? $el : null;
            }
            $inep = null;
            if ($school !== null && isset($school['inep']) && is_numeric($school['inep'])) {
                $inep = (int) $school['inep'];
                if ($inep <= 0) {
                    $inep = null;
                }
            }

            $hit = $inep !== null ? ($hits[$inep] ?? null) : null;

            $m['inep_catalog'] = is_array($hit) ? ($hit['catalog'] ?? []) : [];
            $m['inep_catalog_assoc'] = is_array($hit) ? ($hit['catalog_assoc'] ?? []) : [];

            if (is_array($school) && is_array($hit)) {
                $assoc = $hit['catalog_assoc'] ?? [];
                $telCat = trim((string) ($assoc['Telefone'] ?? $assoc['Telefone_1'] ?? ''));
                $endCat = trim((string) ($assoc['Endereço'] ?? $assoc['Endereco'] ?? $assoc['ENDERECO'] ?? ''));
                $endLocal = trim((string) ($school['endereco'] ?? ''));
                $school['endereco_cadastro'] = $endLocal !== '' ? $endLocal : null;
                if ($telCat !== '' && trim((string) ($school['telefone'] ?? '')) === '') {
                    $school['telefone'] = $telCat;
                }
                if ($endCat !== '') {
                    $school['endereco_inep'] = $endCat;
                }
                if ($endLocal === '' && $endCat !== '') {
                    $school['endereco'] = $endCat;
                }
                $m['school'] = $school;
            }

            $m['inep_links'] = [];
            if ($inep !== null) {
                $portalUrl = $this->inepPortalEscolaPublicUrl($inep);
                if ($portalUrl !== '') {
                    $m['inep_links'][] = [
                        'id' => 'inep_portal',
                        'label' => __('Portal IDEB (INEP) — indicadores e painel pedagógico da escola'),
                        'url' => $portalUrl,
                    ];
                }
            }

            $m['inep_portal_escola_base_url'] = trim((string) config('ieducar.inep_geocoding.inep_portal_escola_url_template', ''));
            $m['qedu_escola_base_url'] = $legacyQedu;

            if ($school !== null && $inep !== null) {
                $m['conciliation'] = $this->conciliarUnidadeLocalComInep($school, $hit);
            } else {
                $m['conciliation'] = null;
            }

            $out[] = $m;
        }

        return $this->enrichSchoolMarkersWithCensoGeoAgg($out);
    }

    /**
     * Preenche geografia administrativa (Censo Escolar / microdados indexado) quando não há endereço no cadastro i-Educar.
     *
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, mixed>>
     */
    private function enrichSchoolMarkersWithCensoGeoAgg(array $markers): array
    {
        if (! filter_var(config('ieducar.inep_geocoding.censo_geo_agg_modal_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return $markers;
        }

        $need = [];
        foreach ($markers as $m) {
            $school = $m['school'] ?? null;
            if (! is_array($school)) {
                continue;
            }
            $inep = isset($school['inep']) && is_numeric($school['inep']) ? (int) $school['inep'] : null;
            if ($inep === null || $inep <= 0) {
                continue;
            }
            $endCad = trim((string) ($school['endereco_cadastro'] ?? ''));
            if ($endCad !== '') {
                continue;
            }
            $need[$inep] = true;
        }
        if ($need === []) {
            return $markers;
        }

        $hits = $this->censoGeoAgg->lookupByInepCodes(array_keys($need));
        if ($hits === []) {
            return $markers;
        }

        foreach ($markers as $i => $m) {
            $school = $m['school'] ?? null;
            if (! is_array($school)) {
                continue;
            }
            $inep = isset($school['inep']) && is_numeric($school['inep']) ? (int) $school['inep'] : null;
            if ($inep === null || ! isset($hits[$inep])) {
                continue;
            }
            $endCad = trim((string) ($school['endereco_cadastro'] ?? ''));
            if ($endCad !== '') {
                continue;
            }
            $school['geo_censo_agg'] = $hits[$inep];
            $m['school'] = $school;
            $markers[$i] = $m;
        }

        return $markers;
    }

    /**
     * @param  array<string, mixed>  $school
     * @param  array<string, mixed>|null  $inepHit
     * @return array<string, mixed>
     */
    private function conciliarUnidadeLocalComInep(array $school, ?array $inepHit): array
    {
        $nomeLocal = trim((string) ($school['nome'] ?? ''));
        $nomeCat = $inepHit !== null ? trim((string) ($inepHit['nome_inep'] ?? '')) : '';
        $norm = static function (string $s): string {
            $s = mb_strtolower($s, 'UTF-8');
            $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

            return trim($s);
        };
        $nomesCoincidem = $nomeLocal !== '' && $nomeCat !== ''
            && $norm($nomeLocal) === $norm($nomeCat);

        $assoc = is_array($inepHit) ? ($inepHit['catalog_assoc'] ?? []) : [];
        $telLoc = trim((string) ($school['telefone'] ?? ''));
        $telCat = trim((string) ($assoc['Telefone'] ?? $assoc['Telefone_1'] ?? ''));
        $endLoc = trim((string) ($school['endereco'] ?? ''));
        $endCat = trim((string) ($assoc['Endereço'] ?? $assoc['Endereco'] ?? $assoc['ENDERECO'] ?? ''));

        $digits = static function (string $s): string {
            return preg_replace('/\D+/', '', $s) ?? '';
        };
        $telIguais = $telLoc !== '' && $telCat !== ''
            && ($digits($telLoc) === $digits($telCat) || str_contains($telLoc, $telCat) || str_contains($telCat, $telLoc));

        $endIguais = $endLoc !== '' && $endCat !== ''
            && (mb_stripos($endLoc, mb_substr($endCat, 0, 12, 'UTF-8'), 0, 'UTF-8') !== false
                || mb_stripos($endCat, mb_substr($endLoc, 0, 12, 'UTF-8'), 0, 'UTF-8') !== false);

        return [
            'nomes_coincidem' => $nomesCoincidem,
            'nome_local' => $nomeLocal !== '' ? $nomeLocal : null,
            'nome_catalogo' => $nomeCat !== '' ? $nomeCat : null,
            'telefones_coincidem' => $telIguais,
            'telefone_local' => $telLoc !== '' ? $telLoc : null,
            'telefone_catalogo' => $telCat !== '' ? $telCat : null,
            'enderecos_coincidem' => $endIguais,
            'endereco_local' => $endLoc !== '' ? $endLoc : null,
            'endereco_catalogo' => $endCat !== '' ? $endCat : null,
            'catalogo_disponivel' => $inepHit !== null && ($inepHit['catalog'] ?? []) !== [],
        ];
    }

    /**
     * Nomes de escolas para o mapa (só colunas mínimas).
     *
     * @param  list<int>  $eids
     * @return array<int, string>
     */
    private function fetchEscolaNamesByIdsForMap(Connection $db, City $city, array $eids): array
    {
        if ($eids === []) {
            return [];
        }
        try {
            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eId = (string) config('ieducar.columns.escola.id');
            $en = $this->escolaNomeSql($db, $city, 'e', $eId);
            $g = $db->getQueryGrammar();
            $we = $g->wrap('e');
            $rows = $db->table($escolaT.' as e')
                ->whereIn('e.'.$eId, $eids)
                ->selectRaw($we.'.'.$g->wrap($eId).' as eid')
                ->selectRaw($en['expr'].' as escola_nome')
                ->get();
            $out = [];
            foreach ($rows as $row) {
                $a = (array) $row;
                $eid = (int) ($a['eid'] ?? 0);
                if ($eid > 0) {
                    $out[$eid] = trim((string) ($a['escola_nome'] ?? ''));
                }
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Quando não há escolas no âmbito de matrículas (ou rede) mas existem linhas em school_unit_geos
     * com INEP ou coordenadas — permite mapa e estatísticas coerentes com o cache local.
     *
     * @return list<array<string, mixed>>
     */
    private function escolasRowsFromSchoolUnitGeoFallback(City $city, IeducarFilterState $filters): array
    {
        try {
            $q = SchoolUnitGeo::query()
                ->where('city_id', $city->id)
                ->where('escola_id', '>', 0);
            if ($filters->escola_id !== null) {
                $q->where('escola_id', (int) $filters->escola_id);
            }
            $geos = $q->orderBy('escola_id')->limit(300)->get();
        } catch (\Throwable) {
            return [];
        }

        $candidates = [];
        foreach ($geos as $g) {
            $hasMap = (is_numeric($g->lat) && is_numeric($g->lng))
                || (is_numeric($g->official_lat) && is_numeric($g->official_lng));
            $hasInep = is_numeric($g->inep_code) && (int) $g->inep_code > 0;
            if (! $hasMap && ! $hasInep) {
                continue;
            }
            $candidates[] = $g;
        }

        if ($candidates === []) {
            return [];
        }

        $out = [];
        foreach ($candidates as $g) {
            $eid = (int) $g->escola_id;
            if ($eid <= 0) {
                continue;
            }
            $inep = is_numeric($g->inep_code) && (int) $g->inep_code > 0 ? (int) $g->inep_code : null;
            $out[] = ['eid' => $eid, 'inep' => $inep];
        }

        return $out;
    }

    /**
     * @return array{
     *   markers: list<array<string, mixed>>,
     *   geo_note: ?string,
     *   geo_source: string,
     *   geo_attribution: list<string>,
     *   geo_distribution: array<string, mixed>,
     *   map_scope: 'matricula'|'rede_escola'|'geo_cache',
     *   show_waiting_capacity: bool
     * }
     */
    private function buildMapMarkers(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $attribution = $this->geoAttributionLines();

        $scopedRows = $this->escolasScopedForMap($db, $city, $filters);
        $fromMatricula = $this->buildMarkersFromEscolaRows($db, $city, $filters, $scopedRows, 'matricula');

        if ($fromMatricula['markers'] !== []) {
            return [
                'markers' => $fromMatricula['markers'],
                'geo_note' => $fromMatricula['geo_note'],
                'geo_source' => $fromMatricula['geo_source'],
                'geo_attribution' => $fromMatricula['geo_attribution'],
                'geo_distribution' => $fromMatricula['geo_distribution'],
                'map_scope' => 'matricula',
                'show_waiting_capacity' => true,
            ];
        }

        $redeRows = $this->escolasRedeParaMapaFallback($db, $city, $filters);
        $fromRede = $this->buildMarkersFromEscolaRows($db, $city, $filters, $redeRows, 'rede_escola');

        if ($fromRede['markers'] !== []) {
            $extra = __('Mapa com unidades cadastradas na rede (tabela escola). Não são exibidos indicadores de lista de espera e capacidade por turma neste modo, porque o mapa não está restrito ao âmbito de matrículas ativas nos filtros.');

            return [
                'markers' => $fromRede['markers'],
                'geo_note' => $fromRede['geo_note'] !== null ? $fromRede['geo_note'].' '.$extra : $extra,
                'geo_source' => $fromRede['geo_source'],
                'geo_attribution' => $fromRede['geo_attribution'],
                'geo_distribution' => $fromRede['geo_distribution'],
                'map_scope' => 'rede_escola',
                'show_waiting_capacity' => false,
            ];
        }

        $cacheRowData = $this->escolasRowsFromSchoolUnitGeoFallback($city, $filters);
        if ($cacheRowData !== []) {
            $eidsForNames = array_values(array_unique(array_map(
                static fn (array $r): int => (int) ($r['eid'] ?? 0),
                $cacheRowData,
            )));
            $eidsForNames = array_values(array_filter($eidsForNames, static fn (int $id): bool => $id > 0));
            $namesByEid = $this->fetchEscolaNamesByIdsForMap($db, $city, $eidsForNames);
            $cacheRows = [];
            foreach ($cacheRowData as $row) {
                $eid = (int) ($row['eid'] ?? 0);
                $nome = $eid > 0 ? ($namesByEid[$eid] ?? '') : '';
                $cacheRows[] = [
                    'eid' => $eid,
                    'escola_nome' => $nome !== '' ? $nome : __('Unidade #:id', ['id' => $eid]),
                    'la' => null,
                    'ln' => null,
                    'inep' => $row['inep'] ?? null,
                ];
            }
            $fromCache = $this->buildMarkersFromEscolaRows($db, $city, $filters, $cacheRows, 'geo_cache');

            if ($fromCache['markers'] !== []) {
                $extra = __('Modo cache local (school_unit_geos): não há matrículas ativas no âmbito do ano/filtros ou a consulta não devolveu escolas; o mapa usa coordenadas e INEP já guardados na base local.');

                return [
                    'markers' => $fromCache['markers'],
                    'geo_note' => $fromCache['geo_note'] !== null ? $fromCache['geo_note'].' '.$extra : $extra,
                    'geo_source' => $fromCache['geo_source'],
                    'geo_attribution' => $fromCache['geo_attribution'],
                    'geo_distribution' => $fromCache['geo_distribution'],
                    'map_scope' => 'geo_cache',
                    'show_waiting_capacity' => false,
                ];
            }
        }

        $geoNote = $scopedRows === []
            ? __('Não há escolas no âmbito dos filtros (matrículas ativas) para posicionar no mapa.')
            : __('Sem coordenadas para as escolas do filtro: preencha latitude/longitude na escola na base i-Educar ou garanta o código INEP (ex.: codigo_inep) para consulta ao Catálogo de Escolas INEP.');

        $nEscopo = 0;
        foreach ($scopedRows as $r) {
            $a = (array) $r;
            if (((int) ($a['eid'] ?? 0)) > 0) {
                $nEscopo++;
            }
        }

        return [
            'markers' => [],
            'geo_note' => $geoNote,
            'geo_source' => 'none',
            'geo_attribution' => $attribution,
            'geo_distribution' => [
                'map_scope' => 'matricula',
                'escolas_no_escopo' => $nEscopo,
                'com_coordenadas_base' => 0,
                'com_coordenadas_inep' => 0,
                'com_coordenadas_cache_local' => 0,
                'total_com_coordenadas' => 0,
                'limite_marcadores' => 120,
                'marcadores_exibidos' => 0,
                'inep_geocoding_ativo' => filter_var(config('ieducar.inep_geocoding.enabled', true), FILTER_VALIDATE_BOOLEAN),
                'divergence_threshold_m' => $this->divergenceThresholdMeters(),
            ],
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
                'nr_maximo_alunos',
                'qtd_maxima_alunos',
                'qtde_max_alunos',
                'capacidade_maxima',
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
