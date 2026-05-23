<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('contact_name', 255)->nullable()->after('ibge_municipio');
            $table->string('contact_phone', 32)->nullable()->after('contact_name');
            $table->string('contact_whatsapp', 32)->nullable()->after('contact_phone');
            $table->string('contact_email', 255)->nullable()->after('contact_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn([
                'contact_name',
                'contact_phone',
                'contact_whatsapp',
                'contact_email',
            ]);
        });
    }
};
