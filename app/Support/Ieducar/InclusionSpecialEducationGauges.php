<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Medidores (% sobre matrículas activas filtradas) para deficiências, síndromes e altas habilidades.
 *
 * Prioridade: SQL em config (ieducar.sql.inclusion_gauge_*); senão heurística com aluno_deficiencia + cadastro.deficiencia.
 */
final class InclusionSpecialEducationGauges
{
    /** @return list<array{title: string, percent: float, caption: string}> */
    public static function build(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $out = [];

        $titles = [
            'def' => __('Deficiências'),
            'sin' => __('Síndromes e TEA'),
            'ah' => __('Altas habilidades / superdotação'),
        ];

        $sqlDef = trim((string) config('ieducar.sql.inclusion_gauge_deficiencia', ''));
        $sqlSin = trim((string) config('ieducar.sql.inclusion_gauge_sindrome', ''));
        $sqlAh = trim((string) config('ieducar.sql.inclusion_gauge_altas_habilidades', ''));

        foreach (['def' => $sqlDef, 'sin' => $sqlSin, 'ah' => $sqlAh] as $key => $sql) {
            if ($sql === '') {
                continue;
            }
            $pct = self::percentFromCustomSql($db, $city, $sql);
            if ($pct !== null) {
                $out[] = [
                    'title' => $titles[$key],
                    'percent' => $pct,
                    'caption' => __('Percentagem sobre o denominador devolvido pelo SQL (matrículas activas no filtro, salvo definição explícita na consulta).'),
                ];
            }
        }

        if ($out !== []) {
            return $out;
        }

        return self::buildHeuristic($db, $city, $filters);
    }

    private static function percentFromCustomSql(Connection $db, City $city, string $sql): ?float
    {
        try {
            $sql = IeducarSqlPlaceholders::interpolate($sql, $city);
            $row = $db->selectOne($sql);
            if ($row === null) {
                return null;
            }
            $arr = (array) $row;
            if (array_key_exists('pct', $arr) && is_numeric($arr['pct'])) {
                return max(0.0, min(100.0, (float) $arr['pct']));
            }
            if (array_key_exists('numerador', $arr) && array_key_exists('denominador', $arr)
                && is_numeric($arr['numerador']) && is_numeric($arr['denominador'])) {
                $den = (float) $arr['denominador'];

                return $den <= 0.0 ? 0.0 : max(0.0, min(100.0, 100.0 * (float) $arr['numerador'] / $den));
            }

            return null;
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * @return list<array{title: string, percent: float, caption: string}>
     */
    private static function buildHeuristic(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $den = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);
            if ($den === null || $den <= 0) {
                return [];
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');

            $adTable = self::resolveAlunoDeficienciaTable($db, $city);
            $defTable = self::resolveDeficienciaCatalogTable($db, $city);
            if ($adTable === null) {
                return [];
            }

            $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
                (string) config('ieducar.columns.aluno_deficiencia.aluno'),
                'ref_cod_aluno',
                'cod_aluno',
                'aluno_id',
                'id_aluno',
            ]), $city);
            $adDef = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
                (string) config('ieducar.columns.aluno_deficiencia.deficiencia'),
                'ref_cod_deficiencia',
                'cod_deficiencia',
                'deficiencia_id',
                'ref_deficiencia',
            ]), $city);

            if ($adAluno === null || $adDef === null) {
                return [];
            }

            $nmCol = null;
            $defPk = null;
            if ($defTable !== null) {
                $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
                    (string) config('ieducar.columns.deficiencia.id'),
                    'cod_deficiencia',
                    'id',
                ]), $city);
                $nmCol = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
                    (string) config('ieducar.columns.deficiencia.name'),
                    'nm_deficiencia',
                    'nome',
                    'descricao',
                ]), $city);
                if ($defPk === null) {
                    $defTable = null;
                }
            }

            $base = static function () use ($db, $mat, $aluno, $mAluno, $mAtivo, $aId, $city, $filters): Builder {
                $q = $db->table($mat.' as m')
                    ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
                MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);

                return $q;
            };

            $countDistinct = static function (Builder $q, string $mIdCol): int {
                try {
                    $row = $q->selectRaw('COUNT(DISTINCT m.'.$mIdCol.') as c')->first();

                    return (int) ($row->c ?? 0);
                } catch (QueryException) {
                    return 0;
                }
            };

            if ($defTable !== null && $nmCol !== null && $defPk !== null) {
                $sinExpr = self::keywordMatchExpression('d.'.$nmCol, self::sindromeKeywords());
                $ahExpr = self::keywordMatchExpression('d.'.$nmCol, self::altasHabilidadesKeywords());
                $defExpr = '(NOT ('.$sinExpr.')) AND (NOT ('.$ahExpr.'))';

                $nSin = $countDistinct(
                    $base()
                        ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                        ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk)
                        ->whereRaw($sinExpr),
                    $mId
                );

                $nAh = $countDistinct(
                    $base()
                        ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                        ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk)
                        ->whereRaw($ahExpr),
                    $mId
                );

                $nDef = $countDistinct(
                    $base()
                        ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                        ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk)
                        ->whereRaw($defExpr),
                    $mId
                );

                $pct = static fn (int $n) => round(100.0 * $n / $den, 1);

                return [
                    [
                        'title' => __('Deficiências'),
                        'percent' => $pct($nDef),
                        'caption' => __(':n de :d matrículas com registo associado (exclui pelo nome do cadastro as categorias tratadas em «Síndromes/TEA» e «Altas habilidades»). Vários vínculos em aluno_deficiência podem contar o mesmo estudante em mais do que um medidor.', ['n' => $nDef, 'd' => $den]),
                    ],
                    [
                        'title' => __('Síndromes e TEA'),
                        'percent' => $pct($nSin),
                        'caption' => __(':n de :d matrículas (palavras-chave no nome da deficiência). Ver metodologia na caixa «Referência metodológica».', ['n' => $nSin, 'd' => $den]),
                    ],
                    [
                        'title' => __('Altas habilidades / superdotação'),
                        'percent' => $pct($nAh),
                        'caption' => __(':n de :d matrículas (palavras-chave no nome da deficiência). O mesmo estudante pode entrar em mais do que um medidor se existirem vários registos em aluno_deficiência.', ['n' => $nAh, 'd' => $den]),
                    ],
                ];
            }

            $nAny = $countDistinct(
                $base()->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno),
                $mId
            );
            $pct = round(100.0 * $nAny / $den, 1);

            return [
                [
                    'title' => __('Registos em necessidades especiais'),
                    'percent' => $pct,
                    'caption' => __(':n de :d matrículas com linha em aluno_deficiencia (sem catálogo «deficiencia» para subdividir).', ['n' => $nAny, 'd' => $den]),
                ],
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private static function sindromeKeywords(): array
    {
        return [
            'síndrome', 'sindrome', 'syndrome', 'tea', 'autis', 'asperger', 'down',
            'espectro autista', 'transtorno do espectro',
            'turner', 'fragil', 'x frag', 'rett', 'prader', 'willi', 'angelman',
        ];
    }

    /**
     * @return list<string>
     */
    private static function altasHabilidadesKeywords(): array
    {
        return [
            'superdota', 'super dot', 'alta habilidade', 'altas habilidades', 'gifted', 'talento',
            'precoce', 'habilidade intelectual', 'ah sd', 'superdotacao',
        ];
    }

    /**
     * @param  list<string>  $words
     */
    private static function keywordMatchExpression(string $qualifiedNameCol, array $words): string
    {
        $wrap = $qualifiedNameCol;
        $checks = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '') {
                continue;
            }
            $esc = str_replace("'", "''", $w);
            $checks[] = 'LOWER('.$wrap.') LIKE \'%'.$esc.'%\'';
        }

        return $checks !== [] ? '('.implode(' OR ', $checks).')' : 'FALSE';
    }

    private static function resolveAlunoDeficienciaTable(Connection $db, City $city): ?string
    {
        foreach (self::alunoDeficienciaCandidates($city) as $t) {
            if (IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return IeducarColumnInspector::findQualifiedTableByNames($db, [
            'aluno_deficiencia',
            'aluno_deficiencias',
        ], $city);
    }

    private static function resolveDeficienciaCatalogTable(Connection $db, City $city): ?string
    {
        foreach (self::deficienciaCatalogCandidates($city) as $t) {
            if (IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return IeducarColumnInspector::findQualifiedTableByNames($db, [
            'deficiencia',
            'deficiencias',
        ], $city);
    }

    /**
     * @return list<string>
     */
    private static function alunoDeficienciaCandidates(City $city): array
    {
        $primary = IeducarSchema::resolveTable('aluno_deficiencia', $city);

        return array_values(array_unique(array_filter([
            $primary,
            'pmieducar.aluno_deficiencia',
            'public.aluno_deficiencia',
            'educacenso.aluno_deficiencia',
            'modules.aluno_deficiencia',
            trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.aluno_deficiencia',
        ])));
    }

    /**
     * @return list<string>
     */
    private static function deficienciaCatalogCandidates(City $city): array
    {
        $primary = IeducarSchema::resolveTable('deficiencia', $city);

        return array_values(array_unique(array_filter([
            $primary,
            trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.deficiencia',
            'public.deficiencia',
        ])));
    }
}
