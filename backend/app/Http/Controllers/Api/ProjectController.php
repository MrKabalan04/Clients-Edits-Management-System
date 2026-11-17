<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Edit;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
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
            'data' => Project::query()
                ->with(['company.client'])
                ->withCount([
                    'edits',
                    'edits as pending_edits_count' => fn (Builder $query) => $query->where('status', 'pending'),
                    'edits as completed_edits_count' => fn (Builder $query) => $query->where('status', 'done'),
                ])
                ->get(),
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
     * Return a project's profile, edits, and calculated workload and payment totals.
     */
    public function details(Project $project): JsonResponse
    {
        $project->load([
            'company.client',
            'edits' => fn ($query) => $query
                ->withCount('attachments')
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')
                ->orderByDesc('id'),
        ]);

        $editsByStatus = Edit::query()
            ->where('project_id', $project->id)
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $paymentTotals = Edit::query()
            ->where('project_id', $project->id)
            ->selectRaw('COALESCE(SUM(price), 0) as total_value')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_paid = 1 THEN price ELSE 0 END), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_paid = 0 THEN price ELSE 0 END), 0) as outstanding_amount')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Project details retrieved successfully.',
            'data' => [
                'project' => $project->attributesToArray(),
                'company' => $project->company,
                'client' => $project->company->client,
                'statistics' => [
                    'edits' => (int) $editsByStatus->sum(),
                    'pending_edits' => (int) $editsByStatus->get('pending', 0),
                    'confirmed_edits' => (int) $editsByStatus->get('confirmed', 0),
                    'in_progress_edits' => (int) $editsByStatus->get('in_progress', 0),
                    'completed_edits' => (int) $editsByStatus->get('done', 0),
                    'cancelled_edits' => (int) $editsByStatus->get('cancelled', 0),
                    'total_value' => number_format((float) $paymentTotals->total_value, 2, '.', ''),
                    'paid_amount' => number_format((float) $paymentTotals->paid_amount, 2, '.', ''),
                    'outstanding_amount' => number_format((float) $paymentTotals->outstanding_amount, 2, '.', ''),
                ],
                'edits' => $project->edits,
            ],
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
