<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('ibge_municipio', 7)->nullable()->after('uf');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->unique('ibge_municipio');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropUnique(['ibge_municipio']);
            $table->dropColumn('ibge_municipio');
        });
    }
};
