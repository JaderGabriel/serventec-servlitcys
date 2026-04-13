<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->date('birth_date')->nullable()->after('email');
        });

        $users = DB::table('users')->select('id', 'email')->get();
        foreach ($users as $row) {
            $local = Str::before((string) $row->email, '@') ?: 'user';
            $base = Str::slug($local) ?: 'user';
            $username = $base.'_'.$row->id;
            DB::table('users')->where('id', $row->id)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'birth_date']);
        });
    }
};
