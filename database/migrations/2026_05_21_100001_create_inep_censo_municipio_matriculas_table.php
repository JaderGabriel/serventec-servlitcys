<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inep_censo_municipio_matriculas', function (Blueprint $table) {
            $table->id();
            $table->char('ibge_municipio', 7);
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('matriculas_total')->default(0);
            $table->unsignedInteger('escolas_contagem')->default(0);
            $table->string('fonte', 40)->default('inep_microdados');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'ano'], 'inep_censo_municipio_matriculas_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inep_censo_municipio_matriculas');
    }
};
