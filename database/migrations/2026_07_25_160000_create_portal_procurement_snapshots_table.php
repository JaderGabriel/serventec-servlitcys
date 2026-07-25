<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_procurement_snapshots', function (Blueprint $table) {
            $table->id();
            /** contrato | licitacao */
            $table->string('tipo', 16);
            $table->unsignedSmallInteger('ano')->index();
            $table->string('codigo_orgao', 10)->index();
            $table->string('orgao_sigla', 20)->nullable();
            $table->string('orgao_nome', 180)->nullable();
            $table->string('external_id', 64);
            $table->string('numero', 64)->nullable();
            $table->text('objeto')->nullable();
            $table->string('situacao', 120)->nullable();
            $table->string('modalidade', 120)->nullable();
            $table->decimal('valor', 18, 2)->nullable();
            $table->decimal('valor_final', 18, 2)->nullable();
            $table->string('data_assinatura', 20)->nullable();
            $table->string('data_inicio_vigencia', 20)->nullable();
            $table->string('data_fim_vigencia', 20)->nullable();
            $table->string('data_publicacao', 20)->nullable();
            $table->string('fornecedor_cnpj', 14)->nullable()->index();
            $table->string('fornecedor_nome', 180)->nullable();
            $table->string('ibge_municipio', 7)->nullable()->index();
            $table->string('municipio_nome', 120)->nullable();
            $table->string('uf', 2)->nullable()->index();
            $table->string('ug_codigo', 20)->nullable();
            $table->string('ug_nome', 180)->nullable();
            $table->boolean('vendor_matched')->default(false)->index();
            $table->string('vendor_label', 120)->nullable();
            /** Itens contratados sugerem software/SGE (keywords curadas). */
            $table->boolean('itens_software')->default(false)->index();
            $table->json('itens')->nullable();
            $table->json('payload')->nullable();
            $table->string('fonte', 40)->default('portal_transparencia');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tipo', 'ano', 'codigo_orgao', 'external_id'],
                'portal_procurement_tipo_ano_orgao_id_unique',
            );
            $table->index(['tipo', 'ano', 'codigo_orgao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_procurement_snapshots');
    }
};
