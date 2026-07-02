<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inep_censo_municipio_matriculas', function (Blueprint $table) {
            foreach ([
                'matriculas_regular',
                'matriculas_eja',
                'matriculas_especial',
                'matriculas_complementar',
                'matriculas_infantil',
                'matriculas_fundamental_1',
                'matriculas_fundamental_2',
                'matriculas_medio',
                'matriculas_profissional',
            ] as $metric) {
                $table->unsignedInteger($metric.'_municipal')->nullable()->after($metric);
                $table->unsignedInteger($metric.'_nao_municipal')->nullable()->after($metric.'_municipal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inep_censo_municipio_matriculas', function (Blueprint $table) {
            $drop = [];
            foreach ([
                'matriculas_regular',
                'matriculas_eja',
                'matriculas_especial',
                'matriculas_complementar',
                'matriculas_infantil',
                'matriculas_fundamental_1',
                'matriculas_fundamental_2',
                'matriculas_medio',
                'matriculas_profissional',
            ] as $metric) {
                $drop[] = $metric.'_municipal';
                $drop[] = $metric.'_nao_municipal';
            }
            $table->dropColumn($drop);
        });
    }
};
