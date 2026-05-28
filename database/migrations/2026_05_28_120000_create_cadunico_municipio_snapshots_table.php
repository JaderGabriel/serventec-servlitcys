<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cadunico_municipio_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('ibge_municipio', 7);
            $table->unsignedSmallInteger('ano_referencia');
            $table->unsignedInteger('pessoas_cadastradas')->default(0);
            $table->unsignedInteger('familias_cadastradas')->default(0);
            $table->unsignedInteger('criancas_0_3')->default(0);
            $table->unsignedInteger('criancas_4_5')->default(0);
            $table->unsignedInteger('criancas_6_10')->default(0);
            $table->unsignedInteger('criancas_11_14')->default(0);
            $table->unsignedInteger('criancas_15_17')->default(0);
            $table->unsignedInteger('populacao_escolar_estimada')->default(0);
            $table->string('fonte', 64)->default('cecad_csv');
            $table->string('schema_version', 16)->default('1');
            $table->json('metadados')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'ano_referencia'], 'cadunico_municipio_ano_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cadunico_municipio_snapshots');
    }
};
