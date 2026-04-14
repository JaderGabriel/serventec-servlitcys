<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inep_censo_escola_geo_agg', function (Blueprint $table) {
            $table->unsignedInteger('inep_code')->primary();
            $table->unsignedSmallInteger('nu_ano_censo')->nullable();
            $table->string('no_municipio', 180)->nullable();
            $table->char('sg_uf', 2)->nullable();
            $table->string('no_uf', 120)->nullable();
            $table->string('no_regiao', 120)->nullable();
            $table->unsignedTinyInteger('tp_localizacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inep_censo_escola_geo_agg');
    }
};
