<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipal_education_works', function (Blueprint $table) {
            $table->unsignedInteger('populacao_beneficiada')->nullable()->after('desc_meta_global');
            $table->string('desc_populacao_beneficiada', 255)->nullable()->after('populacao_beneficiada');
            $table->unsignedTinyInteger('salas_projeto')->nullable()->after('desc_populacao_beneficiada');
            $table->string('tipology', 40)->nullable()->after('salas_projeto');
        });
    }

    public function down(): void
    {
        Schema::table('municipal_education_works', function (Blueprint $table) {
            $table->dropColumn([
                'populacao_beneficiada',
                'desc_populacao_beneficiada',
                'salas_projeto',
                'tipology',
            ]);
        });
    }
};
