<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_receive_a_token(): void
    {
        $user = $this->createAdminUser();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'admin-password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ]);

        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Login successful.');
        $response->assertJsonPath('user.id', $user->id);
        $response->assertJsonPath('user.name', $user->name);
        $response->assertJsonPath('user.email', $user->email);
        $response->assertJsonPath('user.role', 'admin');
        $this->assertNotEmpty($response->json('token'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_login_with_incorrect_password_returns_unauthorized_response(): void
    {
        $this->createAdminUser();

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_with_unknown_email_returns_unauthorized_response(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_validation_fails_returns_unprocessable_entity(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_non_admin_user_cannot_login(): void
    {
        $user = User::query()->create([
            'name' => 'Content Manager',
            'email' => 'manager@example.com',
            'password' => 'manager-password',
            'role' => 'manager',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'manager-password',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_admin_route_without_token_returns_unauthorized(): void
    {
        $this->getJson('/api/admin/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_access_protected_admin_route_with_valid_token(): void
    {
        $user = $this->createAdminUser();

        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'admin-password',
        ]);

        $loginResponse->assertStatus(200);

        $response = $this->withToken($loginResponse->json('token'))
            ->getJson('/api/admin/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->createAdminUser();

        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'admin-password',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');

        $logoutResponse = $this->withToken($token)->postJson('/api/logout');

        $logoutResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'token' => $token,
        ]);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/admin/me')
            ->assertStatus(401);
    }

    public function test_authenticated_non_admin_user_receives_403_from_admin_route(): void
    {
        $user = User::query()->create([
            'name' => 'Content Manager',
            'email' => 'manager@example.com',
            'password' => 'manager-password',
            'role' => 'manager',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/me')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    private function createAdminUser(): User
    {
        return User::query()->create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => 'admin-password',
            'role' => 'admin',
        ]);
    }
}
