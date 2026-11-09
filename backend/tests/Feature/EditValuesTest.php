<?php

namespace Tests\Feature;

use App\Enums\EditPriority;
use App\Enums\EditStatus;
use App\Models\Edit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesHierarchy;
use Tests\TestCase;

class EditValuesTest extends TestCase
{
    use CreatesHierarchy, RefreshDatabase;

    public function test_edit_priorities_are_centralized_as_backed_enum_values(): void
    {
        $this->assertSame(['high', 'medium', 'low'], EditPriority::values());
        $this->assertSame('high', EditPriority::High->value);
        $this->assertSame('medium', EditPriority::Medium->value);
        $this->assertSame('low', EditPriority::Low->value);
    }

    public function test_edit_statuses_are_centralized_as_backed_enum_values(): void
    {
        $this->assertSame(
            ['pending', 'confirmed', 'in_progress', 'done', 'cancelled'],
            EditStatus::values()
        );
        $this->assertSame('pending', EditStatus::Pending->value);
        $this->assertSame('confirmed', EditStatus::Confirmed->value);
        $this->assertSame('in_progress', EditStatus::InProgress->value);
        $this->assertSame('done', EditStatus::Done->value);
        $this->assertSame('cancelled', EditStatus::Cancelled->value);
    }

    public function test_priority_and_status_are_cast_to_enums(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $edit = $this->createEdit($project, [
            'priority' => EditPriority::Medium,
            'status' => EditStatus::InProgress,
        ]);

        $edit->refresh();

        $this->assertInstanceOf(EditPriority::class, $edit->priority);
        $this->assertSame(EditPriority::Medium, $edit->priority);
        $this->assertInstanceOf(EditStatus::class, $edit->status);
        $this->assertSame(EditStatus::InProgress, $edit->status);
        $this->assertSame('medium', $edit->getRawOriginal('priority'));
        $this->assertSame('in_progress', $edit->getRawOriginal('status'));
    }

    public function test_every_valid_priority_can_be_persisted_and_read_back(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        foreach (EditPriority::cases() as $priority) {
            $edit = $this->createEdit($project, [
                'title' => "Priority {$priority->value}",
                'priority' => $priority,
            ]);

            $edit->refresh();

            $this->assertSame($priority, $edit->priority);
            $this->assertSame($priority->value, $edit->getRawOriginal('priority'));
        }
    }

    public function test_every_valid_status_can_be_persisted_and_read_back(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        foreach (EditStatus::cases() as $status) {
            $edit = $this->createEdit($project, [
                'title' => "Status {$status->value}",
                'status' => $status,
            ]);

            $edit->refresh();

            $this->assertSame($status, $edit->status);
            $this->assertSame($status->value, $edit->getRawOriginal('status'));
        }
    }

    public function test_is_paid_defaults_to_false_at_the_database_level(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $edit = Edit::query()->create([
            'title' => 'Unpaid edit',
            'priority' => EditPriority::Low,
            'status' => EditStatus::Pending,
            'project_id' => $project->getKey(),
        ]);

        $edit->refresh();

        $this->assertFalse($edit->is_paid);
        $this->assertSame('0', (string) DB::table('edits')->where('id', $edit->getKey())->value('is_paid'));
    }

    public function test_is_paid_is_treated_as_a_boolean(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $edit = $this->createEdit($project, ['is_paid' => true]);
        $edit->refresh();

        $this->assertIsBool($edit->is_paid);
        $this->assertTrue($edit->is_paid);
    }

    public function test_description_price_and_due_date_are_nullable(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $edit = $this->createEdit($project, [
            'description' => null,
            'price' => null,
            'due_date' => null,
        ]);

        $edit->refresh();

        $this->assertNull($edit->description);
        $this->assertNull($edit->price);
        $this->assertNull($edit->due_date);

        $raw = DB::table('edits')->where('id', $edit->getKey())->first();

        $this->assertNull($raw->description);
        $this->assertNull($raw->price);
        $this->assertNull($raw->due_date);
    }

    public function test_price_is_stored_as_a_two_decimal_decimal_value(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $edit = $this->createEdit($project, ['price' => '1234.5']);
        $edit->refresh();

        $this->assertSame('1234.50', $edit->price);

        $rawPrice = DB::table('edits')->where('id', $edit->getKey())->value('price');
        $this->assertSame('1234.50', $rawPrice);
    }

    public function test_due_date_is_cast_to_a_date_instance(): void
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        $edit = $this->createEdit($project, ['due_date' => '2026-12-31']);
        $edit->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $edit->due_date);
        $this->assertSame('2026-12-31', $edit->due_date->toDateString());
    }
}
