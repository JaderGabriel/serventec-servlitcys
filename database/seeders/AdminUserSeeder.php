<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) '';
        $plainPassword = (string) '';
        $username = (string) '';

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador',
                'username' => $username,
                'password' => $plainPassword,
                'birth_date' => env('ADMIN_BIRTH_DATE', '1990-31-12'),
                'cpf' => env('ADMIN_CPF', ''),
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ]
        );
    }
}
