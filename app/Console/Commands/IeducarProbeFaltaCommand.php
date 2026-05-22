<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use Illuminate\Console\Command;

class IeducarProbeFaltaCommand extends Command
{
    protected $signature = 'ieducar:probe-falta {city : ID da cidade}';

    protected $description = 'Lista tabelas/colunas de faltas na base i-Educar e sugere variáveis .env';

    public function __construct(
        private CityDataConnection $cityData,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $city = City::query()->find((int) $this->argument('city'));
        if ($city === null) {
            $this->error(__('Cidade não encontrada.'));

            return self::FAILURE;
        }

        try {
            $this->cityData->run($city, function ($db) use ($city) {
                $this->line(__('Município: :name (driver :d)', [
                    'name' => $city->name,
                    'd' => $city->effectiveIeducarDriver(),
                ]));

                if ($db->getDriverName() === 'pgsql') {
                    $rows = $db->select(
                        "select table_schema, table_name
                         from information_schema.tables
                         where table_type = 'BASE TABLE'
                         and table_name ilike '%falta%'
                         order by table_schema, table_name"
                    );
                    $this->info(__('Tabelas com «falta» no nome:'));
                    foreach ($rows as $r) {
                        $this->line('  '.$r->table_schema.'.'.$r->table_name);
                    }
                } else {
                    $rows = $db->select(
                        "select table_schema, table_name
                         from information_schema.tables
                         where table_name like '%falta%'
                         order by table_schema, table_name"
                    );
                    foreach ($rows as $r) {
                        $this->line('  '.$r->table_schema.'.'.$r->table_name);
                    }
                }

                $resolved = IeducarSchema::resolveTable('falta_aluno', $city);
                $this->newLine();
                $this->info(__('Tabela configurada (resolveTable): :t', ['t' => $resolved]));
                $exists = IeducarColumnInspector::tableExists($db, $resolved, $city);
                $this->line($exists ? '  ✓ tabela existe' : '  ✗ tabela NÃO encontrada');

                if ($exists) {
                    $cols = $db->select(
                        'select column_name, data_type
                         from information_schema.columns
                         where table_schema = ? and table_name = ?
                         order by ordinal_position',
                        ...$this->schemaTableParts($db, $resolved, $city)
                    );
                    $this->info(__('Colunas:'));
                    foreach ($cols as $c) {
                        $this->line('  '.$c->column_name.' ('.$c->data_type.')');
                    }

                    $matCandidates = ['ref_cod_matricula', 'cod_matricula', 'matricula_id'];
                    $dataCandidates = ['data_falta', 'data', 'dt_falta', 'data_falta_abono'];
                    $mat = IeducarColumnInspector::firstExistingColumn($db, $resolved, $matCandidates, $city);
                    $data = IeducarColumnInspector::firstExistingColumn($db, $resolved, $dataCandidates, $city);

                    $tableEnv = str_contains($resolved, '.')
                        ? $resolved
                        : (string) config('ieducar.tables.falta_aluno', 'falta_aluno');

                    $this->newLine();
                    $this->comment(__('Cole no .env (ajuste só se diferir):'));
                    $this->line('IEDUCAR_TABLE_FALTA_ALUNO='.$tableEnv);
                    $this->line('IEDUCAR_COL_FALTA_MATRICULA='.($mat ?? 'ref_cod_matricula'));
                    $this->line('IEDUCAR_COL_FALTA_DATA='.($data ?? 'data_falta'));
                }
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function schemaTableParts($db, string $qualified, City $city): array
    {
        if (str_contains($qualified, '.')) {
            [$schema, $table] = explode('.', $qualified, 2);

            return [$schema, $table];
        }

        $schema = IeducarSchema::effectiveSchema($city) ?: 'public';

        return [$schema, $qualified];
    }
}
