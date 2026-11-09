<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_password_and_keep_the_current_session(): void
    {
        $user = $this->createAdminUser();
        $origin = 'http://localhost:5173';
        $loginResponse = $this->withHeader('Origin', $origin)
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'admin-password',
            ]);
        $loginResponse->assertOk();
        $sessionCookie = $loginResponse->getCookie(config('session.cookie'), false);
        $this->assertNotNull($sessionCookie);

        $this->app['auth']->forgetGuards();
        $response = $this->withHeader('Origin', $origin)
            ->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
            ->putJson('/api/admin/password', [
                'current_password' => 'admin-password',
                'new_password' => 'new-admin-password',
                'new_password_confirmation' => 'new-admin-password',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password updated successfully.')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('user.password');

        $this->assertTrue(Hash::check('new-admin-password', $user->fresh()->password));
        $this->assertFalse(Hash::check('admin-password', $user->fresh()->password));

        $regeneratedCookie = $response->getCookie(config('session.cookie'), false);
        $this->assertNotNull($regeneratedCookie);
        $this->assertNotSame($sessionCookie->getValue(), $regeneratedCookie->getValue());

        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', $origin)
            ->withCookie($regeneratedCookie->getName(), $regeneratedCookie->getValue())
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Origin', $origin)
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'admin-password',
            ])->assertUnauthorized();

        $this->withHeader('Origin', $origin)
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'new-admin-password',
            ])->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_cannot_change_password_with_an_incorrect_current_password(): void
    {
        $this->actingAs($this->createAdminUser(), 'web')
            ->putJson('/api/admin/password', [
                'current_password' => 'incorrect-password',
                'new_password' => 'new-admin-password',
                'new_password_confirmation' => 'new-admin-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_admin_cannot_set_a_password_shorter_than_the_default_rule(): void
    {
        $this->actingAs($this->createAdminUser(), 'web')
            ->putJson('/api/admin/password', [
                'current_password' => 'admin-password',
                'new_password' => 'short',
                'new_password_confirmation' => 'short',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_admin_must_confirm_the_new_password(): void
    {
        $this->actingAs($this->createAdminUser(), 'web')
            ->putJson('/api/admin/password', [
                'current_password' => 'admin-password',
                'new_password' => 'new-admin-password',
                'new_password_confirmation' => 'different-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_password_change_requires_authentication(): void
    {
        $this->putJson('/api/admin/password', [
            'current_password' => 'admin-password',
            'new_password' => 'new-admin-password',
            'new_password_confirmation' => 'new-admin-password',
        ])->assertUnauthorized()
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
