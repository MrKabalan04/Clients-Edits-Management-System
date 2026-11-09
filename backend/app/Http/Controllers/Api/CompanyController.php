<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\Edit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    /**
     * Return a listing of all companies.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Company::query()
                ->with('client')
                ->withCount('projects')
                ->selectSub(
                    Edit::query()
                        ->selectRaw('count(*)')
                        ->join('projects', 'projects.id', '=', 'edits.project_id')
                        ->whereColumn('projects.company_id', 'companies.id'),
                    'edits_count',
                )
                ->get(),
        ]);
    }

    /**
     * Store a newly created company.
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::query()->create($request->validated());

        $company->loadMissing('client');

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully.',
            'data' => $company,
        ], 201);
    }

    /**
     * Display the specified company.
     */
    public function show(Company $company): JsonResponse
    {
        $company->loadMissing('client');

        return response()->json([
            'success' => true,
            'message' => 'Company retrieved successfully.',
            'data' => $company,
        ]);
    }

    /**
     * Return a company's profile and related work using eager-loaded relationships.
     */
    public function details(Company $company): JsonResponse
    {
        $company->load([
            'client',
            'projects' => fn ($query) => $query
                ->withCount([
                    'edits',
                    'edits as pending_edits_count' => fn (Builder $edits) => $edits->where('status', 'pending'),
                    'edits as completed_edits_count' => fn (Builder $edits) => $edits->where('status', 'done'),
                ])
                ->orderBy('name'),
        ]);

        $editsByStatus = Edit::query()
            ->whereHas('project', fn (Builder $query) => $query->where('company_id', $company->id))
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $recentEdits = Edit::query()
            ->whereHas('project', fn (Builder $query) => $query->where('company_id', $company->id))
            ->with('project:id,name,company_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Company details retrieved successfully.',
            'data' => [
                'company' => $company->attributesToArray(),
                'client' => $company->client,
                'statistics' => [
                    'projects' => $company->projects->count(),
                    'edits' => (int) $editsByStatus->sum(),
                    'pending_edits' => (int) $editsByStatus->get('pending', 0),
                    'completed_edits' => (int) $editsByStatus->get('done', 0),
                ],
                'projects' => $company->projects,
                'recent_edits' => $recentEdits,
            ],
        ]);
    }

    /**
     * Update the specified company.
     */
    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $company->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully.',
            'data' => $company->fresh()->loadMissing('client'),
        ]);
    }

    /**
     * Delete the specified company.
     */
    public function destroy(Company $company): JsonResponse
    {
        try {
            $company->delete();
        } catch (QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Company cannot be deleted because it has associated projects.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully.',
        ]);
    }
}
