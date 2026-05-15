<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fundeb_municipio_references', function (Blueprint $table) {
            $table->id();
            $table->char('ibge_municipio', 7);
            $table->unsignedSmallInteger('ano');
            $table->decimal('vaaf', 12, 2);
            $table->decimal('vaat', 12, 2)->nullable();
            $table->decimal('complementacao_vaar', 14, 2)->nullable();
            $table->string('fonte', 120)->nullable();
            $table->text('notas')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['ibge_municipio', 'ano']);
            $table->index('ibge_municipio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fundeb_municipio_references');
    }
};
