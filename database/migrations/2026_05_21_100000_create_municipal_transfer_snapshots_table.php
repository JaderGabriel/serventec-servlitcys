<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_transfer_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->char('ibge_municipio', 7);
            $table->unsignedSmallInteger('ano');
            $table->string('fonte', 40);
            $table->string('programa_id', 64);
            $table->string('programa_label', 180)->nullable();
            $table->decimal('valor', 16, 2)->default(0);
            $table->string('moeda', 3)->default('BRL');
            $table->json('meta')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'ano', 'fonte', 'programa_id'], 'municipal_transfer_snapshots_unique');
            $table->index(['city_id', 'ano']);
            $table->index(['ibge_municipio', 'ano']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_transfer_snapshots');
    }
};
