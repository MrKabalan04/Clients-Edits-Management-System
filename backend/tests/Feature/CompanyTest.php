<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesHierarchy;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use CreatesHierarchy, RefreshDatabase;

    public function test_admin_can_list_companies(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $this->createEdit($project);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/companies');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.projects_count', 1)
            ->assertJsonPath('data.0.edits_count', 1)
            ->assertJsonPath('data.0.client.name', $client->name);
    }

    public function test_admin_can_create_a_company(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/companies', [
                'name' => 'Acme Company',
                'client_id' => $client->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Company created successfully.')
            ->assertJsonPath('data.name', 'Acme Company')
            ->assertJsonPath('data.client_id', $client->id);

        $this->assertDatabaseHas('companies', [
            'name' => 'Acme Company',
            'client_id' => $client->id,
        ]);
    }

    public function test_admin_can_retrieve_one_company(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/companies/'.$company->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Company retrieved successfully.')
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.name', $company->name)
            ->assertJsonPath('data.client.id', $client->id);
    }

    public function test_admin_can_retrieve_company_details_and_calculated_statistics(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $firstProject = $this->createProject($company);
        $secondProject = $this->createProject($company, ['name' => 'Second project']);
        $this->createEdit($firstProject);
        $this->createEdit($firstProject, [
            'title' => 'Completed edit',
            'status' => 'done',
        ]);
        $this->createEdit($secondProject, [
            'title' => 'Another pending edit',
        ]);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/companies/'.$company->id.'/details');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.statistics.projects', 2)
            ->assertJsonPath('data.statistics.edits', 3)
            ->assertJsonPath('data.statistics.pending_edits', 2)
            ->assertJsonPath('data.statistics.completed_edits', 1)
            ->assertJsonCount(2, 'data.projects')
            ->assertJsonPath('data.projects.0.edits_count', 2)
            ->assertJsonPath('data.projects.0.pending_edits_count', 1)
            ->assertJsonPath('data.projects.0.completed_edits_count', 1)
            ->assertJsonPath('data.recent_edits.0.title', 'Another pending edit');
    }

    public function test_company_details_returns_empty_statistics_without_related_work(): void
    {
        $admin = $this->createAdminUser();
        $company = $this->createCompany($this->createClient());

        $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/companies/'.$company->id.'/details')
            ->assertOk()
            ->assertJsonPath('data.statistics.projects', 0)
            ->assertJsonPath('data.statistics.edits', 0)
            ->assertJsonPath('data.statistics.pending_edits', 0)
            ->assertJsonPath('data.statistics.completed_edits', 0)
            ->assertJsonCount(0, 'data.projects')
            ->assertJsonCount(0, 'data.recent_edits');
    }

    public function test_requesting_details_for_a_missing_company_returns_not_found(): void
    {
        $admin = $this->createAdminUser();

        $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/companies/999999/details')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Company not found.');
    }

    public function test_admin_can_update_a_company(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->putJson('/api/companies/'.$company->id, [
                'name' => 'Acme Corporation',
                'client_id' => $client->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Company updated successfully.')
            ->assertJsonPath('data.name', 'Acme Corporation');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Acme Corporation',
        ]);
    }

    public function test_admin_can_partially_update_a_company(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->patchJson('/api/companies/'.$company->id, [
                'name' => 'Acme Holdings',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Company updated successfully.')
            ->assertJsonPath('data.name', 'Acme Holdings');
    }

    public function test_admin_can_delete_a_company_without_projects(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->deleteJson('/api/companies/'.$company->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Company deleted successfully.');

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    public function test_admin_cannot_delete_a_company_that_has_projects(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $this->createProject($company);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->deleteJson('/api/companies/'.$company->id);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Company cannot be deleted because it has associated projects.');

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_unauthenticated_users_cannot_access_company_endpoints(): void
    {
        $this->getJson('/api/companies')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->postJson('/api/companies', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->getJson('/api/companies/1')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->getJson('/api/companies/1/details')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->putJson('/api/companies/1', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->patchJson('/api/companies/1', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->deleteJson('/api/companies/1')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_non_admin_users_cannot_access_company_endpoints(): void
    {
        $user = User::query()->create([
            'name' => 'Client Manager',
            'email' => 'manager@example.com',
            'password' => 'manager-password',
            'role' => 'manager',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/companies')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->postJson('/api/companies', [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $client = $this->createClient();
        $company = $this->createCompany($client);

        $this->withToken($token)
            ->getJson('/api/companies/'.$company->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->getJson('/api/companies/'.$company->id.'/details')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->putJson('/api/companies/'.$company->id, [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->patchJson('/api/companies/'.$company->id, [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->deleteJson('/api/companies/'.$company->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');
    }

    public function test_invalid_company_creation_data_returns_unprocessable_entity(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/companies', [
                'client_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['name', 'client_id']);
    }

    public function test_invalid_company_update_data_returns_unprocessable_entity(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->patchJson('/api/companies/'.$company->id, [
                'client_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_company_cannot_be_created_with_a_nonexistent_client(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/companies', [
                'name' => 'Acme Company',
                'client_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_requesting_a_nonexistent_company_returns_not_found(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/companies/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Company not found.');
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
