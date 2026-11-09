<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Edit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class AttachmentController extends Controller
{
    /**
     * Return a listing of all attachments that belong to the given edit.
     */
    public function index(Edit $edit): JsonResponse
    {
        $attachments = $edit->attachments()->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $attachments
                ->map(fn (Attachment $attachment) => $this->attachmentData($attachment))
                ->values(),
        ]);
    }

    /**
     * Store new attachment(s) for the given edit.
     */
    public function store(StoreAttachmentRequest $request, Edit $edit): JsonResponse
    {
        $attachments = [];

        DB::transaction(function () use ($request, $edit, &$attachments): void {
            foreach ($request->file('files') as $file) {
                $fileContents = $file->getContent();
                $mimeType = $file->getMimeType();
                $fileSize = $file->getSize();

                if ($mimeType === null || $fileSize === false) {
                    throw new RuntimeException('Unable to read the uploaded attachment metadata.');
                }

                $attachment = Attachment::query()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_data' => base64_encode($fileContents),
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'description' => $request->validated('description'),
                    'edit_id' => $edit->getKey(),
                ]);

                $attachments[] = $this->attachmentData($attachment);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Attachment(s) created successfully.',
            'data' => $attachments,
        ], 201);
    }

    /**
     * Return the stored file contents for the given attachment.
     */
    public function show(Attachment $attachment): Response
    {
        $fileContents = base64_decode($attachment->file_data, true);

        if ($fileContents === false) {
            throw new RuntimeException('The stored attachment data is not valid Base64.');
        }

        $isInline = in_array($attachment->mime_type, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ], true);
        $fileName = basename(str_replace('\\', '/', $attachment->file_name));
        $fallbackFileName = preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($fileName)) ?: 'download';

        return response()->make($fileContents, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $isInline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $fileName,
                $fallbackFileName
            ),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Delete the given attachment.
     */
    public function destroy(Attachment $attachment): JsonResponse
    {
        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully.',
        ]);
    }

    /**
     * Return the attachment metadata without the Base64 file contents.
     *
     * @return array<string, mixed>
     */
    private function attachmentData(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'description' => $attachment->description,
            'edit_id' => $attachment->edit_id,
            'created_at' => $attachment->created_at,
            'updated_at' => $attachment->updated_at,
        ];
    }
}
