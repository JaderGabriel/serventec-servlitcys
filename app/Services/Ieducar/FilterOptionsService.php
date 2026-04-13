<?php

namespace App\Services\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Ieducar\IeducarSchema;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Carrega opções dos selects via SQL na base da cidade (iEducar), com schema configurável
 * e SQL opcional em config/ieducar.php (chave sql.*).
 */
class FilterOptionsService
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * series, segmentos e etapas ficam vazios (resposta legada; filtros retirados da UI).
     *
     * @return array{
     *   years: array<int|string, int|string>,
     *   escolas: list<array{id: string, name: string}>,
     *   cursos: list<array{id: string, name: string}>,
     *   series: list<array{id: string, name: string}>,
     *   segmentos: list<array{id: string, name: string}>,
     *   etapas: list<array{id: string, name: string}>,
     *   turnos: list<array{id: string, name: string}>,
     *   errors: list<string>,
     * }
     */
    public function loadAll(City $city): array
    {
        $errors = [];

        $years = $this->mergeYearOptions($city, $errors);
        $escolas = $this->loadPairs($city, 'escola', $errors);
        $cursos = $this->loadPairs($city, 'curso', $errors);
        $turnos = $this->loadPairs($city, 'turno', $errors);

        return [
            'years' => $years,
            'escolas' => $escolas,
            'cursos' => $cursos,
            'series' => [],
            'segmentos' => [],
            'etapas' => [],
            'turnos' => $turnos,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function loadByKind(City $city, string $kind): array
    {
        $errors = [];

        return match ($kind) {
            'escola', 'escolas' => $this->loadPairs($city, 'escola', $errors),
            'curso', 'cursos' => $this->loadPairs($city, 'curso', $errors),
            'turno', 'turnos' => $this->loadPairs($city, 'turno', $errors),
            default => [],
        };
    }

    /**
     * Placeholder vazio, «Todos os anos», depois só anos existentes na base da cidade.
     *
     * @param  list<string>  $errors
     * @return array<string, string>
     */
    private function mergeYearOptions(City $city, array &$errors): array
    {
        $fromDb = $this->loadYearsFromDatabase($city, $errors);
        $out = [
            '' => __('— Selecione o ano letivo —'),
            'all' => __('Todos os anos'),
        ];
        krsort($fromDb);
        foreach ($fromDb as $y => $_) {
            $ys = (string) $y;
            $out[$ys] = $ys;
        }

        return $out;
    }

    /**
     * @param  list<string>  $errors
     * @return array<int, int>
     */
    private function loadYearsFromDatabase(City $city, array &$errors): array
    {
        $max = (int) config('ieducar.max_rows', 2000);

        try {
            return $this->cityData->run($city, function (Connection $db) use ($max, $city) {
                $custom = config('ieducar.sql.ano_letivo_distinct');
                if (is_string($custom) && trim($custom) !== '') {
                    return $this->yearsFromRawSql($db, $custom, $max);
                }

                try {
                    return $this->yearsFromAnoLetivoTable($db, $max, $city);
                } catch (QueryException $e) {
                    if (! config('ieducar.fallbacks.ano_letivo_from_turma', true)) {
                        throw $e;
                    }

                    return $this->yearsFromTurmaTable($db, $max, $city);
                }
            });
        } catch (QueryException $e) {
            Log::debug('ieducar.ano_letivo', ['message' => $e->getMessage()]);
            $errors[] = __('Ano letivo: não foi possível ler a tabela configurada no banco.');

            return [];
        } catch (\Throwable $e) {
            Log::debug('ieducar.ano_letivo', ['message' => $e->getMessage()]);
            $errors[] = $e->getMessage();

            return [];
        }
    }

    /**
     * @return array<int, int>
     */
    private function yearsFromRawSql(Connection $db, string $sql, int $max): array
    {
        $rows = $db->select($this->appendLimit($sql, $max));
        $out = [];
        foreach ($rows as $row) {
            $ano = $this->readRowInt($row, ['ano', 'year', 'Ano']);
            if ($ano !== null) {
                $out[$ano] = $ano;
            }
        }
        krsort($out);

        return $out;
    }

    /**
     * @return array<int, int>
     */
    private function yearsFromAnoLetivoTable(Connection $db, int $max, City $city): array
    {
        $table = IeducarSchema::resolveTable('ano_letivo', $city);
        $col = config('ieducar.columns.ano_letivo.year');

        $years = $db->table($table)
            ->select($col)
            ->whereNotNull($col)
            ->distinct()
            ->orderByDesc($col)
            ->limit($max)
            ->pluck($col)
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->sortDesc()
            ->values();

        $out = [];
        foreach ($years as $y) {
            $out[$y] = $y;
        }

        return $out;
    }

    /**
     * @return array<int, int>
     */
    private function yearsFromTurmaTable(Connection $db, int $max, City $city): array
    {
        $table = IeducarSchema::resolveTable('turma', $city);
        $col = config('ieducar.columns.turma.year');

        $years = $db->table($table)
            ->select($col)
            ->whereNotNull($col)
            ->distinct()
            ->orderByDesc($col)
            ->limit($max)
            ->pluck($col)
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->sortDesc()
            ->values();

        $out = [];
        foreach ($years as $y) {
            $out[$y] = $y;
        }

        return $out;
    }

    /**
     * @param  'escola'|'curso'|'serie'|'nivel_ensino'|'turno'  $logical
     * @param  list<string>  $errors
     * @return list<array{id: string, name: string}>
     */
    private function loadPairs(City $city, string $logical, array &$errors): array
    {
        $sqlKey = match ($logical) {
            'escola' => 'escola_pairs',
            'curso' => 'curso_pairs',
            'serie' => 'serie_pairs',
            'nivel_ensino' => 'nivel_ensino_pairs',
            'turno' => 'turno_pairs',
            default => null,
        };

        try {
            return $this->cityData->run($city, function (Connection $db) use ($logical, $sqlKey, $city) {
                $max = (int) config('ieducar.max_rows', 2000);

                if ($sqlKey !== null) {
                    $custom = config('ieducar.sql.'.$sqlKey);
                    if (is_string($custom) && trim($custom) !== '') {
                        return $this->pairsFromRawSql($db, $custom, $max);
                    }
                }

                return $this->pairsFromTable($db, $logical, $max, $city);
            });
        } catch (QueryException $e) {
            Log::debug("ieducar.{$logical}", ['message' => $e->getMessage()]);
            $errors[] = __('Não foi possível carregar :entidade. Ajuste config/ieducar.php ou IEDUCAR_SCHEMA / tabelas no .env.', [
                'entidade' => match ($logical) {
                    'escola' => __('escolas'),
                    'curso' => __('cursos'),
                    'serie' => __('séries'),
                    'nivel_ensino' => __('segmentos'),
                    'turno' => __('turnos'),
                    default => $logical,
                },
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::debug("ieducar.{$logical}", ['message' => $e->getMessage()]);
            $errors[] = $e->getMessage();

            return [];
        }
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function pairsFromRawSql(Connection $db, string $sql, int $max): array
    {
        $rows = $db->select($this->appendLimit($sql, $max));
        $out = [];
        foreach ($rows as $row) {
            $pair = $this->pairFromRow($row);
            if ($pair !== null) {
                $out[] = $pair;
            }
        }

        return $out;
    }

    /**
     * @return ?array{id: string, name: string}
     */
    private function pairFromRow(stdClass $row): ?array
    {
        $id = $this->readRowString($row, ['id', 'codigo', 'cod', 'cod_escola', 'cod_curso', 'cod_serie', 'cod_nivel_ensino']);
        $name = $this->readRowString($row, ['name', 'nome', 'label', 'titulo', 'nm_curso', 'nm_serie', 'nm_nivel']);
        if ($id !== null && $name !== null) {
            return ['id' => $id, 'name' => $name];
        }

        $vals = array_values((array) $row);
        if (count($vals) >= 2 && $vals[0] !== null && $vals[0] !== '' && $vals[1] !== null && $vals[1] !== '') {
            return ['id' => (string) $vals[0], 'name' => (string) $vals[1]];
        }

        return null;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function pairsFromTable(Connection $db, string $logical, int $max, City $city): array
    {
        $tableKey = match ($logical) {
            'escola' => 'escola',
            'curso' => 'curso',
            'serie' => 'serie',
            'nivel_ensino' => 'nivel_ensino',
            'turno' => 'turno',
            default => 'escola',
        };

        $table = IeducarSchema::resolveTable($tableKey, $city);
        $idCol = config("ieducar.columns.{$logical}.id");
        $nameCol = config("ieducar.columns.{$logical}.name");

        $q = $db->table($table)->select([$idCol.' as id', $nameCol.' as name'])->orderBy($nameCol);

        if ($logical === 'escola' && config('ieducar.filters.escola_only_active', true)) {
            $activeCol = config('ieducar.columns.escola.active');
            if (is_string($activeCol) && $activeCol !== '') {
                $this->applyEscolaAtivoFilter($db, $q, $activeCol);
            }
        }

        return $q->limit($max)->get()->map(fn ($r) => [
            'id' => (string) $r->id,
            'name' => (string) $r->name,
        ])->all();
    }

    /**
     * Filtro «ativo» compatível com PostgreSQL (boolean / smallint / char).
     */
    private function applyEscolaAtivoFilter(Connection $db, Builder $q, string $activeCol): void
    {
        if ($db->getDriverName() === 'pgsql') {
            $col = $db->getQueryGrammar()->wrap($activeCol);
            $q->whereRaw("({$col} IS TRUE OR {$col} = 1 OR ({$col})::text IN ('1','t','true','T'))");

            return;
        }

        $q->whereIn($activeCol, [1, '1', true, 't', 'true']);
    }

    private function appendLimit(string $sql, int $max): string
    {
        $sql = trim($sql);
        if ($sql === '') {
            return $sql;
        }
        if (preg_match('/\blimit\s+\d+\s*$/i', $sql)) {
            return $sql;
        }

        return $sql.' LIMIT '.max(1, $max);
    }

    /**
     * @param  list<string>  $keys
     */
    private function readRowInt(stdClass $row, array $keys): ?int
    {
        foreach ($keys as $k) {
            if (property_exists($row, $k) && $row->{$k} !== null && $row->{$k} !== '') {
                return (int) $row->{$k};
            }
        }
        $arr = (array) $row;
        foreach ($arr as $v) {
            if (is_numeric($v)) {
                return (int) $v;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     */
    private function readRowString(stdClass $row, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (property_exists($row, $k) && $row->{$k} !== null && (string) $row->{$k} !== '') {
                return (string) $row->{$k};
            }
        }

        return null;
    }
}
