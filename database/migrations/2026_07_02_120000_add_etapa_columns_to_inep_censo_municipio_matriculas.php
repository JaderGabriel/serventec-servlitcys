<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inep_censo_municipio_matriculas', function (Blueprint $table) {
            $table->unsignedInteger('matriculas_infantil')->nullable()->after('matriculas_complementar');
            $table->unsignedInteger('matriculas_fundamental_1')->nullable()->after('matriculas_infantil');
            $table->unsignedInteger('matriculas_fundamental_2')->nullable()->after('matriculas_fundamental_1');
            $table->unsignedInteger('matriculas_medio')->nullable()->after('matriculas_fundamental_2');
            $table->unsignedInteger('matriculas_profissional')->nullable()->after('matriculas_medio');
        });
    }

    public function down(): void
    {
        Schema::table('inep_censo_municipio_matriculas', function (Blueprint $table) {
            $table->dropColumn([
                'matriculas_infantil',
                'matriculas_fundamental_1',
                'matriculas_fundamental_2',
                'matriculas_medio',
                'matriculas_profissional',
            ]);
        });
    }
};
