<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_is_hashed_when_a_user_is_created(): void
    {
        $user = User::query()->create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => 'plain-text-password',
            'role' => 'admin',
        ]);

        $user->refresh();

        $this->assertNotSame('plain-text-password', $user->password);
        $this->assertTrue(Hash::check('plain-text-password', $user->password));
    }

    public function test_password_is_hidden_when_the_user_is_serialized(): void
    {
        $user = User::query()->create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => 'plain-text-password',
            'role' => 'admin',
        ]);

        $array = $user->toArray();
        $json = $user->toJson();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString($user->getAttributes()['password'], $json);
    }

    public function test_role_defaults_to_admin(): void
    {
        $user = User::query()->create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => 'plain-text-password',
        ]);

        $user->refresh();

        $this->assertSame('admin', $user->role);
    }

    public function test_role_can_be_set_for_future_roles(): void
    {
        $user = User::query()->create([
            'name' => 'Content Manager',
            'email' => 'manager@example.com',
            'password' => 'plain-text-password',
            'role' => 'manager',
        ]);

        $user->refresh();

        $this->assertSame('manager', $user->role);
    }

    public function test_name_email_password_and_role_are_mass_assignable(): void
    {
        $user = new User([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => 'plain-text-password',
            'role' => 'admin',
        ]);

        $user->save();
        $user->refresh();

        $this->assertSame('System Administrator', $user->name);
        $this->assertSame('admin@example.com', $user->email);
        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check('plain-text-password', $user->password));
    }

    public function test_timestamps_are_populated_automatically(): void
    {
        $user = User::query()->create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => 'plain-text-password',
            'role' => 'admin',
        ]);

        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
    }
}
