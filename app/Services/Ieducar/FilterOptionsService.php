<?php

namespace App\Services\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Carrega opções dos selects a partir da base da cidade (schema iEducar, MySQL ou PostgreSQL).
 */
class FilterOptionsService
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
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
        $series = $this->loadPairs($city, 'serie', $errors);
        $segmentos = $this->loadPairs($city, 'nivel_ensino', $errors);
        $turnos = $this->loadPairs($city, 'turno', $errors);

        return [
            'years' => $years,
            'escolas' => $escolas,
            'cursos' => $cursos,
            'series' => $series,
            'segmentos' => $segmentos,
            'etapas' => $series,
            'turnos' => $turnos,
            'errors' => $errors,
        ];
    }

    /**
     * Opções para pedidos AJAX em cascata (extensível).
     *
     * @return list<array{id: string, name: string}>
     */
    public function loadByKind(City $city, string $kind): array
    {
        $errors = [];

        return match ($kind) {
            'escola', 'escolas' => $this->loadPairs($city, 'escola', $errors),
            'curso', 'cursos' => $this->loadPairs($city, 'curso', $errors),
            'serie', 'series' => $this->loadPairs($city, 'serie', $errors),
            'segmento', 'segmentos' => $this->loadPairs($city, 'nivel_ensino', $errors),
            'etapa', 'etapas' => $this->loadPairs($city, 'serie', $errors),
            'turno', 'turnos' => $this->loadPairs($city, 'turno', $errors),
            default => [],
        };
    }

    /**
     * @param  list<string>  $errors
     * @return array<int|string, int|string>
     */
    private function mergeYearOptions(City $city, array &$errors): array
    {
        $fromDb = $this->loadYearsFromDatabase($city, $errors);
        $current = (int) date('Y');
        $range = [];
        for ($y = $current + 1; $y >= $current - 8; $y--) {
            $range[$y] = $y;
        }

        return $fromDb + $range;
    }

    /**
     * @param  list<string>  $errors
     * @return array<int, int>
     */
    private function loadYearsFromDatabase(City $city, array &$errors): array
    {
        try {
            return $this->cityData->run($city, function ($db) {
                $table = config('ieducar.tables.ano_letivo');
                $col = config('ieducar.columns.ano_letivo.year');
                $max = (int) config('ieducar.max_rows', 2000);

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
     * @param  'escola'|'curso'|'serie'|'nivel_ensino'|'turno'  $logical
     * @param  list<string>  $errors
     * @return list<array{id: string, name: string}>
     */
    private function loadPairs(City $city, string $logical, array &$errors): array
    {
        $tableKey = match ($logical) {
            'escola' => 'escola',
            'curso' => 'curso',
            'serie' => 'serie',
            'nivel_ensino' => 'nivel_ensino',
            'turno' => 'turno',
            default => 'escola',
        };

        try {
            return $this->cityData->run($city, function ($db) use ($tableKey, $logical) {
                $table = config("ieducar.tables.{$tableKey}");
                $idCol = config("ieducar.columns.{$logical}.id");
                $nameCol = config("ieducar.columns.{$logical}.name");
                $max = (int) config('ieducar.max_rows', 2000);

                $q = $db->table($table)->select([$idCol.' as id', $nameCol.' as name'])->orderBy($nameCol);

                return $q->limit($max)->get()->map(fn ($r) => [
                    'id' => (string) $r->id,
                    'name' => (string) $r->name,
                ])->all();
            });
        } catch (QueryException $e) {
            Log::debug("ieducar.{$logical}", ['message' => $e->getMessage()]);
            $errors[] = __('Não foi possível carregar :entidade. Ajuste config/ieducar.php.', [
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
}
