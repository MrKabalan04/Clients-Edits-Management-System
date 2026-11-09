<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesHierarchy;
use Tests\TestCase;

class EditTest extends TestCase
{
    use CreatesHierarchy, RefreshDatabase;

    public function test_admin_can_list_edits(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $this->createEdit($project);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/edits');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create_an_edit(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/edits', [
                'title' => 'Homepage banner edit',
                'description' => 'Change the banner copy.',
                'priority' => 'high',
                'status' => 'pending',
                'price' => 150.75,
                'due_date' => '2026-11-15',
                'project_id' => $project->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Edit created successfully.')
            ->assertJsonPath('data.title', 'Homepage banner edit')
            ->assertJsonPath('data.description', 'Change the banner copy.')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.price', '150.75')
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.due_date', '2026-11-15T00:00:00.000000Z')
            ->assertJsonPath('data.project.id', $project->id);

        $this->assertDatabaseHas('edits', [
            'title' => 'Homepage banner edit',
            'priority' => 'high',
            'status' => 'pending',
            'price' => 150.75,
            'is_paid' => false,
            'project_id' => $project->id,
        ]);
    }

    public function test_admin_can_retrieve_one_edit(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $edit = $this->createEdit($project);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/edits/'.$edit->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Edit retrieved successfully.')
            ->assertJsonPath('data.id', $edit->id)
            ->assertJsonPath('data.title', 'Homepage banner edit')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.project.company.id', $company->id)
            ->assertJsonPath('data.project.company.client.id', $client->id);
    }

    public function test_admin_can_update_an_edit(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $edit = $this->createEdit($project);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->putJson('/api/edits/'.$edit->id, [
                'title' => 'Homepage banner edit v2',
                'description' => 'Change the banner copy again.',
                'priority' => 'medium',
                'status' => 'in_progress',
                'price' => 200,
                'is_paid' => true,
                'due_date' => '2026-12-01',
                'project_id' => $project->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Edit updated successfully.')
            ->assertJsonPath('data.title', 'Homepage banner edit v2')
            ->assertJsonPath('data.priority', 'medium')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.price', '200.00')
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.due_date', '2026-12-01T00:00:00.000000Z');

        $this->assertDatabaseHas('edits', [
            'id' => $edit->id,
            'title' => 'Homepage banner edit v2',
            'priority' => 'medium',
            'status' => 'in_progress',
            'price' => 200,
            'is_paid' => true,
        ]);
    }

    public function test_admin_can_partially_update_an_edit(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $edit = $this->createEdit($project);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->patchJson('/api/edits/'.$edit->id, [
                'title' => 'Homepage hero section edit',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Edit updated successfully.')
            ->assertJsonPath('data.title', 'Homepage hero section edit')
            ->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('edits', [
            'id' => $edit->id,
            'title' => 'Homepage hero section edit',
        ]);
    }

    public function test_admin_can_partially_update_the_is_paid_flag(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $edit = $this->createEdit($project);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->patchJson('/api/edits/'.$edit->id, [
                'is_paid' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_paid', false);
    }

    public function test_admin_can_delete_an_edit_without_attachments(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $edit = $this->createEdit($project);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->deleteJson('/api/edits/'.$edit->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Edit deleted successfully.');

        $this->assertDatabaseMissing('edits', ['id' => $edit->id]);
    }

    public function test_admin_cannot_delete_an_edit_that_has_attachments(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $edit = $this->createEdit($project);
        $this->createAttachment($edit);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->deleteJson('/api/edits/'.$edit->id);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Edit cannot be deleted because it has associated attachments.');

        $this->assertDatabaseHas('edits', ['id' => $edit->id]);
    }

    public function test_unauthenticated_users_cannot_access_edit_endpoints(): void
    {
        $this->getJson('/api/edits')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->postJson('/api/edits', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->getJson('/api/edits/1')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->putJson('/api/edits/1', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->patchJson('/api/edits/1', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->deleteJson('/api/edits/1')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_non_admin_users_cannot_access_edit_endpoints(): void
    {
        $user = User::query()->create([
            'name' => 'Project Manager',
            'email' => 'project-manager@example.com',
            'password' => 'project-manager-password',
            'role' => 'manager',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/edits')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->postJson('/api/edits', [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $edit = $this->createEdit($project);

        $this->withToken($token)
            ->getJson('/api/edits/'.$edit->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->putJson('/api/edits/'.$edit->id, [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->patchJson('/api/edits/'.$edit->id, [])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');

        $this->withToken($token)
            ->deleteJson('/api/edits/'.$edit->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden. Administrator access is required.');
    }

    public function test_invalid_edit_creation_data_returns_unprocessable_entity(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/edits', [
                'title' => '',
                'priority' => 'urgent',
                'status' => 'unknown',
                'price' => -10,
                'due_date' => '2026-13-45',
                'project_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors([
                'title',
                'priority',
                'status',
                'price',
                'due_date',
                'project_id',
            ]);
    }

    public function test_invalid_edit_update_data_returns_unprocessable_entity(): void
    {
        $admin = $this->createAdminUser();
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $edit = $this->createEdit($project);

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->patchJson('/api/edits/'.$edit->id, [
                'project_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_edit_cannot_be_created_with_a_nonexistent_project(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->postJson('/api/edits', [
                'title' => 'Homepage banner edit',
                'priority' => 'high',
                'status' => 'pending',
                'price' => 150.75,
                'project_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_requesting_a_nonexistent_edit_returns_not_found(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->withToken($admin->createToken('auth_token')->plainTextToken)
            ->getJson('/api/edits/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Edit not found.');
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
