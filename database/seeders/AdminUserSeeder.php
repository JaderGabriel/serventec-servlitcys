<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) 'jadergabriel8@gmail.com';
        $plainPassword = (string) '123456789';
        $username = (string) 'jadergabriel';

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador',
                'username' => $username,
                'password' => $plainPassword,
                'birth_date' => env('ADMIN_BIRTH_DATE', '1993-08-07'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
