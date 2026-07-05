<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_fiscal_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('ibge_municipio', 7)->index();
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('periodo')->default(6);
            $table->decimal('receita_corrente_liquida', 18, 2)->nullable();
            $table->decimal('despesa_educacao_liquidada', 18, 2)->nullable();
            $table->decimal('pct_educacao_receita_corrente', 8, 3)->nullable();
            $table->decimal('pct_minimo_constitucional', 8, 3)->nullable();
            $table->decimal('divida_consolidada', 18, 2)->nullable();
            $table->decimal('disponibilidade_caixa', 18, 2)->nullable();
            $table->decimal('restos_pagar_processados', 18, 2)->nullable();
            $table->decimal('restos_pagar_educacao', 18, 2)->nullable();
            $table->decimal('receita_propria', 18, 2)->nullable();
            $table->decimal('pct_receita_propria', 8, 3)->nullable();
            $table->unsignedTinyInteger('fiscal_capacity_score')->nullable();
            $table->string('fonte', 32)->default('siconfi_rreo');
            $table->json('metadados')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'ano'], 'municipal_fiscal_snapshots_ibge_ano_unique');
        });

        Schema::create('municipal_transparency_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('ibge_municipio', 7)->index();
            $table->unsignedSmallInteger('ano');
            $table->unsignedSmallInteger('convenios_ativos')->default(0);
            $table->decimal('empenhos_educacao', 18, 2)->nullable();
            $table->decimal('empenhos_tecnologia', 18, 2)->nullable();
            $table->unsignedSmallInteger('contratos_software')->default(0);
            $table->json('highlights')->nullable();
            $table->string('fonte', 32)->default('portal_transparencia');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'ano'], 'municipal_transparency_snapshots_ibge_ano_unique');
        });

        Schema::create('municipal_pnad_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('ibge_municipio', 7)->index();
            $table->unsignedSmallInteger('ano_referencia');
            $table->decimal('escolaridade_media', 6, 2)->nullable();
            $table->decimal('pct_neet_jovem', 8, 3)->nullable();
            $table->string('fonte', 32)->default('ibge_sidra');
            $table->json('metadados')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'ano_referencia'], 'municipal_pnad_snapshots_ibge_ano_unique');
        });

        if (Schema::hasTable('inep_censo_municipio_matriculas')) {
            Schema::table('inep_censo_municipio_matriculas', function (Blueprint $table) {
                if (! Schema::hasColumn('inep_censo_municipio_matriculas', 'docentes_total')) {
                    $table->unsignedInteger('docentes_total')->nullable()->after('escolas_contagem');
                }
                if (! Schema::hasColumn('inep_censo_municipio_matriculas', 'docentes_municipal')) {
                    $table->unsignedInteger('docentes_municipal')->nullable()->after('docentes_total');
                }
                if (! Schema::hasColumn('inep_censo_municipio_matriculas', 'docentes_nao_municipal')) {
                    $table->unsignedInteger('docentes_nao_municipal')->nullable()->after('docentes_municipal');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inep_censo_municipio_matriculas')) {
            Schema::table('inep_censo_municipio_matriculas', function (Blueprint $table) {
                foreach (['docentes_nao_municipal', 'docentes_municipal', 'docentes_total'] as $column) {
                    if (Schema::hasColumn('inep_censo_municipio_matriculas', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('municipal_pnad_snapshots');
        Schema::dropIfExists('municipal_transparency_snapshots');
        Schema::dropIfExists('municipal_fiscal_snapshots');
    }
};
