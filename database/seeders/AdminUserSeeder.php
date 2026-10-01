<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Create or promote the panel admin. Configure with ADMIN_EMAIL / ADMIN_PASSWORD.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => env('ADMIN_PASSWORD', 'password'),
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );
    }
}
