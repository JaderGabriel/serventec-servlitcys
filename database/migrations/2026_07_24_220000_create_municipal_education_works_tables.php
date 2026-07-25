<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_education_works', function (Blueprint $table): void {
            $table->id();
            $table->string('id_projeto_investimento', 32)->unique();
            $table->string('ibge_municipio', 7)->nullable()->index();
            $table->string('ibge_confidence', 16)->default('none');
            $table->string('uf_principal', 2)->index();
            $table->string('situacao', 32)->index();
            $table->string('especie_intervencao', 128)->nullable();
            $table->string('natureza_intervencao', 128)->nullable();
            $table->string('desc_nome', 512)->nullable();
            $table->string('desc_meta_global', 255)->nullable();
            $table->string('sistema_resp', 64)->nullable();
            $table->string('organizacao_resp', 255)->nullable();
            $table->string('cnpj_organizacao_resp', 14)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('percentual_execucao_fisica', 8, 2)->nullable();
            $table->decimal('valor_empenhado', 18, 2)->nullable();
            $table->decimal('valor_pago', 18, 2)->nullable();
            $table->json('historico_paralisacao')->nullable();
            $table->json('meta')->nullable();
            $table->string('fonte', 32)->default('obrasgov');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['ibge_municipio', 'situacao']);
            $table->index(['uf_principal', 'situacao']);
        });

        Schema::create('education_work_finance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('id_projeto_investimento', 32)->index();
            $table->string('fonte_orcamentaria', 128)->nullable();
            $table->decimal('valor_empenho', 18, 2)->nullable();
            $table->decimal('valor_liquidado', 18, 2)->nullable();
            $table->decimal('valor_pago', 18, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique('id_projeto_investimento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_work_finance_snapshots');
        Schema::dropIfExists('municipal_education_works');
    }
};
