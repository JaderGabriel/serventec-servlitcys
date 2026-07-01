<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inep_censo_municipio_matriculas', function (Blueprint $table) {
            $table->unsignedInteger('matriculas_regular')->nullable()->after('matriculas_nao_municipal');
            $table->unsignedInteger('matriculas_eja')->nullable()->after('matriculas_regular');
            $table->unsignedInteger('matriculas_especial')->nullable()->after('matriculas_eja');
            $table->unsignedInteger('matriculas_complementar')->nullable()->after('matriculas_especial');
        });
    }

    public function down(): void
    {
        Schema::table('inep_censo_municipio_matriculas', function (Blueprint $table) {
            $table->dropColumn([
                'matriculas_regular',
                'matriculas_eja',
                'matriculas_especial',
                'matriculas_complementar',
            ]);
        });
    }
};
