<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_area_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('ibge_municipio', 7)->index();
            $table->unsignedSmallInteger('ano_referencia');
            $table->decimal('area_km2', 14, 3)->nullable();
            $table->string('fonte', 32)->default('ibge_malha');
            $table->json('metadados')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'ano_referencia', 'fonte'], 'municipal_area_ibge_ano_fonte');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_area_snapshots');
    }
};
