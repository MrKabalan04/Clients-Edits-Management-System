<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    /**
     * Return a listing of all projects.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Project::query()->with(['company.client'])->get(),
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::query()->create($request->validated());

        $project->load(['company.client']);

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully.',
            'data' => $project,
        ], 201);
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project): JsonResponse
    {
        $project->load(['company.client']);

        return response()->json([
            'success' => true,
            'message' => 'Project retrieved successfully.',
            'data' => $project,
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully.',
            'data' => $project->fresh()->load(['company.client']),
        ]);
    }

    /**
     * Delete the specified project.
     */
    public function destroy(Project $project): JsonResponse
    {
        try {
            $project->delete();
        } catch (QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Project cannot be deleted because it has associated edits.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully.',
        ]);
    }
}
