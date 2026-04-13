<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarSchema;
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
                return [
                    'kpis' => [
                        'escolas' => $this->safeCount($db, 'escola'),
                        'turmas' => $this->safeCount($db, 'turma'),
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

    private function safeCount(\Illuminate\Database\Connection $db, string $logicalKey): ?int
    {
        try {
            $table = IeducarSchema::resolveTable($logicalKey);

            return (int) $db->table($table)->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    private function countMatriculas(\Illuminate\Database\Connection $db): ?int
    {
        try {
            $table = IeducarSchema::resolveTable('matricula');

            return (int) $db->table($table)->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }
}
