<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fundeb_municipio_references', function (Blueprint $table) {
            $table->string('tipo_valor', 32)->nullable()->after('fonte');
            $table->decimal('receita_total', 16, 2)->nullable()->after('complementacao_vaar');
            $table->decimal('complementacao_vaaf', 14, 2)->nullable()->after('receita_total');
            $table->unsignedInteger('matriculas_base')->nullable()->after('complementacao_vaaf');
            $table->string('matriculas_fonte', 32)->nullable()->after('matriculas_base');
            $table->string('url_portaria', 500)->nullable()->after('notas');
            $table->json('meta')->nullable()->after('url_portaria');
        });
    }

    public function down(): void
    {
        Schema::table('fundeb_municipio_references', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_valor',
                'receita_total',
                'complementacao_vaaf',
                'matriculas_base',
                'matriculas_fonte',
                'url_portaria',
                'meta',
            ]);
        });
    }
};
