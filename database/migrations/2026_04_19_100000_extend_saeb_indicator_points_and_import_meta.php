<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saeb_import_meta', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->json('meta');
            $table->timestamps();
        });

        Schema::table('saeb_indicator_points', function (Blueprint $table) {
            $table->string('dedupe_key', 192)->nullable();
            $table->json('raw_point')->nullable();
            $table->string('series_key', 192)->nullable();
            $table->boolean('is_final')->default(true);
            $table->string('status', 32)->nullable();
            $table->unsignedBigInteger('escola_id')->nullable();
            $table->json('escola_ids')->nullable();
            $table->json('city_ids')->nullable();
            $table->unique('dedupe_key');
        });
    }

    public function down(): void
    {
        Schema::table('saeb_indicator_points', function (Blueprint $table) {
            $table->dropUnique(['dedupe_key']);
            $table->dropColumn([
                'dedupe_key',
                'raw_point',
                'series_key',
                'is_final',
                'status',
                'escola_id',
                'escola_ids',
                'city_ids',
            ]);
        });

        Schema::dropIfExists('saeb_import_meta');
    }
};
