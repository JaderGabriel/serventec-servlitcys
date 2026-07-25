<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_emenda_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('ibge_municipio', 7)->index();
            $table->unsignedSmallInteger('ano')->index();
            $table->string('codigo_emenda', 32);
            $table->string('numero_emenda', 32)->nullable();
            $table->string('tipo_emenda', 180)->nullable();
            $table->string('autor', 180)->nullable();
            $table->string('localidade_do_gasto', 180)->nullable();
            $table->string('funcao', 120)->nullable();
            $table->string('subfuncao', 120)->nullable();
            $table->decimal('valor_empenhado', 18, 2)->nullable();
            $table->decimal('valor_liquidado', 18, 2)->nullable();
            $table->decimal('valor_pago', 18, 2)->nullable();
            $table->decimal('valor_resto_inscrito', 18, 2)->nullable();
            $table->decimal('valor_resto_cancelado', 18, 2)->nullable();
            $table->decimal('valor_resto_pago', 18, 2)->nullable();
            $table->json('documentos')->nullable();
            $table->json('payload')->nullable();
            $table->string('fonte', 40)->default('portal_transparencia');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'ano', 'codigo_emenda'], 'municipal_emenda_ibge_ano_codigo_unique');
            $table->index(['city_id', 'ano']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_emenda_snapshots');
    }
};
