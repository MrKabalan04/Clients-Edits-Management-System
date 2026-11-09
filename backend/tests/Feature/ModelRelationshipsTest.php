<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesHierarchy;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use CreatesHierarchy, RefreshDatabase;

    public function test_a_client_has_many_companies_and_each_company_belongs_to_that_client(): void
    {
        $client = $this->createClient(['name' => 'Parent Client']);
        $first = $this->createCompany($client, ['name' => 'First Company']);
        $second = $this->createCompany($client, ['name' => 'Second Company']);

        $companies = $client->companies()->orderBy('id')->get();

        $this->assertCount(2, $companies);
        $this->assertTrue($companies->contains($first));
        $this->assertTrue($companies->contains($second));

        $this->assertTrue($first->client->is($client));
        $this->assertTrue($second->client->is($client));
    }

    public function test_a_company_has_many_projects_and_each_project_belongs_to_that_company(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $first = $this->createProject($company, ['name' => 'First Project']);
        $second = $this->createProject($company, ['name' => 'Second Project']);

        $projects = $company->projects()->orderBy('id')->get();

        $this->assertCount(2, $projects);
        $this->assertTrue($projects->contains($first));
        $this->assertTrue($projects->contains($second));

        $this->assertTrue($first->company->is($company));
        $this->assertTrue($second->company->is($company));
    }

    public function test_a_project_has_many_edits_and_each_edit_belongs_to_that_project(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $first = $this->createEdit($project, ['title' => 'First Edit']);
        $second = $this->createEdit($project, ['title' => 'Second Edit']);

        $edits = $project->edits()->orderBy('id')->get();

        $this->assertCount(2, $edits);
        $this->assertTrue($edits->contains($first));
        $this->assertTrue($edits->contains($second));

        $this->assertTrue($first->project->is($project));
        $this->assertTrue($second->project->is($project));
    }

    public function test_an_edit_has_many_attachments_and_each_attachment_belongs_to_that_edit(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);
        $edit = $this->createEdit($project);
        $first = $this->createAttachment($edit, ['file_name' => 'first.pdf']);
        $second = $this->createAttachment($edit, ['file_name' => 'second.pdf']);

        $attachments = $edit->attachments()->orderBy('id')->get();

        $this->assertCount(2, $attachments);
        $this->assertTrue($attachments->contains($first));
        $this->assertTrue($attachments->contains($second));

        $this->assertTrue($first->edit->is($edit));
        $this->assertTrue($second->edit->is($edit));
    }

    public function test_the_full_client_company_project_edit_attachment_chain_is_connected(): void
    {
        $hierarchy = $this->createFullHierarchy();

        $this->assertTrue($hierarchy['client']->companies->contains($hierarchy['company']));
        $this->assertTrue($hierarchy['company']->client->is($hierarchy['client']));
        $this->assertTrue($hierarchy['company']->projects->contains($hierarchy['project']));
        $this->assertTrue($hierarchy['project']->company->is($hierarchy['company']));
        $this->assertTrue($hierarchy['project']->edits->contains($hierarchy['edit']));
        $this->assertTrue($hierarchy['edit']->project->is($hierarchy['project']));
        $this->assertTrue($hierarchy['edit']->attachments->contains($hierarchy['attachment']));
        $this->assertTrue($hierarchy['attachment']->edit->is($hierarchy['edit']));
    }

    public function test_relationships_can_be_eager_loaded(): void
    {
        $hierarchy = $this->createFullHierarchy();

        $loaded = \App\Models\Client::query()
            ->with('companies.projects.edits.attachments')
            ->findOrFail($hierarchy['client']->getKey());

        $attachment = $loaded->companies->first()
            ->projects->first()
            ->edits->first()
            ->attachments->first();

        $this->assertTrue($attachment->is($hierarchy['attachment']));
    }
}
