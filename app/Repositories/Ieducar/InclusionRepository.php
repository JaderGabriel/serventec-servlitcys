<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\IeducarSqlPlaceholders;
use App\Support\Ieducar\InclusionSpecialEducationGauges;
use App\Support\Ieducar\MatriculaAtivoFilter;
use App\Support\Ieducar\MatriculaChartQueries;
use App\Support\Ieducar\MatriculaTurmaJoin;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Inclusão e diversidade: medidores (NEE), equidade (sexo/série), raça/cor e SQL opcional.
 */
class InclusionRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}>,
     *   gauges: list<array{chart: array<string, mixed>, caption: string}>,
     *   notes: list<string>,
     *   error: ?string
     * }
     */
    public function snapshot(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['charts' => [], 'gauges' => [], 'notes' => [], 'error' => null];
        }

        $charts = [];
        $gauges = [];
        $notes = [];

        try {
            $this->cityData->run($city, function (Connection $db) use ($city, $filters, &$charts, &$gauges, &$notes) {
                foreach (InclusionSpecialEducationGauges::build($db, $city, $filters) as $row) {
                    $gauges[] = [
                        'chart' => ChartPayload::gaugePercent($row['title'], $row['percent']),
                        'caption' => $row['caption'],
                    ];
                }

                $sex = MatriculaChartQueries::matriculasPorSexo($db, $city, $filters);
                if ($sex !== null) {
                    $charts[] = $sex;
                } else {
                    $notes[] = __(
                        'Gráfico por sexo indisponível: confirme cadastro.pessoa (sexo, idsexo, …) ou cadastro.fisica, IEDUCAR_TABLE_PESSOA / IEDUCAR_COL_ALUNO_PESSOA e IEDUCAR_COL_PESSOA_ID.'
                    );
                }

                try {
                    $serieTop = MatriculaChartQueries::matriculasPorSerieTop($db, $city, $filters);
                    if ($serieTop !== null) {
                        $charts[] = $serieTop;
                    }
                } catch (QueryException) {
                }

                $customRaca = config('ieducar.sql.inclusion_raca');
                if (is_string($customRaca) && trim($customRaca) !== '') {
                    $racaChart = $this->chartFromRawSql($db, $city, trim($customRaca), __('Matrículas por raça/cor (SQL personalizado)'));
                    if ($racaChart !== null) {
                        $charts[] = $racaChart;
                    } else {
                        $notes[] = __('A consulta personalizada de inclusão (raça) não devolveu linhas válidas.');
                    }
                } else {
                    $racaChart = $this->raceDistributionChart($db, $city, $filters);
                    if ($racaChart !== null) {
                        $charts[] = $racaChart;
                    } else {
                        $notes[] = __(
                            'Não foi possível montar o gráfico de raça/cor automaticamente. Confirme IEDUCAR_TABLE_RACA (cadastro.raca), colunas em pessoa/aluno (ref_cod_raca), IEDUCAR_TABLE_RACA_FALLBACKS ou defina IEDUCAR_SQL_INCLUSION_RACA.'
                        );
                    }
                }

                $customExtra = config('ieducar.sql.inclusion_extra');
                if (is_string($customExtra) && trim($customExtra) !== '') {
                    $extra = $this->chartFromRawSql($db, $city, trim($customExtra), __('Indicador complementar (SQL personalizado)'));
                    if ($extra !== null) {
                        $charts[] = $extra;
                    }
                }
            });
        } catch (\Throwable $e) {
            return [
                'charts' => [],
                'gauges' => [],
                'notes' => [],
                'error' => $e->getMessage(),
            ];
        }

        return ['charts' => $charts, 'gauges' => $gauges, 'notes' => $notes, 'error' => null];
    }

    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function raceDistributionChart(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $pessoa = IeducarSchema::resolveTable('pessoa', $city);

            if (! IeducarColumnInspector::tableExists($db, $mat, $city)
                || ! IeducarColumnInspector::tableExists($db, $aluno, $city)) {
                return null;
            }

            $racaSpec = self::resolveRacaJoinSpec($db, $city);
            if ($racaSpec === null) {
                return null;
            }

            $racaT = $racaSpec['qualified'];
            $rIdCol = $racaSpec['idCol'];
            $rNameCol = $racaSpec['nameCol'];

            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');
            $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                (string) config('ieducar.columns.aluno.pessoa'),
                'ref_cod_pessoa',
                'ref_idpes',
                'idpes',
            ]), $city);
            if ($aPessoa === null) {
                return null;
            }

            $pRaca = null;
            $pId = null;
            if (IeducarColumnInspector::tableExists($db, $pessoa, $city)) {
                $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                    (string) config('ieducar.columns.pessoa.id'),
                    'idpes',
                    'id',
                    'cod_pessoa',
                ]), $city);
                $pRaca = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                    (string) config('ieducar.columns.pessoa.raca'),
                    'ref_cod_raca',
                    'cod_raca',
                    'id_raca',
                    'raca_id',
                    'cor_raca',
                    'cor',
                    'ref_cod_cor',
                ]), $city);
            }

            $aRaca = null;
            if ($pRaca === null) {
                $aRaca = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                    'ref_cod_raca',
                    'cod_raca',
                    'id_raca',
                    'raca_cor',
                ]), $city);
            }

            $fisicaTable = null;
            $fisicaRacaCol = null;
            $fisicaLinkCol = null;
            if ($pRaca === null && $aRaca === null && $pId !== null) {
                foreach (self::fisicaTableCandidates($city) as $cand) {
                    if (! IeducarColumnInspector::tableExists($db, $cand, $city)) {
                        continue;
                    }
                    $fisicaRacaCol = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                        'ref_cod_raca',
                        'cod_raca',
                        'raca_cor',
                        'id_raca',
                    ], $city);
                    $fisicaLinkCol = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                        'idpes',
                        'ref_idpes',
                    ], $city);
                    if ($fisicaRacaCol !== null && $fisicaLinkCol !== null) {
                        $fisicaTable = $cand;
                        break;
                    }
                }
            }

            /** @var array{table: string, racaCol: string, fisicaLink: string, alunoCol: string}|null */
            $fisicaViaAluno = null;
            if ($pRaca === null && $aRaca === null && $fisicaTable === null) {
                $aIdpesCol = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                    'ref_idpes',
                    'idpes',
                ]), $city);
                if ($aIdpesCol !== null) {
                    foreach (self::fisicaTableCandidates($city) as $cand) {
                        if (! IeducarColumnInspector::tableExists($db, $cand, $city)) {
                            continue;
                        }
                        $fc = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                            'ref_cod_raca',
                            'cod_raca',
                            'raca_cor',
                            'id_raca',
                        ], $city);
                        $fl = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                            'idpes',
                            'ref_idpes',
                        ], $city);
                        if ($fc !== null && $fl !== null) {
                            $fisicaViaAluno = [
                                'table' => $cand,
                                'racaCol' => $fc,
                                'fisicaLink' => $fl,
                                'alunoCol' => $aIdpesCol,
                            ];
                            break;
                        }
                    }
                }
            }

            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);

            if ($pRaca !== null && $pId !== null) {
                $q->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId)
                    ->leftJoin($racaT.' as r', 'p.'.$pRaca, '=', 'r.'.$rIdCol);
            } elseif ($aRaca !== null) {
                $q->leftJoin($racaT.' as r', 'a.'.$aRaca, '=', 'r.'.$rIdCol);
            } elseif ($fisicaTable !== null && $fisicaRacaCol !== null && $fisicaLinkCol !== null && $pId !== null) {
                $q->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId)
                    ->leftJoin($fisicaTable.' as pf', 'p.'.$pId, '=', 'pf.'.$fisicaLinkCol)
                    ->leftJoin($racaT.' as r', 'pf.'.$fisicaRacaCol, '=', 'r.'.$rIdCol);
            } elseif ($fisicaViaAluno !== null) {
                $q->leftJoin($fisicaViaAluno['table'].' as pf', 'a.'.$fisicaViaAluno['alunoCol'], '=', 'pf.'.$fisicaViaAluno['fisicaLink'])
                    ->leftJoin($racaT.' as r', 'pf.'.$fisicaViaAluno['racaCol'], '=', 'r.'.$rIdCol);
            } else {
                return null;
            }

            $q->selectRaw('r.'.$rIdCol.' as rid')
                ->selectRaw('MAX(r.'.$rNameCol.') as rname')
                ->selectRaw('COUNT(*) as c')
                ->groupBy('r.'.$rIdCol);

            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);

            $rows = $q->orderByDesc('c')->limit(16)->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $nm = $row->rname ?? null;
                $labels[] = $nm !== null && (string) $nm !== '' ? (string) $nm : __('Não informado');
                $values[] = (int) ($row->c ?? 0);
            }

            return ChartPayload::doughnut(__('Matrículas por raça/cor (cadastro)'), $labels, $values);
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Primeira tabela raca acessível com colunas id + nome (para JOIN).
     *
     * @return array{qualified: string, idCol: string, nameCol: string}|null
     */
    private static function resolveRacaJoinSpec(Connection $db, City $city): ?array
    {
        foreach (IeducarSchema::racaTableCandidates($city) as $qualified) {
            if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
                continue;
            }

            $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
                (string) config('ieducar.columns.raca.id'),
                'cod_raca',
                'id',
                'codigo',
            ]), $city);
            $nameCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
                (string) config('ieducar.columns.raca.name'),
                'nm_raca',
                'nome',
                'nm_cor',
                'descricao',
                'ds_raca',
                'rac_nome',
            ]), $city);

            if ($idCol !== null) {
                return [
                    'qualified' => $qualified,
                    'idCol' => $idCol,
                    'nameCol' => $nameCol ?? $idCol,
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function fisicaTableCandidates(City $city): array
    {
        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica';
        $sch = IeducarSchema::effectiveSchema($city);

        return array_values(array_unique(array_filter([
            $cad,
            $sch !== '' ? $sch.'.fisica' : '',
            'cadastro.fisica',
            'public.fisica',
        ])));
    }

    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function chartFromRawSql(Connection $db, City $city, string $sql, string $title): ?array
    {
        try {
            $rows = $db->select($this->appendLimit(IeducarSqlPlaceholders::interpolate($sql, $city), 32));
            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $vals = array_values($arr);
                if (count($vals) < 2) {
                    continue;
                }
                $labels[] = (string) $vals[0];
                $values[] = (float) $vals[1];
            }
            if ($labels === []) {
                return null;
            }

            return ChartPayload::doughnut($title, $labels, $values);
        } catch (QueryException) {
            return null;
        }
    }

    private function appendLimit(string $sql, int $max): string
    {
        $sql = trim($sql);
        if ($sql === '' || preg_match('/\blimit\s+\d+\s*$/i', $sql)) {
            return $sql;
        }

        return $sql.' LIMIT '.max(1, $max);
    }
}
