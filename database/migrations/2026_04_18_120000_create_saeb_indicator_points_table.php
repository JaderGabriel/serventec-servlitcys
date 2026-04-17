<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pontos SAEB importados (substituem o antigo historico.json): gráficos Desempenho e API /api/saeb/municipio/{ibge}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saeb_indicator_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->char('ibge_municipio', 7)->index();
            $table->unsignedSmallInteger('ano')->index();
            $table->string('disciplina', 64)->nullable();
            $table->string('etapa', 64)->nullable();
            $table->decimal('valor', 12, 4)->nullable();
            $table->string('fonte', 32)->default('import');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['ibge_municipio', 'ano', 'disciplina']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saeb_indicator_points');
    }
};
