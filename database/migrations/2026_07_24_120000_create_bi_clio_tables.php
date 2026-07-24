<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bi_clio_campaign', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->unique()->constrained('clio_campaigns')->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('ibge', 7)->nullable()->index();
            $table->unsignedSmallInteger('year')->index();
            $table->string('municipality_name')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('profile', 32)->nullable();
            $table->string('status', 32)->nullable();
            $table->date('reference_date')->nullable();
            $table->decimal('triade_pct', 5, 1)->nullable();
            $table->unsignedInteger('schools_active')->default(0);
            $table->unsignedInteger('schools_total')->default(0);
            $table->unsignedInteger('mat_curricular')->default(0);
            $table->unsignedInteger('mat_aee')->default(0);
            $table->unsignedInteger('mat_ac')->default(0);
            $table->unsignedInteger('findings_errors')->default(0);
            $table->unsignedInteger('findings_warnings')->default(0);
            $table->decimal('distortion_pct', 5, 1)->nullable();
            $table->decimal('density_avg', 6, 1)->nullable();
            $table->unsignedInteger('nee_people')->default(0);
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
            $table->index(['city_id', 'year']);
        });

        Schema::create('bi_clio_school', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('clio_campaigns')->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('clio_campaign_schools')->nullOnDelete();
            $table->string('inep', 12)->index();
            $table->string('name');
            $table->string('functioning_status')->nullable();
            $table->string('location')->nullable();
            $table->string('dependency')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('triade_parts')->default(0);
            $table->unsignedInteger('rows_aluno')->default(0);
            $table->unsignedInteger('rows_turma')->default(0);
            $table->unsignedInteger('rows_profissional')->default(0);
            $table->integer('delta_curricular')->nullable();
            $table->unsignedInteger('findings_errors')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'inep']);
        });

        Schema::create('bi_clio_enrollment_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('clio_campaigns')->cascadeOnDelete();
            $table->string('inep', 12)->nullable()->index();
            $table->string('etapa');
            $table->unsignedInteger('qt_alunos')->default(0);
            $table->unsignedInteger('qt_turmas')->default(0);
            $table->timestamps();
            $table->index(['campaign_id', 'etapa']);
        });

        Schema::create('bi_clio_quality', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('clio_campaigns')->cascadeOnDelete();
            $table->string('inep', 12)->nullable()->index();
            $table->boolean('missing_triad')->default(false);
            $table->integer('delta_acomp')->nullable();
            $table->decimal('distortion_pct', 5, 1)->nullable();
            $table->unsignedInteger('distortion_n')->default(0);
            $table->unsignedInteger('eligible')->default(0);
            $table->decimal('density_avg', 6, 1)->nullable();
            $table->unsignedInteger('turmas_ge_40')->default(0);
            $table->unsignedInteger('turmas_sem_docente')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'inep']);
        });

        Schema::create('bi_clio_inclusion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('clio_campaigns')->cascadeOnDelete();
            $table->string('inep', 12)->nullable()->index();
            $table->unsignedInteger('qt_nee_people')->default(0);
            $table->unsignedInteger('qt_deficiency')->default(0);
            $table->unsignedInteger('qt_disorder')->default(0);
            $table->unsignedInteger('qt_ah')->default(0);
            $table->unsignedInteger('qt_without_aee')->default(0);
            $table->unsignedInteger('qt_aee_without_nee')->default(0);
            $table->unsignedInteger('qt_underreporting')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'inep']);
        });

        Schema::create('bi_clio_insight', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('clio_campaigns')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('severity', 16)->default('info');
            $table->string('title');
            $table->text('body');
            $table->string('metric_value')->nullable();
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
            $table->unique(['campaign_id', 'code']);
            $table->index(['campaign_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_clio_insight');
        Schema::dropIfExists('bi_clio_inclusion');
        Schema::dropIfExists('bi_clio_quality');
        Schema::dropIfExists('bi_clio_enrollment_stage');
        Schema::dropIfExists('bi_clio_school');
        Schema::dropIfExists('bi_clio_campaign');
    }
};
