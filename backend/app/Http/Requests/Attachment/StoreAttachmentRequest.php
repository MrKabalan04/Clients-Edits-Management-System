<?php

namespace App\Http\Requests\Attachment;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * @var array<string, list<string>>
     */
    private const MIME_TYPES_BY_EXTENSION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'doc' => ['application/msword', 'application/x-ole-storage'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'required',
                'file',
                'extensions:jpg,jpeg,png,webp,doc,docx,xls,xlsx,pdf,txt',
                'mimetypes:'.implode(',', [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/msword',
                    'application/x-ole-storage',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/zip',
                    'application/pdf',
                    'text/plain',
                ]),
                'max:15360',
            ],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * Add checks that require inspecting the uploaded file and its filename together.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->file('files', []) as $index => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType = $file->getMimeType();

                if (
                    ! in_array($mimeType, self::MIME_TYPES_BY_EXTENSION[$extension] ?? [], true)
                    || mb_strlen($file->getClientOriginalName()) > 255
                ) {
                    $validator->errors()->add(
                        "files.{$index}",
                        'Each file must have a supported filename extension, matching MIME type, and a filename of at most 255 characters.'
                    );
                }
            }
        });
    }

    /**
     * Return a consistent JSON response for validation failures.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
