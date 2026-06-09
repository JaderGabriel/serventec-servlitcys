<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inep_censo_municipio_matriculas', function (Blueprint $table) {
            $table->unsignedInteger('matriculas_municipal')->nullable()->after('matriculas_total');
            $table->unsignedInteger('matriculas_nao_municipal')->nullable()->after('matriculas_municipal');
        });
    }

    public function down(): void
    {
        Schema::table('inep_censo_municipio_matriculas', function (Blueprint $table) {
            $table->dropColumn(['matriculas_municipal', 'matriculas_nao_municipal']);
        });
    }
};
