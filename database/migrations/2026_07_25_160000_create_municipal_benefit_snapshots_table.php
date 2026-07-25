<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_benefit_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('ibge_municipio', 7)->index();
            /** Programa: pbf | nbf | bpc */
            $table->string('programa', 8);
            /** Competência AAAAMM (ex.: 202506). */
            $table->unsignedInteger('mes_ano');
            $table->unsignedInteger('quantidade_beneficiados')->default(0);
            $table->decimal('valor', 18, 2)->nullable();
            $table->string('data_referencia', 32)->nullable();
            $table->string('tipo_descricao', 120)->nullable();
            $table->json('payload')->nullable();
            $table->string('fonte', 40)->default('portal_transparencia');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'programa', 'mes_ano'], 'municipal_benefit_ibge_prog_mes_unique');
            $table->index(['city_id', 'programa', 'mes_ano']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_benefit_snapshots');
    }
};
