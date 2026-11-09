<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Create or update the initial administrator account.
     */
    public function run(): void
    {
        $name = config('admin.name');
        $email = config('admin.email');
        $password = config('admin.password');

        if (blank($name) || blank($email) || blank($password)) {
            throw new RuntimeException(
                'ADMIN_NAME, ADMIN_EMAIL, and ADMIN_PASSWORD must be configured before seeding the administrator.'
            );
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'role' => 'admin',
            ],
        );
    }
}
