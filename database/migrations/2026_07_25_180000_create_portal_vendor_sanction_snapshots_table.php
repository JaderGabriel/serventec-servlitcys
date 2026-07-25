<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_vendor_sanction_snapshots', function (Blueprint $table) {
            $table->id();
            /** ceis | cnep | cepim */
            $table->string('fonte', 16);
            $table->string('cnpj', 14)->index();
            $table->string('external_id', 64);
            $table->string('nome', 180)->nullable();
            $table->string('categoria', 180)->nullable();
            $table->string('data_inicio', 20)->nullable();
            $table->string('data_fim', 20)->nullable();
            $table->string('orgao', 180)->nullable();
            $table->string('vendor_label', 120)->nullable();
            $table->json('payload')->nullable();
            $table->string('fonte_api', 40)->default('portal_transparencia');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['fonte', 'external_id'], 'portal_sanction_fonte_id_unique');
            $table->index(['cnpj', 'fonte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_vendor_sanction_snapshots');
    }
};
