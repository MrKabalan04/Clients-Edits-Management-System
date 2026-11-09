<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Edit\StoreEditRequest;
use App\Http\Requests\Edit\UpdateEditRequest;
use App\Models\Edit;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class EditController extends Controller
{
    /**
     * Return a listing of all edits.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Edit::query()->with(['project.company.client'])->get(),
        ]);
    }

    /**
     * Store a newly created edit.
     */
    public function store(StoreEditRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['is_paid'] = $validated['is_paid'] ?? false;

        $edit = Edit::query()->create($validated);

        $edit->load(['project.company.client']);

        return response()->json([
            'success' => true,
            'message' => 'Edit created successfully.',
            'data' => $edit,
        ], 201);
    }

    /**
     * Display the specified edit.
     */
    public function show(Edit $edit): JsonResponse
    {
        $edit->load(['project.company.client']);

        return response()->json([
            'success' => true,
            'message' => 'Edit retrieved successfully.',
            'data' => $edit,
        ]);
    }

    /**
     * Update the specified edit.
     */
    public function update(UpdateEditRequest $request, Edit $edit): JsonResponse
    {
        $edit->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Edit updated successfully.',
            'data' => $edit->fresh()->load(['project.company.client']),
        ]);
    }

    /**
     * Delete the specified edit.
     */
    public function destroy(Edit $edit): JsonResponse
    {
        try {
            $edit->delete();
        } catch (QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Edit cannot be deleted because it has associated attachments.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Edit deleted successfully.',
        ]);
    }
}
