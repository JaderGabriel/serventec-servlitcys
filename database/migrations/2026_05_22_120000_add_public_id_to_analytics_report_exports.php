<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_report_exports', function (Blueprint $table): void {
            $table->string('public_id', 32)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_report_exports', function (Blueprint $table): void {
            $table->dropColumn('public_id');
        });
    }
};
