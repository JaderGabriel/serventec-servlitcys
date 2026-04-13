<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\QueryException;

class OverviewRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{kpis: ?array{escolas: ?int, turmas: ?int, matriculas: ?int}, error: ?string}
     */
    public function summary(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['kpis' => null, 'error' => null];
        }

        try {
            return $this->cityData->run($city, function ($db) {
                $tables = config('ieducar.tables');

                return [
                    'kpis' => [
                        'escolas' => $this->safeCount($db, $tables['escola'] ?? null),
                        'turmas' => $this->safeCount($db, $tables['turma'] ?? null),
                        'matriculas' => $this->countMatriculas($db),
                    ],
                    'error' => null,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'kpis' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function safeCount(\Illuminate\Database\Connection $db, ?string $table): ?int
    {
        if (! $table) {
            return null;
        }

        try {
            return (int) $db->table($table)->count();
        } catch (QueryException) {
            return null;
        }
    }

    private function countMatriculas(\Illuminate\Database\Connection $db): ?int
    {
        $table = config('ieducar.tables.matricula');

        try {
            return (int) $db->table($table)->count();
        } catch (QueryException) {
            return null;
        }
    }
}
