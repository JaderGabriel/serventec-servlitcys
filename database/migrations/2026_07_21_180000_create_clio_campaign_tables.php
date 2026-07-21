<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clio_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('municipality_name', 255);
            $table->char('uf', 2);
            $table->string('ibge_municipio', 7)->nullable()->index();
            $table->unsignedSmallInteger('year');
            $table->string('stage', 32)->default('stage1');
            $table->string('profile', 32)->default('analysis_only');
            $table->string('status', 32)->default('draft')->index();
            $table->date('reference_date')->nullable();
            $table->string('source', 32)->default('manual_upload');
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['city_id', 'year', 'stage']);
        });

        Schema::create('clio_campaign_schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('clio_campaigns')->cascadeOnDelete();
            $table->string('inep_code', 12)->index();
            $table->string('name', 255);
            $table->string('dependency', 64)->nullable();
            $table->string('collection_form', 128)->nullable();
            $table->string('functioning_status', 128)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'inep_code']);
        });

        Schema::create('clio_campaign_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('clio_campaigns')->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('clio_campaign_schools')->nullOnDelete();
            $table->string('kind', 64)->index();
            $table->string('original_name', 512);
            $table->string('storage_path', 1024);
            $table->string('sha256', 64)->nullable()->index();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('parse_status', 32)->default('pending')->index();
            $table->unsignedInteger('row_count')->nullable();
            $table->json('parse_meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clio_campaign_artifacts');
        Schema::dropIfExists('clio_campaign_schools');
        Schema::dropIfExists('clio_campaigns');
    }
};
