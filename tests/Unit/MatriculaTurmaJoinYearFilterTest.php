<?php

namespace Tests\Unit;

use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\MatriculaTurmaJoin;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Filtro de ano letivo com fallback em matricula.ano (sem cache — query em tempo real).
 */
final class MatriculaTurmaJoinYearFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ieducar.schema' => '',
            'ieducar.columns.matricula.id' => 'cod_matricula',
            'ieducar.columns.matricula.turma' => 'ref_cod_turma',
            'ieducar.columns.matricula.ativo' => 'ativo',
            'ieducar.columns.matricula.ano' => 'ano',
            'ieducar.columns.turma.id' => 'cod_turma',
            'ieducar.columns.turma.year' => 'ano',
        ]);

        Schema::dropIfExists('matricula');
        Schema::dropIfExists('turma');
        Schema::create('matricula', function (Blueprint $table): void {
            $table->integer('cod_matricula')->primary();
            $table->integer('ref_cod_turma')->nullable();
            $table->integer('ano')->nullable();
            $table->integer('ativo')->default(1);
        });
        Schema::create('turma', function (Blueprint $table): void {
            $table->integer('cod_turma')->primary();
            $table->integer('ano')->nullable();
        });

        DB::table('matricula')->insert([
            ['cod_matricula' => 1, 'ref_cod_turma' => 10, 'ano' => 2025, 'ativo' => 1],
            ['cod_matricula' => 2, 'ref_cod_turma' => null, 'ano' => 2025, 'ativo' => 1],
            ['cod_matricula' => 3, 'ref_cod_turma' => 11, 'ano' => 2024, 'ativo' => 1],
        ]);
        DB::table('turma')->insert([
            ['cod_turma' => 10, 'ano' => 2025],
            ['cod_turma' => 11, 'ano' => 2024],
        ]);
    }

    #[Test]
    public function conta_matricula_sem_turma_no_ano_filtrado(): void
    {
        $city = \App\Models\City::factory()->make([
            'ieducar_schema' => null,
            'ieducar_driver' => 'mysql',
        ]);

        $filters = new IeducarFilterState('2025', null, null, null);
        $db = DB::connection();

        $q = $db->table('matricula as m');
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm', left: true);
        MatriculaTurmaJoin::applyYearFilter($q, $db, $city, $filters, 't_filter', 'm');

        $count = (int) $q->distinct()->count('m.cod_matricula');

        $this->assertSame(2, $count);
    }
}
