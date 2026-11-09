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

    public function test_client_directory_includes_relationship_counts(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $this->createEdit($project);

        $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/clients')
            ->assertOk()
            ->assertJsonPath('data.0.companies_count', 1)
            ->assertJsonPath('data.0.projects_count', 1)
            ->assertJsonPath('data.0.edits_count', 1);
    }

    public function test_admin_can_create_a_client(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/clients', [
                'name' => 'Acme Client',
                'phone' => '+1 555 0100',
                'email' => 'acme@example.com',
                'address' => '123 Example Street',
                'notes' => 'Prefers email contact.',
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
            'address' => '123 Example Street',
            'notes' => 'Prefers email contact.',
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
                'address' => '500 New Address',
                'notes' => 'Updated notes.',
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
            'address' => '500 New Address',
            'notes' => 'Updated notes.',
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

    public function test_admin_can_retrieve_a_clients_details_and_accurate_relationship_statistics(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient([
            'address' => '45 Cedar Lane',
            'notes' => 'Call after 10am.',
        ]);
        $company = $this->createCompany($client);
        $secondCompany = $this->createCompany($client, ['name' => 'Second Company']);
        $project = $this->createProject($company);
        $secondProject = $this->createProject($company, ['name' => 'Second Project']);
        $this->createProject($secondCompany, ['name' => 'Third Project']);

        $pendingEdit = $this->createEdit($project, ['status' => 'pending']);
        $this->createAttachment($pendingEdit);
        $this->createEdit($project, ['title' => 'Completed edit', 'status' => 'done']);
        $this->createEdit($secondProject, ['title' => 'In progress edit', 'status' => 'in_progress']);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/clients/'.$client->id.'/details');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.client.address', '45 Cedar Lane')
            ->assertJsonPath('data.client.notes', 'Call after 10am.')
            ->assertJsonPath('data.statistics.companies', 2)
            ->assertJsonPath('data.statistics.projects', 3)
            ->assertJsonPath('data.statistics.edits', 3)
            ->assertJsonPath('data.statistics.pending_edits', 1)
            ->assertJsonPath('data.statistics.completed_edits', 1)
            ->assertJsonCount(2, 'data.companies')
            ->assertJsonCount(3, 'data.projects')
            ->assertJsonCount(3, 'data.recent_edits')
            ->assertJsonPath('data.companies.0.projects_count', 2)
            ->assertJsonPath('data.companies.0.edits_count', 3)
            ->assertJsonPath('data.projects.0.company.id', $company->id)
            ->assertJsonFragment(['file_name' => 'brief.pdf'])
            ->assertDontSee('file_data');
    }

    public function test_client_details_return_empty_relationships_and_zero_statistics(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();

        $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/clients/'.$client->id.'/details')
            ->assertOk()
            ->assertJsonPath('data.statistics.companies', 0)
            ->assertJsonPath('data.statistics.projects', 0)
            ->assertJsonPath('data.statistics.edits', 0)
            ->assertJsonPath('data.statistics.pending_edits', 0)
            ->assertJsonPath('data.statistics.completed_edits', 0)
            ->assertJsonCount(0, 'data.companies')
            ->assertJsonCount(0, 'data.projects')
            ->assertJsonCount(0, 'data.recent_edits');
    }

    public function test_missing_client_details_return_not_found(): void
    {
        $admin = $this->createAdminUser();

        $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/clients/999/details')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_client_creation_and_update_validate_address_and_notes(): void
    {
        $admin = $this->createAdminUser();
        $token = $admin->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/clients', [
                'name' => 'Bad Client',
                'phone' => '+1 555 0100',
                'address' => ['not', 'a', 'string'],
                'notes' => ['not', 'a', 'string'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address', 'notes']);

        $client = $this->createClient();
        $this->withToken($token)
            ->patchJson('/api/clients/'.$client->id, [
                'address' => ['invalid'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address']);

        $this->withToken($token)
            ->patchJson('/api/clients/'.$client->id, [
                'phone' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
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

        $this->getJson('/api/clients/1/details')
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
