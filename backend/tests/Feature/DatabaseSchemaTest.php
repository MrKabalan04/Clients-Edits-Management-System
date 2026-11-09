<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_six_application_tables_exist(): void
    {
        foreach (['users', 'clients', 'companies', 'projects', 'edits', 'attachments'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }
    }

    public function test_default_out_of_scope_tables_are_not_created(): void
    {
        foreach (['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'sessions'] as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "Default table [{$table}] should not be part of the database foundation."
            );
        }
    }

    public function test_users_table_has_the_required_columns_and_types(): void
    {
        $columns = $this->columns('users');

        $this->assertSame(
            ['id', 'name', 'email', 'password', 'role', 'created_at', 'updated_at'],
            array_keys($columns)
        );

        $this->assertSame('bigint', $columns['id']->data_type);
        $this->assertSame('NO', $columns['id']->is_nullable);

        $this->assertSame('varchar', $columns['name']->data_type);
        $this->assertSame(255, (int) $columns['name']->character_maximum_length);
        $this->assertSame('NO', $columns['name']->is_nullable);

        $this->assertSame('varchar', $columns['email']->data_type);
        $this->assertSame(255, (int) $columns['email']->character_maximum_length);
        $this->assertSame('NO', $columns['email']->is_nullable);

        $this->assertSame('varchar', $columns['password']->data_type);
        $this->assertSame(255, (int) $columns['password']->character_maximum_length);
        $this->assertSame('NO', $columns['password']->is_nullable);

        $this->assertSame('varchar', $columns['role']->data_type);
        $this->assertSame(50, (int) $columns['role']->character_maximum_length);
        $this->assertSame('NO', $columns['role']->is_nullable);
        $this->assertSame('admin', $columns['role']->column_default);

        $this->assertArrayNotHasKey('username', $columns);
        $this->assertArrayNotHasKey('email_verified_at', $columns);
        $this->assertArrayNotHasKey('remember_token', $columns);
    }

    public function test_clients_table_has_the_required_columns_and_nullability(): void
    {
        $columns = $this->columns('clients');

        $this->assertSame(
            ['id', 'name', 'phone', 'email', 'address', 'notes', 'last_follow_up_date', 'created_at', 'updated_at'],
            array_keys($columns)
        );

        $this->assertSame('varchar', $columns['name']->data_type);
        $this->assertSame(255, (int) $columns['name']->character_maximum_length);
        $this->assertSame('NO', $columns['name']->is_nullable);

        $this->assertSame('varchar', $columns['phone']->data_type);
        $this->assertSame(30, (int) $columns['phone']->character_maximum_length);
        $this->assertSame('NO', $columns['phone']->is_nullable);

        $this->assertSame('varchar', $columns['email']->data_type);
        $this->assertSame(255, (int) $columns['email']->character_maximum_length);
        $this->assertSame('YES', $columns['email']->is_nullable);

        $this->assertSame('text', $columns['address']->data_type);
        $this->assertSame('YES', $columns['address']->is_nullable);

        $this->assertSame('text', $columns['notes']->data_type);
        $this->assertSame('YES', $columns['notes']->is_nullable);

        $this->assertSame('date', $columns['last_follow_up_date']->data_type);
        $this->assertSame('YES', $columns['last_follow_up_date']->is_nullable);
    }

    public function test_companies_table_has_the_required_columns_and_foreign_key(): void
    {
        $columns = $this->columns('companies');

        $this->assertSame(
            ['id', 'name', 'client_id', 'created_at', 'updated_at'],
            array_keys($columns)
        );

        $this->assertSame('varchar', $columns['name']->data_type);
        $this->assertSame(255, (int) $columns['name']->character_maximum_length);
        $this->assertSame('NO', $columns['name']->is_nullable);

        $this->assertSame('bigint', $columns['client_id']->data_type);
        $this->assertSame('NO', $columns['client_id']->is_nullable);
    }

    public function test_projects_table_has_the_required_columns_and_foreign_key(): void
    {
        $columns = $this->columns('projects');

        $this->assertSame(
            ['id', 'name', 'company_id', 'created_at', 'updated_at'],
            array_keys($columns)
        );

        $this->assertSame('varchar', $columns['name']->data_type);
        $this->assertSame(255, (int) $columns['name']->character_maximum_length);
        $this->assertSame('NO', $columns['name']->is_nullable);

        $this->assertSame('bigint', $columns['company_id']->data_type);
        $this->assertSame('NO', $columns['company_id']->is_nullable);
    }

    public function test_edits_table_has_the_required_columns_and_types(): void
    {
        $columns = $this->columns('edits');

        $this->assertSame(
            ['id', 'title', 'description', 'priority', 'status', 'price', 'is_paid', 'due_date', 'project_id', 'created_at', 'updated_at'],
            array_keys($columns)
        );

        $this->assertSame('varchar', $columns['title']->data_type);
        $this->assertSame(255, (int) $columns['title']->character_maximum_length);
        $this->assertSame('NO', $columns['title']->is_nullable);

        $this->assertSame('text', $columns['description']->data_type);
        $this->assertSame('YES', $columns['description']->is_nullable);

        $this->assertSame('varchar', $columns['priority']->data_type);
        $this->assertSame(20, (int) $columns['priority']->character_maximum_length);
        $this->assertSame('NO', $columns['priority']->is_nullable);

        $this->assertSame('varchar', $columns['status']->data_type);
        $this->assertSame(30, (int) $columns['status']->character_maximum_length);
        $this->assertSame('NO', $columns['status']->is_nullable);

        $this->assertSame('decimal', $columns['price']->data_type);
        $this->assertSame('decimal(10,2)', $columns['price']->column_type);
        $this->assertSame('YES', $columns['price']->is_nullable);

        $this->assertSame('tinyint', $columns['is_paid']->data_type);
        $this->assertSame('tinyint(1)', $columns['is_paid']->column_type);
        $this->assertSame('NO', $columns['is_paid']->is_nullable);
        $this->assertSame('0', $columns['is_paid']->column_default);

        $this->assertSame('date', $columns['due_date']->data_type);
        $this->assertSame('YES', $columns['due_date']->is_nullable);

        $this->assertSame('bigint', $columns['project_id']->data_type);
        $this->assertSame('NO', $columns['project_id']->is_nullable);
    }

    public function test_attachments_table_has_the_required_columns_and_types(): void
    {
        $columns = $this->columns('attachments');

        $this->assertSame(
            ['id', 'file_name', 'file_data', 'mime_type', 'file_size', 'description', 'edit_id', 'created_at', 'updated_at'],
            array_keys($columns)
        );

        $this->assertSame('varchar', $columns['file_name']->data_type);
        $this->assertSame(255, (int) $columns['file_name']->character_maximum_length);
        $this->assertSame('NO', $columns['file_name']->is_nullable);

        $this->assertSame('longtext', $columns['file_data']->data_type);
        $this->assertSame('NO', $columns['file_data']->is_nullable);

        $this->assertSame('varchar', $columns['mime_type']->data_type);
        $this->assertSame(255, (int) $columns['mime_type']->character_maximum_length);
        $this->assertSame('NO', $columns['mime_type']->is_nullable);

        $this->assertSame('bigint', $columns['file_size']->data_type);
        $this->assertSame('NO', $columns['file_size']->is_nullable);

        $this->assertSame('text', $columns['description']->data_type);
        $this->assertSame('YES', $columns['description']->is_nullable);

        $this->assertSame('bigint', $columns['edit_id']->data_type);
        $this->assertSame('NO', $columns['edit_id']->is_nullable);
    }

    public function test_business_foreign_keys_reference_the_correct_tables_with_restrict_delete(): void
    {
        $expected = [
            'companies' => ['client_id' => ['clients', 'id']],
            'projects' => ['company_id' => ['companies', 'id']],
            'edits' => ['project_id' => ['projects', 'id']],
            'attachments' => ['edit_id' => ['edits', 'id']],
        ];

        foreach ($expected as $table => $foreignKeys) {
            $actual = collect($this->foreignKeys($table))->keyBy('column_name');

            foreach ($foreignKeys as $column => [$referencedTable, $referencedColumn]) {
                $this->assertTrue(
                    $actual->has($column),
                    "Expected foreign key on [{$table}.{$column}]."
                );

                $foreignKey = $actual->get($column);

                $this->assertSame($referencedTable, $foreignKey->referenced_table_name);
                $this->assertSame($referencedColumn, $foreignKey->referenced_column_name);
                $this->assertSame('RESTRICT', $foreignKey->delete_rule);
            }
        }
    }

    public function test_each_business_foreign_key_column_is_indexed(): void
    {
        foreach ([
            'companies' => 'client_id',
            'projects' => 'company_id',
            'edits' => 'project_id',
            'attachments' => 'edit_id',
        ] as $table => $column) {
            $indexes = DB::select(
                'SELECT INDEX_NAME AS index_name, COLUMN_NAME AS column_name, NON_UNIQUE AS non_unique
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [DB::getDatabaseName(), $table, $column]
            );

            $this->assertNotEmpty(
                $indexes,
                "Expected an index covering [{$table}.{$column}]."
            );
        }
    }

    public function test_email_has_a_unique_index_and_duplicates_are_rejected(): void
    {
        $indexes = DB::select(
            'SELECT INDEX_NAME AS index_name, NON_UNIQUE AS non_unique
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), 'users', 'email']
        );

        $this->assertNotEmpty($indexes);

        $hasUniqueIndex = collect($indexes)->contains(fn (object $index): bool => (int) $index->non_unique === 0);
        $this->assertTrue($hasUniqueIndex, 'Expected a unique index on users.email.');

        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ]);

        $this->expectException(QueryException::class);

        User::query()->create([
            'name' => 'Admin Two',
            'email' => 'admin@example.com',
            'password' => 'another-secret',
        ]);
    }

    public function test_database_rejects_a_company_with_a_missing_client(): void
    {
        $this->expectException(QueryException::class);

        Company::query()->create([
            'name' => 'Orphan Company',
            'client_id' => 999999,
        ]);
    }

    /**
     * @return array<string, object>
     */
    private function columns(string $table): array
    {
        $columns = DB::select(
            'SELECT COLUMN_NAME AS name,
                    DATA_TYPE AS data_type,
                    COLUMN_TYPE AS column_type,
                    IS_NULLABLE AS is_nullable,
                    COLUMN_DEFAULT AS column_default,
                    CHARACTER_MAXIMUM_LENGTH AS character_maximum_length
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [DB::getDatabaseName(), $table]
        );

        return collect($columns)->keyBy('name')->all();
    }

    /**
     * @return array<int, object>
     */
    private function foreignKeys(string $table): array
    {
        return DB::select(
            'SELECT kcu.COLUMN_NAME AS column_name,
                    kcu.REFERENCED_TABLE_NAME AS referenced_table_name,
                    kcu.REFERENCED_COLUMN_NAME AS referenced_column_name,
                    rc.DELETE_RULE AS delete_rule,
                    rc.UPDATE_RULE AS update_rule
             FROM information_schema.KEY_COLUMN_USAGE kcu
             INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                 ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                 AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
             WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ?
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL',
            [DB::getDatabaseName(), $table]
        );
    }
}
