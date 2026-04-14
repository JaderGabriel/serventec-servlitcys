<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('school_unit_geos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id')->index();
            $table->unsignedBigInteger('escola_id')->index();
            $table->unsignedBigInteger('inep_code')->nullable()->index();

            // Coordenadas "locais" usadas no mapa quando a base iEducar estiver sem lat/lng.
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            // Última observação de coordenadas na base iEducar (para detectar divergências).
            $table->decimal('ieducar_lat', 10, 7)->nullable();
            $table->decimal('ieducar_lng', 10, 7)->nullable();
            $table->timestamp('ieducar_seen_at')->nullable();

            // Coordenadas "oficiais" (quando conseguirmos consultar fonte nacional no futuro).
            $table->decimal('official_lat', 10, 7)->nullable();
            $table->decimal('official_lng', 10, 7)->nullable();
            $table->string('official_source', 32)->nullable();
            $table->timestamp('official_seen_at')->nullable();

            // Divergência para decisão: oficial vs iEducar (quando ambos existem).
            $table->boolean('has_divergence')->default(false);
            $table->decimal('divergence_meters', 10, 2)->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['city_id', 'escola_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_unit_geos');
    }
};
