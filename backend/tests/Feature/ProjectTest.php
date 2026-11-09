<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesHierarchy;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use CreatesHierarchy, RefreshDatabase;

    public function test_admin_can_list_projects(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $this->createProject($company);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create_a_project(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/projects', [
                'name' => 'Acme Project',
                'company_id' => $company->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project created successfully.')
            ->assertJsonPath('data.name', 'Acme Project')
            ->assertJsonPath('data.company_id', $company->id);

        $this->assertDatabaseHas('projects', [
            'name' => 'Acme Project',
            'company_id' => $company->id,
        ]);
    }

    public function test_admin_can_retrieve_one_project(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/projects/'.$project->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project retrieved successfully.')
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.name', $project->name)
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.company.client.id', $client->id);
    }

    public function test_admin_can_update_a_project(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->putJson('/api/projects/'.$project->id, [
                'name' => 'Acme Corporation',
                'company_id' => $company->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project updated successfully.')
            ->assertJsonPath('data.name', 'Acme Corporation');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Acme Corporation',
        ]);
    }

    public function test_admin_can_partially_update_a_project(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->patchJson('/api/projects/'.$project->id, [
                'name' => 'Acme Holdings',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project updated successfully.')
            ->assertJsonPath('data.name', 'Acme Holdings');
    }

    public function test_admin_can_delete_a_project_without_edits(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->deleteJson('/api/projects/'.$project->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project deleted successfully.');

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_admin_cannot_delete_a_project_that_has_edits(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $this->createEdit($project);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->deleteJson('/api/projects/'.$project->id);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Project cannot be deleted because it has associated edits.');

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_unauthenticated_users_cannot_access_project_endpoints(): void
    {
        $this->getJson('/api/projects')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->postJson('/api/projects', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->getJson('/api/projects/1')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->putJson('/api/projects/1', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->patchJson('/api/projects/1', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->deleteJson('/api/projects/1')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_non_admin_users_cannot_access_project_endpoints(): void
    {
        $user = User::query()->create([
            'name' => 'Project Manager',
            'email' => 'project-manager@example.com',
            'password' => 'project-manager-password',
            'role' => 'manager',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/projects')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->postJson('/api/projects', [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $this->withToken($token)
            ->getJson('/api/projects/'.$project->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->putJson('/api/projects/'.$project->id, [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->patchJson('/api/projects/'.$project->id, [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->deleteJson('/api/projects/'.$project->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');
    }

    public function test_invalid_project_creation_data_returns_unprocessable_entity(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/projects', [
                'client_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['name', 'company_id']);
    }

    public function test_invalid_project_update_data_returns_unprocessable_entity(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->patchJson('/api/projects/'.$project->id, [
                'company_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_project_cannot_be_created_with_a_nonexistent_company(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/projects', [
                'name' => 'Acme Project',
                'company_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_requesting_a_nonexistent_project_returns_not_found(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/projects/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Project not found.');
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
