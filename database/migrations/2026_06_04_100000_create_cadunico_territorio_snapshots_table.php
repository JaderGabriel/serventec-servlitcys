<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cadunico_territorio_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('ibge_municipio', 7);
            $table->unsignedSmallInteger('ano_referencia');
            $table->string('territorio_codigo', 64);
            $table->string('territorio_nome', 191);
            $table->string('territorio_tipo', 32)->default('bairro');
            $table->unsignedInteger('criancas_4_17')->default(0);
            $table->unsignedInteger('criancas_4_5')->default(0);
            $table->unsignedInteger('criancas_6_10')->default(0);
            $table->unsignedInteger('criancas_11_14')->default(0);
            $table->unsignedInteger('criancas_15_17')->default(0);
            $table->unsignedInteger('familias_beneficio')->default(0);
            $table->decimal('indice_vulnerabilidade', 6, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('fonte', 64)->default('csv_territorio');
            $table->json('metadados')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['ibge_municipio', 'ano_referencia', 'territorio_codigo'],
                'cadunico_territorio_unique',
            );
            $table->index(['ibge_municipio', 'ano_referencia'], 'cadunico_territorio_ibge_ano');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cadunico_territorio_snapshots');
    }
};
