<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_a_stateful_session_cookie(): void
    {
        $user = $this->createAdminUser();

        $response = $this->spaRequest()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'admin-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonMissingPath('token');

        $sessionCookie = $response->getCookie(config('session.cookie'), false);

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', $sessionCookie->getSameSite());
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $csrfCookie = $response->getCookie('XSRF-TOKEN', false);
        $this->assertNotNull($csrfCookie);
        $this->assertFalse($csrfCookie->isHttpOnly());
    }

    public function test_login_with_incorrect_password_returns_unauthorized_response(): void
    {
        $this->createAdminUser();

        $this->spaRequest()->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_with_unknown_email_returns_unauthorized_response(): void
    {
        $this->spaRequest()->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'password',
        ])->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_validation_fails_returns_unprocessable_entity(): void
    {
        $this->spaRequest()->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_requires_a_stateful_browser_session(): void
    {
        $user = $this->createAdminUser();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'admin-password',
        ])->assertStatus(419)
            ->assertJsonPath('success', false);
    }

    public function test_non_admin_user_cannot_login(): void
    {
        $user = User::query()->create([
            'name' => 'Content Manager',
            'email' => 'manager@example.com',
            'password' => 'manager-password',
            'role' => 'manager',
        ]);

        $this->spaRequest()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'manager-password',
        ])->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_admin_can_access_admin_me_using_the_session_cookie(): void
    {
        $user = $this->createAdminUser();

        $loginResponse = $this->spaRequest()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'admin-password',
        ]);
        $loginResponse->assertOk();

        $this->app['auth']->forgetGuards();
        $sessionCookie = $loginResponse->getCookie(config('session.cookie'), false);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_logout_invalidates_the_server_session(): void
    {
        $user = $this->createAdminUser();

        $loginResponse = $this->spaRequest()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'admin-password',
        ]);
        $loginResponse->assertOk();
        $sessionCookie = $loginResponse->getCookie(config('session.cookie'), false);

        $this->app['auth']->forgetGuards();
        $logoutResponse = $this->withHeader('Origin', 'http://localhost:5173')
            ->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
            ->postJson('/api/logout');

        $logoutResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->app['auth']->forgetGuards();
        $logoutCookie = $logoutResponse->getCookie(config('session.cookie'), false);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->withCookie($logoutCookie->getName(), $logoutCookie->getValue())
            ->getJson('/api/admin/me')
            ->assertUnauthorized();
    }

    public function test_admin_route_without_a_session_returns_unauthorized(): void
    {
        $this->spaRequest()->getJson('/api/admin/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_non_admin_user_receives_forbidden_from_admin_route(): void
    {
        $user = User::query()->create([
            'name' => 'Content Manager',
            'email' => 'manager@example.com',
            'password' => 'manager-password',
            'role' => 'manager',
        ]);

        $this->actingAs($user, 'web')
            ->getJson('/api/admin/me')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_csrf_cookie_endpoint_sets_the_xsrf_cookie(): void
    {
        $response = $this->withHeader('Origin', 'http://localhost:5173')
            ->get('/sanctum/csrf-cookie');

        $response->assertNoContent();

        $csrfCookie = $response->getCookie('XSRF-TOKEN', false);
        $this->assertNotNull($csrfCookie);
        $this->assertFalse($csrfCookie->isHttpOnly());
    }

    public function test_session_cookie_honors_the_secure_cookie_setting(): void
    {
        config(['session.secure' => true]);

        $response = $this->get('/sanctum/csrf-cookie');
        $sessionCookie = $response->getCookie(config('session.cookie'), false);

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
    }

    public function test_stateful_cors_allows_configured_frontend_credentials(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-xsrf-token',
        ])->options('/api/login')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    private function spaRequest(): static
    {
        return $this->withHeader('Origin', 'http://localhost:5173');
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
