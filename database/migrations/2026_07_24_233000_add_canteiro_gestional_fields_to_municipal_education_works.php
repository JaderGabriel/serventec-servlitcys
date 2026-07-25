<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipal_education_works', function (Blueprint $table): void {
            $table->decimal('valor_previsto', 18, 2)->nullable()->after('valor_pago');
            $table->date('data_inicio')->nullable()->after('valor_previsto');
            $table->date('data_paralisacao')->nullable()->after('data_inicio');
            $table->date('data_ultima_afericao')->nullable()->after('data_paralisacao');
            $table->json('meta_execucao')->nullable()->after('historico_paralisacao');
        });
    }

    public function down(): void
    {
        Schema::table('municipal_education_works', function (Blueprint $table): void {
            $table->dropColumn([
                'valor_previsto',
                'data_inicio',
                'data_paralisacao',
                'data_ultima_afericao',
                'meta_execucao',
            ]);
        });
    }
};
