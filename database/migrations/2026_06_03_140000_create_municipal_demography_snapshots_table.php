<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_demography_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('ibge_municipio', 7)->index();
            $table->unsignedSmallInteger('ano_referencia');
            $table->unsignedInteger('populacao_4_17')->nullable();
            $table->unsignedInteger('populacao_total')->nullable();
            $table->string('fonte', 32)->default('ibge_sidra');
            $table->json('metadados')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'ano_referencia', 'fonte'], 'municipal_demography_ibge_ano_fonte');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_demography_snapshots');
    }
};
