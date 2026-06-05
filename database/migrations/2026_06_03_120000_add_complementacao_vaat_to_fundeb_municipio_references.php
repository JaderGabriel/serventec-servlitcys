<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fundeb_municipio_references', function (Blueprint $table) {
            $table->decimal('complementacao_vaat', 14, 2)->nullable()->after('complementacao_vaaf');
        });
    }

    public function down(): void
    {
        Schema::table('fundeb_municipio_references', function (Blueprint $table) {
            $table->dropColumn('complementacao_vaat');
        });
    }
};
