<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('privacy_policy_version_accepted', 32)->nullable()->after('is_active');
            $table->timestamp('privacy_policy_accepted_at')->nullable()->after('privacy_policy_version_accepted');
            $table->string('cookies_consent_version', 32)->nullable()->after('privacy_policy_accepted_at');
            $table->timestamp('cookies_consent_accepted_at')->nullable()->after('cookies_consent_version');
        });

        Schema::create('legal_consent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('consent_type', 32);
            $table->string('privacy_version', 32)->nullable();
            $table->string('cookies_version', 32)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('source', 32)->default('web');
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['user_id', 'accepted_at']);
            $table->index(['consent_type', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_consent_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'privacy_policy_version_accepted',
                'privacy_policy_accepted_at',
                'cookies_consent_version',
                'cookies_consent_accepted_at',
            ]);
        });
    }
};
