<?php

namespace App\Http\Requests\Edit;

use App\Enums\EditPriority;
use App\Enums\EditStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateEditRequest extends FormRequest
{
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'required', Rule::enum(EditPriority::class)],
            'status' => ['sometimes', 'required', Rule::enum(EditStatus::class)],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_paid' => ['sometimes', 'boolean'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'project_id' => ['sometimes', 'integer', 'exists:projects,id'],
        ];
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
