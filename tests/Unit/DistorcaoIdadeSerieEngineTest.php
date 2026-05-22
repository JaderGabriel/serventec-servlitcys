<?php

namespace Tests\Unit;

use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DistorcaoIdadeSerieEngine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DistorcaoIdadeSerieEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ieducar.schema' => '',
            'ieducar.distorcao.margem_anos_inep' => 2,
            'ieducar.columns.matricula.id' => 'cod_matricula',
            'ieducar.columns.matricula.turma' => 'ref_cod_turma',
            'ieducar.columns.matricula.aluno' => 'ref_cod_aluno',
            'ieducar.columns.matricula.ativo' => 'ativo',
            'ieducar.columns.matricula.ano' => 'ano',
            'ieducar.columns.turma.id' => 'cod_turma',
            'ieducar.columns.turma.year' => 'ano',
            'ieducar.columns.turma.serie' => 'ref_cod_serie',
            'ieducar.columns.aluno.id' => 'cod_aluno',
            'ieducar.columns.aluno.pessoa' => 'ref_idpes',
            'ieducar.columns.pessoa.id' => 'idpes',
            'ieducar.columns.serie.id' => 'cod_serie',
        ]);

        Schema::dropIfExists('matricula');
        Schema::dropIfExists('turma');
        Schema::dropIfExists('aluno');
        Schema::dropIfExists('pessoa');
        Schema::dropIfExists('serie');

        Schema::create('matricula', function (Blueprint $table): void {
            $table->integer('cod_matricula')->primary();
            $table->integer('ref_cod_turma')->nullable();
            $table->integer('ref_cod_aluno');
            $table->integer('ano');
            $table->integer('ativo')->default(1);
        });
        Schema::create('turma', function (Blueprint $table): void {
            $table->integer('cod_turma')->primary();
            $table->integer('ano');
            $table->integer('ref_cod_serie');
        });
        Schema::create('aluno', function (Blueprint $table): void {
            $table->integer('cod_aluno')->primary();
            $table->integer('ref_idpes');
        });
        Schema::create('pessoa', function (Blueprint $table): void {
            $table->integer('idpes')->primary();
            $table->date('data_nasc');
        });
        Schema::create('serie', function (Blueprint $table): void {
            $table->integer('cod_serie')->primary();
            $table->integer('idade_maxima');
            $table->integer('idade_minima')->nullable();
        });

        DB::table('serie')->insert([
            ['cod_serie' => 1, 'idade_maxima' => 6, 'idade_minima' => 6],
            ['cod_serie' => 2, 'idade_maxima' => 10, 'idade_minima' => 10],
        ]);
        DB::table('turma')->insert([
            ['cod_turma' => 10, 'ano' => 2024, 'ref_cod_serie' => 1],
            ['cod_turma' => 11, 'ano' => 2025, 'ref_cod_serie' => 2],
        ]);
        DB::table('pessoa')->insert([
            ['idpes' => 100, 'data_nasc' => '2010-05-01'],
            ['idpes' => 101, 'data_nasc' => '2018-01-15'],
        ]);
        DB::table('aluno')->insert([
            ['cod_aluno' => 1, 'ref_idpes' => 100],
            ['cod_aluno' => 2, 'ref_idpes' => 101],
        ]);
        DB::table('matricula')->insert([
            ['cod_matricula' => 1, 'ref_cod_turma' => 10, 'ref_cod_aluno' => 1, 'ano' => 2025, 'ativo' => 1],
            ['cod_matricula' => 2, 'ref_cod_turma' => 11, 'ref_cod_aluno' => 2, 'ano' => 2025, 'ativo' => 1],
        ]);
    }

    #[Test]
    public function prefere_mecanismo_matricula_ano_quando_turma_ano_diverge(): void
    {
        $city = \App\Models\City::factory()->make(['ieducar_schema' => null, 'ieducar_driver' => 'mysql']);
        $filters = new IeducarFilterState('2025', null, null, null);
        $db = DB::connection();

        $pack = DistorcaoIdadeSerieEngine::contagens($db, $city, $filters);

        $this->assertNotNull($pack);
        $this->assertSame(DistorcaoIdadeSerieEngine::METODO_INEP_PESSOA_MATRICULA, $pack['metodo']);
        $this->assertGreaterThan(0, $pack['total']);
        $ids = array_column($pack['mecanismos'], 'id');
        $this->assertContains(DistorcaoIdadeSerieEngine::METODO_INEP_PESSOA_MATRICULA, $ids);
    }

    #[Test]
    public function apura_todos_mecanismos_retorna_catalogo(): void
    {
        $city = \App\Models\City::factory()->make(['ieducar_schema' => null, 'ieducar_driver' => 'mysql']);
        $filters = new IeducarFilterState('2025', null, null, null);

        $linhas = DistorcaoIdadeSerieEngine::apurarTodosMecanismos(DB::connection(), $city, $filters);

        $this->assertGreaterThanOrEqual(4, count($linhas));
        $this->assertSame(DistorcaoIdadeSerieEngine::METODO_CUSTOM, $linhas[0]['id']);
    }
}
