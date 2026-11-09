<?php

namespace Tests\Support;

use App\Enums\EditPriority;
use App\Enums\EditStatus;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Edit;
use App\Models\Project;

trait CreatesHierarchy
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createClient(array $attributes = []): Client
    {
        return Client::query()->create(array_merge([
            'name' => 'Acme Client',
            'phone' => '+961 1 234 567',
            'email' => 'client@example.com',
            'last_follow_up_date' => '2026-11-01',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createCompany(Client $client, array $attributes = []): Company
    {
        return Company::query()->create(array_merge([
            'name' => 'Acme Company',
            'client_id' => $client->getKey(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createProject(Company $company, array $attributes = []): Project
    {
        return Project::query()->create(array_merge([
            'name' => 'Acme Project',
            'company_id' => $company->getKey(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createEdit(Project $project, array $attributes = []): Edit
    {
        return Edit::query()->create(array_merge([
            'title' => 'Homepage banner edit',
            'description' => 'Change the banner copy.',
            'priority' => EditPriority::High,
            'status' => EditStatus::Pending,
            'price' => '150.75',
            'is_paid' => false,
            'due_date' => '2026-11-15',
            'project_id' => $project->getKey(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createAttachment(Edit $edit, array $attributes = []): Attachment
    {
        return Attachment::query()->create(array_merge([
            'file_name' => 'brief.pdf',
            'file_data' => 'YmVjbGFkZXYtYnJlZCk=',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'description' => 'Client brief.',
            'edit_id' => $edit->getKey(),
        ], $attributes));
    }

    /**
     * @return array{client: Client, company: Company, project: Project, edit: Edit, attachment: Attachment}
     */
    protected function createFullHierarchy(): array
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $edit = $this->createEdit($project);
        $attachment = $this->createAttachment($edit);

        return compact('client', 'company', 'project', 'edit', 'attachment');
    }
}
