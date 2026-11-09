<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_creates_an_admin_with_a_hashed_password(): void
    {
        config([
            'admin.name' => 'System Administrator',
            'admin.email' => 'admin@example.com',
            'admin.password' => 'admin-password',
        ]);

        $this->seed(\Database\Seeders\AdminSeeder::class);

        $user = User::query()->where('email', 'admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('System Administrator', $user->name);
        $this->assertSame('admin@example.com', $user->email);
        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check('admin-password', $user->password));
    }

    public function test_admin_seeder_is_safe_to_run_more_than_once(): void
    {
        config([
            'admin.name' => 'System Administrator',
            'admin.email' => 'admin@example.com',
            'admin.password' => 'admin-password',
        ]);

        $this->seed(\Database\Seeders\AdminSeeder::class);
        $this->seed(\Database\Seeders\AdminSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'admin@example.com')->count());
    }
}
