<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesHierarchy;
use Tests\TestCase;

class DeletionRestrictionsTest extends TestCase
{
    use CreatesHierarchy, RefreshDatabase;

    public function test_a_client_with_companies_cannot_be_deleted(): void
    {
        $hierarchy = $this->createFullHierarchy();

        $this->assertDeletionIsRestricted($hierarchy['client'], 'clients');
    }

    public function test_a_company_with_projects_cannot_be_deleted(): void
    {
        $hierarchy = $this->createFullHierarchy();

        $this->assertDeletionIsRestricted($hierarchy['company'], 'companies');
    }

    public function test_a_project_with_edits_cannot_be_deleted(): void
    {
        $hierarchy = $this->createFullHierarchy();

        $this->assertDeletionIsRestricted($hierarchy['project'], 'projects');
    }

    public function test_an_edit_with_attachments_cannot_be_deleted(): void
    {
        $hierarchy = $this->createFullHierarchy();

        $this->assertDeletionIsRestricted($hierarchy['edit'], 'edits');
    }

    public function test_leaf_records_can_be_deleted_once_they_have_no_children(): void
    {
        $hierarchy = $this->createFullHierarchy();

        $hierarchy['attachment']->delete();
        $this->assertDatabaseMissing('attachments', ['id' => $hierarchy['attachment']->getKey()]);

        $hierarchy['edit']->delete();
        $this->assertDatabaseMissing('edits', ['id' => $hierarchy['edit']->getKey()]);

        $hierarchy['project']->delete();
        $this->assertDatabaseMissing('projects', ['id' => $hierarchy['project']->getKey()]);

        $hierarchy['company']->delete();
        $this->assertDatabaseMissing('companies', ['id' => $hierarchy['company']->getKey()]);

        $hierarchy['client']->delete();
        $this->assertDatabaseMissing('clients', ['id' => $hierarchy['client']->getKey()]);
    }

    private function assertDeletionIsRestricted(Model $model, string $table): void
    {
        try {
            $model->delete();
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'foreign key constraint',
                strtolower($exception->getMessage())
            );

            $this->assertDatabaseHas($table, ['id' => $model->getKey()]);

            return;
        }

        $this->fail("Deleting a [{$table}] record with dependent rows should have been restricted.");
    }
}
