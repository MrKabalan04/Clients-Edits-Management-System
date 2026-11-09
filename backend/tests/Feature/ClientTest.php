<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesHierarchy;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use CreatesHierarchy, RefreshDatabase;

    public function test_admin_can_list_clients(): void
    {
        $admin = $this->createAdminUser();

        $this->createClient([
            'name' => 'Acme Client',
            'phone' => '+1 555 0100',
            'email' => 'acme@example.com',
        ]);

        $this->createClient([
            'name' => 'Globex Client',
            'phone' => '+1 555 0200',
            'email' => 'globex@example.com',
        ]);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/clients');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_a_client(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/clients', [
                'name' => 'Acme Client',
                'phone' => '+1 555 0100',
                'email' => 'acme@example.com',
                'last_follow_up_date' => '2026-11-12',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Client created successfully.')
            ->assertJsonPath('data.name', 'Acme Client')
            ->assertJsonPath('data.phone', '+1 555 0100');

        $this->assertDatabaseHas('clients', [
            'name' => 'Acme Client',
            'phone' => '+1 555 0100',
            'email' => 'acme@example.com',
        ]);
    }

    public function test_admin_can_view_a_single_client(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/clients/'.$client->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Client retrieved successfully.')
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.name', $client->name);
    }

    public function test_admin_can_update_a_client(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient([
            'name' => 'Acme Client',
            'phone' => '+1 555 0100',
            'email' => 'acme@example.com',
            'last_follow_up_date' => '2026-11-12',
        ]);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->putJson('/api/clients/'.$client->id, [
                'name' => 'Acme Corporation',
                'phone' => '+1 555 0199',
                'email' => 'acme-corp@example.com',
                'last_follow_up_date' => '2026-11-20',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Client updated successfully.')
            ->assertJsonPath('data.name', 'Acme Corporation');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Acme Corporation',
            'phone' => '+1 555 0199',
        ]);
    }

    public function test_admin_can_partially_update_a_client(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient([
            'name' => 'Acme Client',
            'phone' => '+1 555 0100',
        ]);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->patchJson('/api/clients/'.$client->id, [
                'name' => 'Acme Corporation',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Client updated successfully.')
            ->assertJsonPath('data.name', 'Acme Corporation')
            ->assertJsonPath('data.phone', '+1 555 0100');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Acme Corporation',
        ]);
    }

    public function test_admin_can_delete_a_client_without_associated_companies(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->deleteJson('/api/clients/'.$client->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Client deleted successfully.');

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_admin_cannot_delete_a_client_that_has_companies(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $this->createCompany($client);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->deleteJson('/api/clients/'.$client->id);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Client cannot be deleted because it has associated companies.');

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_unauthenticated_users_cannot_access_client_endpoints(): void
    {
        $this->getJson('/api/clients')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->postJson('/api/clients', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->getJson('/api/clients/1')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->putJson('/api/clients/1', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->patchJson('/api/clients/1', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->deleteJson('/api/clients/1')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_non_admin_users_cannot_access_client_endpoints(): void
    {
        $user = User::query()->create([
            'name' => 'Client Manager',
            'email' => 'manager@example.com',
            'password' => 'manager-password',
            'role' => 'manager',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/clients')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->postJson('/api/clients', [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $client = $this->createClient();

        $this->withToken($token)
            ->getJson('/api/clients/'.$client->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->putJson('/api/clients/'.$client->id, [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->patchJson('/api/clients/'.$client->id, [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->deleteJson('/api/clients/'.$client->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');
    }

    public function test_invalid_client_creation_data_returns_unprocessable_entity(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/clients', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['name', 'phone']);
    }

    public function test_invalid_client_update_data_returns_unprocessable_entity(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->patchJson('/api/clients/'.$client->id, [
                'email' => 'not-an-email',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['email']);
    }

    public function test_requesting_a_nonexistent_client_returns_not_found(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/clients/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Client not found.');
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
