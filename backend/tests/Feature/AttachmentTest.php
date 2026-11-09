<?php

namespace Tests\Feature;

use App\Models\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Tests\Support\CreatesHierarchy;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use CreatesHierarchy, RefreshDatabase;

    /**
     * @var list<string>
     */
    private array $temporaryUploads = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryUploads as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_admin_can_upload_an_image_and_its_metadata_is_stored(): void
    {
        $edit = $this->createEditForAttachments();
        $file = $this->realUpload('original-photo.jpg', "\xFF\xD8\xFF\xE0image-contents");

        $response = $this->withToken($this->adminToken())
            ->post('/api/edits/'.$edit->id.'/attachments', [
                'files' => [$file],
                'description' => 'Homepage image.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.file_name', 'original-photo.jpg')
            ->assertJsonPath('data.0.mime_type', 'image/jpeg')
            ->assertJsonPath('data.0.file_size', strlen("\xFF\xD8\xFF\xE0image-contents"))
            ->assertJsonPath('data.0.description', 'Homepage image.')
            ->assertJsonPath('data.0.edit_id', $edit->id);

        $this->assertArrayNotHasKey('file_data', $response->json('data.0'));
        $this->assertDatabaseHas('attachments', [
            'file_name' => 'original-photo.jpg',
            'file_data' => base64_encode("\xFF\xD8\xFF\xE0image-contents"),
            'mime_type' => 'image/jpeg',
            'file_size' => strlen("\xFF\xD8\xFF\xE0image-contents"),
            'description' => 'Homepage image.',
            'edit_id' => $edit->id,
        ]);
    }

    public function test_admin_can_upload_multiple_files_in_one_request(): void
    {
        $edit = $this->createEditForAttachments();
        $files = [
            File::createWithContent('first.jpg', 'first-image'),
            File::createWithContent('second.txt', 'second-document'),
        ];

        $response = $this->withToken($this->adminToken())
            ->post('/api/edits/'.$edit->id.'/attachments', [
                'files' => $files,
                'description' => 'Shared description.',
            ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.description', 'Shared description.')
            ->assertJsonPath('data.1.description', 'Shared description.');

        $this->assertDatabaseCount('attachments', 2);
    }

    public function test_admin_can_upload_supported_word_excel_pdf_and_text_files(): void
    {
        $edit = $this->createEditForAttachments();
        $files = [
            File::createWithContent('brief.doc', 'legacy Word file'),
            File::createWithContent('brief.docx', 'OpenXML Word file'),
            File::createWithContent('table.xls', 'legacy Excel file'),
            File::createWithContent('table.xlsx', 'OpenXML Excel file'),
            File::createWithContent('brief.pdf', '%PDF-1.7 document'),
            File::createWithContent('notes.txt', 'Plain text document'),
        ];

        $response = $this->withToken($this->adminToken())
            ->post('/api/edits/'.$edit->id.'/attachments', ['files' => $files]);

        $response->assertCreated()->assertJsonCount(6, 'data');
        $this->assertDatabaseCount('attachments', 6);
    }

    public function test_admin_can_list_attachments_without_file_data(): void
    {
        $edit = $this->createEditForAttachments();
        $attachment = $this->createAttachment($edit, [
            'file_data' => base64_encode('private file data'),
            'file_size' => strlen('private file data'),
        ]);

        $response = $this->withToken($this->adminToken())
            ->getJson('/api/edits/'.$edit->id.'/attachments');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $attachment->id)
            ->assertJsonPath('data.0.file_name', 'brief.pdf')
            ->assertJsonPath('data.0.mime_type', 'application/pdf')
            ->assertJsonPath('data.0.file_size', strlen('private file data'))
            ->assertJsonPath('data.0.description', 'Client brief.')
            ->assertJsonPath('data.0.edit_id', $edit->id)
            ->assertJsonStructure(['data' => [['created_at', 'updated_at']]]);

        $this->assertArrayNotHasKey('file_data', $response->json('data.0'));
    }

    public function test_admin_can_retrieve_images_inline_and_documents_as_downloads(): void
    {
        $edit = $this->createEditForAttachments();
        $image = $this->createAttachment($edit, [
            'file_name' => 'preview.png',
            'file_data' => base64_encode('image bytes'),
            'mime_type' => 'image/png',
            'file_size' => strlen('image bytes'),
        ]);
        $document = $this->createAttachment($edit, [
            'file_name' => 'notes.txt',
            'file_data' => base64_encode('document bytes'),
            'mime_type' => 'text/plain',
            'file_size' => strlen('document bytes'),
        ]);
        $token = $this->adminToken();

        $this->withToken($token)
            ->get('/api/attachments/'.$image->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeaderContains('Content-Disposition', 'inline')
            ->assertHeaderContains('Content-Disposition', 'preview.png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertContent('image bytes');

        $this->withToken($token)
            ->get('/api/attachments/'.$document->id)
            ->assertOk()
            ->assertHeaderContains('Content-Type', 'text/plain')
            ->assertHeaderContains('Content-Disposition', 'attachment')
            ->assertHeaderContains('Content-Disposition', 'notes.txt')
            ->assertContent('document bytes');
    }

    public function test_attachment_download_headers_do_not_allow_filename_header_injection(): void
    {
        $edit = $this->createEditForAttachments();
        $attachment = $this->createAttachment($edit, [
            'file_name' => "notes\r\nX-Injected: yes.txt",
            'mime_type' => 'text/plain',
        ]);

        $response = $this->withToken($this->adminToken())
            ->get('/api/attachments/'.$attachment->id);

        $response->assertOk()
            ->assertHeaderMissing('X-Injected');
        $this->assertStringNotContainsString("\r\nX-Injected: yes", $response->headers->get('Content-Disposition'));
    }

    public function test_admin_can_delete_an_attachment(): void
    {
        $edit = $this->createEditForAttachments();
        $attachment = $this->createAttachment($edit);

        $this->withToken($this->adminToken())
            ->deleteJson('/api/attachments/'.$attachment->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Attachment deleted successfully.');

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_missing_edit_returns_not_found(): void
    {
        $this->withToken($this->adminToken())
            ->getJson('/api/edits/999999/attachments')
            ->assertNotFound();
    }

    public function test_missing_attachment_returns_not_found(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)->get('/api/attachments/999999')->assertNotFound();
        $this->withToken($token)->deleteJson('/api/attachments/999999')->assertNotFound();
    }

    public function test_invalid_file_extensions_and_mismatched_mime_types_are_rejected(): void
    {
        $edit = $this->createEditForAttachments();
        $token = $this->adminToken();

        $this->withToken($token)
            ->post('/api/edits/'.$edit->id.'/attachments', [
                'files' => [File::createWithContent('script.exe', 'not allowed')],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0']);

        $mismatchedFile = $this->realUpload('renamed.jpg', 'plain text');

        $this->withToken($token)
            ->post('/api/edits/'.$edit->id.'/attachments', ['files' => [$mismatchedFile]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0']);
    }

    public function test_files_larger_than_fifteen_megabytes_are_rejected(): void
    {
        $edit = $this->createEditForAttachments();
        $file = File::create('oversized.txt', 15 * 1024 + 1);

        $this->withToken($this->adminToken())
            ->post('/api/edits/'.$edit->id.'/attachments', ['files' => [$file]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0']);
    }

    public function test_upload_without_files_is_rejected(): void
    {
        $edit = $this->createEditForAttachments();

        $this->withToken($this->adminToken())
            ->post('/api/edits/'.$edit->id.'/attachments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files']);
    }

    public function test_attachment_endpoints_require_authentication(): void
    {
        $edit = $this->createEditForAttachments();

        $this->getJson('/api/edits/'.$edit->id.'/attachments')->assertUnauthorized();
        $this->getJson('/api/attachments/999999')->assertUnauthorized();
    }

    public function test_non_admin_cannot_access_attachment_endpoints(): void
    {
        $edit = $this->createEditForAttachments();
        $manager = User::query()->create([
            'name' => 'Content Manager',
            'email' => 'attachment-manager@example.com',
            'password' => 'manager-password',
            'role' => 'manager',
        ]);
        $token = $manager->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/edits/'.$edit->id.'/attachments')
            ->assertForbidden();
    }

    public function test_nested_listing_only_returns_attachments_for_its_edit(): void
    {
        $firstEdit = $this->createEditForAttachments();
        $secondEdit = $this->createEditForAttachments('Second edit');
        $attachment = $this->createAttachment($firstEdit);

        $response = $this->withToken($this->adminToken())
            ->getJson('/api/edits/'.$secondEdit->id.'/attachments');

        $response->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('attachments', [
            'id' => $attachment->id,
            'edit_id' => $firstEdit->id,
        ]);
    }

    public function test_deleting_the_last_attachment_allows_its_edit_to_be_deleted(): void
    {
        $edit = $this->createEditForAttachments();
        $attachment = $this->createAttachment($edit);
        $token = $this->adminToken();

        $this->withToken($token)->deleteJson('/api/attachments/'.$attachment->id)->assertOk();
        $this->withToken($token)->deleteJson('/api/edits/'.$edit->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('edits', ['id' => $edit->id]);
    }

    private function createEditForAttachments(string $title = 'Attachment test edit'): Edit
    {
        $client = $this->createClient();
        $company = $this->createCompany($client);
        $project = $this->createProject($company);

        return $this->createEdit($project, ['title' => $title]);
    }

    private function adminToken(): string
    {
        $admin = User::query()->create([
            'name' => 'System Administrator',
            'email' => 'attachment-admin@example.com',
            'password' => 'admin-password',
            'role' => 'admin',
        ]);

        return $admin->createToken('auth_token')->plainTextToken;
    }

    private function realUpload(string $fileName, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'attachment-upload-');
        file_put_contents($path, $contents);
        $this->temporaryUploads[] = $path;

        return new UploadedFile($path, $fileName, null, null, true);
    }
}
