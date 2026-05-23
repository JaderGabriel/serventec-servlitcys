<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acelera histórico de logins (action=login) por utilizador.
     */
    public function up(): void
    {
        Schema::table('admin_user_logs', function (Blueprint $table) {
            $table->index(['action', 'subject_user_id', 'created_at'], 'admin_user_logs_action_subject_created');
        });
    }

    public function down(): void
    {
        Schema::table('admin_user_logs', function (Blueprint $table) {
            $table->dropIndex('admin_user_logs_action_subject_created');
        });
    }
};
