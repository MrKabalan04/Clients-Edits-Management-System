<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use App\Models\Edit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    /**
     * Return a listing of all clients.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Client::query()
                ->withCount(['companies', 'projects'])
                ->selectSub(
                    Edit::query()
                        ->selectRaw('count(*)')
                        ->join('projects', 'projects.id', '=', 'edits.project_id')
                        ->join('companies', 'companies.id', '=', 'projects.company_id')
                        ->whereColumn('companies.client_id', 'clients.id'),
                    'edits_count',
                )
                ->get(),
        ]);
    }

    /**
     * Store a newly created client.
     */
    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Client created successfully.',
            'data' => $client,
        ], 201);
    }

    /**
     * Display the specified client.
     */
    public function show(Client $client): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Client retrieved successfully.',
            'data' => $client,
        ]);
    }

    /**
     * Return a client's profile and related work using eager-loaded relationships.
     */
    public function details(Client $client): JsonResponse
    {
        $client->load([
            'companies.projects' => fn ($query) => $query->withCount('edits')->orderBy('name'),
            'companies' => fn ($query) => $query->orderBy('name'),
        ]);

        $projects = $client->companies
            ->flatMap(fn ($company) => $company->projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'company_id' => $company->id,
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                ],
                'edits_count' => $project->edits_count,
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]))
            ->values();

        $editsByStatus = Edit::query()
            ->whereHas('project.company', fn (Builder $query) => $query->where('client_id', $client->id))
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $recentEdits = Edit::query()
            ->whereHas('project.company', fn (Builder $query) => $query->where('client_id', $client->id))
            ->with([
                'project:id,name,company_id',
                'project.company:id,name',
                'attachments:id,edit_id,file_name,mime_type,file_size,description',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $companies = $client->companies->map(fn ($company) => [
            'id' => $company->id,
            'name' => $company->name,
            'client_id' => $company->client_id,
            'projects_count' => $company->projects->count(),
            'edits_count' => $company->projects->sum('edits_count'),
            'created_at' => $company->created_at,
            'updated_at' => $company->updated_at,
        ]);

        $projectsCount = $projects->count();
        $editsCount = (int) $editsByStatus->sum();

        return response()->json([
            'success' => true,
            'message' => 'Client details retrieved successfully.',
            'data' => [
                'client' => $client->attributesToArray(),
                'statistics' => [
                    'companies' => $client->companies->count(),
                    'projects' => $projectsCount,
                    'edits' => $editsCount,
                    'pending_edits' => (int) $editsByStatus->get('pending', 0),
                    'completed_edits' => (int) $editsByStatus->get('done', 0),
                ],
                'companies' => $companies,
                'projects' => $projects,
                'recent_edits' => $recentEdits,
            ],
        ]);
    }

    /**
     * Update the specified client.
     */
    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Client updated successfully.',
            'data' => $client->fresh(),
        ]);
    }

    /**
     * Delete the specified client.
     */
    public function destroy(Client $client): JsonResponse
    {
        try {
            $client->delete();
        } catch (QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Client cannot be deleted because it has associated companies.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Client deleted successfully.',
        ]);
    }
}
