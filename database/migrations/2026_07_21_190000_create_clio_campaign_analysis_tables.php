<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clio_campaign_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('clio_campaigns')->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('clio_campaign_schools')->nullOnDelete();
            $table->foreignId('artifact_id')->nullable()->constrained('clio_campaign_artifacts')->nullOnDelete();
            $table->string('code', 64)->index();
            $table->string('severity', 16)->default('warning');
            $table->string('message', 512);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'code']);
        });

        Schema::create('clio_campaign_inferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('clio_campaigns')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('summary', 512)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clio_campaign_inferences');
        Schema::dropIfExists('clio_campaign_findings');
    }
};
